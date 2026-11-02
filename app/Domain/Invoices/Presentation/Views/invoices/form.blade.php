<section class="w-full">
    <x-dense::page-header :title="$isEditing ? 'Edit Draft Invoice' : 'New Draft Invoice'">
        <x-slot:breadcrumbs>
            <a href="{{ route('invoices.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Invoices</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>{{ $isEditing ? 'Edit' : 'Create' }}</span>
        </x-slot:breadcrumbs>
    </x-dense::page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3 max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <flux:field>
                <flux:label>Member (Organisation)</flux:label>
                <flux:select wire:model="memberId" :disabled="$isEditing">
                    <option value="0">Select member…</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="memberId" />
            </flux:field>

            <flux:field>
                <flux:label>Store</flux:label>
                <flux:select wire:model="storeId" :disabled="$isEditing">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="storeId" />
            </flux:field>

            <flux:field>
                <flux:label>Type</flux:label>
                <flux:select wire:model="type" :disabled="$isEditing">
                    @foreach($types as $invoiceType)
                        <option value="{{ $invoiceType->value }}">{{ $invoiceType->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="type" />
            </flux:field>

            <flux:field>
                <flux:label>Reference</flux:label>
                <flux:input wire:model="reference" />
                <flux:error name="reference" />
            </flux:field>

            <flux:field>
                <flux:label>Notes</flux:label>
                <flux:textarea wire:model="notes" rows="4" />
                <flux:error name="notes" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">Save</flux:button>
                <flux:button variant="ghost" href="{{ $isEditing ? route('invoices.show', $invoiceId) : route('invoices.index') }}" wire:navigate.hover>Cancel</flux:button>
            </div>
        </form>
    </div>
</section>
