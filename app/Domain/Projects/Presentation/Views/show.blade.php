<section class="relative min-w-0 w-full">
    <x-dense::page-header :title="$project->name">
        <x-slot:meta>
            <x-dense::status variant="outline" :color="match ($project->status->value) {
                'active' => 'green',
                'completed' => 'blue',
                'archived' => 'zinc',
                default => 'amber',
            }" :label="$project->status->label()" />
            <span>{{ $opportunities->count() }} jobs</span>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $project)
                <x-dense::button :href="route('projects.edit', $project)" variant="secondary" size="sm" wire:navigate.hover>
                    Edit
                </x-dense::button>
            @endcan
        </x-slot:actions>
    </x-dense::page-header>

    <div class="grid gap-4 px-6 pb-6 max-md:px-5 max-sm:px-3 xl:grid-cols-[minmax(0,1fr)_320px]">
        <x-dense::table-card title="Jobs / Rentals" :count="$opportunities->count()">
            @if ($opportunities->isEmpty())
                <x-dense::empty-state title="No jobs assigned"
                    description="Attach an existing Opportunity without changing its Store or operational dates." />
            @else
                <div class="overflow-x-auto">
                    <table class="s-table w-full">
                        <thead>
                            <tr>
                                <th>Job</th>
                                <th>Customer</th>
                                <th>Store</th>
                                <th>Dates</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($opportunities as $opportunity)
                                <tr wire:key="project-opportunity-{{ $opportunity->id }}">
                                    <td>
                                        <a href="{{ route('opportunities.show', $opportunity) }}" wire:navigate.hover
                                            class="font-medium hover:underline">
                                            {{ $opportunity->subject }}
                                        </a>
                                    </td>
                                    <td>{{ $opportunity->member?->name ?? '—' }}</td>
                                    <td>{{ $opportunity->store?->name ?? '—' }}</td>
                                    <td>{{ $opportunity->starts_at?->format('d M Y') ?? '—' }}</td>
                                    <td class="text-right">
                                        <x-dense::button type="button" variant="ghost" size="sm"
                                            wire:click="detach({{ $opportunity->id }})"
                                            wire:confirm="Detach this Opportunity from the Project?">Detach</x-dense::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-dense::table-card>

        <div class="space-y-4">
            <x-dense::card title="Attach job">
                <form wire:submit="attach" class="space-y-3">
                    <x-dense::field label="Unassigned Opportunity" for="project-attach-opportunity" :error="$errors->first('selectedOpportunityId')"
                        error-id="project-attach-opportunity-error">
                        <x-dense::select id="project-attach-opportunity" wire:model="selectedOpportunityId"
                            :invalid="$errors->has('selectedOpportunityId')" :aria-describedby="$errors->has('selectedOpportunityId')
                                ? 'project-attach-opportunity-error'
                                : null">
                            <option value="">Select an opportunity</option>
                            @forelse($unassignedOpportunities as $opportunity)
                                <option value="{{ $opportunity->id }}">{{ $opportunity->subject }}</option>
                            @empty
                            @endforelse
                        </x-dense::select>
                    </x-dense::field>
                    <x-dense::button type="submit" variant="primary" size="sm" wire:loading.attr="disabled"
                        wire:target="attach">Attach</x-dense::button>
                </form>
            </x-dense::card>

            <x-dense::card title="Move job">
                <form wire:submit="move" class="space-y-3">
                    <x-dense::field label="Opportunity" for="project-move-opportunity" :error="$errors->first('moveOpportunityId')"
                        error-id="project-move-opportunity-error">
                        <x-dense::select id="project-move-opportunity" wire:model="moveOpportunityId" :invalid="$errors->has('moveOpportunityId')"
                            :aria-describedby="$errors->has('moveOpportunityId') ? 'project-move-opportunity-error' : null">
                            <option value="">Select an opportunity</option>
                            @forelse($opportunities as $opportunity)
                                <option value="{{ $opportunity->id }}">{{ $opportunity->subject }}</option>
                            @empty
                            @endforelse
                        </x-dense::select>
                    </x-dense::field>
                    <x-dense::field label="Destination Project" for="project-move-destination" :error="$errors->first('moveDestinationId')"
                        error-id="project-move-destination-error">
                        <x-dense::select id="project-move-destination" wire:model="moveDestinationId" :invalid="$errors->has('moveDestinationId')"
                            :aria-describedby="$errors->has('moveDestinationId') ? 'project-move-destination-error' : null">
                            <option value="">Select a project</option>
                            @forelse($otherProjects as $otherProject)
                                <option value="{{ $otherProject->id }}">{{ $otherProject->name }}</option>
                            @empty
                            @endforelse
                        </x-dense::select>
                    </x-dense::field>
                    <x-dense::button type="submit" variant="secondary" size="sm" wire:loading.attr="disabled"
                        wire:target="move">Move</x-dense::button>
                </form>
            </x-dense::card>
        </div>
    </div>
</section>
