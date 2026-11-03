<?php

declare(strict_types=1);

namespace App\Domain\Products\Actions;

use App\Domain\CustomFields\Services\CustomFieldValidator;
use App\Domain\Products\Data\CreateProductGroupData;
use App\Domain\Products\Data\ProductGroupData;
use App\Domain\Products\Models\ProductGroup;
use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateProductGroup
{
    public function __invoke(CreateProductGroupData $data): ProductGroupData
    {
        Gate::authorize('products.create');

        app(CustomFieldValidator::class)->validate('ProductGroup', $data->custom_fields, enforceRequired: true);

        return DB::transaction(function () use ($data): ProductGroupData {
            $group = ProductGroup::create([
                'name' => $data->name,
                'description' => $data->description,
                'parent_id' => $data->parent_id,
                'sort_order' => $data->sort_order,
            ]);

            $group->syncCustomFields($data->custom_fields, applyDefaults: true);

            event(new AuditableEvent($group, 'product_group.created'));

            app(WebhookService::class)->dispatch('product_group.created', [
                'product_group' => ProductGroupData::fromModel($group)->toArray(),
            ]);

            return ProductGroupData::fromModel($group);
        });
    }
}
