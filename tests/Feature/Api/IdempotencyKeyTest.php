<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueInvoice;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Enums\InvoiceType;
use App\Enums\PricingRoundingDirection;
use App\Models\ApiIdempotencyKey;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Api\IdempotencyKeyService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
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
function idempotencyApiToken(User $user, array $abilities = ['invoices:read', 'invoices:write', 'payments:write']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

function idempotencyIssuedInvoice(User $owner): Invoice
{
    Queue::fake();
    \Pest\Laravel\actingAs($owner);
    $opportunity = giCreateOrderOpportunity();
    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    return $invoice->fresh(['payments']);
}

describe('POST /api/v1/opportunities/{opportunity}/invoices', function () {
    it('replays a stored checkout response without creating a duplicate invoice', function () {
        Queue::fake();
        $this->actingAs($this->owner);
        $opportunity = giCreateOrderOpportunity();
        $token = idempotencyApiToken($this->owner);
        $key = 'checkout-'.fake()->uuid();

        $first = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", [
            'type' => InvoiceType::Standard->value,
        ])->assertCreated();

        expect(Invoice::query()->count())->toBe(1);

        $second = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", [
            'type' => InvoiceType::Standard->value,
        ])->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        expect($second->json('invoice.id'))->toBe($first->json('invoice.id'))
            ->and(Invoice::query()->count())->toBe(1);
    });

    it('returns 409 when the same key is reused with a different payload', function () {
        Queue::fake();
        $this->actingAs($this->owner);
        $opportunity = giCreateOrderOpportunity();
        $token = idempotencyApiToken($this->owner);
        $key = 'checkout-conflict-'.fake()->uuid();

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", [
            'type' => InvoiceType::Standard->value,
        ])->assertCreated();

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", [
            'type' => InvoiceType::Deposit->value,
        ])->assertConflict()
            ->assertJsonPath('message', 'The Idempotency-Key was already used with a different request payload.');

        expect(Invoice::query()->count())->toBe(1);
    });

    it('allows the same key for different bearer tokens', function () {
        Queue::fake();
        $this->actingAs($this->owner);
        $zones = giWireZoneTaxInfrastructure();
        $opportunityA = giCreateOrderOpportunity([], [], $zones);
        $opportunityB = giCreateOrderOpportunity([], [], $zones);
        $otherOwner = User::factory()->owner()->create();
        $tokenA = idempotencyApiToken($this->owner);
        $tokenB = idempotencyApiToken($otherOwner);
        $key = 'shared-key-'.fake()->uuid();

        $this->withHeaders([
            'Authorization' => "Bearer {$tokenA}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/opportunities/{$opportunityA->id}/invoices", [
            'type' => InvoiceType::Standard->value,
        ])->assertCreated();

        $this->withHeaders([
            'Authorization' => "Bearer {$tokenB}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/opportunities/{$opportunityB->id}/invoices", [
            'type' => InvoiceType::Standard->value,
        ])->assertCreated()
            ->assertHeaderMissing('Idempotency-Replayed');

        expect(Invoice::query()->count())->toBe(2);
    });

    it('replays validation failures without re-executing checkout', function () {
        $this->actingAs($this->owner);
        $opportunity = giCreateOrderOpportunity();
        $token = idempotencyApiToken($this->owner);
        $key = 'checkout-invalid-'.fake()->uuid();

        $payload = ['type' => InvoiceType::Deposit->value];

        $first = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", $payload)
            ->assertUnprocessable();

        expect(Invoice::query()->count())->toBe(0);

        $second = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", $payload)
            ->assertUnprocessable()
            ->assertHeader('Idempotency-Replayed', 'true');

        expect($second->json())->toBe($first->json())
            ->and(Invoice::query()->count())->toBe(0);
    });

    it('behaves unchanged when no idempotency header is sent', function () {
        Queue::fake();
        $this->actingAs($this->owner);
        $zones = giWireZoneTaxInfrastructure();
        $opportunityA = giCreateOrderOpportunity([], [], $zones);
        $opportunityB = giCreateOrderOpportunity([], [], $zones);
        $token = idempotencyApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunityA->id}/invoices", [
                'type' => InvoiceType::Standard->value,
            ])->assertCreated()
            ->assertHeaderMissing('Idempotency-Replayed');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/opportunities/{$opportunityB->id}/invoices", [
                'type' => InvoiceType::Standard->value,
            ])->assertCreated()
            ->assertHeaderMissing('Idempotency-Replayed');

        expect(Invoice::query()->count())->toBe(2);
    });
});

describe('POST /api/v1/invoices/{invoice}/payments', function () {
    it('replays a stored payment response without recording a duplicate payment', function () {
        $invoice = idempotencyIssuedInvoice($this->owner);
        $token = idempotencyApiToken($this->owner);
        $key = 'payment-'.fake()->uuid();
        $amount = $invoice->amount_outstanding;

        $first = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => $amount,
            'payment_method' => 'bank_transfer',
        ])->assertOk();

        expect($invoice->payments()->count())->toBe(1);

        $second = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => $amount,
            'payment_method' => 'bank_transfer',
        ])->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        expect($second->json('invoice.status'))->toBe($first->json('invoice.status'))
            ->and($invoice->payments()->count())->toBe(1);
    });

    it('returns 409 when the same payment key is reused with a different payload', function () {
        $invoice = idempotencyIssuedInvoice($this->owner);
        $token = idempotencyApiToken($this->owner);
        $key = 'payment-conflict-'.fake()->uuid();

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 100,
            'payment_method' => 'bank_transfer',
        ])->assertOk();

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 200,
            'payment_method' => 'bank_transfer',
        ])->assertConflict();

        expect($invoice->payments()->count())->toBe(1);
    });

    it('replays payment validation failures without re-executing the action', function () {
        $invoice = idempotencyIssuedInvoice($this->owner);
        $token = idempotencyApiToken($this->owner);
        $key = 'payment-invalid-'.fake()->uuid();

        $first = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/invoices/{$invoice->id}/payments", [])
            ->assertUnprocessable();

        $second = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $key,
        ])->postJson("/api/v1/invoices/{$invoice->id}/payments", [])
            ->assertUnprocessable()
            ->assertHeader('Idempotency-Replayed', 'true');

        expect($second->json())->toBe($first->json())
            ->and($invoice->payments()->count())->toBe(0);
    });
});

describe('ApiIdempotencyKey pruning', function () {
    it('prunes expired idempotency rows via model:prune', function () {
        $token = $this->owner->createToken('test', ['invoices:write']);
        $expired = ApiIdempotencyKey::query()->create([
            'key' => 'expired-key',
            'personal_access_token_id' => $token->accessToken->id,
            'request_fingerprint' => hash('sha256', 'expired'),
            'response_status' => 201,
            'response_body' => ['invoice' => ['id' => 1]],
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('model:prune', ['--model' => [ApiIdempotencyKey::class]])
            ->assertSuccessful();

        expect(ApiIdempotencyKey::query()->whereKey($expired->id)->exists())->toBeFalse();
    });
});

describe('IdempotencyKeyService fingerprint', function () {
    it('scopes fingerprints to method, path, and normalized payload', function () {
        $service = app(IdempotencyKeyService::class);
        $requestA = Request::create('/api/v1/invoices/5/payments', 'POST', [
            'amount' => 1000,
            'payment_method' => 'bank_transfer',
        ]);
        $requestB = Request::create('/api/v1/invoices/5/payments', 'POST', [
            'payment_method' => 'bank_transfer',
            'amount' => 1000,
        ]);
        $requestC = Request::create('/api/v1/invoices/5/payments', 'POST', [
            'amount' => 2000,
            'payment_method' => 'bank_transfer',
        ]);

        expect($service->fingerprint($requestA))->toBe($service->fingerprint($requestB))
            ->and($service->fingerprint($requestA))->not->toBe($service->fingerprint($requestC));
    });
});
