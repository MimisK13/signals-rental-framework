<?php

namespace App\Console\Commands\Members;

use App\Jobs\ValidateMemberVatNumber;
use App\Models\Member;
use App\Services\Tax\MemberVatValidationService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Queues VIES/HMRC revalidation for members whose cached VAT check has expired.
 *
 * Does not mutate validation fields — {@see ValidateMemberVatNumber} applies the
 * existing rate-limited job pipeline and persists the outcome when it completes.
 */
#[AsCommand(name: 'members:revalidate-expired-vat')]
class RevalidateExpiredVatNumbers extends Command
{
    protected $signature = 'members:revalidate-expired-vat';

    protected $description = 'Queue VIES revalidation for members whose VAT number validation has expired';

    public function handle(MemberVatValidationService $vatValidation): int
    {
        if ($vatValidation->validityDays() === null) {
            $this->info('VAT validation never expires (finance.vat_validation_validity_days is 0 or unset); nothing to do.');

            return self::SUCCESS;
        }

        $batchSize = max(1, (int) config('finance.vat_revalidation_batch_size', 500));

        $expired = $vatValidation
            ->scopeValidationExpired(Member::query())
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No members with expired VAT validation found.');

            return self::SUCCESS;
        }

        foreach ($expired as $member) {
            ValidateMemberVatNumber::dispatch($member->id);
        }

        $this->info(sprintf(
            'Queued VAT revalidation for %d member(s) (batch cap %d).',
            $expired->count(),
            $batchSize,
        ));

        return self::SUCCESS;
    }
}
