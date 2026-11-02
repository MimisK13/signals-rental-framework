<?php

declare(strict_types=1);

namespace App\Domain\Communications\Actions;

use App\Domain\Communications\Mail\TemplatedEmail;
use App\Domain\Communications\Models\EmailTemplate;
use App\Domain\Communications\Services\EmailTemplateRenderer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

final class SendTestTemplateEmail
{
    /**
     * Render the given template with sample data and send it as a test email.
     *
     * @throws AuthorizationException
     */
    public function __invoke(EmailTemplate $template, string $recipient): void
    {
        Gate::authorize('email-templates.manage');

        $rendered = app(EmailTemplateRenderer::class)->renderTemplate(
            $template,
            EmailTemplateRenderer::sampleData(),
        );

        Mail::to($recipient)->send(new TemplatedEmail(
            subjectLine: '[Test] '.$rendered['subject'],
            bodyHtml: $rendered['html'],
            eyebrow: $template->name,
            footerContext: 'This is a test of the "'.$template->name.'" email template.',
            preheader: 'Test email: '.$template->name,
        ));
    }
}
