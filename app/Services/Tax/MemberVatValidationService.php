<?php

namespace App\Services\Tax;

use App\Jobs\ValidateMemberVatNumber;
use App\Models\Member;

class MemberVatValidationService
{
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
