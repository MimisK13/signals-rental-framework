<?php

declare(strict_types=1);

namespace App\Domain\Crew\Console;

use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class ScanCrewShortages extends Command
{
    protected $signature = 'crew:scan-shortages {--days=21 : Scan role lines starting within this many days}';

    protected $description = 'Detect unfilled Crew request lines and notify coordinators once per line per day';

    public function handle(): int
    {
        $days = Validator::make(['days' => $this->option('days')], ['days' => ['required', 'integer', 'min:1', 'max:365']])->validate()['days'];
        if (! settings()->moduleEnabled('crew')) {
            return self::SUCCESS;
        }
        $count = 0;
        $lines = CrewRequestLine::query()->with('request')->withCount(['assignments as confirmed_count' => function ($query): void {
            $query->where('status', 'confirmed');
        }])
            ->whereHas('request', function ($query): void {
                $query->whereIn('status', ['open', 'partially_filled']);
            })
            ->whereRaw('COALESCE(crew_request_lines.starts_at, (SELECT starts_at FROM crew_requests WHERE crew_requests.id = crew_request_lines.crew_request_id)) <= ?', [now()->addDays((int) $days)])
            ->whereRaw('COALESCE(crew_request_lines.ends_at, (SELECT ends_at FROM crew_requests WHERE crew_requests.id = crew_request_lines.crew_request_id)) > ?', [now()]);
        foreach ($lines->lazyById(100) as $line) {
            if ((int) $line->getAttribute('confirmed_count') >= $line->quantity) {
                continue;
            }
            $key = 'crew:shortage:'.$line->id.':'.today()->toDateString();
            if (! Cache::add($key, true, now()->endOfDay())) {
                continue;
            }
            try {
                $daysToStart = now()->diffInDays($line->effectiveStartsAt(), false);
                $severity = $daysToStart <= (int) settings('crew.critical_shortage_days', 7) ? 'critical' : ($daysToStart <= 21 ? 'warning' : 'informational');
                DB::transaction(function () use ($line, $severity): void {
                    CrewAudit::record('crew.shortage.detected', $line, ['request_id' => $line->crew_request_id, 'shortage' => $line->quantity - (int) $line->getAttribute('confirmed_count'), 'severity' => $severity]);
                });
                $count++;
            } catch (Throwable $exception) {
                Cache::forget($key);
                throw $exception;
            }
        }
        $this->info('Detected '.$count.' crew shortages.');

        return self::SUCCESS;
    }
}
