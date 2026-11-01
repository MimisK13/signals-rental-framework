<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')
    <flux:heading size="xl">Crew calendar</flux:heading>
    <flux:input
        label="Week beginning"
        type="date"
        wire:model.live="week"
        class="max-w-sm"
    />
    @include('crew::partials.errors')

    <flux:text>Shift and leave times are shown in each crew member's home timezone.</flux:text>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-2">Member</th>
                    <th class="p-2">Project</th>
                    <th class="p-2">Shift</th>
                    <th class="p-2">Local start</th>
                    <th class="p-2">Local end</th>
                    <th class="p-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->shifts as $shift)
                    <tr
                        wire:key="shift-{{ $shift->id }}"
                        class="border-b"
                    >
                        <td class="p-2">{{ $shift->crewMember->name }}<p class="text-xs text-zinc-500">
                                {{ $shift->crewMember->home_timezone }}</p>
                        </td>
                        <td class="p-2">{{ $shift->project?->name }}</td>
                        <td class="p-2">
                            @can('crew.assignments.view')
                                <a
                                    class="underline"
                                    href="{{ route('crew.assignments.show', $shift->crew_assignment_id) }}"
                                    wire:navigate.hover
                                >
                                    {{ $shift->type->label() }}
                                </a>
                            @else
                                {{ $shift->type->label() }}
                            @endcan

                        </td>
                        <td class="p-2">
                            {{ app(\App\Support\Timezone::class)->toZone($shift->starts_at, $shift->crewMember->home_timezone)->format('Y-m-d H:i') }}
                        </td>
                        <td class="p-2">
                            {{ app(\App\Support\Timezone::class)->toZone($shift->ends_at, $shift->crewMember->home_timezone)->format('Y-m-d H:i') }}
                        </td>
                        <td class="p-2">{{ $shift->status->label() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="6"
                            class="p-6 text-center"
                        >
                            No shifts in this week.
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>
    </div>{{ $this->shifts->links() }}
    @can('crew.availability.view')
        <section class="space-y-3 border-t pt-4">
            <flux:heading>Leave and availability</flux:heading>
            @forelse($this->availability as $block)
                <div
                    wire:key="availability-{{ $block->id }}"
                    class="flex flex-wrap gap-3 text-sm"
                >
                    <span>{{ $block->crewMember->name }}</span>
                    <span>{{ $block->type->label() }} · {{ $block->status->label() }}</span>
                    <span>{{ app(\App\Support\Timezone::class)->toZone($block->starts_at, $block->crewMember->home_timezone)->format('Y-m-d H:i') }}
                        –
                        {{ app(\App\Support\Timezone::class)->toZone($block->ends_at, $block->crewMember->home_timezone)->format('Y-m-d H:i') }}
                        · {{ $block->crewMember->home_timezone }}</span>
                </div>
            @empty
                <flux:text>No leave or availability blocks in this week.</flux:text>
            @endforelse

        </section>
    @endcan

</div>
