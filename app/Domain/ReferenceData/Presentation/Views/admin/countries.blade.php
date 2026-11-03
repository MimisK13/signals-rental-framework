<section class="w-full">
    <x-admin.layout group="data" title="Countries" description="Manage active countries.">

        {{-- Search --}}
        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search countries..." />
        </div>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Currency Code</th>
                        <th>Phone Prefix</th>
                        <th>Default Timezone</th>
                        <th>Active</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($countries as $country)
                        <tr wire:key="country-{{ $country->id }}">
                            <td class="font-medium">{{ $country->code }}</td>
                            <td>{{ $country->name }}</td>
                            <td>{{ $country->currency_code }}</td>
                            <td>{{ $country->phone_prefix }}</td>
                            <td>{{ $country->default_timezone }}</td>
                            <td>
                                <button wire:click="toggleActive({{ $country->id }})"
                                        class="s-btn-ghost s-btn-xs">
                                    @if($country->is_active)
                                        <span class="s-badge s-badge-green">Active</span>
                                    @else
                                        <span class="s-badge s-badge-zinc">Inactive</span>
                                    @endif
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-[var(--text-muted)]">No countries found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $countries->links() }}
        </div>
    </x-admin.layout>
</section>
