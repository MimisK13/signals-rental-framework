<?php

use App\Enums\ShortageResolutionStatus;
use App\Models\ShortageResolution;
use App\Models\User;
use App\Services\Shortages\ShortageEventRecorder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

/**
 * @param  array<string, mixed>  $body
 * @param  list<string>  $abilities
 * @return TestResponse<JsonResponse>
 */
function postTransition(
    TestCase $testCase,
    User $owner,
    ShortageResolution $resolution,
    string $status,
    array $body = [],
    array $abilities = ['shortages:write'],
): TestResponse {
    $token = $owner->createToken('test', $abilities)->plainTextToken;

    return $testCase->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/shortage_resolutions/{$resolution->id}/transition", ['status' => $status] + $body);
}

describe('POST shortage_resolutions/{id}/transition', function () {
    it('confirms a pending resolution via the unified transition endpoint', function () {
        $resolution = ShortageResolution::factory()->pending()->create();
        $spy = Mockery::spy(ShortageEventRecorder::class);
        $this->app->instance(ShortageEventRecorder::class, $spy);

        postTransition($this, $this->owner, $resolution, 'confirmed')->assertOk()
            ->assertJsonPath('resolution.status', 'confirmed');

        expect($resolution->refresh()->status)->toBe(ShortageResolutionStatus::Confirmed);
        $spy->shouldHaveReceived('resolutionConfirmed')->once();
    });

    it('starts, fulfills, cancels, and fails through legal transitions', function () {
        $confirmed = ShortageResolution::factory()->create();
        postTransition($this, $this->owner, $confirmed, 'in_progress')->assertOk();
        expect($confirmed->refresh()->status)->toBe(ShortageResolutionStatus::InProgress);

        postTransition($this, $this->owner, $confirmed, 'fulfilled')->assertOk();
        expect($confirmed->refresh()->status)->toBe(ShortageResolutionStatus::Fulfilled);

        $pending = ShortageResolution::factory()->pending()->create();
        postTransition($this, $this->owner, $pending, 'cancelled', ['reason' => 'No longer needed'])->assertOk();
        expect($pending->refresh()->status)->toBe(ShortageResolutionStatus::Cancelled)
            ->and($pending->cancellation_reason)->toBe('No longer needed');

        $monitoring = ShortageResolution::factory()->monitoring()->create();
        postTransition($this, $this->owner, $monitoring, 'failed', ['reason' => 'Supplier declined'])->assertOk();
        expect($monitoring->refresh()->status)->toBe(ShortageResolutionStatus::Failed);
    });

    it('422s illegal matrix transitions', function () {
        $resolution = ShortageResolution::factory()->pending()->create();

        postTransition($this, $this->owner, $resolution, 'in_progress')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('status');

        postTransition($this, $this->owner, $resolution, 'fulfilled')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('status');
    });

    it('422s transitioning to the current status', function () {
        $resolution = ShortageResolution::factory()->create();

        postTransition($this, $this->owner, $resolution, 'confirmed')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('status');
    });

    it('requires shortages:write ability and authentication', function () {
        $resolution = ShortageResolution::factory()->pending()->create();

        postTransition($this, $this->owner, $resolution, 'confirmed', [], ['shortages:read'])->assertForbidden();

        $this->postJson("/api/v1/shortage_resolutions/{$resolution->id}/transition", ['status' => 'confirmed'])
            ->assertForbidden();
    });

    it('404s for an unknown resolution', function () {
        $token = $this->owner->createToken('test', ['shortages:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/shortage_resolutions/999999/transition', ['status' => 'confirmed'])
            ->assertNotFound();
    });

    it('marks partially_fulfilled from in_progress', function () {
        $resolution = ShortageResolution::factory()->create([
            'status' => ShortageResolutionStatus::InProgress->value,
        ]);

        postTransition($this, $this->owner, $resolution, 'partially_fulfilled')->assertOk();

        expect($resolution->refresh()->status)->toBe(ShortageResolutionStatus::PartiallyFulfilled);
    });
});

describe('§8.3 legal edges via POST transition', function () {
    it('permits each documented forward transition', function (ShortageResolutionStatus $from, ShortageResolutionStatus $to) {
        $resolution = ShortageResolution::factory()->create(['status' => $from->value]);

        postTransition($this, $this->owner, $resolution, $to->value)->assertOk();

        expect($resolution->refresh()->status)->toBe($to);
    })->with([
        'pending → confirmed' => [ShortageResolutionStatus::Pending, ShortageResolutionStatus::Confirmed],
        'pending → cancelled' => [ShortageResolutionStatus::Pending, ShortageResolutionStatus::Cancelled],
        'pending → failed' => [ShortageResolutionStatus::Pending, ShortageResolutionStatus::Failed],
        'monitoring → confirmed' => [ShortageResolutionStatus::Monitoring, ShortageResolutionStatus::Confirmed],
        'monitoring → cancelled' => [ShortageResolutionStatus::Monitoring, ShortageResolutionStatus::Cancelled],
        'monitoring → failed' => [ShortageResolutionStatus::Monitoring, ShortageResolutionStatus::Failed],
        'confirmed → in_progress' => [ShortageResolutionStatus::Confirmed, ShortageResolutionStatus::InProgress],
        'confirmed → cancelled' => [ShortageResolutionStatus::Confirmed, ShortageResolutionStatus::Cancelled],
        'in_progress → fulfilled' => [ShortageResolutionStatus::InProgress, ShortageResolutionStatus::Fulfilled],
        'in_progress → partially_fulfilled' => [ShortageResolutionStatus::InProgress, ShortageResolutionStatus::PartiallyFulfilled],
        'partially_fulfilled → fulfilled' => [ShortageResolutionStatus::PartiallyFulfilled, ShortageResolutionStatus::Fulfilled],
    ]);
});
