<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

enum ExportDateFormat: string
{
    case Iso = 'Y-m-d';
    case German = 'd.m.Y';
    case MonthYear = 'M Y';

    public function format(CarbonInterface|string $date): string
    {
        $date = Date::parse($date);

        if ($this === self::MonthYear) {
            // DIN 5008 abbreviations, which Carbon does not know
            return __('profile.months_short')[$date->month - 1].' '.$date->year;
        }

        return $date->format($this->value);
    }

    /** Today in this format, so the choice shows what it looks like. */
    public function label(): string
    {
        return $this->format(Date::today());
    }
}
