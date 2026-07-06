<?php

namespace App\Console\Commands;

use App\Models\InboundWebhookLog;
use App\Models\RequestLog;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'api:prune-logs')]
class ApiPruneLogs extends Command
{
    protected $signature = 'api:prune-logs';

    protected $description = 'Prune API request and inbound webhook detail logs older than the configured retention period';

    public function handle(): int
    {
        $retentionDays = max((int) config('api_metering.detail_retention_days', 90), 1);
        $cutoff = now()->subDays($retentionDays);

        $requestLogsDeleted = RequestLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $inboundWebhookLogsDeleted = InboundWebhookLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $total = $requestLogsDeleted + $inboundWebhookLogsDeleted;

        $this->components->info(
            "Pruned {$total} detail log entries older than {$retentionDays} days "
            ."({$requestLogsDeleted} request logs, {$inboundWebhookLogsDeleted} inbound webhook logs).",
        );

        return self::SUCCESS;
    }
}
