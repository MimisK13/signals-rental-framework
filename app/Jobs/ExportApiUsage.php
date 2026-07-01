<?php

namespace App\Jobs;

use App\Enums\MeteringChannel;
use App\Queries\Api\UsageQuery;
use App\Support\DateWindow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportApiUsage implements ShouldQueue
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
        public array $filters = [],
    ) {
        $this->queue = 'exports';
    }

    public function handle(UsageQuery $usageQuery): void
    {
        $filename = 'exports/api-usage-'.Str::uuid().'.csv';
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Failed to open temporary stream for CSV export.');
        }

        $this->writeCsv($handle, $usageQuery);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Storage::put($filename, $csv);

        $cacheKey = "api-usage-export:{$this->userId}";
        Cache::put($cacheKey, $filename, now()->addHour());
    }

    /**
     * Write the usage aggregate CSV to the given writable stream resource.
     *
     * Shared by the queued job (writes to a temp stream) and the synchronous
     * UI download path (writes directly to php://output).
     *
     * @param  resource  $handle
     */
    public function writeCsv($handle, UsageQuery $usageQuery): void
    {
        $result = $usageQuery(
            window: $this->window(),
            dimension: (string) ($this->filters['dimension'] ?? 'endpoint'),
            channel: $this->channel(),
            tokenId: $this->tokenId(),
        );

        fputcsv($handle, ['Key', 'Label', 'Request Count', 'Weighted Units']);

        foreach ($result->rows as $row) {
            fputcsv($handle, [
                $row['key'],
                $row['label'],
                $row['request_count'],
                $row['weighted_units'],
            ]);
        }
    }

    private function window(): DateWindow
    {
        return DateWindow::fromRequest(
            (string) ($this->filters['window'] ?? '7d'),
            isset($this->filters['from']) ? (string) $this->filters['from'] : null,
            isset($this->filters['to']) ? (string) $this->filters['to'] : null,
        );
    }

    private function channel(): ?MeteringChannel
    {
        return isset($this->filters['channel'])
            ? MeteringChannel::from((string) $this->filters['channel'])
            : null;
    }

    private function tokenId(): ?int
    {
        return isset($this->filters['token_id'])
            ? (int) $this->filters['token_id']
            : null;
    }

    /**
     * Handle job failure so the user knows the export did not complete.
     */
    public function failed(\Throwable $exception): void
    {
        $cacheKey = "api-usage-export:{$this->userId}";
        Cache::put($cacheKey, 'failed', now()->addHour());

        logger()->error('API usage export failed', [
            'user_id' => $this->userId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
