<?php

declare(strict_types=1);

namespace App\Domain\Attachments\Presentation\Livewire;

use App\Domain\Attachments\Presentation\Concerns\HasFileActions;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Presentation\Concerns\HasOpportunityActions;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class OpportunityFiles extends Component
{
    use HasFileActions;
    use HasOpportunityActions;

    public Opportunity $opportunity;

    public ?int $deleteAttachmentId = null;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');
        $this->opportunity = $opportunity->loadCount('attachments');
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Files');
    }

    protected function getFileableModel(): Opportunity
    {
        return $this->opportunity;
    }

    public function render(): View
    {
        return view('attachments::opportunity-files', [
            ...$this->opportunityActionData(),
            ...$this->fileData(),
        ]);
    }
}
