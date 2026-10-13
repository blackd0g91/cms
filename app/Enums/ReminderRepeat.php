<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * How often a reminder comes back once it is done.
 */
enum ReminderRepeat: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Every day',
            self::Weekly => 'Every week',
            self::Monthly => 'Every month',
            self::Yearly => 'Every year',
        };
    }

    /**
     * The next time after $time, in the time zone $time is in, so 9:00
     * stays 9:00 across daylight saving changes. The 31st falls back to the
     * month's last day rather than spilling into the next month.
     */
    public function after(CarbonImmutable $time): CarbonImmutable
    {
        return match ($this) {
            self::Daily => $time->addDay(),
            self::Weekly => $time->addWeek(),
            self::Monthly => $time->addMonthNoOverflow(),
            self::Yearly => $time->addYearNoOverflow(),
        };
    }
}
