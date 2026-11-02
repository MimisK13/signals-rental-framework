<?php

declare(strict_types=1);

namespace App\Domain\Communications\Presentation\Livewire;

use App\Domain\Communications\Actions\ResetEmailTemplate;
use App\Domain\Communications\Actions\UpdateEmailTemplate;
use App\Domain\Communications\Models\EmailTemplate;
use App\Domain\Communications\Services\EmailTemplateRenderer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Email Template')]
final class EmailTemplateForm extends Component
{
    public EmailTemplate $template;

    public string $subject = '';

    public string $bodyMarkdown = '';

    public string $previewHtml = '';

    public function mount(EmailTemplate $template): void
    {
        $this->template = $template;
        $this->subject = $template->subject;
        $this->bodyMarkdown = $template->body_markdown;
    }

    public function save(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'bodyMarkdown' => ['required', 'string'],
        ]);

        (new UpdateEmailTemplate)($this->template, [
            'subject' => $this->subject,
            'body_markdown' => $this->bodyMarkdown,
        ]);

        $this->template->refresh();
        $this->dispatch('email-template-saved');
    }

    public function preview(): void
    {
        $renderer = app(EmailTemplateRenderer::class);

        $result = $renderer->renderTemplate($this->template->replicate()->fill([
            'subject' => $this->subject,
            'body_markdown' => $this->bodyMarkdown,
        ]), EmailTemplateRenderer::sampleData());

        $this->previewHtml = view('communications::emails.layouts.signals', [
            'bodyHtml' => $result['html'],
            'eyebrow' => $this->template->name,
            'footerContext' => null,
            'preheader' => null,
        ])->render();
    }

    public function resetToDefault(): void
    {
        $template = (new ResetEmailTemplate)($this->template);

        $this->subject = $template->subject;
        $this->bodyMarkdown = $template->body_markdown;
        $this->previewHtml = '';
        $this->dispatch('email-template-reset');
    }

    public function render(): View
    {
        return view('communications::admin.email-template-form');
    }
}
