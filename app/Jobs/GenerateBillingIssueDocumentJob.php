<?php

namespace App\Jobs;

use App\Actions\Documents\GenerateBillingIssueDocument;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\IssueInvoice;
use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Thunk\Verbs\Facades\Verbs;

class GenerateBillingIssueDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public string $jobId,
        public string $sourceType,
        public int $sourceId,
        public ?int $userId = null,
    ) {
        $this->queue = 'exports';
        $this->afterCommit = true;
    }

    public function handle(GenerateBillingIssueDocument $generateBillingIssueDocument): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        $cacheKey = $this->progressCacheKey();

        Cache::put($cacheKey, [
            'status' => 'processing',
            'progress' => 0,
        ], now()->addHours(1));

        if ($this->userId !== null) {
            Auth::guard('web')->loginUsingId($this->userId);
        }

        $statusCacheKey = $this->billingStatusCacheKey();

        try {
            $document = match ($this->sourceType) {
                'invoice' => $generateBillingIssueDocument->forInvoice(
                    Invoice::query()->findOrFail($this->sourceId),
                    $this->userId,
                ),
                'credit_note' => $generateBillingIssueDocument->forCreditNote(
                    CreditNote::query()->findOrFail($this->sourceId),
                    $this->userId,
                ),
                default => throw new \InvalidArgumentException("Unsupported billing document source [{$this->sourceType}]."),
            };

            Cache::put($statusCacheKey, [
                'status' => 'completed',
                'job_id' => $this->jobId,
                'document_id' => $document->id,
            ], now()->addHours(1));

            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'document_id' => $document->id,
            ], now()->addHours(1));
        } catch (\Throwable $exception) {
            Cache::put($statusCacheKey, [
                'status' => 'failed',
                'job_id' => $this->jobId,
                'error' => $exception->getMessage(),
            ], now()->addHours(1));

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
        Cache::put($this->billingStatusCacheKey(), [
            'status' => 'failed',
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ], now()->addHours(1));

        Cache::put($this->progressCacheKey(), [
            'status' => 'failed',
            'progress' => 100,
            'error' => $exception->getMessage(),
        ], now()->addHours(1));
    }

    public function progressCacheKey(): string
    {
        return "job-progress:GenerateBillingIssueDocumentJob:{$this->jobId}";
    }

    private function billingStatusCacheKey(): string
    {
        return match ($this->sourceType) {
            'invoice' => IssueInvoice::pdfStatusCacheKey($this->sourceId),
            'credit_note' => IssueCreditNote::pdfStatusCacheKey($this->sourceId),
            default => "billing-document:{$this->sourceType}:{$this->sourceId}",
        };
    }
}
