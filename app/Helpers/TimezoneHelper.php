<?php

namespace App\Helpers;

use Illuminate\Support0\Carbon;
use Illuminate\Support\Carbon as SupportCarbon;

class TimezoneHelper
{
    /**
     * Check if a timezone identifier is valid.
     */
    public static function isValid(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }

    /**
     * Convert any date string/Carbon instance from one timezone to another.
     */
    public static function convert(string|SupportCarbon $date, string $fromTimezone, string $toTimezone, string $format = 'Y-m-d H:i:s'): string
    {
        $carbon = $date instanceof SupportCarbon ? $date->copy() : SupportCarbon::parse($date, $fromTimezone);
        
        if ($carbon->timezoneName !== $fromTimezone) {
            $carbon->setTimezone($fromTimezone);
        }

        return $carbon->setTimezone($toTimezone)->format($format);
    }

    /**
     * Convert a local time (in tenant/user timezone) to UTC database representation.
     */
    public static function toUtc(string|SupportCarbon $date, string $localTimezone, string $format = 'Y-m-d H:i:s'): string
    {
        return self::convert($date, $localTimezone, 'UTC', $format);
    }

    /**
     * Convert database UTC time to local user/tenant representation.
     */
    public static function toLocal(string|SupportCarbon $date, string $localTimezone, string $format = 'Y-m-d H:i:s'): string
    {
        return self::convert($date, 'UTC', $localTimezone, $format);
    }

    /**
     * Get GMT offset format (e.g. "+03:00", "-05:00") for a specific timezone.
     */
    public static function getOffset(string $timezone): string
    {
        if (!self::isValid($timezone)) {
            $timezone = 'UTC';
        }

        $tz = new \DateTimeZone($timezone);
        $transition = $tz->getTransitions(time(), time());
        $offsetSeconds = $transition[0]['offset'] ?? 0;

        $hours = floor(abs($offsetSeconds) / 3600);
        $minutes = floor((abs($offsetSeconds) % 3600) / 60);

        $sign = $offsetSeconds >= 0 ? '+' : '-';

        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }

    /**
     * List all standard system timezones grouped by continental region.
     */
    public static function listGrouped(): array
    {
        $regions = [
            'Africa' => \DateTimeZone::AFRICA,
            'America' => \DateTimeZone::AMERICA,
            'Antarctica' => \DateTimeZone::ANTARCTICA,
            'Arctic' => \DateTimeZone::ARCTIC,
            'Asia' => \DateTimeZone::ASIA,
            'Atlantic' => \DateTimeZone::ATLANTIC,
            'Australia' => \DateTimeZone::AUSTRALIA,
            'Europe' => \DateTimeZone::EUROPE,
            'Indian' => \DateTimeZone::INDIAN,
            'Pacific' => \DateTimeZone::PACIFIC,
        ];

        $grouped = [];

        foreach ($regions as $name => $mask) {
            $zones = \DateTimeZone::listIdentifiers($mask);
            foreach ($zones as $zone) {
                // Strip the region prefix for clean display
                $cleanName = str_replace($name . '/', '', $zone);
                $cleanName = str_replace('_', ' ', $cleanName);
                
                $grouped[$name][] = [
                    'identifier' => $zone,
                    'name' => $cleanName,
                    'offset' => self::getOffset($zone),
                ];
            }
        }

        return $grouped;
    }
}
