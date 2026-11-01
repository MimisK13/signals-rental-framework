<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')

    <div class="flex flex-wrap justify-between gap-3">
        <flux:heading size="xl">Crew requests</flux:heading>
        @can('create', \App\Domain\Crew\Models\CrewRequest::class)
            <flux:button
                variant="primary"
                wire:click="$toggle('creating')"
            >
                {{ $creating ? 'Close form' : 'New request' }}
            </flux:button>
        @endcan

    </div>
    @include('crew::partials.errors')

    @if ($creating)
        @can('create', \App\Domain\Crew\Models\CrewRequest::class)
            <form
                wire:submit="save"
                class="grid max-w-3xl gap-3 rounded border p-4 sm:grid-cols-2"
            >
                <flux:input
                    label="Title"
                    wire:model="title"
                    required
                />
                <flux:select
                    label="Project"
                    wire:model="project_id"
                >
                    <option value="">Choose project</option>
                    @foreach ($this->projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach

                </flux:select>
                <flux:select
                    label="Store"
                    wire:model="store_id"
                >
                    <option value="">Choose store</option>
                    @foreach ($this->stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach

                </flux:select>
                <flux:text>Dates in {{ app(\App\Support\Timezone::class)->current() }}</flux:text>
                <flux:input
                    label="Starts"
                    type="datetime-local"
                    wire:model="starts_at"
                    required
                />
                <flux:input
                    label="Ends"
                    type="datetime-local"
                    wire:model="ends_at"
                    required
                />
                <flux:button
                    type="submit"
                    variant="primary"
                    wire:loading.attr="disabled"
                >
                    Create draft request
                </flux:button>
            </form>
        @endcan
    @endif

    <div class="grid gap-3 sm:grid-cols-2">
        <flux:input
            label="Search requests"
            wire:model.live.debounce.300ms="search"
        />
        <flux:select
            label="Status"
            wire:model.live="status"
        >
            <option value="">All statuses</option>
            @foreach (\App\Domain\Crew\Enums\CrewRequestStatus::cases() as $state)
                <option value="{{ $state->value }}">{{ $state->label() }}</option>
            @endforeach

        </flux:select>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-2">Request</th>
                    <th class="p-2">Project</th>
                    <th class="p-2">Status</th>
                    <th class="p-2">Coverage</th>
                    <th class="p-2">Starts</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->requests as $request)
                    <tr
                        wire:key="request-{{ $request->id }}"
                        class="border-b"
                    >
                        <td class="p-2">
                            <a
                                class="underline"
                                href="{{ route('crew.requests.show', $request) }}"
                                wire:navigate.hover
                            >
                                {{ $request->title }}
                            </a>
                        </td>
                        <td class="p-2">{{ $request->project?->name }}</td>
                        <td class="p-2">{{ $request->status->label() }}</td>
                        <td class="p-2 tabular-nums">
                            {{ $request->lines->sum('filled_quantity') }}/{{ $request->lines->sum('quantity') }}</td>
                        <td class="p-2">
                            {{ app(\App\Support\Timezone::class)->toLocal($request->starts_at)->format('Y-m-d H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="5"
                            class="p-6 text-center"
                        >
                            No crew requests match these filters.
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>
    </div>{{ $this->requests->links() }}
</div>
