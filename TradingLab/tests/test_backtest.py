"""Tests for backtest engine."""

import pytest
import pandas as pd
import numpy as np
from pathlib import Path
import tempfile
from datetime import datetime

from engine.backtest_engine import BacktestEngine, Trade, BacktestResult
from strategies.base import StrategyBase
from config.schema import StrategyConfig


class DummyStrategy(StrategyBase):
    """Dummy strategy for testing."""
    
    def generate_signals(self, df_by_tf):
        signals = self._create_signal_dataframe()
        
        # Generate a simple signal
        if '1h' in df_by_tf and len(df_by_tf['1h']) > 0:
            df_1h = df_by_tf['1h']
            first_bar = df_1h.iloc[0]
            
            signal = pd.Series({
                'direction': 'long',
                'entry_price': first_bar['close'],
                'stop_price': first_bar['close'] * 0.99,  # 1% stop
                'weight': 1.0,
                'metadata': {}
            }, name=df_1h.index[0])
            
            signals = pd.concat([signals, signal.to_frame().T])
            signals = signals.set_index('timestamp') if 'timestamp' in signals.columns else signals
        
        return signals
    
    def get_indicators(self, df):
        return df


def create_test_csv(file_path: Path, n_bars=100):
    """Create a test CSV file."""
    dates = pd.date_range(start='2024-01-01 00:00:00', periods=n_bars, freq='1T')
    np.random.seed(42)
    
    base_price = 100.0
    data = []
    for i in range(n_bars):
        change = np.random.randn() * 0.1
        base_price += change
        data.append({
            'timestamp': dates[i],
            'open': base_price,
            'high': base_price * 1.001,
            'low': base_price * 0.999,
            'close': base_price * 1.0005,
            'volume': np.random.randint(100, 1000),
        })
    
    df = pd.DataFrame(data)
    df.to_csv(file_path, index=False)
    return file_path


def test_backtest_engine_initialization():
    """Test backtest engine initialization."""
    config_dict = {
        "strategy_name": "test_strategy",
        "market": {"exchange": "binance", "symbol": "BTCUSDT", "base_timeframe": "1m"},
        "timeframes": {"signal_tf": "1h", "entry_tf": "15m"},
        "moving_averages": {"ema5": {"enabled": True, "length": 5}},
        "alignment_rules": {
            "long": {"macd_bars_signal_tf": 1, "macd_bars_entry_tf": 2},
            "short": {"macd_bars_signal_tf": 1, "macd_bars_entry_tf": 2},
        },
    }
    config = StrategyConfig(**config_dict)
    strategy = DummyStrategy(config)
    
    engine = BacktestEngine(strategy, initial_capital=10000.0)
    
    assert engine.initial_capital == 10000.0
    assert engine.strategy == strategy


def test_load_data():
    """Test data loading."""
    with tempfile.NamedTemporaryFile(mode='w', suffix='.csv', delete=False) as f:
        file_path = Path(f.name)
    
    try:
        create_test_csv(file_path, n_bars=50)
        
        config_dict = {
            "strategy_name": "test_strategy",
            "market": {"exchange": "binance", "symbol": "BTCUSDT", "base_timeframe": "1m"},
            "timeframes": {"signal_tf": "1h", "entry_tf": "15m"},
            "moving_averages": {"ema5": {"enabled": True, "length": 5}},
            "alignment_rules": {
                "long": {"macd_bars_signal_tf": 1, "macd_bars_entry_tf": 2},
                "short": {"macd_bars_signal_tf": 1, "macd_bars_entry_tf": 2},
            },
        }
        config = StrategyConfig(**config_dict)
        strategy = DummyStrategy(config)
        engine = BacktestEngine(strategy)
        
        df = engine.load_data(file_path)
        
        assert len(df) == 50
        assert isinstance(df.index, pd.DatetimeIndex)
        assert 'open' in df.columns
        assert 'high' in df.columns
        assert 'low' in df.columns
        assert 'close' in df.columns
        assert 'volume' in df.columns
    finally:
        file_path.unlink()


def test_calculate_position_size():
    """Test position size calculation."""
    config_dict = {
        "strategy_name": "test_strategy",
        "market": {"exchange": "binance", "symbol": "BTCUSDT", "base_timeframe": "1m"},
        "timeframes": {"signal_tf": "1h", "entry_tf": "15m"},
        "moving_averages": {"ema5": {"enabled": True, "length": 5}},
        "alignment_rules": {
            "long": {"macd_bars_signal_tf": 1, "macd_bars_entry_tf": 2},
            "short": {"macd_bars_signal_tf": 1, "macd_bars_entry_tf": 2},
        },
    }
    config = StrategyConfig(**config_dict)
    strategy = DummyStrategy(config)
    engine = BacktestEngine(strategy, initial_capital=10000.0)
    
    # Test equity-based sizing
    size = engine.calculate_position_size(100.0, 99.0, 1.0, "equity")
    assert size > 0
    
    # Test account-size-based sizing
    size2 = engine.calculate_position_size(100.0, 99.0, 1.0, "account_size")
    assert size2 > 0


def test_apply_costs():
    """Test cost application."""
    config_dict = {
        "strategy_name": "test_strategy",
        "market": {"exchange": "binance", "symbol": "BTCUSDT", "base_timeframe": "1m"},
        "timeframes": {"signal_tf": "1h", "entry_tf": "15m"},
        "moving_averages": {"ema5": {"enabled": True, "length": 5}},
        "alignment_rules": {
            "long": {"macd_bars_signal_tf": 1, "macd_bars_entry_tf": 2},
            "short": {"macd_bars_signal_tf": 1, "macd_bars_entry_tf": 2},
        },
    }
    config = StrategyConfig(**config_dict)
    strategy = DummyStrategy(config)
    engine = BacktestEngine(strategy, commission_rate=0.0004, slippage_ticks=0.1)
    
    price, cost = engine.apply_costs(100.0, 1.0, is_entry=True)
    assert price > 100.0  # Slippage added
    assert cost > 0  # Commission


def test_backtest_result_creation():
    """Test BacktestResult creation."""
    result = BacktestResult(
            trades=[],
            equity_curve=pd.Series([10000.0]),
            initial_capital=10000.0,
            final_capital=10000.0,
            total_trades=0,
            winning_trades=0,
            losing_trades=0,
            total_pnl=0.0,
            total_commission=0.0,
            total_slippage=0.0,
            max_drawdown=0.0,
            max_drawdown_pct=0.0,
            win_rate=0.0,
            avg_win=0.0,
            avg_loss=0.0,
            profit_factor=0.0,
            sharpe_ratio=0.0,
            metadata={}
        )
    
    assert result.initial_capital == 10000.0
    assert result.total_trades == 0


