<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;

class DateHelper
{
    /**
     * Determine if a date falls on a weekend (Saturday or Sunday).
     */
    public static function isWeekend(string|Carbon $date): bool
    {
        $carbon = Carbon::parse($date);
        return $carbon->isWeekend();
    }

    /**
     * Calculate total business days between two dates, excluding weekends and optional holidays.
     */
    public static function businessDaysBetween(string|Carbon $start, string|Carbon $end, array $holidays = []): int
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();

        if ($startDate->greaterThan($endDate)) {
            $temp = $startDate;
            $startDate = $endDate;
            $endDate = $temp;
        }

        $businessDays = 0;
        $current = $startDate->copy();

        // Convert holiday strings to standardized Y-m-d format for fast lookup
        $holidayList = array_map(fn($h) => Carbon::parse($h)->format('Y-m-d'), $holidays);

        while ($current->lessThanOrEqualTo($endDate)) {
            if (!$current->isWeekend() && !in_array($current->format('Y-m-d'), $holidayList)) {
                $businessDays++;
            }
            $current->addDay();
        }

        return $businessDays;
    }

    /**
     * Add a specific number of business days to a date, skipping weekends and optional holidays.
     */
    public static function addBusinessDays(string|Carbon $date, int $days, array $holidays = []): Carbon
    {
        $current = Carbon::parse($date);
        $holidayList = array_map(fn($h) => Carbon::parse($h)->format('Y-m-d'), $holidays);

        $added = 0;
        while ($added < $days) {
            $current->addDay();
            if (!$current->isWeekend() && !in_array($current->format('Y-m-d'), $holidayList)) {
                $added++;
            }
        }

        return $current;
    }

    /**
     * Retrieve fiscal quarter info (quarter index, start date, end date) for a given date.
     */
    public static function getFiscalQuarter(string|Carbon $date): array
    {
        $carbon = Carbon::parse($date);
        $quarter = ceil($carbon->month / 3);
        
        $startMonth = ($quarter - 1) * 3 + 1;
        $start = Carbon::create($carbon->year, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addMonths(2)->endOfMonth()->endOfDay();

        return [
            'quarter' => (int) $quarter,
            'start' => $start,
            'end' => $end,
            'label' => "Q{$quarter} {$carbon->year}",
        ];
    }

    /**
     * Detect if two date ranges overlap.
     */
    public static function rangesOverlap(
        string|Carbon $start1,
        string|Carbon $end1,
        string|Carbon $start2,
        string|Carbon $end2
    ): bool {
        $s1 = Carbon::parse($start1);
        $e1 = Carbon::parse($end1);
        $s2 = Carbon::parse($start2);
        $e2 = Carbon::parse($end2);

        return $s1->lessThanOrEqualTo($e2) && $e1->greaterThanOrEqualTo($s2);
    }

    /**
     * Format to dynamic localized friendly diff string.
     */
    public static function toHumanDiff(string|Carbon $date): string
    {
        return Carbon::parse($date)->diffForHumans();
    }
}
