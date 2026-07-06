<?php

namespace App\Settings;

use Illuminate\Validation\Rule;

/**
 * Settings for the event-sourced opportunity lifecycle.
 *
 * Quote versioning caps (`max_versions`, `max_alternatives`) are read at
 * version-create time only; replay re-applies the stored event and never
 * consults these, so sourcing them from settings is replay-safe.
 *
 * @property int $number_pad @deprecated Migration-input only. Opportunity
 *                           numbering padding is managed via document
 *                           sequences after convergence (Numbering admin).
 * @property string $number_scope @deprecated Migration-input only. Numbering is
 *                                per-store; the legacy `global` scope is not
 *                                supported at runtime after convergence.
 */
class OpportunitySettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'opportunities';
    }

    public function defaults(): array
    {
        return [
            'number_pad' => 10,
            'number_scope' => 'store',
            // Quote versioning caps (opportunity-lifecycle.md §8.6). Read at
            // version-create time only; replay re-applies the stored event and
            // never consults these, so sourcing them from settings is replay-safe.
            'max_versions' => 20,
            'max_alternatives' => 5,
        ];
    }

    public function rules(): array
    {
        return [
            'number_pad' => ['required', 'integer', 'min:0', 'max:20'],
            'number_scope' => ['required', 'string', Rule::in(['store', 'global'])],
            'max_versions' => ['required', 'integer', 'min:1', 'max:200'],
            'max_alternatives' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function types(): array
    {
        return [
            'number_pad' => 'integer',
            'number_scope' => 'string',
            'max_versions' => 'integer',
            'max_alternatives' => 'integer',
        ];
    }
}
