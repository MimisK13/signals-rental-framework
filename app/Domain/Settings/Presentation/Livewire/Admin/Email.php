<?php

declare(strict_types=1);

namespace App\Domain\Settings\Presentation\Livewire\Admin;

use App\Domain\Communications\Actions\SendTestEmail;
use App\Domain\Settings\Services\SettingsRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Email')]
final class Email extends Component
{
    public string $mailer = 'log';

    public string $smtpHost = '';

    public int $smtpPort = 587;

    public string $smtpUsername = '';

    public string $smtpPassword = '';

    public string $smtpEncryption = 'tls';

    public string $sesKey = '';

    public string $sesSecret = '';

    public string $sesRegion = 'eu-west-1';

    public string $mailgunDomain = '';

    public string $mailgunSecret = '';

    public string $postmarkToken = '';

    public string $fromAddress = '';

    public string $fromName = '';

    public string $replyToAddress = '';

    public string $testEmailAddress = '';

    public function mount(): void
    {
        $group = settings()->group('email');

        $this->mailer = (string) $group['mailer'];
        $this->smtpHost = (string) $group['smtp_host'];
        $this->smtpPort = (int) $group['smtp_port'];
        $this->smtpUsername = (string) $group['smtp_username'];
        $this->smtpPassword = (string) $group['smtp_password'];
        $this->smtpEncryption = (string) $group['smtp_encryption'];
        $this->sesKey = (string) $group['ses_key'];
        $this->sesSecret = (string) $group['ses_secret'];
        $this->sesRegion = (string) $group['ses_region'];
        $this->mailgunDomain = (string) $group['mailgun_domain'];
        $this->mailgunSecret = (string) $group['mailgun_secret'];
        $this->postmarkToken = (string) $group['postmark_token'];
        $this->fromAddress = (string) $group['from_address'];
        $this->fromName = (string) $group['from_name'];
        $this->replyToAddress = (string) $group['reply_to_address'];
    }

    public function save(): void
    {
        $registry = app(SettingsRegistry::class);
        $rules = $registry->rules('email');

        $validated = $this->validate([
            'mailer' => $rules['mailer'],
            'smtpHost' => $rules['smtp_host'],
            'smtpPort' => $rules['smtp_port'],
            'smtpUsername' => $rules['smtp_username'],
            'smtpPassword' => $rules['smtp_password'],
            'smtpEncryption' => $rules['smtp_encryption'],
            'sesKey' => $rules['ses_key'],
            'sesSecret' => $rules['ses_secret'],
            'sesRegion' => $rules['ses_region'],
            'mailgunDomain' => $rules['mailgun_domain'],
            'mailgunSecret' => $rules['mailgun_secret'],
            'postmarkToken' => $rules['postmark_token'],
            'fromAddress' => $rules['from_address'],
            'fromName' => $rules['from_name'],
            'replyToAddress' => $rules['reply_to_address'],
        ]);

        $types = $registry->types('email');

        settings()->setMany([
            'email.mailer' => $validated['mailer'],
            'email.smtp_host' => $validated['smtpHost'],
            'email.smtp_port' => ['value' => $validated['smtpPort'], 'type' => $types['smtp_port'] ?? 'string'],
            'email.smtp_username' => $validated['smtpUsername'],
            'email.smtp_password' => ['value' => $validated['smtpPassword'], 'type' => $types['smtp_password'] ?? 'string'],
            'email.smtp_encryption' => $validated['smtpEncryption'],
            'email.ses_key' => $validated['sesKey'],
            'email.ses_secret' => ['value' => $validated['sesSecret'], 'type' => $types['ses_secret'] ?? 'string'],
            'email.ses_region' => $validated['sesRegion'],
            'email.mailgun_domain' => $validated['mailgunDomain'],
            'email.mailgun_secret' => ['value' => $validated['mailgunSecret'], 'type' => $types['mailgun_secret'] ?? 'string'],
            'email.postmark_token' => ['value' => $validated['postmarkToken'], 'type' => $types['postmark_token'] ?? 'string'],
            'email.from_address' => $validated['fromAddress'],
            'email.from_name' => $validated['fromName'],
            'email.reply_to_address' => $validated['replyToAddress'],
        ]);

        $this->dispatch('email-settings-saved');
    }

    public function sendTestEmail(): void
    {
        $this->validate([
            'testEmailAddress' => ['required', 'email:filter'],
        ]);

        try {
            (new SendTestEmail)($this->testEmailAddress);
            $this->dispatch('test-email-sent');
        } catch (\Exception $e) {
            $this->addError('testEmailAddress', 'Failed to send test email: '.$e->getMessage());
        }
    }

    public function render(): View
    {
        return view('settings::admin.email');
    }
}
