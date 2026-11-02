<?php

declare(strict_types=1);

namespace App\Domain\Rates\Presentation\Http\Requests;

use App\Domain\Rates\Data\CreateProductRateData;
use App\Domain\Rates\Enums\RateTransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

final class CreateProductRateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('transaction_type')) {
            $this->merge([
                'transaction_type' => RateTransactionType::coerce($this->input('transaction_type')),
            ]);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('rates.create')) {
            return false;
        }

        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        return ! $token instanceof PersonalAccessToken || $token->can('rates:write');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return CreateProductRateData::nestedRules();
    }
}
