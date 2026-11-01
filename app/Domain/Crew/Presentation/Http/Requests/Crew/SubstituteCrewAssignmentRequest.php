<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Requests\Crew;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

final class SubstituteCrewAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = $this->user()?->currentAccessToken();

        return $this->user()?->can('crew.assignments.substitute') === true && (! ($token instanceof PersonalAccessToken) || $token->can('crew:write'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'override_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'crew_member_id' => ['required', 'integer', 'exists:crew_members,id'],
            'reason' => ['required', 'string', 'max:2000'],
            'copy_shifts' => ['sometimes', 'boolean'],
        ];
    }
}
