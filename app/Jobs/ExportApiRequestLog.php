<?php

namespace App\Jobs;

use App\Models\InboundWebhookLog;
use App\Models\RequestLog;
use App\Models\WebhookLog;
use App\Support\DateWindow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportApiRequestLog implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public int $userId,
        public string $channel,
        public array $filters = [],
    ) {
        $this->queue = 'exports';
    }

    public function handle(): void
    {
        $filename = 'exports/api-logs-'.Str::uuid().'.csv';
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Failed to open temporary stream for CSV export.');
        }

        $this->writeCsv($handle);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Storage::put($filename, $csv);

        Cache::put("api-logs-export:{$this->userId}", $filename, now()->addHour());
    }

    /**
     * Write the channel-appropriate log CSV to the given writable stream resource.
     *
     * Shared by the queued job (writes to a temp stream) and the synchronous
     * UI download path (writes directly to php://output).
     *
     * @param  resource  $handle
     */
    public function writeCsv($handle): void
    {
        $window = DateWindow::fromRequest(
            (string) ($this->filters['window'] ?? '7d'),
            isset($this->filters['from']) ? (string) $this->filters['from'] : null,
            isset($this->filters['to']) ? (string) $this->filters['to'] : null,
        );

        match ($this->channel) {
            'webhook_sent' => $this->exportWebhookSent($handle, $window),
            'webhook_received' => $this->exportWebhookReceived($handle, $window),
            default => $this->exportApi($handle, $window),
        };
    }

    /**
     * Handle job failure so the user knows the export did not complete.
     */
    public function failed(\Throwable $exception): void
    {
        Cache::put("api-logs-export:{$this->userId}", 'failed', now()->addHour());

        logger()->error('API request log export failed', [
            'user_id' => $this->userId,
            'channel' => $this->channel,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * @param  resource  $handle
     */
    private function exportApi($handle, DateWindow $window): void
    {
        fputcsv($handle, ['ID', 'Method', 'Route', 'Status', 'Price Weight', 'Token', 'Duration (ms)', 'Created At']);

        $this->apiQuery($window)
            ->with('token')
            ->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->method,
                        $log->route_name,
                        $log->status_code,
                        $log->price_weight,
                        $log->token?->name,
                        $log->duration_ms,
                        $log->created_at?->toIso8601String(),
                    ]);
                }
            });
    }

    /**
     * @param  resource  $handle
     */
    private function exportWebhookSent($handle, DateWindow $window): void
    {
        fputcsv($handle, ['ID', 'Event', 'Response Code', 'Attempts', 'Price Weight', 'Delivered At', 'Created At']);

        $this->webhookSentQuery($window)
            ->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->event,
                        $log->response_code,
                        $log->attempts,
                        $log->price_weight,
                        $log->delivered_at?->toIso8601String(),
                        $log->created_at?->toIso8601String(),
                    ]);
                }
            });
    }

    /**
     * @param  resource  $handle
     */
    private function exportWebhookReceived($handle, DateWindow $window): void
    {
        fputcsv($handle, ['ID', 'Source', 'Event', 'Signature Valid', 'Status', 'Price Weight', 'Created At']);

        $this->webhookReceivedQuery($window)
            ->with('inboundWebhookSource')
            ->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->inboundWebhookSource?->name,
                        $log->event,
                        $log->signature_valid ? 'yes' : 'no',
                        $log->status_code,
                        $log->price_weight,
                        $log->created_at?->toIso8601String(),
                    ]);
                }
            });
    }

    /**
     * @return Builder<RequestLog>
     */
    private function apiQuery(DateWindow $window): Builder
    {
        $query = RequestLog::query()
            ->where('channel', 'api')
            ->whereBetween('created_at', [$window->from, $window->to])
            ->latest('created_at');

        if (! empty($this->filters['method'])) {
            $query->where('method', $this->filters['method']);
        }

        if (! empty($this->filters['token_id'])) {
            $query->where('personal_access_token_id', (int) $this->filters['token_id']);
        }

        $this->applyStatusClassFilter($query, 'status_code');

        return $query;
    }

    /**
     * @return Builder<WebhookLog>
     */
    private function webhookSentQuery(DateWindow $window): Builder
    {
        $query = WebhookLog::query()
            ->whereBetween('created_at', [$window->from, $window->to])
            ->latest('created_at');

        $this->applyStatusClassFilter($query, 'response_code');

        return $query;
    }

    /**
     * @return Builder<InboundWebhookLog>
     */
    private function webhookReceivedQuery(DateWindow $window): Builder
    {
        $query = InboundWebhookLog::query()
            ->whereBetween('created_at', [$window->from, $window->to])
            ->latest('created_at');

        $this->applyStatusClassFilter($query, 'status_code');

        return $query;
    }

    /**
     * @param  Builder<RequestLog>|Builder<WebhookLog>|Builder<InboundWebhookLog>  $query
     */
    private function applyStatusClassFilter(Builder $query, string $column): void
    {
        $statusClass = (string) ($this->filters['status_class'] ?? '');

        if ($statusClass === '') {
            return;
        }

        match ($statusClass) {
            '2xx' => $query->whereBetween($column, [200, 399]),
            '4xx' => $query->whereBetween($column, [400, 499]),
            '5xx' => $query->where(function (Builder $inner) use ($column) {
                $inner->where($column, '>=', 500)
                    ->orWhere($column, 0)
                    ->orWhereNull($column);
            }),
            default => null,
        };
    }
}
