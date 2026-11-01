<div class="min-w-0 w-full space-y-4 px-3 py-4 sm:px-5 md:px-6">
    @include('crew::partials.navigation')

    <div class="flex flex-wrap gap-2">
        <flux:button
            size="sm"
            :href="route('crew.roles.index')"
            wire:navigate.hover
        >
            Roles
        </flux:button>
        <flux:button
            size="sm"
            :href="route('crew.skills.index')"
            wire:navigate.hover
        >
            Skills
        </flux:button>
        <flux:button
            size="sm"
            :href="route('crew.qualifications.index')"
            wire:navigate.hover
        >
            Qualifications
        </flux:button>
    </div>
    <flux:heading size="xl">Crew {{ $kind }}</flux:heading>
    @include('crew::partials.errors')

    <form
        wire:submit="save"
        class="grid max-w-3xl gap-3 sm:grid-cols-2"
    >
        <flux:input
            label="Name"
            wire:model="name"
            required
        />
        <flux:input
            label="Description"
            wire:model="description"
        />
        @if ($kind === 'skills')
            <flux:input
                label="Category"
                wire:model="category"
                required
            />
        @endif

        @if ($kind === 'qualifications')
            <flux:input
                label="Issuer"
                wire:model="issuer"
            />
            <flux:checkbox
                label="Expiry date required"
                wire:model="requires_expiry"
            />
        @endif

        <flux:checkbox
            label="Active"
            wire:model="is_active"
        />
        <div class="flex gap-2">
            <flux:button
                type="submit"
                variant="primary"
                wire:loading.attr="disabled"
            >
                {{ $editingId ? 'Save changes' : 'Add record' }}
            </flux:button>
            @if ($editingId)
                <flux:button wire:click="cancelEdit">Cancel edit</flux:button>
            @endif

        </div>
    </form>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-2">Name</th>
                    <th class="p-2">Description</th>
                    <th class="p-2">Status</th>
                    <th class="w-12 p-2">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->records as $record)
                    <tr
                        wire:key="record-{{ $record->id }}"
                        class="border-b"
                    >
                        <td class="p-2">{{ $record->name }}</td>
                        <td class="p-2">{{ $record->description }}</td>
                        <td class="p-2">{{ $record->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="p-2">
                            <flux:dropdown>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="ellipsis-horizontal"
                                    aria-label="Configuration actions"
                                />
                                <flux:menu>
                                    <flux:menu.item wire:click="edit({{ $record->id }})">Edit</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="4"
                            class="p-6 text-center"
                        >
                            No {{ $kind }} configured.
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>
    </div>{{ $this->records->links() }}
    @if ($kind === 'roles' && $editingId !== null)

        <section class="space-y-3 border-t pt-4">
            <flux:heading>Requirements for {{ $name }}</flux:heading>
            @forelse($this->requirements as $requirement)
                <div
                    wire:key="requirement-{{ $requirement->id }}"
                    class="flex flex-wrap items-center justify-between gap-3 text-sm"
                >
                    <span>{{ $requirement->skill?->name ?? $requirement->qualification?->name }} ·
                        {{ $requirement->is_mandatory ? 'Mandatory' : 'Desirable' }}</span>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        wire:click="removeRequirement({{ $requirement->id }})"
                        wire:confirm="Remove this role requirement?"
                    >
                        Remove requirement
                    </flux:button>
                </div>
            @empty

                <flux:text>No requirements configured.</flux:text>
            @endforelse

            <form
                wire:submit="addRequirement"
                class="grid max-w-3xl items-end gap-3 sm:grid-cols-2"
            >
                <flux:select
                    label="Requirement type"
                    wire:model.live="requirement_type"
                >
                    <option value="skill">Skill</option>
                    <option value="qualification">Qualification</option>
                </flux:select>
                @if ($requirement_type === 'skill')
                    <flux:select
                        label="Required skill"
                        wire:model="requirement_skill_id"
                    >
                        <option value="">Choose skill</option>
                        @foreach ($this->requirementSkills as $skill)
                            <option value="{{ $skill->id }}">{{ $skill->name }}</option>
                        @endforeach
                    </flux:select>
                @else
                    <flux:select
                        label="Required qualification"
                        wire:model="requirement_qualification_id"
                    >
                        <option value="">Choose qualification</option>
                        @foreach ($this->requirementQualifications as $qualification)
                            <option value="{{ $qualification->id }}">{{ $qualification->name }}</option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:checkbox
                    label="Mandatory"
                    wire:model="is_mandatory"
                />
                <flux:button
                    type="submit"
                    wire:loading.attr="disabled"
                >
                    Add requirement
                </flux:button>
            </form>
        </section>
    @endif

</div>
