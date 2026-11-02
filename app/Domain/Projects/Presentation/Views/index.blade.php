<section class="relative min-w-0 w-full" x-data="{ projectPanelOpen: @js($panel !== null) }" x-on:open-project-panel.window="projectPanelOpen = true"
    x-on:close-project-panel.window="projectPanelOpen = false">
    <x-dense::page-header title="Projects">
        <x-slot:actions>
            @can('create', \App\Domain\Projects\Models\Project::class)
                <x-dense::button type="button" variant="accent" size="sm" wire:click="openCreateProject"
                    x-on:click="$dispatch('open-project-panel')">
                    New Project
                </x-dense::button>
            @endcan
        </x-slot:actions>
    </x-dense::page-header>

    <div class="flex min-w-0 flex-1 items-stretch max-md:flex-col"
        :class="{ 'pr-[360px] max-md:pr-0': projectPanelOpen }">
        <div class="min-w-0 flex-1 px-6 pb-6 max-md:px-5 max-sm:px-3">
            <livewire:components.data-table :columns="$columns" :model="\App\Domain\Projects\Models\Project::class" :searchable="['name']" :with="['customer', 'venue', 'owner']"
                :with-counts="['opportunities']" :scopes="$scopes" default-sort="-starts_at" empty-message="No projects found."
                actions-view="projects::partials.row-actions" :key="'projects-table-' . $archiveFilter" />
        </div>

        <x-dense::side-panel x-show="projectPanelOpen" x-cloak mode="viewport" :title="$isEditing ? 'Edit Project' : 'New Project'"
            close="$wire.closeProjectPanel(); projectPanelOpen = false">
            <form id="project-panel-form" wire:submit="saveProject" class="space-y-4">
                <x-dense::field label="Project name" for="project-panel-name" required :error="$errors->first('projectName')"
                    error-id="project-panel-name-error">
                    <x-dense::input id="project-panel-name" wire:model="projectName" required autocomplete="off"
                        :invalid="$errors->has('projectName')" :aria-describedby="$errors->has('projectName') ? 'project-panel-name-error' : null" />
                </x-dense::field>

                @if ($isEditing)
                    <x-dense::field label="Status" for="project-panel-status" :error="$errors->first('projectStatus')"
                        error-id="project-panel-status-error">
                        <x-dense::select id="project-panel-status" wire:model="projectStatus" :invalid="$errors->has('projectStatus')"
                            :aria-describedby="$errors->has('projectStatus') ? 'project-panel-status-error' : null">
                            @foreach ($statuses as $projectStatus)
                                <option value="{{ $projectStatus->value }}">{{ $projectStatus->label() }}</option>
                            @endforeach
                        </x-dense::select>
                    </x-dense::field>
                @endif

                <x-dense::field label="Customer" for="project-panel-customer" :error="$errors->first('projectCustomerId')"
                    error-id="project-panel-customer-error">
                    <x-dense::select id="project-panel-customer" wire:model="projectCustomerId" :invalid="$errors->has('projectCustomerId')"
                        :aria-describedby="$errors->has('projectCustomerId') ? 'project-panel-customer-error' : null">
                        <option value="">None</option>
                        @forelse($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @empty
                        @endforelse
                    </x-dense::select>
                </x-dense::field>

                <x-dense::field label="Primary venue" for="project-panel-venue" :error="$errors->first('projectVenueId')"
                    error-id="project-panel-venue-error">
                    <x-dense::select id="project-panel-venue" wire:model="projectVenueId" :invalid="$errors->has('projectVenueId')"
                        :aria-describedby="$errors->has('projectVenueId') ? 'project-panel-venue-error' : null">
                        <option value="">None</option>
                        @forelse($venues as $venue)
                            <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                        @empty
                        @endforelse
                    </x-dense::select>
                </x-dense::field>

                <x-dense::field label="Owner" for="project-panel-owner" :error="$errors->first('projectOwnerId')"
                    error-id="project-panel-owner-error">
                    <x-dense::select id="project-panel-owner" wire:model="projectOwnerId" :invalid="$errors->has('projectOwnerId')"
                        :aria-describedby="$errors->has('projectOwnerId') ? 'project-panel-owner-error' : null">
                        <option value="">None</option>
                        @forelse($owners as $owner)
                            <option value="{{ $owner->id }}">{{ $owner->name }}</option>
                        @empty
                        @endforelse
                    </x-dense::select>
                </x-dense::field>

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-dense::field label="Starts" for="project-panel-starts" :error="$errors->first('projectStartsAt')"
                        error-id="project-panel-starts-error">
                        <x-dense::input id="project-panel-starts" type="datetime-local" wire:model="projectStartsAt"
                            :invalid="$errors->has('projectStartsAt')" :aria-describedby="$errors->has('projectStartsAt') ? 'project-panel-starts-error' : null" />
                    </x-dense::field>
                    <x-dense::field label="Ends" for="project-panel-ends" :error="$errors->first('projectEndsAt')"
                        error-id="project-panel-ends-error">
                        <x-dense::input id="project-panel-ends" type="datetime-local" wire:model="projectEndsAt"
                            :invalid="$errors->has('projectEndsAt')" :aria-describedby="$errors->has('projectEndsAt') ? 'project-panel-ends-error' : null" />
                    </x-dense::field>
                </div>
            </form>

            <x-slot:footer>
                <div class="dense-side-panel-footer-actions">
                    <x-dense::button type="button" variant="ghost" size="sm"
                        wire:click="closeProjectPanel">Cancel</x-dense::button>
                    <x-dense::button type="submit" form="project-panel-form" variant="accent" size="sm"
                        wire:loading.attr="disabled" wire:target="saveProject">
                        <span wire:loading.remove
                            wire:target="saveProject">{{ $isEditing ? 'Save Changes' : 'Create Project' }}</span>
                        <span wire:loading wire:target="saveProject">{{ $isEditing ? 'Saving…' : 'Creating…' }}</span>
                    </x-dense::button>
                </div>
            </x-slot:footer>
        </x-dense::side-panel>
    </div>
</section>
