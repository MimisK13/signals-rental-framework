<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Presentation\Livewire;

use App\Domain\Dashboard\Concerns\ScopesDashboardQueries;
use App\Domain\Administration\Models\User;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Dashboard widget listing the most recently created opportunities.
 *
 * Renders a small table of the latest opportunities — number, subject, member,
 * status badge and charge total — each row linking through to the opportunity
 * Show page, plus a "View All" link to the filtered Index. The whole widget is
 * gated on `opportunities.access`; users without it see nothing.
 */
final class RecentOpportunities extends Component
{
    use ScopesDashboardQueries;

    /**
     * How many opportunities to list.
     */
    public int $limit = 5;

    /**
     * The latest opportunities, eager-loading the member to avoid an N+1 on the
     * member-name column.
     *
     * @return Collection<int, Opportunity>
     */
    public function recent(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return $this->scopeToAccessibleStores(Opportunity::query(), $user)
            ->with('member')
            ->latest()
            // Tie-break on the (ascending) primary key so rows sharing a
            // created_at second still order newest-first deterministically.
            ->latest('id')
            ->take($this->limit)
            ->get();
    }

    public function render(): View
    {
        $canAccess = Gate::allows('opportunities.access');

        // Gate before the query: an unauthorized user gets an empty collection
        // without ever running the recent() query.
        $opportunities = $canAccess ? $this->recent() : collect();

        return view('dashboard::widgets.recent-opportunities', [
            'opportunities' => $opportunities,
            'canAccess' => $canAccess,
        ]);
    }
}
