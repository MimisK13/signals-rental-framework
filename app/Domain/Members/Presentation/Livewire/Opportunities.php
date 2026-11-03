<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Members\Models\Member;
use App\Domain\Projects\Opportunities\Presentation\Columns\OpportunityColumnRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class Opportunities extends Component
{
    public Member $member;

    public function mount(Member $member): void
    {
        Gate::authorize('opportunities.access');

        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name.' — Opportunities');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        // Derive sortable/filterable flags from the canonical OpportunityColumnRegistry
        // so this related list's column metadata stays in lockstep with the main
        // opportunities index and the custom-view / column-picker machinery.
        $registry = app(OpportunityColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)->sortable ?? false,
            'filterable' => $registry->get($key)->filterable ?? false,
        ];

        return [
            'columns' => [
                ['key' => 'subject', 'label' => 'Subject', ...$columnMeta('subject'), 'filter_type' => 'text', 'view' => 'project-opportunities::partials.column-subject'],
                ['key' => 'reference', 'label' => 'Reference', ...$columnMeta('reference'), 'filter_type' => 'text', 'view' => 'project-opportunities::partials.column-reference'],
                ['key' => 'state', 'label' => 'State', ...$columnMeta('state'), 'view' => 'project-opportunities::partials.column-state'],
                ['key' => 'status', 'label' => 'Status', ...$columnMeta('status'), 'view' => 'project-opportunities::partials.column-status'],
                ['key' => 'starts_at', 'label' => 'Starts', ...$columnMeta('starts_at'), 'view' => 'project-opportunities::partials.column-starts-at'],
                ['key' => 'ends_at', 'label' => 'Ends', ...$columnMeta('ends_at'), 'view' => 'project-opportunities::partials.column-ends-at'],
                ['key' => 'charge_total', 'label' => 'Charge Total', ...$columnMeta('charge_total'), 'view' => 'project-opportunities::partials.column-charge-total'],
                ['key' => 'created_at', 'label' => 'Created', ...$columnMeta('created_at'), 'view' => 'project-opportunities::partials.column-created'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                'forMember' => $this->member->id,
            ],
        ];
    }

    public function render(): View
    {
        return view('members::opportunities', $this->with());
    }
}
