//+------------------------------------------------------------------+
//|                                 HSL_Holidays.mqh                 |
//|                        Author: AMfree Owusu                      |
//+------------------------------------------------------------------+
#ifndef HSL_HOLIDAYS_MQH
#define HSL_HOLIDAYS_MQH

// Function to check if today is a U.S. public holiday
bool IsHoliday() {
    datetime holidays[] = {
        D'2025-01-01', D'2025-01-20', D'2025-02-17', D'2025-04-18',
        D'2025-05-26', D'2025-06-19', D'2025-07-04', D'2025-09-01',
        D'2025-11-27', D'2025-12-25'
    };

    datetime today = TimeCurrent();
    for (int i = 0; i < ArraySize(holidays); i++) {
        if (TimeDay(today) == TimeDay(holidays[i]) && TimeMonth(today) == TimeMonth(holidays[i])) {
            return true;
        }
    }
    return false;
}

#endif

