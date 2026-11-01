<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Requests\Crew;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

final class AddCrewTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = $this->user()?->currentAccessToken();

        return $this->user()?->can('crew.timesheets.create') === true && (! ($token instanceof PersonalAccessToken) || $token->can('crew:write'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'kind' => ['sometimes', 'string', 'in:work,travel,standby,training,other'],
            'break_minutes' => ['sometimes', 'integer', 'min:0'],
            'crew_shift_id' => ['sometimes', 'nullable', 'integer', 'exists:crew_shifts,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'version' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
