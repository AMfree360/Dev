# HSL Scalping EA
Author: AMfree Owusu  
Version: 1.0  

## Description
This EA implements the HSL Comprehensive Scalping Plan 2025 strategy for MetaTrader 5. It trades EUR/USD, GBP/USD, and USD/JPY during the London-New York overlap session.

## Installation
1. Copy the following files to the `MQL5/Experts` directory:
   - `HSL_Scalping_EA.mq5`
   - `HSL_Utilities.mqh`
   - `HSL_NewsFilter.mqh`
   - `HSL_Holidays.mqh`

2. Compile the EA in MetaEditor.

3. Attach the EA to a chart in MT5.

## Running on Ubuntu 24.04
1. Install MT5 using Wine.
2. Use the `run_mt5.sh` script to start MT5.
3. Attach the EA to a chart.

## Backtesting
1. Use the `backtest.set` configuration file for backtesting.
2. Test on 2 years of historical data for EUR/USD, GBP/USD, and USD/JPY.
