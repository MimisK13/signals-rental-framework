<?php

/**
 * P4-CL4b burndown batch 4 — policy branch coverage.
 */

use App\Enums\DocumentStatus;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Policies\CommunicationLogPolicy;
use App\Policies\CommunicationTemplatePolicy;
use App\Policies\DocumentPolicy;
use App\Policies\DocumentSequencePolicy;
use App\Policies\DocumentTemplatePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OpportunityCostPolicy;
use App\Policies\ProductGroupPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\VirtualStockIntakePolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

function batch4PolicyUser(array $permissions = []): User
{
    $user = User::factory()->create();
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function batch4GrantStore(User $user, Store $store): void
{
    MemberStore::create([
        'member_id' => $user->member_id,
        'store_id' => $store->id,
        'created_at' => now(),
    ]);
}

function batch4InvokePolicy(object $policy, string $method): mixed
{
    $reflection = new ReflectionMethod($policy, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($policy);
}

it('InvoicePolicy view update delete without model instances', function () {
    $policy = new InvoicePolicy;
    $user = batch4PolicyUser(['invoices.view', 'invoices.edit', 'invoices.delete']);

    expect($policy->view($user))->toBeTrue()
        ->and($policy->update($user))->toBeTrue()
        ->and($policy->delete($user))->toBeTrue();
});

it('ProductGroupPolicy exposes permission method names', function () {
    $policy = new ProductGroupPolicy;

    expect(batch4InvokePolicy($policy, 'viewPermission'))->toBe('products.view')
        ->and(batch4InvokePolicy($policy, 'managePermission'))->toBe('products.create')
        ->and(batch4InvokePolicy($policy, 'createPermission'))->toBe('products.create')
        ->and(batch4InvokePolicy($policy, 'deletePermission'))->toBe('products.delete');
});

it('PurchaseOrderPolicy view update delete without models and store-restricted view', function () {
    $policy = new PurchaseOrderPolicy;
    $user = batch4PolicyUser(['purchase_orders.view', 'costs.view', 'purchase_orders.edit', 'purchase_orders.delete']);

    expect($policy->view($user))->toBeTrue()
        ->and($policy->update($user))->toBeTrue()
        ->and($policy->delete($user))->toBeTrue();

    $store = Store::factory()->create();
    $po = PurchaseOrder::factory()->create(['store_id' => $store->id, 'status' => 'draft']);
    $viewer = batch4PolicyUser(['purchase_orders.view']);
    batch4GrantStore($viewer, $store);

    expect($policy->view($viewer, $po))->toBeTrue()
        ->and($policy->view(batch4PolicyUser(['purchase_orders.view']), $po))->toBeFalse();
});

it('VirtualStockIntakePolicy list abilities and manageCostOverrides gate', function () {
    $policy = new VirtualStockIntakePolicy;
    $store = Store::factory()->create();
    $intake = createTestVirtualStockIntake(['store_id' => $store->id, 'status' => 'confirmed']);
    $user = batch4PolicyUser([
        'virtual_stock.view',
        'costs.view',
        'virtual_stock.edit',
    ]);
    batch4GrantStore($user, $store);

    expect($policy->view($user))->toBeTrue()
        ->and($policy->view($user, $intake))->toBeTrue()
        ->and($policy->update($user))->toBeTrue()
        ->and($policy->delete($user))->toBeTrue()
        ->and($policy->manageCostOverrides($user, $intake))->toBeTrue();
});

it('CommunicationTemplatePolicy view update delete without models', function () {
    $policy = new CommunicationTemplatePolicy;
    $user = batch4PolicyUser(['notifications.manage']);

    expect($policy->view($user))->toBeTrue()
        ->and($policy->update($user))->toBeTrue()
        ->and($policy->delete($user))->toBeTrue();

    $store = Store::factory()->create();
    $template = CommunicationTemplate::factory()->for($store)->create(['is_system' => true]);
    batch4GrantStore($user, $store);

    expect($policy->delete($user, $template))->toBeFalse();
});

it('CommunicationTemplatePolicy update denies store-restricted users', function () {
    $policy = new CommunicationTemplatePolicy;
    $store = Store::factory()->create();
    $template = CommunicationTemplate::factory()->for($store)->create();
    $editor = batch4PolicyUser(['notifications.manage']);

    expect($policy->update($editor, $template))->toBeFalse();
});

it('OpportunityCostPolicy abilities', function () {
    $policy = new OpportunityCostPolicy;
    $cost = OpportunityCost::factory()->create();
    $viewer = batch4PolicyUser(['opportunities.view']);
    $costViewer = batch4PolicyUser(['opportunities.view', 'costs.view']);
    $editor = batch4PolicyUser(['opportunities.edit']);

    expect($policy->viewAny($viewer))->toBeTrue()
        ->and($policy->view($viewer, $cost))->toBeTrue()
        ->and($policy->viewAmounts($viewer, $cost))->toBeFalse()
        ->and($policy->viewAmounts($costViewer, $cost))->toBeTrue()
        ->and($policy->create($editor))->toBeTrue()
        ->and($policy->update($editor, $cost))->toBeTrue()
        ->and($policy->delete($editor, $cost))->toBeTrue();
});

it('DocumentTemplatePolicy managePermission and model-less checks', function () {
    $policy = new DocumentTemplatePolicy;
    $user = batch4PolicyUser(['document_templates.view', 'document_templates.edit']);

    expect(batch4InvokePolicy($policy, 'managePermission'))->toBe('document_templates.edit')
        ->and($policy->view($user))->toBeTrue()
        ->and($policy->update($user))->toBeTrue()
        ->and($policy->delete($user))->toBeTrue();
});

it('DocumentSequencePolicy permission helpers and view without model', function () {
    $policy = new DocumentSequencePolicy;
    $user = batch4PolicyUser(['document_templates.view', 'document_templates.edit']);

    expect(batch4InvokePolicy($policy, 'viewPermission'))->toBe('document_templates.view')
        ->and(batch4InvokePolicy($policy, 'managePermission'))->toBe('document_templates.edit')
        ->and($policy->view($user))->toBeTrue()
        ->and($policy->update($user))->toBeTrue();

    $store = Store::factory()->create();
    $sequence = DocumentSequence::factory()->for($store)->create();
    batch4GrantStore($user, $store);

    expect($policy->view($user, $sequence))->toBeTrue();
});

it('CommunicationLogPolicy canAccessSubjectStore branches', function () {
    $policy = new CommunicationLogPolicy;
    $manager = batch4PolicyUser(['notifications.manage']);
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $opportunity = Opportunity::factory()->for($store)->create();

    $log = CommunicationLog::factory()->create([
        'subject_type' => Opportunity::class,
        'subject_id' => $opportunity->id,
    ]);

    expect($policy->view($manager))->toBeTrue()
        ->and($policy->update($manager))->toBeTrue()
        ->and($policy->view($manager, $log))->toBeTrue();

    $member = Member::factory()->create();
    $restricted = User::factory()->create(['member_id' => $member->id]);
    $restricted->givePermissionTo('notifications.manage');
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $otherStore->id,
        'created_at' => now(),
    ]);

    expect($policy->view($restricted, $log))->toBeFalse();

    $nonOpportunityLog = CommunicationLog::factory()->create([
        'subject_type' => Member::class,
        'subject_id' => Member::factory()->create()->id,
    ]);

    expect($policy->view($restricted, $nonOpportunityLog))->toBeTrue();
});

it('DocumentPolicy managePermission and ability branches', function () {
    $policy = new DocumentPolicy;
    $store = Store::factory()->create();
    $user = batch4PolicyUser([
        'documents.view',
        'documents.create',
        'documents.finalise',
        'documents.delete',
        'documents.share',
    ]);
    batch4GrantStore($user, $store);

    expect(batch4InvokePolicy($policy, 'managePermission'))->toBe('documents.create')
        ->and($policy->view($user))->toBeTrue()
        ->and($policy->delete($user))->toBeTrue();

    $draft = Document::factory()->for($store)->create();
    $final = Document::factory()->for($store)->finalised()->create();
    $voided = Document::factory()->for($store)->create(['status' => DocumentStatus::Void]);

    expect($policy->finalise($user, $draft))->toBeTrue()
        ->and($policy->finalise($user, $final))->toBeFalse()
        ->and($policy->void($user, $final))->toBeTrue()
        ->and($policy->void($user, $draft))->toBeFalse()
        ->and($policy->share($user, $final))->toBeTrue()
        ->and($policy->share($user, $voided))->toBeFalse()
        ->and($policy->delete($user, $draft))->toBeTrue();
});

it('DocumentPolicy delete denies store-restricted users on model check', function () {
    $policy = new DocumentPolicy;
    $store = Store::factory()->create();
    $document = Document::factory()->for($store)->create();
    $deleter = batch4PolicyUser(['documents.delete']);

    expect($policy->delete($deleter, $document))->toBeFalse();
});

it('DocumentPolicy view finalise void share deny wrong store access', function () {
    $policy = new DocumentPolicy;
    $store = Store::factory()->create();
    $document = Document::factory()->for($store)->create();
    $user = batch4PolicyUser([
        'documents.view',
        'documents.finalise',
        'documents.delete',
        'documents.share',
    ]);

    expect($policy->view($user, $document))->toBeFalse()
        ->and($policy->finalise($user, $document))->toBeFalse()
        ->and($policy->void($user, Document::factory()->for($store)->finalised()->create()))->toBeFalse()
        ->and($policy->share($user, $document))->toBeFalse();
});

it('VirtualStockIntakePolicy view denies store-restricted users', function () {
    $policy = new VirtualStockIntakePolicy;
    $store = Store::factory()->create();
    $intake = createTestVirtualStockIntake(['store_id' => $store->id]);
    $viewer = batch4PolicyUser(['virtual_stock.view']);

    expect($policy->view($viewer, $intake))->toBeFalse();
});

it('InvoicePolicy view denies store-restricted users with permission', function () {
    $policy = new InvoicePolicy;
    $invoice = Invoice::factory()->create();
    $viewer = batch4PolicyUser(['invoices.view']);

    expect($policy->view($viewer, $invoice))->toBeFalse();
});
