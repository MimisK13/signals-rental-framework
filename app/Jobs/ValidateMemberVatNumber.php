<?php

namespace App\Jobs;

use App\Actions\Members\ProcessMemberVatValidation;
use App\Exceptions\Tax\ViesServiceUnavailableException;
use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;

class ValidateMemberVatNumber implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(
        public int $memberId,
    ) {
        $this->onQueue('default');
        $this->afterCommit = true;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            new RateLimited('vies-validation'),
            (new ThrottlesExceptions(3, 300))
                ->backoff(60)
                ->when(fn (\Throwable $throwable): bool => $throwable instanceof ViesServiceUnavailableException),
        ];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ProcessMemberVatValidation $processValidation): void
    {
        $member = Member::query()->find($this->memberId);

        if ($member === null || trim((string) ($member->tax_number ?? '')) === '') {
            return;
        }

        try {
            $processValidation($member);
        } catch (ViesServiceUnavailableException $exception) {
            throw $exception;
        }
    }
}
