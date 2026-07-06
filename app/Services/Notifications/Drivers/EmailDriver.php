<?php

namespace App\Services\Notifications\Drivers;

use App\Contracts\Notifications\ChannelDriver;
use App\Data\Notifications\ChannelConfigFieldData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\ChannelTestResultData;
use App\Data\Notifications\TestChannelProviderData;
use App\Mail\TemplatedEmail;
use App\Services\EmailTemplateRenderer;
use App\Services\SettingsService;
use App\Settings\EmailSettings;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Email channel driver — reads tenant SMTP/SES settings (P23).
 *
 * Email credentials remain in {@see EmailSettings}; there is no
 * channel_providers row for the core email driver.
 */
class EmailDriver implements ChannelDriver
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function key(): string
    {
        return 'email';
    }

    public function channel(): string
    {
        return 'mail';
    }

    public function label(): string
    {
        return 'Email (Settings)';
    }

    /**
     * @return list<ChannelConfigFieldData>
     */
    public function configSchema(): array
    {
        return [
            new ChannelConfigFieldData('mailer', 'Mailer', 'select', required: true),
            new ChannelConfigFieldData('smtp_host', 'SMTP Host', 'string'),
            new ChannelConfigFieldData('smtp_port', 'SMTP Port', 'integer'),
            new ChannelConfigFieldData('smtp_username', 'SMTP Username', 'string'),
            new ChannelConfigFieldData('smtp_password', 'SMTP Password', 'password', secret: true),
            new ChannelConfigFieldData('smtp_encryption', 'SMTP Encryption', 'select'),
            new ChannelConfigFieldData('ses_key', 'SES Access Key', 'string', secret: true),
            new ChannelConfigFieldData('ses_secret', 'SES Secret Key', 'password', secret: true),
            new ChannelConfigFieldData('ses_region', 'SES Region', 'string'),
            new ChannelConfigFieldData('mailgun_domain', 'Mailgun Domain', 'string'),
            new ChannelConfigFieldData('mailgun_secret', 'Mailgun Secret', 'password', secret: true),
            new ChannelConfigFieldData('postmark_token', 'Postmark Token', 'password', secret: true),
            new ChannelConfigFieldData('from_address', 'From Address', 'email', required: true),
            new ChannelConfigFieldData('from_name', 'From Name', 'string'),
            new ChannelConfigFieldData('reply_to_address', 'Reply-To Address', 'email'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveConfig(): array
    {
        return $this->settings->group('email');
    }

    public function send(ChannelSendData $payload): void
    {
        $failure = $this->validateOutboundConfiguration();

        if ($failure !== null) {
            throw new \RuntimeException($failure);
        }

        $recipient = $this->resolveRecipient($payload);

        if ($recipient === null) {
            throw new \RuntimeException('No email address available for the notifiable.');
        }

        Mail::to($recipient)->send(new TemplatedEmail(
            subjectLine: $payload->title,
            bodyHtml: Str::markdown($payload->body),
            eyebrow: 'Notification',
            footerContext: settings('company.name', config('app.name')),
            preheader: $payload->title,
        ));
    }

    public function test(TestChannelProviderData $data): ChannelTestResultData
    {
        $failure = $this->validateOutboundConfiguration();

        if ($failure !== null) {
            return ChannelTestResultData::failed($this, $failure);
        }

        if ($data->recipient === null || $data->recipient === '') {
            return ChannelTestResultData::failed($this, 'A recipient email address is required to test email delivery.');
        }

        $companyName = settings('company.name', config('app.name'));

        try {
            $rendered = app(EmailTemplateRenderer::class)->render('test_email', [
                'company' => ['name' => $companyName],
            ]);
            $subject = $rendered['subject'];
            $bodyHtml = $rendered['html'];
        } catch (ModelNotFoundException) {
            $subject = 'Test email from '.$companyName;
            $bodyHtml = Str::markdown(
                "Hello,\n\nThis is a test email from **{$companyName}** to verify your email configuration is working correctly.\n\nIf you received this email, your email settings are configured properly."
            );
        }

        try {
            Mail::to($data->recipient)->send(new TemplatedEmail(
                subjectLine: $subject,
                bodyHtml: $bodyHtml,
                eyebrow: 'Email Test',
                footerContext: 'This is an automated test message from '.$companyName.'.',
                preheader: 'Your email configuration is working.',
            ));
        } catch (\Throwable $exception) {
            return ChannelTestResultData::failed(
                $this,
                'Email delivery failed: '.$exception->getMessage(),
            );
        }

        return ChannelTestResultData::passed(
            $this,
            "Test email sent to {$data->recipient}.",
        );
    }

    private function validateOutboundConfiguration(): ?string
    {
        $mailer = (string) ($this->settings->get('email.mailer') ?? 'log');

        if ($mailer === '' || $mailer === 'log') {
            return 'Email is configured to use the log driver only; configure an outbound mailer in Settings → Email before testing delivery.';
        }

        $fromAddress = (string) ($this->settings->get('email.from_address') ?? '');

        if ($fromAddress === '') {
            return 'A from address is required in Settings → Email before testing delivery.';
        }

        return match ($mailer) {
            'smtp' => $this->validateSmtp(),
            'ses' => $this->validateSes(),
            'mailgun' => $this->validateMailgun(),
            'postmark' => $this->validatePostmark(),
            default => "Unknown mailer [{$mailer}] configured in Settings → Email.",
        };
    }

    private function validateSmtp(): ?string
    {
        if ((string) ($this->settings->get('email.smtp_host') ?? '') === '') {
            return 'SMTP host is required when using the SMTP mailer.';
        }

        return null;
    }

    private function validateSes(): ?string
    {
        if ((string) ($this->settings->get('email.ses_key') ?? '') === ''
            || (string) ($this->settings->get('email.ses_secret') ?? '') === '') {
            return 'SES credentials are required when using the SES mailer.';
        }

        return null;
    }

    private function validateMailgun(): ?string
    {
        if ((string) ($this->settings->get('email.mailgun_domain') ?? '') === ''
            || (string) ($this->settings->get('email.mailgun_secret') ?? '') === '') {
            return 'Mailgun domain and secret are required when using the Mailgun mailer.';
        }

        return null;
    }

    private function validatePostmark(): ?string
    {
        if ((string) ($this->settings->get('email.postmark_token') ?? '') === '') {
            return 'Postmark token is required when using the Postmark mailer.';
        }

        return null;
    }

    private function resolveRecipient(ChannelSendData $payload): ?string
    {
        if (isset($payload->data['recipient']) && is_string($payload->data['recipient'])) {
            return $payload->data['recipient'];
        }

        $notifiableClass = $payload->notifiableType;

        if (! class_exists($notifiableClass)) {
            return null;
        }

        /** @var object|null $notifiable */
        $notifiable = $notifiableClass::query()->find($payload->notifiableId);

        if ($notifiable === null) {
            return null;
        }

        if (method_exists($notifiable, 'routeNotificationForMail')) {
            return $notifiable->routeNotificationForMail();
        }

        if (isset($notifiable->email) && is_string($notifiable->email)) {
            return $notifiable->email;
        }

        return null;
    }
}
