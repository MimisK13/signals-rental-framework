<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')

    <flux:heading size="xl">
        Crew overview
    </flux:heading>

    <div class="grid gap-6 lg:grid-cols-2">
        @can('crew.requests.view')
            <section class="space-y-3">
                <flux:heading>Open requests and shortages</flux:heading>
                @forelse($this->requests as $request)
                    <div
                        wire:key="request-{{ $request->id }}"
                        class="border-b pb-3"
                    >
                        <a
                            class="font-medium underline"
                            href="{{ route('crew.requests.show', $request) }}"
                            wire:navigate.hover
                        >
                            {{ $request->title }}
                        </a>
                        <p class="text-sm text-zinc-500">{{ $request->project?->name }}</p>
                        @forelse($request->lines as $line)
                            <p
                                wire:key="line-{{ $line->id }}"
                                class="text-sm"
                            >{{ $line->role->name }}: {{ $line->filled_quantity }}/{{ $line->quantity }} filled ·
                                {{ $line->remainingQuantity() }} remaining</p>
                        @empty
                            <p class="text-sm">No role lines.</p>
                        @endforelse

                    </div>
                @empty
                    <flux:text>No open crew requests.</flux:text>
                @endforelse

            </section>
        @endcan

        @can('crew.shifts.view')
            <section class="space-y-3">
                <flux:heading>Today's calls · {{ app(\App\Support\Timezone::class)->current() }}</flux:heading>
                @forelse($this->calls as $shift)
                    <div
                        wire:key="call-{{ $shift->id }}"
                        class="border-b pb-3 text-sm"
                    >
                        <a
                            class="underline"
                            href="{{ route('crew.assignments.show', $shift->crew_assignment_id) }}"
                            wire:navigate.hover
                        >
                            {{ $shift->crewMember->name }}
                        </a>
                        <p>{{ $shift->project?->name }} ·
                            {{ app(\App\Support\Timezone::class)->toLocal($shift->starts_at)->format('H:i') }} –
                            {{ app(\App\Support\Timezone::class)->toLocal($shift->ends_at)->format('H:i') }}</p>
                    </div>
                @empty
                    <flux:text>No confirmed calls today.</flux:text>
                @endforelse
            </section>
        @endcan
    </div>

    @can('crew.timesheets.approve')
        @can('crew.timesheets.view')
            <flux:button
                :href="route('crew.timesheets.index', ['status' => 'submitted'])"
                wire:navigate.hover
            >
                {{ $this->approvals }} timesheets awaiting approval
            </flux:button>
        @endcan
    @endcan
</div>
