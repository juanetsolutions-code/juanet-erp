<?php

namespace App\Helpers;

class ArrayHelper
{
    /**
     * Flatten a multi-dimensional associative array into a single-level array with dot notation keys.
     */
    public static function flatten(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value) && !empty($value) && self::isAssociative($value)) {
                $result = array_merge($result, self::flatten($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Expand a single-level dot-notated array back into a nested multi-dimensional array.
     */
    public static function expand(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $parts = explode('.', $key);
            $temp = &$result;

            foreach ($parts as $part) {
                if (!isset($temp[$part]) || !is_array($temp[$part])) {
                    $temp[$part] = [];
                }
                $temp = &$temp[$part];
            }

            $temp = $value;
            unset($temp);
        }

        return $result;
    }

    /**
     * Group an array of associative arrays or objects by a nested property (dot notation support).
     */
    public static function groupBy(array $array, string|\Closure $keySelector): array
    {
        $result = [];

        foreach ($array as $item) {
            if ($keySelector instanceof \Closure) {
                $groupKey = $keySelector($item);
            } else {
                $groupKey = self::getNestedValue($item, $keySelector);
            }

            $groupKey = (string) ($groupKey ?? 'null');
            $result[$groupKey][] = $item;
        }

        return $result;
    }

    /**
     * Sort an array of associative arrays by a nested dot-notated key.
     */
    public static function sortByNestedKey(array $array, string $nestedKey, string $direction = 'asc'): array
    {
        usort($array, function ($a, $b) use ($nestedKey, $direction) {
            $valA = self::getNestedValue($a, $nestedKey);
            $valB = self::getNestedValue($b, $nestedKey);

            if ($valA === $valB) {
                return 0;
            }

            $comparison = ($valA < $valB) ? -1 : 1;
            return strtolower($direction) === 'desc' ? -$comparison : $comparison;
        });

        return $array;
    }

    /**
     * Determine if an array is associative (contains non-sequential string or mixed keys).
     */
    public static function isAssociative(array $array): bool
    {
        if ([] === $array) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Deep recursive merge of two arrays. Handled correctly without overwriting numeric indices completely where possible.
     */
    public static function deepMerge(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::deepMerge($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Get a nested value from an array or object using dot-notated key.
     */
    protected static function getNestedValue(mixed $target, string $key): mixed
    {
        if (empty($key)) {
            return $target;
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } elseif (is_object($target) && method_exists($target, 'getAttribute') && $target->getAttribute($segment) !== null) {
                $target = $target->getAttribute($segment);
            } else {
                return null;
            }
        }

        return $target;
    }
}
