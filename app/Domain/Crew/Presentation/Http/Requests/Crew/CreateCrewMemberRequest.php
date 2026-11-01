<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Requests\Crew;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

final class CreateCrewMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = $this->user()?->currentAccessToken();

        return $this->user()?->can('crew.create') === true && (! ($token instanceof PersonalAccessToken) || $token->can('crew:write'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'engagement_type' => ['sometimes', 'string', 'in:employee,freelancer,contractor,external_crew'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:190'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:60'],
            'home_timezone' => ['sometimes', 'string', 'timezone'],
            'member_id' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'supplier_member_id' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
