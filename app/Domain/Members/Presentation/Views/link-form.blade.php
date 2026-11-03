<section class="w-full">
    @include('members::partials.member-header', [
        'member' => $member,
        'subpage' => $isEditing ? 'Edit Link' : 'Add Link',
    ])
    @include('members::partials.member-tabs', ['member' => $member, 'activeTab' => 'information'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form
            wire:submit="save"
            class="max-w-2xl space-y-8"
        >
            <x-signals.form-section title="Link Details">
                <div class="space-y-4">
                    <flux:input
                        wire:model="url"
                        label="URL"
                        type="url"
                        placeholder="https://example.com"
                        required
                    />
                    <flux:input
                        wire:model="name"
                        label="Label"
                        placeholder="e.g. Company Website"
                    />
                    <flux:select
                        wire:model="typeId"
                        label="Type"
                    >
                        <option value="">Select type...</option>
                        @foreach ($linkTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button
                    variant="primary"
                    type="submit"
                >{{ $isEditing ? 'Save Changes' : 'Add Link' }}</flux:button>
                <flux:button
                    variant="ghost"
                    href="{{ route('members.show', $member) }}"
                    wire:navigate.hover
                >Cancel</flux:button>
            </div>
        </form>
    </div>
</section>
