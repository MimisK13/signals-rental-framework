<?php

use App\Domain\Administration\Models\User;
use App\Domain\Settings\Presentation\Livewire\Admin\Scheduling;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
});

it('renders the scheduling settings page', function () {
    $this->get(route('admin.settings.scheduling'))
        ->assertOk()
        ->assertSee('Scheduling');
});

it('loads scheduling settings with defaults', function () {
    Livewire::test(Scheduling::class)
        ->assertSet('defaultOpportunityDurationDays', 1)
        ->assertSet('defaultBufferBeforeMinutes', 0)
        ->assertSet('defaultBufferAfterMinutes', 0)
        ->assertSet('collectionReminderDays', 1)
        ->assertSet('returnReminderDays', 1)
        ->assertSet('defaultStartTime', '09:00')
        ->assertSet('defaultEndTime', '17:00')
        ->assertSet('weekendAvailability', false);
});

it('saves scheduling settings', function () {
    Livewire::test(Scheduling::class)
        ->set('defaultOpportunityDurationDays', 7)
        ->set('defaultBufferBeforeMinutes', 30)
        ->set('defaultBufferAfterMinutes', 60)
        ->set('collectionReminderDays', 3)
        ->set('returnReminderDays', 2)
        ->set('defaultStartTime', '08:00')
        ->set('defaultEndTime', '18:00')
        ->set('weekendAvailability', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('scheduling-settings-saved');

    expect(settings('scheduling.default_opportunity_duration_days'))->toBe(7);
    expect(settings('scheduling.default_buffer_before_minutes'))->toBe(30);
    expect(settings('scheduling.default_buffer_after_minutes'))->toBe(60);
    expect(settings('scheduling.collection_reminder_days'))->toBe(3);
    expect(settings('scheduling.return_reminder_days'))->toBe(2);
    expect(settings('scheduling.default_start_time'))->toBe('08:00');
    expect(settings('scheduling.default_end_time'))->toBe('18:00');
    expect(settings('scheduling.weekend_availability'))->toBe(true);
});

it('validates duration range', function () {
    Livewire::test(Scheduling::class)
        ->set('defaultOpportunityDurationDays', 0)
        ->call('save')
        ->assertHasErrors(['defaultOpportunityDurationDays']);
});

it('validates time format', function () {
    Livewire::test(Scheduling::class)
        ->set('defaultStartTime', 'invalid')
        ->call('save')
        ->assertHasErrors(['defaultStartTime']);
});

it('validates buffer minutes range', function () {
    Livewire::test(Scheduling::class)
        ->set('defaultBufferBeforeMinutes', -1)
        ->call('save')
        ->assertHasErrors(['defaultBufferBeforeMinutes']);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.scheduling'))
        ->assertForbidden();
});
