<?php

use App\Actions\Containers\CreateTemporaryContainer;
use App\Data\Containers\CreateTemporaryContainerData;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL temporary-container barcode race lane
|--------------------------------------------------------------------------
|
| Proves the partial unique index uq_containers_active_temporary_barcode
| rejects duplicate active temporary barcodes and that a concurrent insert
| between the in-action guard and INSERT is translated to a friendly 422.
|
|   php artisan test --compact --group=pgsql
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
});

it('rejects a second active temporary container with the same barcode', function () {
    Container::factory()->temporary()->create([
        'barcode' => 'TEMP-RACE-001',
        'store_id' => $this->store->id,
        'status' => ContainerStatus::Open->value,
    ]);

    expect(fn () => Container::factory()->temporary()->create([
        'barcode' => 'TEMP-RACE-001',
        'store_id' => $this->store->id,
        'status' => ContainerStatus::Open->value,
    ]))->toThrow(QueryException::class);
});

it('translates a raced temporary barcode violation into a 422', function () {
    $barcode = 'TEMP-RACE-CONCURRENT';

    $injected = false;
    DB::listen(function ($query) use (&$injected, $barcode): void {
        if ($injected) {
            return;
        }

        if (str_contains($query->sql, 'containers')
            && str_contains($query->sql, 'exists')
            && str_contains($query->sql, 'barcode')) {
            $injected = true;

            DB::connection($query->connectionName)->table('containers')->insert([
                'uuid' => (string) Str::uuid(),
                'name' => 'Raced temporary',
                'serialised_item_id' => null,
                'product_id' => null,
                'is_temporary' => true,
                'barcode' => $barcode,
                'store_id' => $this->store->id,
                'scan_mode' => 'free',
                'status' => ContainerStatus::Open->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    expect(fn () => (new CreateTemporaryContainer)(CreateTemporaryContainerData::from([
        'barcode' => $barcode,
        'store_id' => $this->store->id,
    ])))->toThrow(ValidationException::class);
});
