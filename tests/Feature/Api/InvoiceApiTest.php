<?php

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueInvoice;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Enums\InvoiceType;
use App\Enums\PricingRoundingDirection;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    $this->owner = User::factory()->owner()->create();
});

/**
 * @param  list<string>  $abilities
 */
function invoiceApiToken(User $user, array $abilities = ['invoices:read', 'invoices:write', 'payments:write']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function apiCreateDraftInvoice(array $overrides = []): Invoice
{
    $store = Store::factory()->create();
    $member = Member::factory()->organisation()->create();

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'type' => InvoiceType::Standard->value,
        ...$overrides,
    ]));

    return Invoice::query()->findOrFail($draft->id);
}

/**
 * @param  list<string>  $permissions
 */
function invoiceApiManager(array $permissions = ['invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.issue', 'invoices.void', 'invoices.credit', 'payments.record'], ?int $storeId = null): User
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

describe('GET /api/v1/invoices', function () {
    it('lists invoices with pagination meta', function () {
        Invoice::factory()->count(2)->create();
        $token = invoiceApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonStructure([
                'invoices' => [
                    '*' => ['id', 'number', 'type', 'status', 'total', 'amount_outstanding'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 2);
    });

    it('filters by status using Ransack', function () {
        Invoice::factory()->create(['status' => 'draft']);
        Invoice::factory()->issued()->create();
        $token = invoiceApiToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/invoices?q[status_eq]=draft')
            ->assertOk();

        expect($response->json('invoices'))->toHaveCount(1)
            ->and($response->json('invoices.0.status'))->toBe('draft');
    });

    it('filters by member_id and exact number', function () {
        $member = Member::factory()->organisation()->create();
        Invoice::factory()->create(['member_id' => $member->id, 'number' => 'INV-1001']);
        Invoice::factory()->create(['number' => 'INV-2002']);
        $token = invoiceApiToken($this->owner);

        $byMember = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/invoices?q[member_id_eq]={$member->id}")
            ->assertOk();

        expect($byMember->json('invoices'))->toHaveCount(1);

        $byNumber = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/invoices?q[number_eq]=INV-1001')
            ->assertOk();

        expect($byNumber->json('invoices.0.number'))->toBe('INV-1001');
    });

    it('includes payments when requested', function () {
        $invoice = Invoice::factory()->issued()->create(['amount_paid' => 1000, 'amount_outstanding' => 0]);
        $invoice->payments()->create([
            'id' => snowflake_id(),
            'amount' => 1000,
            'payment_method' => 'bank_transfer',
            'paid_at' => now(),
        ]);
        $token = invoiceApiToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/invoices/{$invoice->id}?include=payments")
            ->assertOk();

        expect($response->json('invoice.payments'))->toBeArray()->toHaveCount(1);
    });

    it('requires invoices:read ability', function () {
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/invoices')
            ->assertForbidden();
    });
});

describe('GET /api/v1/invoices/{invoice}', function () {
    it('shows a single invoice', function () {
        $invoice = Invoice::factory()->create(['number' => 'INV-SHOW']);
        $token = invoiceApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('invoice.number', 'INV-SHOW');
    });

    it('returns 404 for cross-store access', function () {
        $invoice = Invoice::factory()->create();
        $member = Member::factory()->create();
        $restricted = User::factory()->create(['member_id' => $member->id, 'is_owner' => false, 'is_active' => true]);
        $restricted->givePermissionTo('invoices.view');
        MemberStore::create([
            'member_id' => $member->id,
            'store_id' => Store::factory()->create()->id,
            'created_at' => now(),
        ]);
        $token = invoiceApiToken($restricted);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertNotFound();
    });
});

describe('POST /api/v1/invoices', function () {
    it('creates a draft invoice', function () {
        $store = Store::factory()->create();
        $member = Member::factory()->organisation()->create();
        $token = invoiceApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/invoices', [
                'member_id' => $member->id,
                'store_id' => $store->id,
                'type' => InvoiceType::Standard->value,
                'notes' => 'Manual draft',
            ])
            ->assertCreated()
            ->assertJsonPath('invoice.status', 'draft')
            ->assertJsonPath('invoice.notes', 'Manual draft');
    });

    it('validates required fields', function () {
        $token = invoiceApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/invoices', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member_id', 'store_id', 'type']);
    });
});

describe('PATCH /api/v1/invoices/{invoice}', function () {
    it('updates a draft invoice', function () {
        $this->actingAs($this->owner);
        $invoice = apiCreateDraftInvoice(['notes' => 'Old']);
        $token = invoiceApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/invoices/{$invoice->id}", ['notes' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('invoice.notes', 'Updated');
    });

    it('rejects updating a non-draft invoice', function () {
        $invoice = Invoice::factory()->issued()->create();
        $manager = invoiceApiManager(['invoices.view', 'invoices.edit'], $invoice->store_id);
        $token = invoiceApiToken($manager);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/invoices/{$invoice->id}", ['notes' => 'Nope'])
            ->assertForbidden();
    });
});

describe('DELETE /api/v1/invoices/{invoice}', function () {
    it('deletes a draft invoice', function () {
        $this->actingAs($this->owner);
        $invoice = apiCreateDraftInvoice();
        $token = invoiceApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/invoices/{$invoice->id}")
            ->assertNoContent();

        expect(Invoice::withTrashed()->find($invoice->id)?->trashed())->toBeTrue();
    });

    it('rejects deleting an issued invoice', function () {
        $invoice = Invoice::factory()->issued()->create();
        $manager = invoiceApiManager(['invoices.view', 'invoices.delete'], $invoice->store_id);
        $token = invoiceApiToken($manager);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/invoices/{$invoice->id}")
            ->assertForbidden();
    });
});

describe('POST /api/v1/opportunities/{opportunity}/invoices', function () {
    it('generates an invoice from an opportunity', function () {
        Queue::fake();
        $this->actingAs($this->owner);
        $opportunity = giCreateOrderOpportunity();
        $token = invoiceApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", [
                'type' => InvoiceType::Standard->value,
            ])
            ->assertCreated()
            ->assertJsonPath('invoice.opportunity_id', $opportunity->id);
    });
});

describe('invoice lifecycle endpoints', function () {
    it('issues, records payment, and emails an invoice', function () {
        Queue::fake();
        $this->actingAs($this->owner);
        $opportunity = giCreateOrderOpportunity();
        $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));
        $invoice = Invoice::query()->findOrFail($draft->id);
        $token = invoiceApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice->id}/issue")
            ->assertOk()
            ->assertJsonPath('invoice.status', 'issued');

        $invoice->refresh();
        $outstanding = $invoice->amount_outstanding;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'amount' => $outstanding,
                'payment_method' => 'bank_transfer',
            ])
            ->assertOk()
            ->assertJsonPath('invoice.status', 'paid');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice->id}/email")
            ->assertAccepted();
    });

    it('voids a draft invoice', function () {
        $this->actingAs($this->owner);
        $invoice = apiCreateDraftInvoice();
        $token = invoiceApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice->id}/void")
            ->assertOk()
            ->assertJsonPath('invoice.status', 'void');
    });

    it('issues a full credit note against a paid invoice', function () {
        Queue::fake();
        $this->actingAs($this->owner);
        $opportunity = giCreateOrderOpportunity();
        $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
        ]));
        $invoice = Invoice::query()->findOrFail($draft->id);
        (new IssueInvoice)($invoice);
        $invoice->refresh();

        $token = invoiceApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'amount' => $invoice->amount_outstanding,
                'payment_method' => 'bank_transfer',
            ])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice->id}/credit_notes", [])
            ->assertCreated()
            ->assertJsonStructure(['credit_note' => ['id', 'number', 'total']]);
    });
});

describe('ability enforcement', function () {
    it('forbids issue without invoices:write', function () {
        $this->actingAs($this->owner);
        $invoice = apiCreateDraftInvoice();
        auth()->logout();

        $token = $this->owner->createToken('test', ['invoices:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/invoices/{$invoice->id}/issue")
            ->assertForbidden();
    });
});
