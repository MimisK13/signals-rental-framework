<?php

use App\Enums\OpportunityState;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Experimental Quotations')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('opportunities.access');
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'columns' => [
                ['key' => 'subject', 'label' => 'Quotation', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'livewire.experimental.quotations.partials.column-subject'],
                ['key' => 'number', 'label' => 'Number', 'sortable' => true, 'view' => 'livewire.experimental.quotations.partials.column-number'],
                ['key' => 'member_name', 'label' => 'Client', 'view' => 'livewire.experimental.quotations.partials.column-member'],
                ['key' => 'store_name', 'label' => 'Store', 'view' => 'livewire.experimental.quotations.partials.column-store'],
                ['key' => 'starts_at', 'label' => 'Schedule', 'sortable' => true, 'view' => 'livewire.experimental.quotations.partials.column-schedule'],
                ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'view' => 'livewire.opportunities.partials.column-status'],
                ['key' => 'charge_total', 'label' => 'Total', 'sortable' => true, 'view' => 'livewire.opportunities.partials.column-charge-total'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => ['ofState' => OpportunityState::Quotation],
        ];
    }
}; ?>

<section class="w-full">
    <x-dense::page-header title="Quotations">
        <x-slot:meta>
            <span class="s-badge s-badge-amber">Experimental</span>
        </x-slot:meta>
        <x-slot:actions>
            @can('opportunities.create')
                <a href="{{ route('experimental.quotations.create') }}" wire:navigate.hover class="s-btn s-btn-primary">
                    <flux:icon.plus class="size-4" />
                    Create quotation
                </a>
            @endcan
        </x-slot:actions>
    </x-dense::page-header>

    <div class="px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.alert type="warning" class="mb-4">
            Experimental workflow. This page uses real Opportunities and canonical domain actions.
        </x-signals.alert>

        <livewire:components.data-table
            :columns="$columns"
            :model="Opportunity::class"
            :searchable="['subject', 'number', 'reference']"
            :with="['member', 'store']"
            :scopes="$scopes"
            default-sort="-updated_at"
            empty-message="No quotations found."
            actions-view="livewire.experimental.quotations.partials.row-actions"
            entity-type="experimental-quotations"
            :key="'experimental-quotations-table'"
        />
    </div>
</section>
