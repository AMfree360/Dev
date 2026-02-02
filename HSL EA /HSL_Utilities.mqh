//+------------------------------------------------------------------+
//|                                 HSL_Utilities.mqh                |
//|                        Author: AMfree Owusu                      |
//+------------------------------------------------------------------+
#ifndef HSL_UTILITIES_MQH
#define HSL_UTILITIES_MQH

int TimeSecond(datetime time)   {MqlDateTime mt;bool turn=TimeToStruct(time,mt);return(mt.sec);}
int TimeMinute(datetime time)   {MqlDateTime mt;bool turn=TimeToStruct(time,mt);return(mt.min);}
int TimeHour(datetime time)     {MqlDateTime mt;bool turn=TimeToStruct(time,mt);return(mt.hour);}
int TimeDayOfWeek(datetime time){MqlDateTime mt;bool turn=TimeToStruct(time,mt);return(mt.day_of_week);}
int TimeDay(datetime time)      {MqlDateTime mt;bool turn=TimeToStruct(time,mt);return(mt.day);}
int TimeMonth(datetime time)    {MqlDateTime mt;bool turn=TimeToStruct(time,mt);return(mt.mon);}
int TimeYear(datetime time)     {MqlDateTime mt;bool turn=TimeToStruct(time,mt);return(mt.year);}

// Function to check if a long signal exists
bool IsLongSignal(string symbol) {
    // Get handles for the Moving Averages
    int ema10_handle = iMA(symbol, PERIOD_M15, 10, 0, MODE_EMA, PRICE_CLOSE);
    int ema21_handle = iMA(symbol, PERIOD_M15, 21, 0, MODE_EMA, PRICE_CLOSE);
    int ema50_handle = iMA(symbol, PERIOD_M15, 50, 0, MODE_EMA, PRICE_CLOSE);

    // Get the current values of the Moving Averages
    double ema10[1], ema21[1], ema50[1];
    CopyBuffer(ema10_handle, 0, 0, 1, ema10);
    CopyBuffer(ema21_handle, 0, 0, 1, ema21);
    CopyBuffer(ema50_handle, 0, 0, 1, ema50);

    // Get handles for the MACD
    int macd_handle = iMACD(symbol, PERIOD_M15, 12, 26, 9, PRICE_CLOSE);
    double macd[1], signal[1];
    CopyBuffer(macd_handle, 0, 0, 1, macd);  // MODE_MAIN
    CopyBuffer(macd_handle, 1, 0, 1, signal); // MODE_SIGNAL

    // Check conditions for a long signal
    return (ema10[0] > ema21[0] && ema21[0] > ema50[0] && macd[0] > signal[0] && macd[0] > 0);
}

// Function to check if a short signal exists
bool IsShortSignal(string symbol) {
    // Get handles for the Moving Averages
    int ema10_handle = iMA(symbol, PERIOD_M15, 10, 0, MODE_EMA, PRICE_CLOSE);
    int ema21_handle = iMA(symbol, PERIOD_M15, 21, 0, MODE_EMA, PRICE_CLOSE);
    int ema50_handle = iMA(symbol, PERIOD_M15, 50, 0, MODE_EMA, PRICE_CLOSE);

    // Get the current values of the Moving Averages
    double ema10[1], ema21[1], ema50[1];
    CopyBuffer(ema10_handle, 0, 0, 1, ema10);
    CopyBuffer(ema21_handle, 0, 0, 1, ema21);
    CopyBuffer(ema50_handle, 0, 0, 1, ema50);

    // Get handles for the MACD
    int macd_handle = iMACD(symbol, PERIOD_M15, 12, 26, 9, PRICE_CLOSE);
    double macd[1], signal[1];
    CopyBuffer(macd_handle, 0, 0, 1, macd);  // MODE_MAIN
    CopyBuffer(macd_handle, 1, 0, 1, signal); // MODE_SIGNAL

    // Check conditions for a short signal
    return (ema10[0] < ema21[0] && ema21[0] < ema50[0] && macd[0] < signal[0] && macd[0] < 0);
}

// Function to check if the session is open
bool IsSessionOpen() {
    datetime now = TimeCurrent();
    int hour = TimeHour(now);
    int minute = TimeMinute(now);

    // London-New York overlap: 1:00 PM–4:00 PM GMT
    if (hour >= 13 && hour < 16) return true;
    return false;
}

#endif
