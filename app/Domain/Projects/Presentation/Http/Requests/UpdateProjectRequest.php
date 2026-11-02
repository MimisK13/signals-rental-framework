<?php

declare(strict_types=1);

namespace App\Domain\Projects\Presentation\Http\Requests;

use App\Domain\Projects\Data\UpdateProjectData;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Sanctum\PersonalAccessToken;

final class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('projects.edit')) {
            return false;
        }

        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        return ! $token instanceof PersonalAccessToken || $token->can('projects:write');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return UpdateProjectData::rules();
    }
}
