<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Http\Requests\Crew;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

final class RequestCrewAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = $this->user()?->currentAccessToken();

        return $this->user()?->can('crew.availability.request') === true && (! ($token instanceof PersonalAccessToken) || $token->can('crew:write'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'type' => ['sometimes', 'string', 'max:24'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'all_day' => ['sometimes', 'boolean'],
        ];
    }
}
