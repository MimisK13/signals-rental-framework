<?php

namespace App\Services\Tax;

use App\Jobs\ValidateMemberVatNumber;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class MemberVatValidationService
{
    /**
     * Configured VAT validation freshness window in whole days, or null when
     * validations never expire (setting is 0 or null).
     */
    public function validityDays(): ?int
    {
        $days = settings('finance.vat_validation_validity_days', 30);

        if ($days === null || (int) $days === 0) {
            return null;
        }

        return (int) $days;
    }

    /**
     * Whether the member's VAT number counts as validated for tax resolution at
     * the current moment (fire-time semantics against tax_number_validated_at).
     */
    public function isValidatedForTaxResolution(Member $member): bool
    {
        if (trim((string) ($member->tax_number ?? '')) === '') {
            return false;
        }

        if ($member->tax_number_validated !== true) {
            return false;
        }

        $validityDays = $this->validityDays();

        if ($validityDays === null) {
            return true;
        }

        $validatedAt = $member->tax_number_validated_at;

        if ($validatedAt === null) {
            return false;
        }

        return Carbon::parse($validatedAt)->gt($this->expiryCutoff($validityDays));
    }

    /**
     * Members with a previously successful validation that has aged past the
     * configured window and should be re-checked against VIES/HMRC.
     *
     * @param  Builder<Member>  $query
     * @return Builder<Member>
     */
    public function scopeValidationExpired(Builder $query, ?Carbon $asOf = null): Builder
    {
        $validityDays = $this->validityDays();

        if ($validityDays === null) {
            return $query->whereRaw('1 = 0');
        }

        $cutoff = $this->expiryCutoff($validityDays, $asOf);

        return $query
            ->where('tax_number_validated', true)
            ->whereNotNull('tax_number')
            ->where('tax_number', '!=', '')
            ->whereNotNull('tax_number_validated_at')
            ->where('tax_number_validated_at', '<=', $cutoff);
    }

    private function expiryCutoff(int $validityDays, ?Carbon $asOf = null): Carbon
    {
        return Carbon::parse($asOf ?? now())->subDays($validityDays);
    }

    public function handleTaxNumberChange(Member $member, ?string $previousTaxNumber): void
    {
        $previous = $this->normalizeTaxNumber($previousTaxNumber);
        $current = $this->normalizeTaxNumber($member->tax_number);

        if ($current === $previous) {
            return;
        }

        if ($current === null) {
            $this->clearValidationState($member);

            return;
        }

        $this->markPendingAndDispatch($member);
    }

    public function requestValidation(Member $member): void
    {
        if ($this->normalizeTaxNumber($member->tax_number) === null) {
            return;
        }

        $this->markPendingAndDispatch($member);
    }

    private function markPendingAndDispatch(Member $member): void
    {
        $member->forceFill([
            'tax_number_validated' => null,
            'tax_number_validated_at' => null,
            'tax_number_validation' => null,
        ])->saveQuietly();

        ValidateMemberVatNumber::dispatch($member->id);
    }

    private function clearValidationState(Member $member): void
    {
        $member->forceFill([
            'tax_number_validated' => null,
            'tax_number_validated_at' => null,
            'tax_number_validation' => null,
        ])->saveQuietly();
    }

    private function normalizeTaxNumber(?string $taxNumber): ?string
    {
        if ($taxNumber === null) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', trim($taxNumber)) ?? '');

        return $normalized !== '' ? $normalized : null;
    }
}
