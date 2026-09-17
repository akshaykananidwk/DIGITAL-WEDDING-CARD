<?php

declare(strict_types=1);

namespace App\Core;

/** Dot-notation array helpers. */
final class Arr
{
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $default;
        }
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        $current = $array;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }
        return $current;
    }

    public static function set(array &$array, string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &$array;
        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
                return;
            }
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }
    }

    public static function has(array $array, string $key): bool
    {
        return self::get($array, $key, '__missing__') !== '__missing__';
    }

    public static function forget(array &$array, string $key): void
    {
        $segments = explode('.', $key);
        $current = &$array;
        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                unset($current[$segment]);
                return;
            }
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return;
            }
            $current = &$current[$segment];
        }
    }

    /** Recursive merge where later arrays win; lists are replaced, not appended. */
    public static function mergeDeep(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !array_is_list($value)) {
                $base[$key] = self::mergeDeep($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    /** Index a list of rows by one of their columns. */
    public static function keyBy(array $rows, string $column): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && array_key_exists($column, $row)) {
                $out[$row[$column]] = $row;
            }
        }
        return $out;
    }

    /** Group a list of rows by one of their columns. */
    public static function groupBy(array $rows, string $column): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && array_key_exists($column, $row)) {
                $out[$row[$column]][] = $row;
            }
        }
        return $out;
    }

    public static function pluck(array $rows, string $column): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && array_key_exists($column, $row)) {
                $out[] = $row[$column];
            }
        }
        return $out;
    }
}
