<?php

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

/**
 * @param  list<string>  $abilities
 */
function creditNoteApiToken(User $user, array $abilities = ['credit-notes:read']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

/**
 * @param  list<string>  $permissions
 */
function creditNoteApiUser(array $permissions, ?int $storeId = null): User
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

describe('GET /api/v1/credit_notes', function () {
    it('lists credit notes with pagination meta', function () {
        CreditNote::factory()->count(2)->create();
        $token = creditNoteApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/credit_notes')
            ->assertOk()
            ->assertJsonStructure([
                'credit_notes' => [
                    '*' => ['id', 'number', 'status', 'invoice_id', 'total'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 2);
    });

    it('filters by exact number using Ransack', function () {
        CreditNote::factory()->create(['number' => 'CN-1001']);
        CreditNote::factory()->create(['number' => 'CN-2002']);
        $token = creditNoteApiToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/credit_notes?q[number_eq]=CN-1001')
            ->assertOk();

        expect($response->json('credit_notes'))->toHaveCount(1)
            ->and($response->json('credit_notes.0.number'))->toBe('CN-1001');
    });

    it('scopes results to accessible stores', function () {
        $storeA = Store::factory()->create();
        $storeB = Store::factory()->create();
        $invoiceA = Invoice::factory()->create(['store_id' => $storeA->id]);
        $invoiceB = Invoice::factory()->create(['store_id' => $storeB->id]);
        CreditNote::factory()->create(['invoice_id' => $invoiceA->id, 'number' => 'CN-STORE-A']);
        CreditNote::factory()->create(['invoice_id' => $invoiceB->id, 'number' => 'CN-STORE-B']);

        $user = creditNoteApiUser(['invoices.access', 'invoices.view'], $storeA->id);
        $token = creditNoteApiToken($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/credit_notes')
            ->assertOk();

        expect($response->json('credit_notes'))->toHaveCount(1)
            ->and($response->json('credit_notes.0.number'))->toBe('CN-STORE-A');
    });

    it('returns 403 without the credit-notes:read ability', function () {
        CreditNote::factory()->create();
        $token = $this->owner->createToken('test', ['invoices:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/credit_notes')
            ->assertForbidden();
    });

    it('returns 403 without invoices.view permission', function () {
        CreditNote::factory()->create();
        $user = User::factory()->create(['is_owner' => false, 'is_active' => true]);
        $token = creditNoteApiToken($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/credit_notes')
            ->assertForbidden();
    });
});

describe('GET /api/v1/credit_notes/{credit_note}', function () {
    it('returns a single credit note', function () {
        $creditNote = CreditNote::factory()->create(['number' => 'CN-SHOW-1']);
        $token = creditNoteApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/credit_notes/{$creditNote->id}")
            ->assertOk()
            ->assertJsonPath('credit_note.id', $creditNote->id)
            ->assertJsonPath('credit_note.number', 'CN-SHOW-1');
    });

    it('returns 404 for cross-store access', function () {
        $creditNote = CreditNote::factory()->create();
        $user = creditNoteApiUser(['invoices.access', 'invoices.view']);
        $token = creditNoteApiToken($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/credit_notes/{$creditNote->id}")
            ->assertNotFound();
    });
});
