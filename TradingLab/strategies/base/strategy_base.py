"""Base strategy interface that all strategies must implement."""

from abc import ABC, abstractmethod
from typing import Dict, Optional, TYPE_CHECKING
import pandas as pd
from config.schema import StrategyConfig
from strategies.filters import FilterManager
from strategies.filters.base import FilterContext
import yaml
from pathlib import Path

if TYPE_CHECKING:
    from engine.market import MarketSpec


class StrategyBase(ABC):
    """Abstract base class for all trading strategies."""
    
    def __init__(self, config: StrategyConfig):
        """
        Initialize strategy with configuration.
        
        Args:
            config: Validated strategy configuration
        """
        self.config = config
        self.name = config.strategy_name
        
        # Initialize filter manager
        self.filter_manager: Optional[FilterManager] = None
        self._initialize_filters()
    
    @abstractmethod
    def generate_signals(self, df_by_tf: Dict[str, pd.DataFrame]) -> pd.DataFrame:
        """
        Generate trading signals from multi-timeframe data.
        
        Args:
            df_by_tf: Dictionary mapping timeframe strings to DataFrames.
                     Each DataFrame should have datetime index and OHLCV columns:
                     ['open', 'high', 'low', 'close', 'volume']
        
        Returns:
            DataFrame with columns:
            - timestamp: datetime index
            - direction: 'long' or 'short'
            - entry_price: float (price to enter at)
            - stop_price: float (stop loss price)
            - weight: float (signal strength, 0.0 to 1.0, optional)
            - metadata: dict (additional signal info, optional)
        """
        pass
    
    @abstractmethod
    def get_indicators(self, df: pd.DataFrame, tf: Optional[str] = None) -> pd.DataFrame:
        """
        Compute technical indicators for a DataFrame.
        
        Args:
            df: DataFrame with OHLCV data and datetime index
            tf: Optional timeframe label (e.g., '1h', '15m')
        
        Returns:
            DataFrame with original data plus indicator columns
        """
        pass
    
    def validate_config(self) -> bool:
        """
        Validate strategy configuration.
        
        Returns:
            True if config is valid, raises exception otherwise
        """
        # Basic validation - can be overridden by subclasses
        if not self.config.strategy_name:
            raise ValueError("Strategy name is required")
        return True
    
    def get_required_timeframes(self) -> list[str]:
        """
        Get list of required timeframes for this strategy.
        
        Returns:
            List of timeframe strings (e.g., ['1h', '15m'])
        """
        return [
            self.config.timeframes.signal_tf,
            self.config.timeframes.entry_tf,
        ]
    
    def prepare_data(self, df_by_tf: Dict[str, pd.DataFrame]) -> Dict[str, pd.DataFrame]:
        """
        Prepare data by computing indicators for each timeframe.
        
        Args:
            df_by_tf: Dictionary mapping timeframe strings to DataFrames
        
        Returns:
            Dictionary with indicators computed for each timeframe
        """
        prepared = {}
        for tf, df in df_by_tf.items():
            prepared[tf] = self.get_indicators(df.copy(), tf=tf)
        return prepared
    
    def _validate_dataframe(self, df: pd.DataFrame, required_cols: list[str] = None) -> None:
        """
        Validate that DataFrame has required columns.
        
        Args:
            df: DataFrame to validate
            required_cols: List of required column names (default: OHLCV)
        
        Raises:
            ValueError if required columns are missing
        """
        if required_cols is None:
            required_cols = ['open', 'high', 'low', 'close', 'volume']
        
        missing = [col for col in required_cols if col not in df.columns]
        if missing:
            raise ValueError(f"DataFrame missing required columns: {missing}")
        
        if not isinstance(df.index, pd.DatetimeIndex):
            raise ValueError("DataFrame index must be DatetimeIndex")
    
    def _create_signal_dataframe(self) -> pd.DataFrame:
        """
        Create empty signal DataFrame with correct schema.
        
        Returns:
            Empty DataFrame with signal columns
        """
        return pd.DataFrame(columns=[
            'timestamp',
            'direction',
            'entry_price',
            'stop_price',
            'weight',
            'metadata'
        ]).set_index('timestamp')
    
    def _initialize_filters(self) -> None:
        """
        Initialize filter manager with master and strategy configs.
        
        This method loads the master filter configuration and merges it with
        strategy-specific filter configuration.
        """
        # Load master config if available
        master_config = None
        master_config_path = Path(__file__).parent.parent.parent / 'config' / 'master_filters.yml'
        if master_config_path.exists():
            try:
                with open(master_config_path, 'r') as f:
                    master_data = yaml.safe_load(f)
                    master_config = master_data.get('master_filters', {})
            except Exception:
                # If master config can't be loaded, continue without it
                master_config = None
        
        # Create filter manager
        self.filter_manager = FilterManager(
            master_config=master_config,
            strategy_config=self.config
        )
    
    def _should_block_signal_generation(self, timestamp: pd.Timestamp, symbol: str) -> bool:
        """
        Check if signal generation should be blocked at this timestamp.
        
        This method checks time blackout filters BEFORE generating signals,
        allowing early exit from signal generation loops.
        
        Args:
            timestamp: Timestamp to check (assumed UTC+00)
            symbol: Symbol to check
            
        Returns:
            True if signal generation should be blocked
        """
        if not self.filter_manager:
            return False
        
        # Check time blackout filters
        for filter_obj in self.filter_manager.filters:
            if hasattr(filter_obj, 'should_block_signal_generation'):
                if filter_obj.should_block_signal_generation(timestamp, symbol):
                    return True
        
        return False
    
    def apply_filters(
        self,
        signal: pd.Series,
        timestamp: pd.Timestamp,
        symbol: str,
        df_by_tf: Dict[str, pd.DataFrame],
        market_spec: Optional["MarketSpec"] = None
    ) -> bool:
        """
        Apply filter chain to a signal.
        
        This is a convenience method that creates a FilterContext and applies
        all enabled filters to the signal.
        
        Args:
            signal: Signal Series with direction, entry_price, etc.
            timestamp: Signal timestamp
            symbol: Trading symbol
            df_by_tf: All timeframe data
            market_spec: Optional MarketSpec (auto-loaded if None)
            
        Returns:
            True if signal passed all filters, False otherwise
        """
        if not self.filter_manager:
            return True  # No filters = always pass
        
        # Load MarketSpec if not provided
        if market_spec is None:
            # Import here to avoid circular import at module level
            from engine.market import MarketSpec
            try:
                market_spec = MarketSpec.load_from_profiles(symbol)
            except (ValueError, FileNotFoundError):
                # Fallback: create basic MarketSpec
                market_spec = MarketSpec(
                    symbol=symbol,
                    exchange='unknown',
                    asset_class='crypto'  # Default fallback
                )
        
        # Create filter context
        context = FilterContext(
            timestamp=timestamp,
            symbol=symbol,
            signal_direction=1 if signal.get('direction') == 'long' else -1,
            signal_data=signal,
            df_by_tf=df_by_tf,
            market_spec=market_spec
        )
        
        # Apply filters
        result = self.filter_manager.apply_filters(signal, context)
        return result.passed


