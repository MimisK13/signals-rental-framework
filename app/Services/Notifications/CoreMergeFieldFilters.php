<?php

namespace App\Services\Notifications;

use App\Support\Formatter;
use App\Support\Timezone;
use DateTimeInterface;

/**
 * Registers core merge-field filters on the filter registry.
 */
class CoreMergeFieldFilters
{
    public static function register(MergeFieldFilterRegistry $registry): void
    {
        $registry->register('date', self::date(...));
        $registry->register('time', self::time(...));
        $registry->register('currency', self::currency(...));
        $registry->register('number', self::number(...));
        $registry->register('upper', self::upper(...));
        $registry->register('lower', self::lower(...));
        $registry->register('title', self::title(...));
        $registry->register('default', self::default(...));
        $registry->register('truncate', self::truncate(...));
        $registry->register('plural', self::plural(...));
    }

    public static function date(mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! is_string($value) && ! $value instanceof DateTimeInterface) {
            return $value;
        }

        try {
            $timezone = app(Timezone::class);
            $local = $timezone->toLocal($value);
            $format = $arg !== null && $arg !== '' ? $arg : (settings('company.date_format_php') ?? 'd/m/Y');

            return $local->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }

    public static function time(mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! is_string($value) && ! $value instanceof DateTimeInterface) {
            return $value;
        }

        try {
            $timezone = app(Timezone::class);
            $local = $timezone->toLocal($value);
            $format = $arg !== null && $arg !== '' ? $arg : (settings('company.time_format_php') ?? 'H:i');

            return $local->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }

    public static function currency(mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException('Currency filter requires integer minor units, float values are not permitted.');
        }

        if (is_string($value) && str_contains($value, '.')) {
            throw new \InvalidArgumentException('Currency filter requires integer minor units, decimal strings are not permitted.');
        }

        if (! is_int($value) && ! (is_string($value) && ctype_digit(ltrim($value, '-')))) {
            return $value;
        }

        $minorUnits = (int) $value;
        $currencyCode = $arg !== null && $arg !== '' ? $arg : $context->currencyCode;

        return app(Formatter::class)->money($minorUnits, $currencyCode);
    }

    public static function number(mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! is_numeric($value)) {
            return $value;
        }

        $decimals = $arg !== null && $arg !== '' ? (int) $arg : 2;

        return app(Formatter::class)->number((float) $value, $decimals);
    }

    public static function upper(mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed
    {
        if ($value === null) {
            return $value;
        }

        return strtoupper((string) $value);
    }

    public static function lower(mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed
    {
        if ($value === null) {
            return $value;
        }

        return strtolower((string) $value);
    }

    public static function title(mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed
    {
        if ($value === null) {
            return $value;
        }

        return ucwords(strtolower((string) $value));
    }

    public static function default(mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed
    {
        if ($value === null || $value === '') {
            return $arg ?? '';
        }

        return $value;
    }

    public static function truncate(mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed
    {
        if ($value === null) {
            return $value;
        }

        $length = $arg !== null && $arg !== '' ? (int) $arg : 50;
        $string = (string) $value;

        if (strlen($string) <= $length) {
            return $string;
        }

        return substr($string, 0, max(0, $length - 3)).'...';
    }

    public static function plural(mixed $value, ?string $arg, MergeFieldRenderContext $context): mixed
    {
        if ($arg === null || $arg === '') {
            return $value;
        }

        $parts = explode('|', $arg, 2);
        $singular = $parts[0];
        $pluralForm = $parts[1] ?? $singular.'s';
        $count = is_numeric($value) ? (int) $value : 1;

        return abs($count) === 1 ? $singular : $pluralForm;
    }
}
