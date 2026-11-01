<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Requests\Crew;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

final class ExportToFinanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = $this->user()?->currentAccessToken();

        return $this->user()?->can('crew.timesheets.export') === true && (! ($token instanceof PersonalAccessToken) || $token->can('crew:write'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'finance_reference' => ['required', 'string'],
            'version' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
