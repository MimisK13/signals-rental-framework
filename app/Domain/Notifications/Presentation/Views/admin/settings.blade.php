<section class="w-full">
    <x-admin.layout group="preferences" title="Notifications" description="Configure channel providers, notification types, recipients, and communication templates.">
        <x-slot:actions>
            <a href="{{ route('admin.settings.communication-log') }}" wire:navigate.hover class="s-btn s-btn-ghost s-btn-sm">{{ __('Communication log') }}</a>
        </x-slot:actions>
        {{-- Channel Providers --}}
        <x-dense::card>
            <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Channel Providers</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Delivery drivers registered for this installation.</p>
                </div>
            </div>
            <div class="p-4">
                <div class="flex flex-wrap gap-3">
                    @foreach ($drivers as $driver)
                        @php
                            $status = $this->providerStatus($driver->key);
                        @endphp
                        <div wire:key="provider-{{ $driver->key }}" class="s-card p-4 min-w-[220px] flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $driver->label }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $channelLabels[$driver->channel] ?? ucfirst($driver->channel) }}</div>
                                </div>
                                @if ($status === 'passed')
                                    <span class="s-badge bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Tested</span>
                                @elseif ($status === 'failed')
                                    <span class="s-badge bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">Failing</span>
                                @else
                                    <span class="s-badge bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">Untested</span>
                                @endif
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @if (count($driver->configSchema) > 0 || $driver->key === 'email')
                                    <button type="button" wire:click="openProviderConfig('{{ $driver->key }}')" class="s-btn s-btn-ghost s-btn-sm" wire:loading.attr="disabled">
                                        Configure
                                    </button>
                                @endif
                                <button type="button" wire:click="sendProviderTest('{{ $driver->key }}')" class="s-btn s-btn-ghost s-btn-sm" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="sendProviderTest('{{ $driver->key }}')">Send test</span>
                                    <span wire:loading wire:target="sendProviderTest('{{ $driver->key }}')">Testing…</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-dense::card>

        {{-- Communication log & unsubscribe settings --}}
        <x-dense::card>
            <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Communication log &amp; unsubscribe</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Retention for delivery history and signed unsubscribe link lifetime.</p>
            </div>
            <form wire:submit="saveNotificationSettings" class="p-4 space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <flux:input
                        wire:model="communicationLogRetentionDays"
                        label="Log retention (days)"
                        type="number"
                        min="30"
                        max="3650"
                        description="Terminal delivery rows are removed after this many days."
                    />
                    <flux:input
                        wire:model="communicationLogPruneSafetyDays"
                        label="Prune safety window (days)"
                        type="number"
                        min="1"
                        max="90"
                        description="Extra days beyond retention before in-flight (non-terminal) rows are removed."
                    />
                    <flux:input
                        wire:model="unsubscribeUrlTtlDays"
                        label="Unsubscribe link lifetime (days)"
                        type="number"
                        min="1"
                        max="3650"
                        description="How long newly generated one-click unsubscribe links remain valid."
                    />
                </div>
                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled">Save settings</flux:button>
                    <x-action-message on="notification-settings-saved">Saved.</x-action-message>
                </div>
            </form>
        </x-dense::card>

        {{-- Notification Types --}}
        @if ($grouped->isEmpty())
            <div class="s-card p-8 text-center text-zinc-500 dark:text-zinc-400">
                <p>No notification types found. Run database seeders to create default notification types.</p>
            </div>
        @else
            @foreach ($grouped as $category => $types)
                <div wire:key="category-{{ $category }}" class="s-card mb-4">
                    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $category }}</h3>
                    </div>

                    <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($types as $type)
                            @php
                                $setting = $type->setting;
                                $isEnabled = ! $setting || $setting->is_enabled;
                                $audiences = $type->audience === \App\Domain\Notifications\Enums\NotificationAudience::Both
                                    ? ['internal' => 'Internal', 'external' => 'External']
                                    : ['default' => ''];
                            @endphp
                            <div wire:key="type-{{ $type->id }}" class="p-4 space-y-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-sm">{{ $type->name }}</span>
                                            @if ($type->is_system)
                                                <span class="s-badge bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">Required</span>
                                            @endif
                                            <span class="s-badge bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">{{ $type->audience->value }}</span>
                                        </div>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ $type->description }}</p>
                                    </div>
                                    <flux:switch
                                        wire:click="toggleEnabled({{ $type->id }})"
                                        :checked="$isEnabled"
                                        :disabled="$type->is_system"
                                    />
                                </div>

                                @foreach ($audiences as $audienceKey => $audienceLabel)
                                    @php
                                        $effectiveChannels = $this->effectiveChannelsForType($type, $setting, $audienceKey);
                                        $recipientRules = $this->recipientRulesForType($type, $setting, $audienceKey);
                                        $recipientSummary = collect($recipientRules)->pluck('type')->join(', ') ?: 'None';
                                    @endphp
                                    <div wire:key="type-{{ $type->id }}-{{ $audienceKey }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 space-y-3">
                                        @if ($audienceLabel !== '')
                                            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $audienceLabel }}</div>
                                        @endif

                                        <div class="flex flex-wrap gap-4">
                                            @foreach ($type->available_channels as $channel)
                                                @php
                                                    $canEnable = in_array($channel, $type->default_channels, true);
                                                    $isOn = in_array($channel, $effectiveChannels, true);
                                                @endphp
                                                <div class="flex items-center gap-2" wire:key="channel-{{ $type->id }}-{{ $audienceKey }}-{{ $channel }}">
                                                    <flux:switch
                                                        wire:click="toggleChannel({{ $type->id }}, '{{ $channel }}', '{{ $audienceKey }}')"
                                                        :checked="$isOn"
                                                        :disabled="$type->is_system || ! $isEnabled || (! $isOn && ! $canEnable)"
                                                    />
                                                    <span class="text-sm {{ ! $canEnable && ! $isOn ? 'text-zinc-400' : '' }}">
                                                        {{ $channelLabels[$channel] ?? ucfirst($channel) }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="flex flex-wrap items-center gap-3 text-sm">
                                            <span class="text-zinc-500 dark:text-zinc-400">Recipients:</span>
                                            <span class="text-zinc-700 dark:text-zinc-300">{{ $recipientSummary }}</span>
                                            <button type="button" class="s-btn s-btn-ghost s-btn-sm" wire:click="openRecipientEditor({{ $type->id }}, '{{ $audienceKey }}')">
                                                Edit rules
                                            </button>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 text-sm">
                                            <span class="text-zinc-500 dark:text-zinc-400">Templates:</span>
                                            @forelse ($this->templatesForType($type, 'mail') as $template)
                                                <a href="{{ route('admin.settings.communication-templates.edit', $template) }}" wire:navigate.hover class="s-btn s-btn-ghost s-btn-sm" wire:key="tpl-{{ $template->id }}">
                                                    {{ $template->name }}
                                                </a>
                                            @empty
                                                <span class="text-zinc-400 text-xs">No templates</span>
                                            @endforelse
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif

        {{-- Provider config slide-over --}}
        <flux:modal wire:model="showProviderModal" class="max-w-lg">
            <flux:heading size="lg">Configure provider</flux:heading>
            <div class="mt-4 space-y-4">
                <flux:input wire:model="providerName" label="Provider name" />

                @if ($editingDriverKey !== '')
                    @php
                        $editingDriver = collect($drivers)->firstWhere('key', $editingDriverKey);
                    @endphp
                    @if ($editingDriver)
                        @foreach ($editingDriver->configSchema as $field)
                            <div wire:key="cfg-{{ $field->key }}">
                                @if ($field->type === 'password')
                                    <flux:input wire:model="providerConfig.{{ $field->key }}" :label="$field->label" type="password" />
                                @elseif ($field->type === 'integer')
                                    <flux:input wire:model="providerConfig.{{ $field->key }}" :label="$field->label" type="number" />
                                @elseif ($field->type === 'select' && $field->key === 'mailer')
                                    <flux:select wire:model="providerConfig.{{ $field->key }}" :label="$field->label">
                                        <option value="log">Log (development)</option>
                                        <option value="smtp">SMTP</option>
                                        <option value="ses">Amazon SES</option>
                                        <option value="mailgun">Mailgun</option>
                                        <option value="postmark">Postmark</option>
                                    </flux:select>
                                @elseif ($field->type === 'select' && $field->key === 'smtp_encryption')
                                    <flux:select wire:model="providerConfig.{{ $field->key }}" :label="$field->label">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="none">None</option>
                                    </flux:select>
                                @else
                                    <flux:input wire:model="providerConfig.{{ $field->key }}" :label="$field->label" />
                                @endif
                            </div>
                        @endforeach
                    @endif
                @endif

                <flux:input wire:model="testRecipient" label="Test recipient (email channels)" type="email" />

                @if ($providerTestMessage)
                    <div class="text-sm {{ $providerTestSuccess ? 'text-emerald-600' : 'text-red-600' }}">
                        {{ $providerTestMessage }}
                    </div>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showProviderModal', false)">Cancel</flux:button>
                    <flux:button variant="ghost" wire:click="sendProviderTest('{{ $editingDriverKey }}')" wire:loading.attr="disabled">Send test</flux:button>
                    <flux:button variant="primary" wire:click="saveProviderConfig" wire:loading.attr="disabled">Save</flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Recipient rules editor --}}
        <flux:modal wire:model="showRecipientModal" class="max-w-xl">
            <flux:heading size="lg">Recipient rules</flux:heading>
            <div class="mt-4 space-y-4">
                @foreach ($editingRecipientRules as $index => $rule)
                    <div wire:key="rule-{{ $index }}" class="flex items-start gap-2 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <div class="flex-1 space-y-2">
                            <div class="text-sm font-medium">{{ str_replace('_', ' ', ucfirst($rule['type'] ?? '')) }}</div>
                            @if (($rule['type'] ?? '') === 'role')
                                <flux:select wire:model="editingRecipientRules.{{ $index }}.role" label="Role">
                                    <option value="">Select role…</option>
                                    @foreach ($roles as $roleName => $label)
                                        <option value="{{ $roleName }}">{{ $label }}</option>
                                    @endforeach
                                </flux:select>
                            @endif
                            @if (($rule['type'] ?? '') === 'specific_member')
                                <flux:select wire:model="editingRecipientRules.{{ $index }}.member_id" label="Member">
                                    <option value="">Select member…</option>
                                    @foreach ($members as $member)
                                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                                    @endforeach
                                </flux:select>
                            @endif
                        </div>
                        <button type="button" class="s-btn s-btn-ghost s-btn-sm text-red-600" wire:click="removeRecipientRule({{ $index }})">Remove</button>
                    </div>
                @endforeach

                <div class="flex flex-wrap gap-2">
                    @foreach ($recipientTypes as $ruleType)
                        <button type="button" wire:key="add-{{ $ruleType }}" class="s-btn s-btn-ghost s-btn-sm" wire:click="addRecipientRule('{{ $ruleType }}')">
                            + {{ str_replace('_', ' ', $ruleType) }}
                        </button>
                    @endforeach
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showRecipientModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" wire:click="saveRecipientRules" wire:loading.attr="disabled">Save rules</flux:button>
                </div>
            </div>
        </flux:modal>
    </x-admin.layout>
</section>
