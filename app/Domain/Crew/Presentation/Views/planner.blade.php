<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')
    <flux:heading size="xl">Crew coverage planner</flux:heading>
    <flux:checkbox
        label="Show requests with shortages only"
        wire:model.live="shortagesOnly"
    />
    @forelse($this->requests as $request)
        <section
            wire:key="request-{{ $request->id }}"
            class="space-y-3 border-b pb-4"
        >
            <div class="flex flex-wrap justify-between gap-2">
                <a
                    class="font-medium underline"
                    href="{{ route('crew.requests.show', $request) }}"
                    wire:navigate.hover
                >
                    {{ $request->title }} · {{ $request->project?->name }}
                </a>
                <span
                    class="text-sm">{{ app(\App\Support\Timezone::class)->toZone($request->starts_at, $request->timezone)->format('Y-m-d') }}
                    –
                    {{ app(\App\Support\Timezone::class)->toZone($request->ends_at, $request->timezone)->format('Y-m-d') }}
                    · {{ $request->timezone }}</span>
            </div>
            @forelse($request->lines as $line)
                <div
                    wire:key="line-{{ $line->id }}"
                    class="grid gap-2 rounded border p-3 sm:grid-cols-3"
                >
                    <div class="text-sm font-medium">{{ $line->role->name }}</div>
                    <div class="text-sm tabular-nums">{{ $line->filled_quantity }}/{{ $line->quantity }} filled
                        @if ($line->remainingQuantity() > 0)
                            <span class="font-medium text-amber-700 dark:text-amber-300"> ·
                                {{ $line->remainingQuantity() }} needed</span>
                        @endif

                    </div>
                    <div class="flex flex-wrap gap-2">
                        @can('crew.assignments.view')
                            @forelse($line->assignments as $assignment)
                                <a
                                    wire:key="assignment-{{ $assignment->id }}"
                                    class="rounded border px-2 py-1 text-xs underline"
                                    href="{{ route('crew.assignments.show', $assignment) }}"
                                    wire:navigate.hover
                                >
                                    {{ $assignment->crewMember->name }} · {{ $assignment->status->label() }}
                                </a>
                            @empty
                                <span class="text-sm text-zinc-500">Unstaffed</span>
                            @endforelse
                        @endcan

                    </div>
                </div>
                @empty
                    <flux:text>No role lines.</flux:text>
                @endforelse

            </section>
            @empty
                <flux:text>No requests match this coverage filter.</flux:text>
            @endforelse

            {{ $this->requests->links() }}
        </div>
