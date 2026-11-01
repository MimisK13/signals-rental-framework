<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl">Crew members</flux:heading>
        @can('create', \App\Domain\Crew\Models\CrewMember::class)
            <flux:button
                variant="primary"
                :href="route('crew.members.create')"
                wire:navigate.hover
            >
                Add member
            </flux:button>
        @endcan

    </div>
    @include('crew::partials.errors')

    <div class="grid gap-3 sm:grid-cols-2">
        <flux:input
            label="Search members"
            wire:model.live.debounce.300ms="search"
        />
        <flux:select
            label="Status"
            wire:model.live="status"
        >
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="archived">Archived</option>
            <option value="all">All</option>
        </flux:select>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-2">Name</th>
                    <th class="p-2">Engagement</th>
                    <th class="p-2">Roles</th>
                    <th class="p-2">Status</th>
                    <th class="w-12 p-2">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->members as $member)
                    <tr
                        wire:key="member-{{ $member->id }}"
                        class="border-b border-zinc-100 dark:border-zinc-800"
                    >
                        <td class="p-2">{{ $member->name }}</td>
                        <td class="p-2">{{ $member->engagement_type->label() }}</td>
                        <td class="p-2">{{ $member->roles->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="p-2">{{ $member->status->label() }}</td>
                        <td class="p-2">
                            <flux:dropdown>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="ellipsis-horizontal"
                                    aria-label="Member actions"
                                />
                                <flux:menu>
                                    @if (!$member->trashed())
                                        <flux:menu.item
                                            :href="route('crew.members.show', $member)"
                                            wire:navigate.hover
                                        >
                                            View
                                        </flux:menu.item>
                                        @can('update', $member)
                                            <flux:menu.item
                                                :href="route('crew.members.edit', $member)"
                                                wire:navigate.hover
                                            >
                                                Edit
                                            </flux:menu.item>
                                        @endcan

                                        @can('delete', $member)
                                            <flux:menu.item
                                                variant="danger"
                                                wire:click="archive({{ $member->id }})"
                                                wire:confirm="Archive {{ $member->name }}? Future confirmed work must be cancelled first."
                                            >
                                                Archive
                                            </flux:menu.item>
                                        @endcan
                                    @else
                                        @can('restore', $member)
                                            <flux:menu.item wire:click="restore({{ $member->id }})">Restore
                                            </flux:menu.item>
                                        @endcan
                                    @endif

                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="5"
                            class="p-6 text-center text-zinc-500"
                        >
                            No crew members match these filters.
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>
    </div>{{ $this->members->links() }}
</div>
