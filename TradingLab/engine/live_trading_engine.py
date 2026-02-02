"""Live trading engine that executes strategies in real-time."""

import logging
import time
from typing import Optional, Dict, Any, Tuple
from datetime import datetime
import pandas as pd

from strategies.base import StrategyBase
from adapters.execution.binance_futures import BinanceFuturesAdapter, Position as AdapterPosition
from engine.market import MarketSpec
from engine.account import AccountState
from engine.broker import BrokerModel

logger = logging.getLogger(__name__)


class LiveTradingEngine:
    """Engine for live trading with real-time execution."""
    
    def __init__(
        self,
        strategy: StrategyBase,
        adapter: BinanceFuturesAdapter,
        market_spec: MarketSpec,
        initial_capital: float = 10000.0,
        max_daily_loss_pct: float = 5.0,
        max_drawdown_pct: float = 15.0
    ):
        """
        Initialize live trading engine.
        
        Args:
            strategy: Strategy instance
            adapter: Exchange API adapter
            market_spec: Market specification
            initial_capital: Starting capital
            max_daily_loss_pct: Maximum daily loss percentage before stopping
            max_drawdown_pct: Maximum drawdown percentage before stopping
        """
        self.strategy = strategy
        self.adapter = adapter
        self.market_spec = market_spec
        self.initial_capital = initial_capital
        self.max_daily_loss_pct = max_daily_loss_pct
        self.max_drawdown_pct = max_drawdown_pct
        
        # Initialize account and broker
        self.account = AccountState(initial_capital)
        self.broker = BrokerModel(market_spec)
        
        # Trading state
        self.symbol = market_spec.symbol
        self.active_positions: Dict[str, AdapterPosition] = {}
        self.active_stop_orders: Dict[str, str] = {}  # position_id -> stop_order_id
        self.daily_pnl = 0.0
        self.daily_start_balance = initial_capital
        self.peak_equity = initial_capital
        
        # Statistics
        self.trades_executed = 0
        self.trades_skipped = 0
        
    def update_positions(self):
        """Update active positions from exchange."""
        positions = self.adapter.get_positions(self.symbol)
        self.active_positions = {pos.symbol: pos for pos in positions if abs(pos.size) > 0.0001}
        
        # Update account with unrealized P&L
        total_unrealized = sum(pos.unrealized_pnl for pos in self.active_positions.values())
        # Note: In live trading, we track this separately from account equity
        
    def check_safety_limits(self) -> Tuple[bool, Optional[str]]:
        """Check if safety limits are exceeded.
        
        Returns:
            (should_stop, reason)
        """
        balance = self.adapter.get_account_balance()
        current_equity = balance.total_balance
        
        # Check daily loss
        daily_loss_pct = ((self.daily_start_balance - current_equity) / self.daily_start_balance) * 100
        if daily_loss_pct >= self.max_daily_loss_pct:
            return True, f"Daily loss limit exceeded: {daily_loss_pct:.2f}%"
        
        # Check drawdown
        if current_equity > self.peak_equity:
            self.peak_equity = current_equity
        
        drawdown_pct = ((self.peak_equity - current_equity) / self.peak_equity) * 100
        if drawdown_pct >= self.max_drawdown_pct:
            return True, f"Drawdown limit exceeded: {drawdown_pct:.2f}%"
        
        return False, None
    
    def process_signal(self, signal: Dict[str, Any], current_price: float, current_time: pd.Timestamp):
        """Process a trading signal from the strategy.
        
        Args:
            signal: Signal dict with 'action' ('BUY', 'SELL', 'CLOSE'), 'quantity', etc.
            current_price: Current market price
            current_time: Current timestamp
        """
        action = signal.get('action')
        if not action:
            return
        
        # Check safety limits
        should_stop, reason = self.check_safety_limits()
        if should_stop:
            logger.warning(f"Trading stopped: {reason}")
            return
        
        # Update positions
        self.update_positions()
        
        # Handle different actions
        if action == 'CLOSE':
            self._close_all_positions()
        elif action in ['BUY', 'SELL']:
            self._process_entry_signal(signal, current_price, current_time)
        elif action == 'UPDATE_STOP':
            self._update_stop_loss(signal, current_price)
    
    def _process_entry_signal(
        self,
        signal: Dict[str, Any],
        current_price: float,
        current_time: pd.Timestamp
    ):
        """Process an entry signal."""
        action = signal.get('action')  # 'BUY' or 'SELL'
        quantity = signal.get('quantity')
        stop_price = signal.get('stop_price')
        target_price = signal.get('target_price')
        
        if not quantity or quantity <= 0:
            logger.warning(f"Invalid quantity in signal: {quantity}")
            return
        
        # Check if we already have a position
        if self.symbol in self.active_positions:
            logger.info(f"Position already exists for {self.symbol}, skipping entry")
            self.trades_skipped += 1
            return
        
        # Check balance
        balance = self.adapter.get_account_balance()
        if balance.available_balance <= 0:
            logger.warning("Insufficient balance for trade")
            self.trades_skipped += 1
            return
        
        # Calculate margin required
        margin_required = self.market_spec.calculate_margin(current_price, quantity)
        if margin_required > balance.available_balance:
            logger.warning(f"Insufficient margin: required {margin_required}, available {balance.available_balance}")
            self.trades_skipped += 1
            return
        
        # Place entry order
        try:
            side = 'BUY' if action == 'BUY' else 'SELL'
            order = self.adapter.place_market_order(
                symbol=self.symbol,
                side=side,
                quantity=quantity
            )
            
            logger.info(f"Entry order placed: {side} {quantity} {self.symbol} @ {current_price}")
            self.trades_executed += 1
            
            # Place stop loss if provided
            if stop_price:
                self._place_stop_loss(quantity, stop_price, side)
            
        except Exception as e:
            logger.error(f"Failed to place entry order: {e}")
            self.trades_skipped += 1
    
    def _place_stop_loss(self, quantity: float, stop_price: float, entry_side: str):
        """Place a stop loss order."""
        try:
            # Stop order side is opposite of entry
            stop_side = 'SELL' if entry_side == 'BUY' else 'BUY'
            
            stop_order = self.adapter.place_stop_market_order(
                symbol=self.symbol,
                side=stop_side,
                quantity=quantity,
                stop_price=stop_price,
                reduce_only=True
            )
            
            self.active_stop_orders[self.symbol] = stop_order.order_id
            logger.info(f"Stop loss placed: {stop_side} {quantity} {self.symbol} @ {stop_price}")
            
        except Exception as e:
            logger.error(f"Failed to place stop loss: {e}")
    
    def _update_stop_loss(self, signal: Dict[str, Any], current_price: float):
        """Update stop loss (trailing stop)."""
        if self.symbol not in self.active_positions:
            return
        
        new_stop_price = signal.get('stop_price')
        if not new_stop_price:
            return
        
        # Cancel old stop order
        if self.symbol in self.active_stop_orders:
            try:
                self.adapter.cancel_order(self.symbol, self.active_stop_orders[self.symbol])
            except Exception as e:
                logger.warning(f"Failed to cancel old stop order: {e}")
        
        # Place new stop order
        position = self.active_positions[self.symbol]
        stop_side = 'SELL' if position.side == 'LONG' else 'BUY'
        
        try:
            stop_order = self.adapter.place_stop_market_order(
                symbol=self.symbol,
                side=stop_side,
                quantity=position.size,
                stop_price=new_stop_price,
                reduce_only=True
            )
            
            self.active_stop_orders[self.symbol] = stop_order.order_id
            logger.info(f"Stop loss updated: {stop_side} {position.size} {self.symbol} @ {new_stop_price}")
            
        except Exception as e:
            logger.error(f"Failed to update stop loss: {e}")
    
    def _close_all_positions(self):
        """Close all open positions."""
        for symbol, position in self.active_positions.items():
            try:
                side = 'SELL' if position.side == 'LONG' else 'BUY'
                self.adapter.place_market_order(
                    symbol=symbol,
                    side=side,
                    quantity=position.size,
                    reduce_only=True
                )
                logger.info(f"Closed position: {side} {position.size} {symbol}")
            except Exception as e:
                logger.error(f"Failed to close position {symbol}: {e}")
        
        # Cancel all stop orders
        for symbol, order_id in self.active_stop_orders.items():
            try:
                self.adapter.cancel_order(symbol, order_id)
            except Exception as e:
                logger.warning(f"Failed to cancel stop order {order_id}: {e}")
        
        self.active_positions.clear()
        self.active_stop_orders.clear()
    
    def get_status(self) -> Dict[str, Any]:
        """Get current trading status."""
        balance = self.adapter.get_account_balance()
        
        return {
            'balance': balance.total_balance,
            'available': balance.available_balance,
            'margin_used': balance.margin_used,
            'unrealized_pnl': balance.unrealized_pnl,
            'positions': len(self.active_positions),
            'trades_executed': self.trades_executed,
            'trades_skipped': self.trades_skipped,
            'daily_pnl': balance.total_balance - self.daily_start_balance,
            'paper_mode': self.adapter.paper_mode
        }

