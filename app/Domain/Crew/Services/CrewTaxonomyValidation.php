<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Models\CrewSkill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CrewTaxonomyValidation
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function validate(string $model, array $attributes, ?Model $record): array
    {
        $attributes['name'] = trim($attributes['name']);
        $attributes['slug'] = Str::slug($attributes['slug'] ?? $attributes['name']);
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', Rule::unique((new $model)->getTable(), 'slug')->ignore($record?->getKey())],
            'description' => ['nullable', 'string', 'max:20000'], 'is_active' => ['required', 'boolean'],
        ];
        if ($model === CrewRole::class) {
            $rules += ['is_supervisory' => ['required', 'boolean'], 'requires_certification' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0', 'max:32767']];
        } elseif ($model === CrewSkill::class) {
            $rules += ['category' => ['required', 'string', 'max:60']];
        } else {
            $rules += ['issuer' => ['nullable', 'string', 'max:160'], 'validity_months' => ['nullable', 'integer', 'min:1', 'max:1200'], 'requires_expiry' => ['required', 'boolean'], 'is_mandatory' => ['required', 'boolean']];
        }

        return Validator::make($attributes, $rules)->validate();
    }
}
