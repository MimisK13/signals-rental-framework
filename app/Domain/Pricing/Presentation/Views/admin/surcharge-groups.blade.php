<section class="w-full">
    <x-admin.layout group="pricing" title="Surcharge Groups" description="Organise automatic line surcharges into toggleable groups.">
        <x-slot:actions>
            @can('pricing.manage')
                <flux:button variant="primary" href="{{ route('admin.settings.pricing.surcharge-groups.create') }}" wire:navigate.hover>
                    Add Surcharge Group
                </flux:button>
            @endcan
        </x-slot:actions>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Default</th>
                        <th>Status</th>
                        <th class="w-[120px]"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groups as $group)
                        <tr wire:key="surcharge-group-{{ $group->id }}">
                            <td class="font-medium">{{ $group->name }}</td>
                            <td>
                                @if($group->is_default)
                                    <span class="s-badge s-badge-blue">Default</span>
                                @else
                                    <span class="s-badge">Optional</span>
                                @endif
                            </td>
                            <td>
                                @if($group->is_active)
                                    <span class="s-badge s-badge-green">Active</span>
                                @else
                                    <span class="s-badge">Inactive</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @can('pricing.manage')
                                    @include('pricing::partials.row-actions', [
                                        'editUrl' => route('admin.settings.pricing.surcharge-groups.edit', $group),
                                        'deleteMethod' => 'deleteGroup',
                                        'recordId' => $group->id,
                                        'confirmation' => 'Delete this surcharge group?',
                                    ])
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-[var(--text-muted)]">No surcharge groups configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.layout>
</section>
