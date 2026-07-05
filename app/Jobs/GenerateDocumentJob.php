<?php

namespace App\Jobs;

use App\Actions\Documents\GenerateDocument;
use App\Data\Documents\GenerateDocumentData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class GenerateDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public string $jobId,
        public GenerateDocumentData $data,
        public int $userId,
    ) {
        $this->queue = 'exports';
    }

    public function handle(GenerateDocument $generateDocument): void
    {
        $cacheKey = $this->progressCacheKey();

        Cache::put($cacheKey, [
            'status' => 'processing',
            'progress' => 0,
        ], now()->addHours(1));

        Auth::loginUsingId($this->userId);

        try {
            $result = $generateDocument($this->data);

            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'document_id' => $result->id,
            ], now()->addHours(1));
        } catch (\Throwable $exception) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'progress' => 100,
                'error' => $exception->getMessage(),
            ], now()->addHours(1));

            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Cache::put($this->progressCacheKey(), [
            'status' => 'failed',
            'progress' => 100,
            'error' => $exception->getMessage(),
        ], now()->addHours(1));
    }

    public function progressCacheKey(): string
    {
        return "job-progress:GenerateDocumentJob:{$this->jobId}";
    }
}
