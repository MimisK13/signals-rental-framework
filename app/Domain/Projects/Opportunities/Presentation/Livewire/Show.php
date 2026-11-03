<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Presentation\Livewire;

use App\Domain\Invoices\Actions\GenerateDepositInvoice;
use App\Domain\Invoices\Actions\GenerateInvoiceFromOpportunity;
use App\Domain\Invoices\Data\GenerateDepositInvoiceData;
use App\Domain\Invoices\Data\GenerateInvoiceFromOpportunityData;
use App\Domain\Invoices\Enums\InvoiceType;
use App\Domain\Invoices\Services\InvoiceSourceStateRule;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Presentation\Concerns\HasOpportunityActions;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Opportunity Show (overview) page (M8-2).
 *
 * Renders the record overview plus the Actions split-button. The permitted
 * state-transition actions + the confirm/change-status modal plumbing are shared
 * with every opportunity tab page via {@see HasOpportunityActions}; the verdicts
 * are computed exactly the way the API's `available_actions` endpoint does and the
 * transition wire-methods call the SAME action classes the API calls.
 */
#[Layout('components.layouts.app')] final class Show extends Component
{
    use HasOpportunityActions;

    public Opportunity $opportunity;

    public ?int $shortageCount = null;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity->load(['member', 'venue', 'store', 'owner', 'activeVersion']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject);
    }

    /**
     * Live availability refresh requires the Reverb/Echo client bundle to be
     * configured (not yet wired in resources/js/app.js); the server-side broadcast
     * (App\Domain\Availability\Events\OpportunityAvailabilityChanged, broadcastAs
     * `availability.changed` on `availability.opportunity.{id}`) and the channel
     * auth (routes/channels.php) already exist. Until the client Echo instance is
     * registered this listener is inert but correct — the dynamic `{opportunity.id}`
     * channel segment and the dot-prefixed custom broadcast name follow the Livewire
     * 4 / Laravel Echo convention.
     */
    #[On('echo-private:availability.opportunity.{opportunity.id},.availability.changed')]
    public function onAvailabilityChanged(): void
    {
        // Re-read the projection; with() re-evaluates on the subsequent re-render so
        // totals + shortage flags pick up the recalculated picture.
        $this->opportunity->refresh();
    }

    /**
     * Refresh the projection when the nested line-item editor reports a
     * totals-affecting change (#10: qty / rate override / discount). The editor
     * recomputes its own footer + line totals, but the sidebar Totals panel + header
     * live on THIS component, so they would otherwise stay stale.
     */
    #[On('opportunity-totals-updated')]
    public function onTotalsUpdated(?int $chargeTotalMinor = null): void
    {
        if ($chargeTotalMinor !== null) {
            $this->opportunity->charge_total = $chargeTotalMinor;

            return;
        }

        $this->opportunity->refresh();
    }

    #[On('opportunity-shortage-summary-updated')]
    public function onShortageSummaryUpdated(int $opportunityId, int $unresolvedCount): void
    {
        if ($opportunityId !== $this->opportunity->id) {
            return;
        }

        $this->shortageCount = max(0, $unresolvedCount);
    }

    #[On('open-opportunity-lock-price-modal')]
    public function openLockPriceModal(): void
    {
        $this->openConfirmModal('unlock_locks');
    }

    public function generateInvoice(): void
    {
        $result = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $this->opportunity->id,
        ]));

        $this->redirect(route('invoices.show', $result->id), navigate: true);
    }

    public function generateDeposit(): void
    {
        $result = (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
            'opportunity_id' => $this->opportunity->id,
            'amount_percentage' => (string) settings('invoices.deposit_percentage_default', '25'),
        ]));

        $this->redirect(route('invoices.show', $result->id), navigate: true);
    }

    /**
     * Merge the shared Actions data ({@see HasOpportunityActions}) into the view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $sourceRule = app(InvoiceSourceStateRule::class);
        $canCreate = Gate::check('invoices.create');
        $fullyInvoiced = $this->opportunity->isFullyQuantityInvoiced();

        return [
            ...$this->opportunityActionData(),
            'canGenerateStandard' => $canCreate
                && ! $fullyInvoiced
                && in_array($this->opportunity->state->value, $sourceRule->allowedStatesFor(InvoiceType::Standard), true),
            'canGenerateDeposit' => $canCreate
                && in_array($this->opportunity->state->value, $sourceRule->allowedStatesFor(InvoiceType::Deposit), true),
            'fullyInvoiced' => $fullyInvoiced,
        ];
    }

    public function render(): View
    {
        return view('project-opportunities::show');
    }
}
