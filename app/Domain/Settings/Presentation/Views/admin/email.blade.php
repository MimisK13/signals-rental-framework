<section class="w-full min-w-0">
    <x-admin.layout group="preferences" title="Email" description="Configure how your application sends email.">
        <x-signals.form-section title="Mail Provider">
            <form wire:submit="save" class="space-y-6">
                <flux:select wire:model.live="mailer" label="Mail Driver">
                    <option value="log">Log (development only)</option>
                    <option value="smtp">SMTP</option>
                    <option value="ses">Amazon SES</option>
                    <option value="mailgun">Mailgun</option>
                    <option value="postmark">Postmark</option>
                </flux:select>

                {{-- SMTP Settings --}}
                <div x-show="$wire.mailer === 'smtp'" x-cloak class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="smtpHost" label="SMTP Host" placeholder="smtp.example.com" />
                        <flux:input wire:model="smtpPort" label="SMTP Port" type="number" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="smtpUsername" label="Username" />
                        <flux:input wire:model="smtpPassword" label="Password" type="password" />
                    </div>
                    <flux:select wire:model="smtpEncryption" label="Encryption">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="none">None</option>
                    </flux:select>
                </div>

                {{-- SES Settings --}}
                <div x-show="$wire.mailer === 'ses'" x-cloak class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="sesKey" label="AWS Access Key" />
                        <flux:input wire:model="sesSecret" label="AWS Secret Key" type="password" />
                    </div>
                    <flux:input wire:model="sesRegion" label="AWS Region" placeholder="eu-west-1" />
                </div>

                {{-- Mailgun Settings --}}
                <div x-show="$wire.mailer === 'mailgun'" x-cloak class="space-y-4">
                    <flux:input wire:model="mailgunDomain" label="Mailgun Domain" placeholder="mg.example.com" />
                    <flux:input wire:model="mailgunSecret" label="Mailgun Secret" type="password" />
                </div>

                {{-- Postmark Settings --}}
                <div x-show="$wire.mailer === 'postmark'" x-cloak class="space-y-4">
                    <flux:input wire:model="postmarkToken" label="Postmark Server Token" type="password" />
                </div>

                <hr class="border-[var(--card-border)]" />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="fromAddress" label="From Address" type="email" placeholder="noreply@example.com" />
                    <flux:input wire:model="fromName" label="From Name" placeholder="My Company" />
                </div>

                <flux:input wire:model="replyToAddress" label="Reply-To Address" type="email" placeholder="support@example.com" />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">Save Changes</flux:button>

                    <x-action-message on="email-settings-saved">
                        Saved.
                    </x-action-message>
                </div>
            </form>
        </x-signals.form-section>

        <x-signals.form-section title="Test Email">
            <form wire:submit="sendTestEmail" class="space-y-4">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Send a test email to verify your mail configuration is working correctly. Save your settings first.
                </p>

                <div class="flex items-end gap-4">
                    <div class="flex-1">
                        <flux:input wire:model="testEmailAddress" label="Recipient Email" type="email" placeholder="you@example.com" />
                    </div>
                    <flux:button type="submit" variant="filled">Send Test Email</flux:button>
                </div>

                <x-action-message on="test-email-sent">
                    Test email sent successfully.
                </x-action-message>
            </form>
        </x-signals.form-section>
    </x-admin.layout>
</section>
