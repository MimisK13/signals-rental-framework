<?php

declare(strict_types=1);

namespace App\Domain\Crew\Settings;

use App\Domain\Settings\Settings\SettingsDefinition;

/**
 * Crew planning, compensation, availability, and retention policy.
 *
 * Numeric multipliers are stored as decimal strings so a value round-trips
 * through the settings table without binary-float drift. Consumers cast them
 * with {@see CrewSettings::decimal()} at calculation time.
 */
class CrewSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'crew';
    }

    public function defaults(): array
    {
        return [
            'rounding_mode' => 'nearest',
            'rounding_increment_minutes' => 15,
            'daily_overtime_threshold_hours' => 8,
            'weekly_overtime_threshold_hours' => 40,
            'week_starts_on' => 'monday',
            'daily_ot_multiplier' => '1.50',
            'weekly_ot_multiplier' => '1.50',
            'night_multiplier' => '1.25',
            'weekend_multiplier' => '1.25',
            'holiday_multiplier' => '2.00',
            'night_window_start' => '00:00',
            'night_window_end' => '06:00',
            'daily_call_hours' => '10',
            'min_rest_hours' => '11',
            'rest_enforcement' => 'hard',
            'travel_earns_overtime' => false,
            'stack_multipliers' => false,
            'prorate_partial_days' => false,
            'per_diem_min_minutes' => 240,
            'qualification_expiry_warning_days' => 30,
            'critical_shortage_days' => 7,
            'invite_expiry_hours' => 72,
            'cancellation_pay_hours' => 48,
            'default_break_minutes' => 30,
            'allow_self_service_timesheets' => false,
            'allow_conflict_override' => true,
            'pii_retention_months' => 24,
            'max_timesheet_period_days' => 31,
        ];
    }

    public function rules(): array
    {
        return [
            'rounding_mode' => ['required', 'string', 'in:'.implode(',', ['none', 'up', 'nearest', 'down'])],
            'rounding_increment_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'daily_overtime_threshold_hours' => ['required', 'integer', 'min:0', 'max:24'],
            'weekly_overtime_threshold_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'week_starts_on' => ['required', 'string', 'in:'.implode(',', ['monday', 'sunday'])],
            'daily_ot_multiplier' => ['required', 'numeric', 'min:1', 'max:4'],
            'weekly_ot_multiplier' => ['required', 'numeric', 'min:1', 'max:4'],
            'night_multiplier' => ['required', 'numeric', 'min:1', 'max:4'],
            'weekend_multiplier' => ['required', 'numeric', 'min:1', 'max:4'],
            'holiday_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'night_window_start' => ['required', 'string', 'date_format:H:i'],
            'night_window_end' => ['required', 'string', 'date_format:H:i'],
            'daily_call_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'min_rest_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'rest_enforcement' => ['required', 'string', 'in:'.implode(',', ['hard', 'warning', 'off'])],
            'travel_earns_overtime' => ['required', 'boolean'],
            'stack_multipliers' => ['required', 'boolean'],
            'prorate_partial_days' => ['required', 'boolean'],
            'per_diem_min_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'qualification_expiry_warning_days' => ['required', 'integer', 'min:0', 'max:365'],
            'critical_shortage_days' => ['required', 'integer', 'min:0', 'max:90'],
            'invite_expiry_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'cancellation_pay_hours' => ['required', 'integer', 'min:0', 'max:336'],
            'default_break_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'allow_self_service_timesheets' => ['required', 'boolean'],
            'allow_conflict_override' => ['required', 'boolean'],
            'pii_retention_months' => ['required', 'integer', 'min:0', 'max:120'],
            'max_timesheet_period_days' => ['required', 'integer', 'min:1', 'max:92'],
        ];
    }

    public function types(): array
    {
        return [
            'rounding_increment_minutes' => 'integer',
            'daily_overtime_threshold_hours' => 'integer',
            'weekly_overtime_threshold_hours' => 'integer',
            'travel_earns_overtime' => 'boolean',
            'stack_multipliers' => 'boolean',
            'prorate_partial_days' => 'boolean',
            'per_diem_min_minutes' => 'integer',
            'qualification_expiry_warning_days' => 'integer',
            'critical_shortage_days' => 'integer',
            'invite_expiry_hours' => 'integer',
            'cancellation_pay_hours' => 'integer',
            'default_break_minutes' => 'integer',
            'allow_self_service_timesheets' => 'boolean',
            'allow_conflict_override' => 'boolean',
            'pii_retention_months' => 'integer',
            'max_timesheet_period_days' => 'integer',
        ];
    }
}
