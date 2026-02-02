//+------------------------------------------------------------------+
//|                                 HSL_Scalping_EA.mq5              |
//|                        Author: AMfree Owusu                      |
//|                        Version: 1.3                              |
//+------------------------------------------------------------------+
#include <HSL_Utilities.mqh>
#include <HSL_NewsFilter.mqh>
#include <HSL_Holidays.mqh>

// Input parameters
input double RiskPerTrade = 20.0;          // Risk per trade in USD
input double DailyLossLimit = 40.0;       // Daily loss limit in USD
input int ATRPeriod = 14;                 // ATR period
input double ATRMultiplier = 1.5;         // Stop-loss multiplier for ATR
input int MaxTradesPerDay = 2;            // Maximum trades per day
input int MagicNumber = 2025;             // Magic number for EA
input string TradeSymbols = "EURUSD";     // Symbols to trade

// Global variables
double LotSize;
int TradesToday = 0;
datetime LastTradeTime = 0;

//+------------------------------------------------------------------+
//| Expert initialization function                                   |
//+------------------------------------------------------------------+
int OnInit() {
    // Check if today is a holiday
    if (IsHoliday()) {
        Print("Today is a holiday. EA will not trade.");
        return INIT_FAILED;
    }
    return INIT_SUCCEEDED;
}

//+------------------------------------------------------------------+
//| Expert deinitialization function                                 |
//+------------------------------------------------------------------+
void OnDeinit(const int reason) {
    Print("EA deinitialized.");
}

//+------------------------------------------------------------------+
//| Expert tick function                                             |
//+------------------------------------------------------------------+
void OnTick() {
    // Check if trading is allowed
    if (!IsTradingAllowed()) return;

    // Check if maximum trades per day reached
    if (TradesToday >= MaxTradesPerDay) return;

    // Check for new trade opportunities
    CheckForTrade();
}

//+------------------------------------------------------------------+
//| Check if trading is allowed                                      |
//+------------------------------------------------------------------+
bool IsTradingAllowed() {
    // Check for high-impact news
    if (IsHighImpactNews()) return false;

    // Check session times (London-New York overlap)
    if (!IsSessionOpen()) return false;

    return true;
}

//+------------------------------------------------------------------+
//| Check for trade opportunities                                    |
//+------------------------------------------------------------------+
void CheckForTrade() {
    // Loop through trade symbols
    string symbols[];
    StringSplit(TradeSymbols, ',', symbols);
    for (int i = 0; i < ArraySize(symbols); i++) {
        string symbol = symbols[i];

        // Get the handle for the ATR indicator
        int atr_handle = iATR(symbol, PERIOD_M5, ATRPeriod);
        if (atr_handle == INVALID_HANDLE) {
            Print("Failed to create ATR handle for symbol: ", symbol);
            continue;
        }

        // Get the current ATR value
        double atr[1];
        if (CopyBuffer(atr_handle, 0, 0, 1, atr) <= 0) {
            Print("Failed to copy ATR data for symbol: ", symbol);
            IndicatorRelease(atr_handle);
            continue;
        }

        // Convert ATR value to pips
        double atrPips = atr[0] * 10000; // Convert ATR from points to pips

        // Calculate stop-loss distance in pips
        double minStopLossPips = 5; // Minimum stop-loss distance in pips (adjust as needed)
        double stopLossPips = MathMax(ATRMultiplier * atrPips, minStopLossPips); // Ensure stop-loss is not too small

        // Calculate lot size
        LotSize = CalculateLotSize(symbol, stopLossPips, RiskPerTrade);
        if (LotSize <= 0) {
            Print("Invalid lot size calculated for symbol: ", symbol);
            IndicatorRelease(atr_handle);
            continue;
        }

        // Check for long trade
        if (IsLongSignal(symbol)) {
            OpenTrade(symbol, ORDER_TYPE_BUY, stopLossPips);
        }

        // Check for short trade
        if (IsShortSignal(symbol)) {
            OpenTrade(symbol, ORDER_TYPE_SELL, stopLossPips);
        }

        // Release the ATR handle
        IndicatorRelease(atr_handle);
    }
}

//+------------------------------------------------------------------+
//| Open a trade                                                     |
//+------------------------------------------------------------------+
void OpenTrade(string symbol, ENUM_ORDER_TYPE orderType, double stopLossPips) {
    double price = (orderType == ORDER_TYPE_BUY) ? SymbolInfoDouble(symbol, SYMBOL_ASK) : SymbolInfoDouble(symbol, SYMBOL_BID);

    // Ensure stop-loss and take-profit levels are valid
    double minStopDistance = SymbolInfoInteger(symbol, SYMBOL_TRADE_STOPS_LEVEL) * _Point; // Minimum stop distance in points
    double sl = (orderType == ORDER_TYPE_BUY) ? price - MathMax(stopLossPips * 10 * _Point, minStopDistance) : price + MathMax(stopLossPips * 10 * _Point, minStopDistance);
    double tp = (orderType == ORDER_TYPE_BUY) ? price + MathMax(2 * stopLossPips * 10 * _Point, minStopDistance) : price - MathMax(2 * stopLossPips * 10 * _Point, minStopDistance);

    MqlTradeRequest request;
    MqlTradeResult result;
    ZeroMemory(request);
    ZeroMemory(result);

    request.action = TRADE_ACTION_DEAL;
    request.symbol = symbol;
    request.volume = LotSize;
    request.type = orderType;
    request.price = price;
    request.sl = sl;
    request.tp = tp;
    request.deviation = 3;
    request.magic = MagicNumber;

    if (OrderSend(request, result)) {
        TradesToday++;
        LastTradeTime = TimeCurrent();
        Print("Trade opened successfully: ", symbol, ", Lot Size: ", LotSize, ", Stop Loss: ", sl, ", Take Profit: ", tp);
    } else {
        Print("Failed to open trade: ", symbol, ", Error: ", result.retcode);
    }
}

//+------------------------------------------------------------------+
//| Calculate lot size                                               |
//+------------------------------------------------------------------+
double CalculateLotSize(string symbol, double stopLossPips, double risk) {
    // Check for invalid input values
    if (stopLossPips <= 0 || risk <= 0) {
        Print("Invalid input values for lot size calculation: Stop Loss = ", stopLossPips, ", Risk = ", risk);
        return 0.01; // Return minimum lot size
    }

    // Hardcoded tick value
    double tickValue = 10; // Hardcoded tick value

    // Calculate lot size
    double lotSize = risk / (stopLossPips * tickValue);

    // Ensure lot size is within broker's allowed range
    double minLot = SymbolInfoDouble(symbol, SYMBOL_VOLUME_MIN);
    double maxLot = 10; // Maximum lot size (adjust as needed)
    lotSize = MathMax(lotSize, minLot); // Ensure lot size is not below minimum
    lotSize = MathMin(lotSize, maxLot); // Ensure lot size is not above maximum

    // Round lot size to the nearest step
    double lotStep = SymbolInfoDouble(symbol, SYMBOL_VOLUME_STEP);
    lotSize = MathRound(lotSize / lotStep) * lotStep;

    // Debugging print
    Print("Symbol: ", symbol, ", Stop Loss (pips): ", stopLossPips, ", Risk: ", risk, ", Tick Value: ", tickValue, ", Calculated Lot Size: ", lotSize);

    return lotSize;
}
