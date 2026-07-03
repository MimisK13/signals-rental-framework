<?php

namespace App\Console\Commands;

use App\Models\CommunicationLog;
use App\Services\Notifications\CommunicationLogStatusRegistry;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'communication-log:prune')]
class PruneCommunicationLogCommand extends Command
{
    protected $signature = 'communication-log:prune
                            {--days= : Retention period in days (overrides setting)}
                            {--safety-days= : Safety window for non-terminal statuses (overrides setting)}';

    protected $description = 'Prune communication log entries beyond retention while preserving pinned and in-flight rows';

    public function handle(CommunicationLogStatusRegistry $statusRegistry): int
    {
        $retentionDays = (int) ($this->option('days') ?? settings('notifications.communication_log_retention_days', 365));
        $safetyDays = (int) ($this->option('safety-days') ?? settings('notifications.communication_log_prune_safety_days', 7));

        if ($retentionDays < 1) {
            $this->components->error('Retention period must be at least 1 day.');

            return self::FAILURE;
        }

        if ($safetyDays < 1) {
            $this->components->error('Safety window must be at least 1 day.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($retentionDays);
        $safetyCutoff = now()->subDays($safetyDays);

        $terminalStatuses = array_keys(array_filter(
            $statusRegistry->statuses(),
            static fn (array $meta): bool => $meta['terminal'],
        ));

        $deleted = CommunicationLog::query()
            ->where('is_pinned', false)
            ->where('created_at', '<', $cutoff)
            ->where(function ($query) use ($terminalStatuses, $safetyCutoff): void {
                $query
                    ->whereIn('status', $terminalStatuses)
                    ->orWhere('created_at', '<', $safetyCutoff);
            })
            ->delete();

        $this->components->info("Pruned {$deleted} communication log entries older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
