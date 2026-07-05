<?php

use App\Enums\InvoiceType;
use App\Models\ApiIdempotencyKey;
use App\Models\User;
use App\Services\Api\IdempotencyKeyService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL idempotency concurrent-claim lane
|--------------------------------------------------------------------------
|
| Proves the insert-first claim pattern backed by the unique
| (personal_access_token_id, key) constraint: an in-flight claim yields 425,
| and a completed claim replays the stored response.
|
|   php artisan test --compact tests/Pgsql/IdempotencyKeyConcurrentClaimPostgresTest.php
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

/**
 * @param  list<string>  $abilities
 * @return array{plain: string, token_id: int}
 */
function pgsqlIdempotencyBearer(User $user, array $abilities = ['invoices:write']): array
{
    $newToken = $user->createToken('pgsql-idempotency', $abilities);

    return [
        'plain' => $newToken->plainTextToken,
        'token_id' => $newToken->accessToken->id,
    ];
}

it('returns 425 when an in-flight claim already exists for the same key', function () {
    Queue::fake();
    $this->actingAs($this->owner);
    $opportunity = giCreateOrderOpportunity();
    $bearer = pgsqlIdempotencyBearer($this->owner);
    $key = 'pgsql-in-flight-'.fake()->uuid();
    $payload = ['type' => InvoiceType::Standard->value];

    $request = Request::create("/api/v1/opportunities/{$opportunity->id}/invoices", 'POST', $payload);
    $fingerprint = app(IdempotencyKeyService::class)->fingerprint($request);

    ApiIdempotencyKey::query()->create([
        'key' => $key,
        'personal_access_token_id' => $bearer['token_id'],
        'request_fingerprint' => $fingerprint,
        'response_status' => null,
        'response_body' => null,
        'expires_at' => now()->addDay(),
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$bearer['plain'],
        'Idempotency-Key' => $key,
    ])->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", $payload)
        ->assertStatus(425)
        ->assertJsonPath('message', 'A request with this Idempotency-Key is still being processed. Retry after a short delay.');
});

it('replays a completed claim for the same key and fingerprint', function () {
    Queue::fake();
    $this->actingAs($this->owner);
    $opportunity = giCreateOrderOpportunity();
    $bearer = pgsqlIdempotencyBearer($this->owner);
    $key = 'pgsql-replay-'.fake()->uuid();
    $payload = ['type' => InvoiceType::Standard->value];
    $storedBody = [
        'invoice' => [
            'id' => 4242,
            'status' => 'issued',
        ],
    ];

    $request = Request::create("/api/v1/opportunities/{$opportunity->id}/invoices", 'POST', $payload);
    $fingerprint = app(IdempotencyKeyService::class)->fingerprint($request);

    ApiIdempotencyKey::query()->create([
        'key' => $key,
        'personal_access_token_id' => $bearer['token_id'],
        'request_fingerprint' => $fingerprint,
        'response_status' => 201,
        'response_body' => $storedBody,
        'expires_at' => now()->addDay(),
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$bearer['plain'],
        'Idempotency-Key' => $key,
    ])->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", $payload)
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true')
        ->assertJson($storedBody);
});

it('returns 409 when a completed claim fingerprint differs', function () {
    Queue::fake();
    $this->actingAs($this->owner);
    $opportunity = giCreateOrderOpportunity();
    $bearer = pgsqlIdempotencyBearer($this->owner);
    $key = 'pgsql-conflict-'.fake()->uuid();

    ApiIdempotencyKey::query()->create([
        'key' => $key,
        'personal_access_token_id' => $bearer['token_id'],
        'request_fingerprint' => hash('sha256', 'different-payload'),
        'response_status' => 201,
        'response_body' => ['invoice' => ['id' => 1]],
        'expires_at' => now()->addDay(),
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$bearer['plain'],
        'Idempotency-Key' => $key,
    ])->postJson("/api/v1/opportunities/{$opportunity->id}/invoices", [
        'type' => InvoiceType::Deposit->value,
        'amount_percentage' => '10',
    ])->assertConflict();
});
