<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Services\CustomFieldDefinitionResolver;
use App\Services\SchemaRegistry;
use Illuminate\Support\Facades\Gate;

class DeleteCustomField
{
    public function __invoke(CustomField $field): void
    {
        Gate::authorize('custom-fields.manage');

        event(new AuditableEvent($field, 'custom_field.deleted'));

        $field->delete();

        app(CustomFieldDefinitionResolver::class)->clearCache($field->module_type);
        app(SchemaRegistry::class)->invalidateAll();
    }
}
