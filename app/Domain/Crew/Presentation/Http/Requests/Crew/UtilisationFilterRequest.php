<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Requests\Crew;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

final class UtilisationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = $this->user()?->currentAccessToken();

        return $this->user()?->can('crew.reports.view') === true && (! ($token instanceof PersonalAccessToken) || $token->can('crew:read'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'crew_member_id' => ['sometimes', 'nullable', 'integer', 'exists:crew_members,id'],
        ];
    }
}
