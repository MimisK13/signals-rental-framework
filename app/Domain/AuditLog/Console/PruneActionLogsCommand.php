<?php

declare(strict_types=1);

namespace App\Domain\AuditLog\Console;

use App\Domain\AuditLog\Models\ActionLog;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'action-log:prune')]
class PruneActionLogsCommand extends Command
{
    protected $signature = 'action-log:prune {--months= : Retention period in months (overrides setting)}';

    protected $description = 'Prune action log entries older than the configured retention period';

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?? settings('action-log.retention_months', 12));

        if ($months < 1) {
            $this->components->error('Retention period must be at least 1 month.');

            return self::FAILURE;
        }

        $cutoff = now()->subMonths($months);

        $deleted = ActionLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->components->info("Pruned {$deleted} action log entries older than {$months} months.");

        return self::SUCCESS;
    }
}
