<section class="w-full min-w-0">
    <x-admin.layout group="preferences" title="Integrations" description="Configure API keys for third-party services.">
        <form wire:submit="save" class="space-y-6">
            <x-signals.form-section title="Geocoding">
                <div class="space-y-4">
                    <flux:input wire:model="what3wordsApiKey" label="what3words API Key" type="password" placeholder="Enter your what3words API key" description="Used for precise address geocoding. Get a key at developer.what3words.com" />
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Maps">
                <div class="space-y-4">
                    <flux:input wire:model="googleMapsApiKey" label="Google Maps API Key" type="password" placeholder="Enter your Google Maps API key" description="Used for address autocomplete and map display." />
                </div>
            </x-signals.form-section>

            <x-signals.form-section title="Single Sign-On">
                <div class="space-y-6">
                    @if($this->isCloud)
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Single sign-on credentials are managed by Signals Cloud. Use the toggles below to control which providers your users can sign in with.
                        </p>
                    @endif

                    @foreach($this->ssoProviders as $provider)
                        <div class="space-y-4" wire:key="sso-{{ $provider['slug'] }}">
                            <label class="flex items-center gap-2 cursor-pointer" x-data="{ checked: @js($this->{$provider['enabledProp']}) }">
                                <input type="checkbox" wire:model="{{ $provider['enabledProp'] }}" class="hidden" x-on:change="checked = $el.checked" />
                                <x-signals.checkbox x-bind:class="checked && 'checked'" />
                                <span class="text-sm font-medium">Enable {{ $provider['enableLabel'] }} sign-in</span>
                            </label>

                            @unless($this->isCloud)
                                <div class="space-y-4 pl-7">
                                    <flux:input wire:model="{{ $provider['clientIdProp'] }}" label="{{ $provider['label'] }} Client ID" placeholder="Enter your {{ $provider['label'] }} OAuth client ID" />
                                    <flux:input wire:model="{{ $provider['clientSecretProp'] }}" label="{{ $provider['label'] }} Client Secret" type="password"
                                        placeholder="{{ $provider['secretConfigured'] ? 'Configured — leave blank to keep' : 'Enter your ' . $provider['label'] . ' OAuth client secret' }}"
                                        description="{{ $provider['secretConfigured'] ? 'A secret is already configured. Leave blank to keep it, or enter a new value to replace it.' : 'Stored encrypted and never shown again after saving.' }}" />
                                    <flux:input value="{{ $provider['callbackUrl'] }}" label="Redirect / Callback URL" readonly copyable description="Add this URL to your {{ $provider['label'] }} OAuth client's authorised redirect URIs." />
                                </div>
                            @endunless
                        </div>
                    @endforeach

                    {{-- Allowed email domains (policy — shown on cloud and self-hosted) --}}
                    <div class="space-y-3 border-t pt-6" style="border-color: var(--card-border);" wire:key="sso-allowed-domains">
                        <flux:textarea wire:model="ssoAllowedEmailDomains" label="Allowed email domains"
                            placeholder="example.com&#10;example.co.uk" rows="3"
                            description="One per line (commas and spaces also work). Only users whose email domain is on this list may sign in or be auto-linked via SSO. Leave blank to allow any domain." />
                        <x-signals.alert type="warning" title="Recommended for Microsoft sign-in" class="text-sm">
                            Microsoft is configured with the multitenant <code>common</code> setting, so any work, school, or personal Microsoft account can complete the OAuth flow. Configuring an allow-list of <em>your owned domains</em> is strongly recommended to prevent cross-tenant account takeover. <strong>An empty allow-list permits any email domain.</strong>
                        </x-signals.alert>
                    </div>
                </div>
            </x-signals.form-section>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>
                <x-action-message on="integration-settings-saved">Saved.</x-action-message>
            </div>
        </form>
    </x-admin.layout>
</section>
