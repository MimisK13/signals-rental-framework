<?php

declare(strict_types=1);

namespace App\Domain\Communications\Presentation\Livewire;

use App\Domain\Communications\Actions\SendTestTemplateEmail;
use App\Domain\Communications\Models\EmailTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Email Templates')]
final class EmailTemplates extends Component
{
    public bool $showTestModal = false;

    public ?int $testTemplateId = null;

    public string $testRecipient = '';

    /** Bumped on each modal open so the form re-renders fresh — a morph alone leaves the native select displaying its previous choice after the bound property is reset. */
    public int $testModalNonce = 0;

    public function openTestModal(): void
    {
        $this->reset(['testTemplateId', 'testRecipient']);
        $this->resetErrorBag();
        $this->testModalNonce++;
        $this->showTestModal = true;
    }

    public function sendTest(): void
    {
        $this->validate([
            'testTemplateId' => ['required', 'integer', 'exists:email_templates,id'],
            'testRecipient' => ['required', 'email:filter'],
        ], [
            'testTemplateId.required' => 'Choose a template to send.',
        ]);

        $template = EmailTemplate::findOrFail($this->testTemplateId);

        try {
            (new SendTestTemplateEmail)($template, $this->testRecipient);
        } catch (\Throwable $e) {
            $this->addError('testRecipient', 'Failed to send test email: '.$e->getMessage());

            return;
        }

        $this->showTestModal = false;
        $this->reset(['testTemplateId', 'testRecipient']);
        $this->dispatch('test-template-sent');
    }

    /**
     * @return array{templates: Collection<int, EmailTemplate>}
     */
    public function with(): array
    {
        return [
            'templates' => EmailTemplate::query()
                ->orderBy('name')
                ->get(),
        ];
    }

    public function render(): View
    {
        return view('communications::admin.email-templates', $this->with());
    }
}
