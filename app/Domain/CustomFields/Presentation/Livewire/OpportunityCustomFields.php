<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Presentation\Livewire;

use App\Domain\CustomFields\Models\CustomField;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Presentation\Concerns\HasOpportunityActions;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class OpportunityCustomFields extends Component
{
    use HasOpportunityActions;

    public Opportunity $opportunity;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Custom Fields');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $fields = CustomField::query()
            ->forModule('Opportunity')
            ->active()
            ->with('group')
            ->orderBy('sort_order')
            ->get();

        $values = $this->opportunity->customFieldValues()
            ->with('customField')
            ->get()
            ->keyBy('custom_field_id');

        $grouped = $fields->groupBy(fn ($field): string => (string) (data_get($field, 'group.name') ?? 'General'));

        return [
            ...$this->opportunityActionData(),

            'grouped' => $grouped,
            'values' => $values,
        ];
    }

    public function render(): View
    {
        return view('custom-fields::opportunity-custom-fields');
    }
}
