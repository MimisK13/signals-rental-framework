<?php

namespace App\Actions\Members;

use App\Contracts\Tax\VatNumberValidator;
use App\Data\Members\MemberData;
use App\Models\Member;
use App\Services\Api\WebhookService;

class ProcessMemberVatValidation
{
    public function __construct(
        private readonly VatNumberValidator $validator,
    ) {}

    public function __invoke(Member $member): MemberData
    {
        $taxNumber = trim((string) ($member->tax_number ?? ''));

        if ($taxNumber === '') {
            return MemberData::fromModel($member);
        }

        $result = $this->validator->validate($taxNumber);

        $member->forceFill([
            'tax_number_validated' => $result->isValid,
            'tax_number_validated_at' => now(),
            'tax_number_validation' => $result->toStorageArray(),
        ])->saveQuietly();

        $member->refresh();

        app(WebhookService::class)->dispatch('member.vat_validated', [
            'member' => MemberData::fromModel($member)->toArray(),
            'validation' => [
                'is_valid' => $result->isValid,
                'validated_at' => $member->tax_number_validated_at?->toIso8601String(),
                'trader_name' => $result->traderName,
                'trader_address' => $result->traderAddress,
                'source' => $result->source,
            ],
        ]);

        return MemberData::fromModel($member);
    }
}
