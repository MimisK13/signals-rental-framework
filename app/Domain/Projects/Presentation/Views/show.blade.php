<section class="relative min-w-0 w-full">
    <x-dense::page-header :title="$project->name">
        <x-slot:meta>
            <x-dense::status variant="outline" :color="match ($project->status->value) {
                'active' => 'green',
                'completed' => 'blue',
                'archived' => 'zinc',
                default => 'amber',
            }" :label="$project->status->label()" />
        </x-slot:meta>
        <x-slot:actions>
            @can('create', \App\Domain\Quotations\Models\Quotation::class)
                <x-dense::button :href="route('projects.quotations.create', $project)" variant="accent" size="sm" wire:navigate.hover>
                    New Quotation
                </x-dense::button>
            @endcan
            @can('update', $project)
                <x-dense::button :href="route('projects.edit', $project)" variant="secondary" size="sm" wire:navigate.hover>
                    Edit
                </x-dense::button>
            @endcan
        </x-slot:actions>
    </x-dense::page-header>

    <div class="space-y-4 px-6 pb-6 max-md:px-5 max-sm:px-3">
        <x-dense::card title="Project overview">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div><div class="text-xs text-[var(--text-muted)]">Customer</div><div class="font-medium">{{ $project->customer?->name ?? '—' }}</div></div>
                <div><div class="text-xs text-[var(--text-muted)]">Venue</div><div class="font-medium">{{ $project->venue?->name ?? '—' }}</div></div>
                <div><div class="text-xs text-[var(--text-muted)]">Owner</div><div class="font-medium">{{ $project->owner?->name ?? '—' }}</div></div>
                <div><div class="text-xs text-[var(--text-muted)]">Dates</div><div class="font-medium">{{ $project->starts_at?->format('d M Y') ?? '—' }}@if($project->ends_at) – {{ $project->ends_at->format('d M Y') }}@endif</div></div>
            </div>
        </x-dense::card>

        <div class="grid gap-4 xl:grid-cols-2">
            <x-dense::table-card title="Jobs / Rentals" :count="$opportunities->count()">
                @if ($opportunities->isEmpty())
                    <x-dense::empty-state title="No operational jobs" description="Operational work will appear here after planning." />
                @else
                    <div class="overflow-x-auto">
                        <table class="s-table w-full">
                            <thead><tr><th>Job</th><th>Store</th><th>Dates</th></tr></thead>
                            <tbody>
                                @foreach ($opportunities as $opportunity)
                                    <tr wire:key="project-opportunity-{{ $opportunity->id }}">
                                        <td><a href="{{ route('opportunities.show', $opportunity) }}" wire:navigate.hover class="font-medium hover:underline">{{ $opportunity->subject }}</a></td>
                                        <td>{{ $opportunity->store?->name ?? '—' }}</td>
                                        <td>{{ $opportunity->starts_at?->format('d M Y') ?? '—' }}@if($opportunity->ends_at) – {{ $opportunity->ends_at->format('d M Y') }}@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-dense::table-card>

            <x-dense::table-card title="Quotations" :count="$quotations->count()">
                @if ($quotations->isEmpty())
                    <x-dense::empty-state title="No quotations yet" description="Create a commercial offer for this Project." />
                @else
                    <div class="overflow-x-auto">
                        <table class="s-table w-full">
                            <thead><tr><th>Quotation</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                            <tbody>
                                @foreach ($quotations as $quotation)
                                    <tr wire:key="project-quotation-{{ $quotation->id }}">
                                        <td>
                                            <a href="{{ route('quotations.edit', $quotation) }}" wire:navigate.hover class="font-medium hover:underline">{{ $quotation->number }} · {{ $quotation->subject }}</a>
                                            @if ($quotation->accepted_version_id !== null)<span class="ml-1 text-xs text-green-600">Accepted</span>@endif
                                        </td>
                                        <td><x-dense::status variant="outline" :color="match ($quotation->status->value) { 'accepted' => 'green', 'sent' => 'blue', 'declined', 'cancelled', 'expired' => 'zinc', default => 'amber' }" :label="$quotation->status->label()" /></td>
                                        <td class="text-right">{{ $quotation->formatMoneyCost('charge_total') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-dense::table-card>
        </div>
    </div>
</section>
