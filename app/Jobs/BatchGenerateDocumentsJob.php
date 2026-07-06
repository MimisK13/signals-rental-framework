<?php

namespace App\Jobs;

use App\Actions\Documents\GenerateDocument;
use App\Data\Documents\GenerateDocumentData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class BatchGenerateDocumentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    /**
     * @param  list<GenerateDocumentData>  $requests
     */
    public function __construct(
        public string $jobId,
        public array $requests,
        public int $userId,
    ) {
        $this->queue = 'exports';
    }

    public function handle(GenerateDocument $generateDocument): void
    {
        $cacheKey = $this->progressCacheKey();
        $total = count($this->requests);

        Cache::put($cacheKey, [
            'status' => 'processing',
            'progress' => 0,
            'total' => $total,
            'completed' => 0,
            'document_ids' => [],
        ], now()->addHours(1));

        Auth::loginUsingId($this->userId);

        $documentIds = [];

        try {
            foreach ($this->requests as $index => $request) {
                $result = $generateDocument($request);
                $documentIds[] = $result->id;

                Cache::put($cacheKey, [
                    'status' => 'processing',
                    'progress' => (int) round((($index + 1) / max(1, $total)) * 100),
                    'total' => $total,
                    'completed' => $index + 1,
                    'document_ids' => $documentIds,
                ], now()->addHours(1));
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'total' => $total,
                'completed' => $total,
                'document_ids' => $documentIds,
            ], now()->addHours(1));
        } catch (\Throwable $exception) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'progress' => 100,
                'total' => $total,
                'completed' => count($documentIds),
                'document_ids' => $documentIds,
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
        return "job-progress:BatchGenerateDocumentsJob:{$this->jobId}";
    }
}
