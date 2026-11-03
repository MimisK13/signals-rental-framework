<section class="w-full min-w-0">
    <x-admin.layout group="preferences" title="Scheduling" description="Configure default durations, buffer times, and availability for opportunities.">
        <form wire:submit="save" class="space-y-8">
            <x-signals.form-section title="Opportunity Defaults">
                <div class="space-y-4">
                    <flux:input wire:model="defaultOpportunityDurationDays" label="Default Duration (days)" type="number" min="1" max="365" />
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="defaultBufferBeforeMinutes" label="Buffer Before (minutes)" type="number" min="0" max="1440" />
                        <flux:input wire:model="defaultBufferAfterMinutes" label="Buffer After (minutes)" type="number" min="0" max="1440" />
                    </div>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Reminders">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="collectionReminderDays" label="Collection Reminder (days before)" type="number" min="0" max="30" />
                        <flux:input wire:model="returnReminderDays" label="Return Reminder (days before)" type="number" min="0" max="30" />
                    </div>
                    <p class="text-xs text-zinc-500">Set to 0 to disable reminders.</p>
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Working Hours">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="defaultStartTime" label="Default Start Time" type="time" />
                        <flux:input wire:model="defaultEndTime" label="Default End Time" type="time" />
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($weekendAvailability) }">
                        <input type="checkbox" wire:model="weekendAvailability" class="hidden" x-on:change="checked = $el.checked" />
                        <x-signals.checkbox x-bind:class="checked && 'checked'" />
                        <span class="text-sm">Allow weekend availability</span>
                    </label>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>

                <x-action-message on="scheduling-settings-saved">
                    Saved.
                </x-action-message>
            </div>
        </form>
    </x-admin.layout>
</section>
