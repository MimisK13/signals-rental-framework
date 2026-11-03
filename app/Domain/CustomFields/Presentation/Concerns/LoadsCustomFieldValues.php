<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Presentation\Concerns;

use App\Domain\CustomFields\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

trait LoadsCustomFieldValues
{
    /**
     * Load custom field values from a model into a keyed array.
     *
     * @return array<string, mixed>
     */
    protected function loadCustomFieldValuesFrom(Model $model): array
    {
        $values = [];
        $model->load('customFieldValues.customField');

        $customFieldValues = $model->getRelation('customFieldValues');

        if (! $customFieldValues instanceof Collection) {
            return $values;
        }

        foreach ($customFieldValues as $cfv) {
            if (! $cfv instanceof CustomFieldValue) {
                continue;
            }

            if ($cfv->customField === null) {
                Log::warning('Orphaned custom field value: definition not found', [
                    'custom_field_value_id' => $cfv->id,
                    'custom_field_id' => $cfv->custom_field_id,
                ]);

                continue;
            }
            $column = $cfv->customField->field_type->valueColumn();
            $values[$cfv->customField->name] = $cfv->{$column};
        }

        return $values;
    }
}
