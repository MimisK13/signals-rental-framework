<?php

use App\Models\User;
use App\Views\ColumnRegistry;
use App\Views\CreditNoteColumnRegistry;
use App\Views\DocumentTemplateColumnRegistry;
use App\Views\InvoiceColumnRegistry;
use App\Views\InvoicePaymentColumnRegistry;
use App\Views\PurchaseOrderColumnRegistry;
use App\Views\VirtualStockIntakeColumnRegistry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('returns default columns for Phase 4 billing registries', function (string $registryClass, array $expected) {
    /** @var ColumnRegistry $registry */
    $registry = app($registryClass);

    expect($registry->defaultColumns())->toBe($expected);
})->with([
    [CreditNoteColumnRegistry::class, ['number', 'status', 'invoice_id', 'total', 'issued_at']],
    [InvoiceColumnRegistry::class, ['number', 'type', 'status', 'member_id', 'store_id', 'total', 'amount_outstanding', 'issued_at']],
    [InvoicePaymentColumnRegistry::class, ['reference', 'invoice_id', 'payment_method', 'paid_at', 'amount']],
    [PurchaseOrderColumnRegistry::class, ['number', 'po_type', 'status', 'supplier_id', 'store_id', 'total', 'expected_at']],
    [VirtualStockIntakeColumnRegistry::class, ['number', 'status', 'product_id', 'store_id', 'quantity', 'unit_cost', 'available_from']],
    [DocumentTemplateColumnRegistry::class, ['name', 'document_type', 'store_id', 'is_default', 'is_active', 'version', 'last_edited_at']],
]);

it('hides cost-gated columns from isColumnVisible without costs.view', function () {
    $registry = new PurchaseOrderColumnRegistry;
    $user = User::factory()->create();

    $visible = new ReflectionMethod($registry, 'isColumnVisible');
    $visible->setAccessible(true);

    expect($visible->invoke($registry, 'total', $user))->toBeFalse()
        ->and($visible->invoke($registry, 'number', $user))->toBeTrue()
        ->and($visible->invoke($registry, 'missing-column', $user))->toBeFalse();
});

it('shows cost-gated columns from isColumnVisible when costs.view is granted', function () {
    $registry = new PurchaseOrderColumnRegistry;
    $user = User::factory()->create();
    $user->givePermissionTo('costs.view');

    $visible = new ReflectionMethod($registry, 'isColumnVisible');
    $visible->setAccessible(true);

    expect($visible->invoke($registry, 'total', $user))->toBeTrue();
});
