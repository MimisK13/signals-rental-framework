<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Presentation\Livewire;

use App\Domain\Invoices\Actions\GenerateDepositInvoice;
use App\Domain\Invoices\Actions\GenerateInvoiceFromOpportunity;
use App\Domain\Invoices\Actions\GenerateProformaInvoice;
use App\Domain\Invoices\Data\GenerateDepositInvoiceData;
use App\Domain\Invoices\Data\GenerateInvoiceFromOpportunityData;
use App\Domain\Invoices\Data\GenerateProformaInvoiceData;
use App\Domain\Invoices\Enums\InvoiceType;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Services\InvoiceSourceStateRule;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Presentation\Concerns\HasOpportunityActions;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class OpportunityInvoices extends Component
{
    use HasOpportunityActions;

    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('invoices.view');

        $this->opportunity = $opportunity->load(['member', 'store']);
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

    public function generateProforma(): void
    {
        $result = (new GenerateProformaInvoice)(GenerateProformaInvoiceData::from([
            'opportunity_id' => $this->opportunity->id,
        ]));

        $this->redirect(route('invoices.show', $result->id), navigate: true);
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Invoices');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $sourceRule = app(InvoiceSourceStateRule::class);
        $canCreate = Gate::check('invoices.create');
        $fullyInvoiced = $this->opportunity->isFullyQuantityInvoiced();

        return [
            ...$this->opportunityActionData(),
            'invoices' => Invoice::query()
                ->where('opportunity_id', $this->opportunity->id)
                ->with('member')
                ->orderByDesc('created_at')
                ->get(),
            'canGenerateStandard' => $canCreate
                && ! $fullyInvoiced
                && in_array($this->opportunity->state->value, $sourceRule->allowedStatesFor(InvoiceType::Standard), true),
            'canGenerateDeposit' => $canCreate
                && in_array($this->opportunity->state->value, $sourceRule->allowedStatesFor(InvoiceType::Deposit), true),
            'canGenerateProforma' => $canCreate
                && in_array($this->opportunity->state->value, $sourceRule->allowedStatesFor(InvoiceType::Proforma), true),
            'fullyInvoiced' => $fullyInvoiced,
        ];
    }

    public function render(): View
    {
        return view('invoices::opportunity-invoices');
    }
}
