<?php

declare(strict_types=1);

namespace App\Domain\Crew\Console;

use App\Domain\Crew\Models\CrewMemberQualification;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ScanCrewQualifications extends Command
{
    protected $signature = 'crew:scan-qualifications';

    protected $description = 'Notify about expired and expiring Crew qualifications once per qualification and expiry date';

    public function handle(): int
    {
        if (! settings()->moduleEnabled('crew')) {
            return self::SUCCESS;
        }

        $days = (int) settings('crew.qualification_expiry_warning_days', 30);
        $count = 0;
        $records = CrewMemberQualification::query()->whereNotNull('expires_at')->whereDate('expires_at', '<=', today()->addDays($days))->whereHas('crewMember', function ($query): void {
            $query->where('status', 'active');
        });

        foreach ($records->lazyById(100) as $record) {
            $event = $record->expires_at->lt(today()) ? 'crew.qualification.expired' : 'crew.qualification.expiring';
            $key = 'crew:qualification:'.$record->id.':'.$record->expires_at->toDateString().':'.$event;
            if (! Cache::add($key, true, now()->addDays(366))) {
                continue;
            }
            try {
                DB::transaction(function () use ($event, $record): void {
                    CrewAudit::record($event, $record, ['expires_at' => $record->expires_at->toDateString()]);
                });
                $count++;
            } catch (Throwable $exception) {
                Cache::forget($key);
                throw $exception;
            }
        }
        $this->info('Detected '.$count.' crew qualification alerts.');

        return self::SUCCESS;
    }
}
