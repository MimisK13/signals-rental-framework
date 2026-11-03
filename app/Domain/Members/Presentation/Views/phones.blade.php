<section class="w-full">
    <x-dense::page-header :title="$member->name">
        <x-slot:breadcrumbs>
            <a
                href="{{ route('members.index') }}"
                wire:navigate.hover
                class="text-[var(--link)] hover:underline"
            >Members</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <a
                href="{{ route('members.show', $member) }}"
                wire:navigate.hover
                class="text-[var(--link)] hover:underline"
            >{{ $member->name }}</a>
            <span class="mx-1 text-[var(--text-muted)]">/</span>
            <span>Phones</span>
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:button
                variant="primary"
                href="{{ route('members.phones.create', $member) }}"
                wire:navigate.hover
            >Add Phone</flux:button>
        </x-slot:actions>
    </x-dense::page-header>

    @include('members::partials.member-tabs', ['member' => $member, 'activeTab' => 'phones'])

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Type</th>
                        <th>Primary</th>
                        <th class="w-[100px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($phones as $phone)
                        <tr wire:key="phone-{{ $phone->id }}">
                            <td class="font-medium">{{ $phone->number }}</td>
                            <td>{{ $phone->type?->name ?? '—' }}</td>
                            <td>
                                @if ($phone->is_primary)
                                    <span class="s-badge s-badge-green">Primary</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a
                                    href="{{ route('members.phones.edit', [$member, $phone]) }}"
                                    wire:navigate.hover
                                    class="s-btn s-btn-ghost s-btn-sm"
                                    title="Edit"
                                >
                                    <flux:icon.pencil-square class="w-4 h-4" />
                                </a>
                                <button
                                    wire:click="deletePhone({{ $phone->id }})"
                                    wire:confirm="Are you sure you want to delete this phone number?"
                                    class="s-btn-ghost s-btn-xs text-[var(--red)]"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="4"
                                class="text-center text-[var(--text-muted)]"
                            >No phone numbers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
