<?php

use App\Actions\Members\UpdateMember;
use App\Data\Invoices\InvoicePaymentData;
use App\Data\Members\UpdateMemberData;
use App\Jobs\DeliverWebhook;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

it('serialises member number in the members API response', function () {
    $member = Member::factory()->organisation()->create(['number' => 'MEM-DTO-001']);
    $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/members/{$member->id}")
        ->assertOk()
        ->assertJsonPath('member.number', 'MEM-DTO-001');
});

it('serialises payment number in InvoicePaymentData', function () {
    $payment = InvoicePayment::factory()->create(['number' => 'PAY-DTO-001']);

    $payload = InvoicePaymentData::fromModel($payment)->toArray();

    expect($payload)->toHaveKey('number')
        ->and($payload['number'])->toBe('PAY-DTO-001');
});

it('includes member number in member.updated webhook payload', function () {
    Queue::fake([DeliverWebhook::class]);
    $this->actingAs($this->owner);

    Webhook::factory()->create([
        'events' => ['member.updated'],
        'is_active' => true,
    ]);

    $member = Member::factory()->organisation()->create(['number' => 'MEM-WH-001']);

    (new UpdateMember)($member, UpdateMemberData::from(['description' => 'Updated notes']));

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job): bool {
        return $job->event === 'member.updated'
            && ($job->payload['member']['number'] ?? null) === 'MEM-WH-001';
    });
});
