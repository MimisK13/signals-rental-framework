<?php

declare(strict_types=1);

namespace App\Domain\Stores\Actions;

use App\Domain\Stores\Data\CreateStoreData;
use App\Domain\Communications\Database\Seeders\CommunicationTemplateSeeder;
use App\Domain\Documents\Database\Seeders\DocumentTemplateSeeder;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Stores\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Create a store location.
 *
 * Authorises via StorePolicy (settings.manage), performs the write inside a
 * transaction, and records an audit-trail entry. The first store created is
 * automatically flagged as the default so store-scoping always resolves.
 */
class CreateStore
{
    public function __invoke(CreateStoreData $data): Store
    {
        Gate::authorize('create', Store::class);

        return DB::transaction(function () use ($data): Store {
            $attributes = $data->toArray();

            if ($data->is_default === null) {
                // Lock existing rows so two concurrent first-store creations
                // cannot both resolve to "no stores yet" and both default.
                $attributes['is_default'] = Store::query()->lockForUpdate()->count() === 0;
            }

            $store = Store::create($attributes);

            app(DocumentTemplateSeeder::class)->seedForStore($store);
            app(CommunicationTemplateSeeder::class)->seedForStore($store);

            event(new AuditableEvent($store, 'store.created'));

            return $store;
        });
    }
}
