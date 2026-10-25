@php
    // Centralise tab-count loading so every opportunity tab shows correct, consistent
    // counts regardless of which relations the individual page component loaded.
    // `items()` is already active-version-scoped (opportunity-lifecycle.md §8.7).
    $opportunity->loadCount(['items', 'costs', 'attachments', 'participants', 'activities', 'documents', 'invoices']);

    // B3: allocation/dispatch (Assets) and shortage resolution (Shortages) only
    // apply once demand is committed — i.e. for an ORDER, or a QUOTATION that has
    // been moved to RESERVED. The two tabs are hidden for drafts and open (non-
    // reserved) quotations. Derived from the state/status enums, never a hardcoded
    // status list, so configurable statuses inherit the predicate.
    $allocationTabsVisible = $opportunity->state === \App\Enums\OpportunityState::Order
        || ($opportunity->state === \App\Enums\OpportunityState::Quotation
            && $opportunity->statusEnum() === \App\Enums\OpportunityStatus::QuotationReserved);

    // Overview receives the derived projection from its nested line-items component.
    // Other tabs retain their existing local count because they do not mount that
    // child component and therefore have no projection source.
    $shortageTabCount = $activeTab === 'overview'
        ? (isset($shortageCount) ? $shortageCount : null)
        : 0;
    if ($activeTab !== 'overview' && $allocationTabsVisible && \Illuminate\Support\Facades\Gate::allows('shortages.view')) {
        $shortageTabCount = app(\App\Services\Shortages\ShortageDetector::class)
            ->forOpportunity($opportunity)
            ->unresolved()
            ->count();
    }

@endphp
{{--
    The Overview tab embeds the live line-item editor (the standalone "Line Items"
    tab/route was removed in the show-page restructure). The "Versions & Timeline"
    tab (M8-5) renders the quote-versioning UI alongside the activity timeline (the
    standalone "Activities" tab/route was likewise removed); it is gated on
    `opportunities.view` (a version is an aspect of an opportunity, not a standalone
    resource) and carries the opportunity's `version_count` as its badge. The
    Shortages tab (M8-4c) is gated on `shortages.view` and only appears for users
    who hold it.
--}}
<x-signals.module-tabs
    :tabs="array_values(array_filter([
        ['name' => 'overview', 'label' => 'Overview', 'route' => route('opportunities.show', $opportunity)],
        $allocationTabsVisible
            ? ['name' => 'assets', 'label' => 'Assets', 'route' => route('opportunities.assets', $opportunity)]
            : null,
        \Illuminate\Support\Facades\Gate::allows('opportunities.view')
            ? ['name' => 'versions', 'label' => 'Versions & Timeline', 'route' => route('opportunities.versions', $opportunity), 'count' => $opportunity->version_count ?? 0]
            : null,
        \Illuminate\Support\Facades\Gate::allows('activities.view')
            ? ['name' => 'activities', 'label' => 'Activities', 'route' => route('opportunities.activities', $opportunity), 'count' => $opportunity->activities_count ?? 0]
            : null,
        $allocationTabsVisible && \Illuminate\Support\Facades\Gate::allows('shortages.view')
            ? array_filter([
                'name' => 'shortages',
                'label' => 'Shortages',
                'route' => route('opportunities.shortages', $opportunity),
                'count' => $shortageTabCount,
            ], static fn (mixed $value, string|int $key): bool => $key !== 'count' || $value !== null, ARRAY_FILTER_USE_BOTH)
            : null,
        ['name' => 'costs', 'label' => 'Costs', 'route' => route('opportunities.costs', $opportunity), 'count' => $opportunity->costs_count ?? 0],
        \Illuminate\Support\Facades\Gate::allows('invoices.view')
            ? ['name' => 'invoices', 'label' => 'Invoices', 'route' => route('opportunities.invoices', $opportunity), 'count' => $opportunity->invoices_count ?? 0]
            : null,
        ['name' => 'participants', 'label' => 'Participants', 'route' => route('opportunities.participants', $opportunity), 'count' => $opportunity->participants_count ?? 0],
        ['name' => 'custom-fields', 'label' => 'Custom Fields', 'route' => route('opportunities.custom-fields', $opportunity)],
        ['name' => 'files', 'label' => 'Files', 'route' => route('opportunities.files', $opportunity), 'count' => $opportunity->attachments_count ?? 0],
        \Illuminate\Support\Facades\Gate::allows('documents.view')
            ? ['name' => 'documents', 'label' => 'Documents', 'route' => route('opportunities.documents', $opportunity), 'count' => $opportunity->documents_count ?? 0]
            : null,
        ['name' => 'communications', 'label' => 'Communications', 'route' => route('opportunities.communications', $opportunity)],
    ]))"
    :active="$activeTab"
/>
