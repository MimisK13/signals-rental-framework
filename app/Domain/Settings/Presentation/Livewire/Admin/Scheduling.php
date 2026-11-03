<?php

declare(strict_types=1);

namespace App\Domain\Settings\Presentation\Livewire\Admin;

use App\Domain\Settings\Services\SettingsRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Scheduling')]
final class Scheduling extends Component
{
    public int $defaultOpportunityDurationDays = 1;

    public int $defaultBufferBeforeMinutes = 0;

    public int $defaultBufferAfterMinutes = 0;

    public int $collectionReminderDays = 1;

    public int $returnReminderDays = 1;

    public string $defaultStartTime = '09:00';

    public string $defaultEndTime = '17:00';

    public bool $weekendAvailability = false;

    public function mount(): void
    {
        $group = settings()->group('scheduling');

        $this->defaultOpportunityDurationDays = (int) $group['default_opportunity_duration_days'];
        $this->defaultBufferBeforeMinutes = (int) $group['default_buffer_before_minutes'];
        $this->defaultBufferAfterMinutes = (int) $group['default_buffer_after_minutes'];
        $this->collectionReminderDays = (int) $group['collection_reminder_days'];
        $this->returnReminderDays = (int) $group['return_reminder_days'];
        $this->defaultStartTime = $group['default_start_time'];
        $this->defaultEndTime = $group['default_end_time'];
        $this->weekendAvailability = (bool) $group['weekend_availability'];
    }

    public function save(): void
    {
        $registry = app(SettingsRegistry::class);
        $rules = $registry->rules('scheduling');
        $types = $registry->types('scheduling');

        $validated = $this->validate([
            'defaultOpportunityDurationDays' => $rules['default_opportunity_duration_days'],
            'defaultBufferBeforeMinutes' => $rules['default_buffer_before_minutes'],
            'defaultBufferAfterMinutes' => $rules['default_buffer_after_minutes'],
            'collectionReminderDays' => $rules['collection_reminder_days'],
            'returnReminderDays' => $rules['return_reminder_days'],
            'defaultStartTime' => $rules['default_start_time'],
            'defaultEndTime' => $rules['default_end_time'],
            'weekendAvailability' => $rules['weekend_availability'],
        ]);

        settings()->setMany([
            'scheduling.default_opportunity_duration_days' => ['value' => $validated['defaultOpportunityDurationDays'], 'type' => $types['default_opportunity_duration_days']],
            'scheduling.default_buffer_before_minutes' => ['value' => $validated['defaultBufferBeforeMinutes'], 'type' => $types['default_buffer_before_minutes']],
            'scheduling.default_buffer_after_minutes' => ['value' => $validated['defaultBufferAfterMinutes'], 'type' => $types['default_buffer_after_minutes']],
            'scheduling.collection_reminder_days' => ['value' => $validated['collectionReminderDays'], 'type' => $types['collection_reminder_days']],
            'scheduling.return_reminder_days' => ['value' => $validated['returnReminderDays'], 'type' => $types['return_reminder_days']],
            'scheduling.default_start_time' => $validated['defaultStartTime'],
            'scheduling.default_end_time' => $validated['defaultEndTime'],
            'scheduling.weekend_availability' => ['value' => $validated['weekendAvailability'], 'type' => $types['weekend_availability']],
        ]);

        $this->dispatch('scheduling-settings-saved');
    }

    public function render(): View
    {
        return view('settings::admin.scheduling');
    }
}
