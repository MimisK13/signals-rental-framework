<?php

declare(strict_types=1);

namespace App\Domain\SupplierCosts\Presentation\Http\Requests;

use App\Domain\SupplierCosts\Data\CreateSupplierCostData;
use App\Domain\SupplierCosts\Models\ProductSupplierCost;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

final class CreateSupplierCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('create', ProductSupplierCost::class)) {
            return false;
        }

        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        return ! $token instanceof PersonalAccessToken || $token->can('products:write');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return CreateSupplierCostData::nestedRules();
    }
}
