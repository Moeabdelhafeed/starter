<?php

namespace App\Traits;

use Illuminate\Support\Carbon;

/**
 * Converts user-entered datetime fields from the admin's active timezone to UTC
 * before persisting, so the database always stores UTC. Display-side conversion
 * back to the viewer's timezone is handled on the frontend (useDateFormat).
 *
 * The active timezone arrives on every request as the `X-Timezone` header
 * (set in resources/js/app.ts from the CMS timezone picker). When the header is
 * missing or invalid it falls back to config('app.timezone') and no shift
 * happens (values are treated as already-UTC).
 *
 * Usage:
 *   class Event extends Model {
 *       use HasUserTimezone;
 *       protected array $userTimezoneDates = ['starts_at', 'ends_at'];
 *   }
 */
trait HasUserTimezone
{
    public static function bootHasUserTimezone(): void
    {
        static::saving(function ($model): void {
            $model->convertUserTimezoneDatesToUtc();
        });
    }

    protected function convertUserTimezoneDatesToUtc(): void
    {
        $tz = static::resolveUserTimezone();

        // Already UTC (or unknown tz) → nothing to shift.
        if ($tz === 'UTC' || $tz === 'utc') {
            return;
        }

        foreach ($this->userTimezoneDates ?? [] as $field) {
            // Only convert fields the user actually set/changed this save.
            if (! $this->isDirty($field)) {
                continue;
            }

            $value = $this->attributes[$field] ?? null;
            if (empty($value)) {
                continue;
            }

            try {
                // Interpret the wall-clock value as being in the user's timezone,
                // then store it as UTC.
                $this->attributes[$field] = Carbon::parse($value, $tz)
                    ->utc()
                    ->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                // Leave the value untouched if it can't be parsed.
            }
        }
    }

    protected static function resolveUserTimezone(): string
    {
        $tz = request()?->header('X-Timezone');

        if (is_string($tz) && $tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return config('app.timezone', 'UTC');
    }
}
