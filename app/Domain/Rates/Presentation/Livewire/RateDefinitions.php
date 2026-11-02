<?php

declare(strict_types=1);

namespace App\Domain\Rates\Presentation\Livewire;

use App\Domain\Rates\Actions\DeleteRateDefinition;
use App\Domain\Rates\Models\RateDefinition;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Rate Definitions')]
final class RateDefinitions extends Component
{
    public ?int $confirmingDeletion = null;

    /**
     * @return array{definitions: Collection<int, RateDefinition>}
     */
    private function viewData(): array
    {
        return [
            'definitions' => RateDefinition::query()
                ->withCount('productRates')
                ->orderByDesc('is_preset')
                ->orderBy('name')
                ->get(),
        ];
    }

    public function deleteDefinition(int $definitionId): void
    {
        $definition = RateDefinition::findOrFail($definitionId);

        (new DeleteRateDefinition)($definition);

        $this->confirmingDeletion = null;
        $this->dispatch('rate-definition-deleted');
    }

    public function render(): View
    {
        return view('rates::rate-definitions', $this->viewData());
    }
}
