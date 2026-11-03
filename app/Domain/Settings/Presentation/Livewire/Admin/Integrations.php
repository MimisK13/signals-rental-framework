<?php

declare(strict_types=1);

namespace App\Domain\Settings\Presentation\Livewire\Admin;

use App\Domain\Auth\Services\SsoService;
use App\Domain\Settings\Services\SettingsRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Integrations')]
final class Integrations extends Component
{
    public string $what3wordsApiKey = '';

    public string $googleMapsApiKey = '';

    public bool $ssoGoogleEnabled = false;

    public string $ssoGoogleClientId = '';

    /**
     * Write-only: never populated from storage. A blank value on save keeps the
     * stored secret; a non-empty value rotates it. Decrypted secrets are never
     * round-tripped to the browser.
     */
    public string $ssoGoogleClientSecret = '';

    public bool $ssoMicrosoftEnabled = false;

    public string $ssoMicrosoftClientId = '';

    /** Write-only — see {@see $ssoGoogleClientSecret}. */
    public string $ssoMicrosoftClientSecret = '';

    /** Comma/space/newline-separated owned email domains for the SSO allow-list. */
    public string $ssoAllowedEmailDomains = '';

    public function mount(): void
    {
        $group = settings()->group('integrations');

        $this->what3wordsApiKey = (string) $group['what3words_api_key'];
        $this->googleMapsApiKey = (string) $group['google_maps_api_key'];

        $this->ssoGoogleEnabled = (bool) settings('sso.google_enabled');
        $this->ssoGoogleClientId = (string) settings('sso.google_client_id');
        $this->ssoMicrosoftEnabled = (bool) settings('sso.microsoft_enabled');
        $this->ssoMicrosoftClientId = (string) settings('sso.microsoft_client_id');

        // Client secrets are write-only: leave the inputs blank and never expose
        // the stored (decrypted) values to the browser.

        $this->ssoAllowedEmailDomains = implode("\n", $this->storedAllowedEmailDomains());
    }

    /**
     * Whether the application is running on Signals Cloud.
     *
     * On Cloud, credentials are managed centrally (env), so the per-provider key
     * fields are hidden and never persisted — only the enable toggles and the
     * allow-list are editable. Cached for the request so both save() and the view
     * resolve it once.
     */
    #[Computed]
    public function isCloud(): bool
    {
        return app(SsoService::class)->isCloud();
    }

    /**
     * Per-provider metadata driving the SSO provider blocks in the view.
     *
     * Each entry carries the provider slug, the credential-field label base and the
     * enable-toggle label, plus the component property names for its enable toggle /
     * client id / client secret inputs, whether a secret is already stored (the
     * write-only "leave blank to keep" indicator) and its self-hosted callback URL.
     *
     * @return list<array{slug: string, label: string, enableLabel: string, enabledProp: string, clientIdProp: string, clientSecretProp: string, secretConfigured: bool, callbackUrl: string}>
     */
    #[Computed]
    public function ssoProviders(): array
    {
        return [
            [
                'slug' => 'google',
                'label' => 'Google',
                'enableLabel' => 'Google',
                'enabledProp' => 'ssoGoogleEnabled',
                'clientIdProp' => 'ssoGoogleClientId',
                'clientSecretProp' => 'ssoGoogleClientSecret',
                'secretConfigured' => filled(settings('sso.google_client_secret')),
                'callbackUrl' => route('sso.callback', ['provider' => 'google']),
            ],
            [
                'slug' => 'microsoft',
                'label' => 'Microsoft',
                'enableLabel' => 'Microsoft 365',
                'enabledProp' => 'ssoMicrosoftEnabled',
                'clientIdProp' => 'ssoMicrosoftClientId',
                'clientSecretProp' => 'ssoMicrosoftClientSecret',
                'secretConfigured' => filled(settings('sso.microsoft_client_secret')),
                'callbackUrl' => route('sso.callback', ['provider' => 'microsoft']),
            ],
        ];
    }

    /**
     * Read the stored allow-list as a clean list of domain strings.
     *
     * @return list<string>
     */
    protected function storedAllowedEmailDomains(): array
    {
        $stored = settings('sso.allowed_email_domains');

        if (! is_array($stored)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($domain): string => is_string($domain) ? trim($domain) : '',
            $stored,
        ), fn (string $domain): bool => $domain !== ''));
    }

    /**
     * Normalise free-text domain input into a lowercased, de-duplicated list.
     *
     * Accepts comma, whitespace, and newline separators.
     *
     * @return list<string>
     */
    protected function normaliseAllowedEmailDomains(string $input): array
    {
        $parts = preg_split('/[\s,]+/', $input) ?: [];
        $domains = [];

        foreach ($parts as $part) {
            $normalised = strtolower(trim($part));

            if ($normalised !== '') {
                $domains[] = $normalised;
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * Validate and persist the integration + SSO settings.
     *
     * Credential fields (client id/secret) are only validated and persisted on
     * self-hosted installs; client secrets are write-only (a blank input keeps the
     * stored value). The allow-list is policy and is persisted on Cloud too.
     */
    public function save(): void
    {
        $registry = app(SettingsRegistry::class);
        // Geocoding/maps keys live in the `integrations` group; the SSO keys are
        // persisted and read under their own `sso` group (see SsoSettings), so each
        // set of rules/types is pulled from the group that actually owns it.
        $rules = $registry->rules('integrations');
        $types = $registry->types('integrations');
        $ssoRules = $registry->rules('sso');
        $ssoTypes = $registry->types('sso');
        $isCloud = $this->isCloud();

        $rulesToValidate = [
            'what3wordsApiKey' => $rules['what3words_api_key'],
            'googleMapsApiKey' => $rules['google_maps_api_key'],
            'ssoGoogleEnabled' => $ssoRules['google_enabled'],
            'ssoMicrosoftEnabled' => $ssoRules['microsoft_enabled'],
        ];

        // Credential fields are only editable on self-hosted installs.
        if (! $isCloud) {
            $rulesToValidate['ssoGoogleClientId'] = $ssoRules['google_client_id'];
            $rulesToValidate['ssoGoogleClientSecret'] = $ssoRules['google_client_secret'];
            $rulesToValidate['ssoMicrosoftClientId'] = $ssoRules['microsoft_client_id'];
            $rulesToValidate['ssoMicrosoftClientSecret'] = $ssoRules['microsoft_client_secret'];
        }

        $validated = $this->validate($rulesToValidate);

        $toPersist = [
            'integrations.what3words_api_key' => ['value' => $validated['what3wordsApiKey'], 'type' => $types['what3words_api_key'] ?? 'string'],
            'integrations.google_maps_api_key' => ['value' => $validated['googleMapsApiKey'], 'type' => $types['google_maps_api_key'] ?? 'string'],
            'sso.google_enabled' => ['value' => $validated['ssoGoogleEnabled'], 'type' => $ssoTypes['google_enabled']],
            'sso.microsoft_enabled' => ['value' => $validated['ssoMicrosoftEnabled'], 'type' => $ssoTypes['microsoft_enabled']],
            // The allow-list is policy, not a credential — persisted on cloud and self-hosted alike.
            'sso.allowed_email_domains' => ['value' => $this->normaliseAllowedEmailDomains($this->ssoAllowedEmailDomains), 'type' => $ssoTypes['allowed_email_domains']],
        ];

        if (! $isCloud) {
            $toPersist['sso.google_client_id'] = ['value' => $validated['ssoGoogleClientId'], 'type' => $ssoTypes['google_client_id']];
            $toPersist['sso.microsoft_client_id'] = ['value' => $validated['ssoMicrosoftClientId'], 'type' => $ssoTypes['microsoft_client_id']];

            // Client secrets are write-only: only persist when a non-empty value was
            // entered. A blank input keeps the existing stored secret untouched.
            if ($validated['ssoGoogleClientSecret'] !== '') {
                $toPersist['sso.google_client_secret'] = ['value' => $validated['ssoGoogleClientSecret'], 'type' => $ssoTypes['google_client_secret']];
            }

            if ($validated['ssoMicrosoftClientSecret'] !== '') {
                $toPersist['sso.microsoft_client_secret'] = ['value' => $validated['ssoMicrosoftClientSecret'], 'type' => $ssoTypes['microsoft_client_secret']];
            }
        }

        settings()->setMany($toPersist);

        // Reset write-only inputs so a stale plaintext secret is never held in component state.
        $this->ssoGoogleClientSecret = '';
        $this->ssoMicrosoftClientSecret = '';
        $this->ssoAllowedEmailDomains = implode("\n", $this->storedAllowedEmailDomains());

        $this->dispatch('integration-settings-saved');
    }

    public function render(): View
    {
        return view('settings::admin.integrations');
    }
}
