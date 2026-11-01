<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')

    <flux:heading size="xl">{{ $memberId ? 'Edit crew member' : 'Add crew member' }}</flux:heading>
    @include('crew::partials.errors')

    <form
        wire:submit="save"
        class="max-w-2xl space-y-4"
    >
        <flux:input
            label="Name"
            wire:model="name"
            required
        />
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select
                label="Engagement type"
                wire:model="engagement_type"
            >
                @foreach (\App\Domain\Crew\Enums\CrewEngagementType::cases() as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach

            </flux:select>
            <flux:input
                label="Home timezone"
                wire:model="home_timezone"
                required
            />
        </div>
        <flux:input
            label="Job title"
            wire:model="job_title"
        />
        @if (!$memberId || auth()->user()->can('crew.view.sensitive'))
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    label="Email"
                    type="email"
                    wire:model="email"
                />
                <flux:input
                    label="Phone"
                    wire:model="phone"
                />
            </div>
        @endif

        <div class="flex gap-2">
            <flux:button
                type="submit"
                variant="primary"
                wire:loading.attr="disabled"
            >
                Save member
            </flux:button>
            <flux:button
                :href="route('crew.members.index')"
                wire:navigate.hover
            >
                Cancel
            </flux:button>
        </div>
    </form>
</div>
