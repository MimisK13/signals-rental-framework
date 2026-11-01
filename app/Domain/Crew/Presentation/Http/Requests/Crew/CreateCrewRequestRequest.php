<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Requests\Crew;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

final class CreateCrewRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = $this->user()?->currentAccessToken();

        return $this->user()?->can('crew.requests.create') === true && (! ($token instanceof PersonalAccessToken) || $token->can('crew:write'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'opportunity_id' => ['sometimes', 'nullable', 'integer', 'exists:opportunities,id'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
        ];
    }
}
