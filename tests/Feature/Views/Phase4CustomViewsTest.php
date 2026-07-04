<?php

use App\Models\CreditNote;
use App\Models\CustomView;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\ColumnRegistryResolver;
use App\Services\ViewResolver;
use App\Views\PurchaseOrderColumnRegistry;
use App\Views\VirtualStockIntakeColumnRegistry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

/**
 * @param  list<string>  $abilities
 */
function phase4CustomViewToken(User $user, array $abilities): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

/**
 * @param  list<string>  $permissions
 */
function phase4CustomViewUser(array $permissions, ?int $storeId = null): User
{
    $member = Member::factory()->create();
    $user = User::factory()->create(['member_id' => $member->id, 'is_owner' => false, 'is_active' => true]);
    $user->givePermissionTo($permissions);

    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $storeId ?? Store::factory()->create()->id,
        'created_at' => now(),
    ]);

    return $user;
}

describe('Phase 4 custom view entity types', function () {
    it('accepts registered Phase 4 entity types on create', function (string $entityType, array $columns) {
        Sanctum::actingAs($this->owner, ['*']);

        $this->postJson('/api/v1/custom_views', [
            'name' => "View for {$entityType}",
            'entity_type' => $entityType,
            'columns' => $columns,
        ])
            ->assertCreated()
            ->assertJsonPath('custom_view.entity_type', $entityType);
    })->with([
        'invoices' => ['invoices', ['number', 'status', 'total']],
        'credit_notes' => ['credit_notes', ['number', 'status', 'total']],
        'purchase_orders' => ['purchase_orders', ['number', 'status', 'supplier_id']],
        'virtual_stock_intakes' => ['virtual_stock_intakes', ['number', 'status', 'quantity']],
        'document_templates' => ['document_templates', ['name', 'document_type', 'is_active']],
    ]);

    it('rejects an unknown entity type on create', function () {
        Sanctum::actingAs($this->owner, ['*']);

        $this->postJson('/api/v1/custom_views', [
            'name' => 'Orphan',
            'entity_type' => 'not_a_real_entity',
            'columns' => ['number'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entity_type']);
    });
});

describe('invoice custom views', function () {
    it('applies a saved view filter via view_id', function () {
        Invoice::factory()->create(['status' => 'draft', 'number' => 'INV-DRAFT-VIEW']);
        Invoice::factory()->issued()->create(['number' => 'INV-ISSUED-VIEW']);

        Sanctum::actingAs($this->owner, ['invoices:read']);

        $view = CustomView::factory()->create([
            'entity_type' => 'invoices',
            'visibility' => 'personal',
            'user_id' => $this->owner->id,
            'columns' => ['number', 'status'],
            'filters' => [
                ['field' => 'status', 'predicate' => 'eq', 'value' => 'draft'],
            ],
        ]);

        $response = $this->getJson("/api/v1/invoices?view_id={$view->id}")
            ->assertOk()
            ->assertJsonPath('meta.view.id', $view->id);

        $numbers = collect($response->json('invoices'))->pluck('number')->all();

        expect($numbers)->toContain('INV-DRAFT-VIEW')
            ->and($numbers)->not->toContain('INV-ISSUED-VIEW');
    });
});

describe('purchase order custom views', function () {
    it('applies a saved view filter via view_id', function () {
        $store = Store::factory()->create();
        $user = phase4CustomViewUser(['costs.view', 'purchase_orders.access', 'purchase_orders.view'], $store->id);
        $token = phase4CustomViewToken($user, ['purchase_orders:read']);

        PurchaseOrder::factory()->create(['status' => 'draft', 'number' => 'PO-DRAFT-VIEW', 'store_id' => $store->id]);
        PurchaseOrder::factory()->sent()->create(['number' => 'PO-SENT-VIEW', 'store_id' => $store->id]);

        $view = CustomView::factory()->create([
            'entity_type' => 'purchase_orders',
            'visibility' => 'personal',
            'user_id' => $user->id,
            'columns' => ['number', 'status'],
            'filters' => [
                ['field' => 'status', 'predicate' => 'eq', 'value' => 'draft'],
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/purchase_orders?view_id={$view->id}")
            ->assertOk();

        $numbers = collect($response->json('purchase_orders'))->pluck('number')->all();

        expect($numbers)->toContain('PO-DRAFT-VIEW')
            ->and($numbers)->not->toContain('PO-SENT-VIEW');
    });
});

describe('virtual stock intake custom views', function () {
    it('applies a saved view filter via view_id', function () {
        $store = Store::factory()->create();
        $user = phase4CustomViewUser(['costs.view', 'virtual_stock.access', 'virtual_stock.view'], $store->id);
        Sanctum::actingAs($user, ['virtual_stock:read']);

        VirtualStockIntake::factory()->create(['status' => 'draft', 'number' => 'VSI-DRAFT-VIEW', 'store_id' => $store->id]);
        VirtualStockIntake::factory()->confirmed()->create(['number' => 'VSI-CONF-VIEW', 'store_id' => $store->id]);

        $view = CustomView::factory()->create([
            'entity_type' => 'virtual_stock_intakes',
            'visibility' => 'personal',
            'user_id' => $user->id,
            'columns' => ['number', 'status'],
            'filters' => [
                ['field' => 'status', 'predicate' => 'eq', 'value' => 'draft'],
            ],
        ]);

        $response = $this->getJson("/api/v1/virtual_stock_intakes?view_id={$view->id}")
            ->assertOk();

        $numbers = collect($response->json('virtual_stock_intakes'))->pluck('number')->all();

        expect($numbers)->toContain('VSI-DRAFT-VIEW')
            ->and($numbers)->not->toContain('VSI-CONF-VIEW');
    });
});

describe('credit note custom views', function () {
    it('applies a saved view filter through ViewResolver', function () {
        CreditNote::factory()->create(['status' => 'issued', 'number' => 'CN-ISSUED-VIEW']);
        CreditNote::factory()->create(['status' => 'void', 'number' => 'CN-VOID-VIEW']);

        $view = CustomView::factory()->create([
            'entity_type' => 'credit_notes',
            'visibility' => 'personal',
            'user_id' => $this->owner->id,
            'columns' => ['number', 'status'],
            'filters' => [
                ['field' => 'status', 'predicate' => 'eq', 'value' => 'issued'],
            ],
        ]);

        $query = CreditNote::query();
        $query = app(ViewResolver::class)->applyFilters($query, $view);

        $numbers = $query->pluck('number')->all();

        expect($numbers)->toContain('CN-ISSUED-VIEW')
            ->and($numbers)->not->toContain('CN-VOID-VIEW');
    });
});

describe('document template custom views', function () {
    it('applies a saved view filter via view_id', function () {
        Sanctum::actingAs($this->owner, ['documents:read']);

        DocumentTemplate::factory()->create(['document_type' => 'quote', 'name' => 'Quote Template View']);
        DocumentTemplate::factory()->create(['document_type' => 'invoice', 'name' => 'Invoice Template View']);

        $view = CustomView::factory()->create([
            'entity_type' => 'document_templates',
            'visibility' => 'personal',
            'user_id' => $this->owner->id,
            'columns' => ['name', 'document_type'],
            'filters' => [
                ['field' => 'document_type', 'predicate' => 'eq', 'value' => 'quote'],
            ],
        ]);

        $response = $this->getJson("/api/v1/document_templates?view_id={$view->id}")
            ->assertOk()
            ->assertJsonPath('meta.view.name', $view->name);

        $names = collect($response->json('document_templates'))->pluck('name')->all();

        expect($names)->toContain('Quote Template View')
            ->and($names)->not->toContain('Invoice Template View');
    });
});

describe('Phase 4 cost column gating in custom views', function () {
    it('hides purchase order cost columns from the visible registry without costs.view', function () {
        $user = phase4CustomViewUser(['purchase_orders.view']);

        $columns = app(PurchaseOrderColumnRegistry::class)->visibleColumns($user);

        expect(array_keys($columns))->not->toContain('total', 'subtotal', 'tax_total');
    });

    it('includes purchase order cost columns in the visible registry with costs.view', function () {
        $user = phase4CustomViewUser(['costs.view', 'purchase_orders.view']);

        $columns = app(PurchaseOrderColumnRegistry::class)->visibleColumns($user);

        expect(array_keys($columns))->toContain('total', 'subtotal', 'tax_total');
    });

    it('hides virtual stock unit_cost from the visible registry without costs.view', function () {
        $user = phase4CustomViewUser(['virtual_stock.view']);

        $columns = app(VirtualStockIntakeColumnRegistry::class)->visibleColumns($user);

        expect(array_keys($columns))->not->toContain('unit_cost');
    });

    it('rejects purchase order views that include cost columns without costs.view', function () {
        $user = phase4CustomViewUser(['purchase_orders.view']);
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/custom_views', [
            'name' => 'PO With Total',
            'entity_type' => 'purchase_orders',
            'columns' => ['number', 'total'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['columns']);
    });

    it('allows purchase order views with cost columns when the user has costs.view', function () {
        $user = phase4CustomViewUser(['costs.view', 'purchase_orders.view']);
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/custom_views', [
            'name' => 'PO With Total',
            'entity_type' => 'purchase_orders',
            'columns' => ['number', 'total'],
        ])
            ->assertCreated()
            ->assertJsonPath('custom_view.columns', ['number', 'total']);
    });
});

describe('ColumnRegistryResolver Phase 4 coverage', function () {
    it('resolves every new Phase 4 registry', function (string $entityType) {
        expect(app(ColumnRegistryResolver::class)->resolve($entityType))->not->toBeNull();
    })->with([
        'invoices',
        'credit_notes',
        'purchase_orders',
        'virtual_stock_intakes',
        'document_templates',
    ]);
});
