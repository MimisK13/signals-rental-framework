<section class="w-full">
    <x-admin.layout group="preferences" :wide="true" title="{{ $template->name }}" description="Edit communication template content and preview with live merge fields.">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Notifications', 'href' => route('admin.settings.notifications')],
                ['label' => 'Edit template'],
            ]" />
        </x-slot:breadcrumbs>

        <x-slot:actions>
            @if ($template->is_system)
                <flux:button variant="primary" wire:click="duplicateTemplate" wire:loading.attr="disabled">Duplicate to customise</flux:button>
            @else
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Save</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </flux:button>
            @endif
        </x-slot:actions>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            {{-- Editor + field browser --}}
            <div class="xl:col-span-2 space-y-4">
                <x-dense::card>
                    <div class="p-4 space-y-4">
                        @if ($template->channel === 'email')
                            <flux:input wire:model.live.debounce.400ms="subject" label="Subject" :disabled="$template->is_system" />
                        @endif

                        <flux:textarea wire:model.live.debounce.400ms="body" label="Body (Markdown)" rows="14" :disabled="$template->is_system" />

                        @if ($template->channel === 'sms')
                            @php $sms = $this->smsStats(); @endphp
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $sms['characters'] }} characters · {{ $sms['segments'] }} segment(s)
                                @if ($sms['segments'] > 1)
                                    ({{ $sms['per_segment'] }} chars per concatenated segment)
                                @else
                                    (160 GSM-7 chars per segment)
                                @endif
                            </p>
                        @endif

                        @if ($template->is_system)
                            <p class="text-sm text-amber-600 dark:text-amber-400">System templates are read-only. Duplicate to create an editable copy.</p>
                        @endif
                    </div>
                </x-dense::card>

                <x-dense::card>
                    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                        <h3 class="text-sm font-semibold">Merge field browser</h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <flux:input wire:model.live.debounce.300ms="fieldSearch" placeholder="Search fields…" />
                        @forelse ($fieldGroups as $group)
                            <div wire:key="group-{{ $group['label'] }}">
                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 mb-2">{{ $group['label'] }}</div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($group['fields'] as $field)
                                        <button
                                            type="button"
                                            wire:key="field-{{ $field['path'] }}"
                                            wire:click="insertField('{{ $field['path'] }}')"
                                            class="s-btn s-btn-ghost s-btn-sm"
                                            @disabled($template->is_system)
                                        >
                                            {{ $field['label'] ?? $field['path'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500">No merge fields defined for this notification type.</p>
                        @endforelse
                    </div>
                </x-dense::card>

                @if ($versions->isNotEmpty())
                    <x-dense::card>
                        <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700">
                            <h3 class="text-sm font-semibold">Version history</h3>
                        </div>
                        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($versions as $version)
                                <div wire:key="version-{{ $version->id }}" class="p-3 flex items-center justify-between gap-3 text-sm">
                                    <div>
                                        <span class="font-medium">v{{ $version->version }}</span>
                                        @if ($version->change_note)
                                            <span class="text-zinc-500">— {{ $version->change_note }}</span>
                                        @endif
                                    </div>
                                    @unless ($template->is_system)
                                        <button type="button" class="s-btn s-btn-ghost s-btn-sm" wire:click="restoreVersion({{ $version->id }})">Restore</button>
                                    @endunless
                                </div>
                            @endforeach
                        </div>
                    </x-dense::card>
                @endif
            </div>

            {{-- Live preview --}}
            <div class="space-y-4">
                <x-dense::card>
                    <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                        <h3 class="text-sm font-semibold">Live preview</h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <flux:select wire:model.live="previewMode" label="Preview data">
                            <flux:select.option value="sample">Sample data</flux:select.option>
                            <flux:select.option value="entity">Real entity</flux:select.option>
                        </flux:select>

                        @if ($previewMode === 'entity')
                            <flux:select wire:model.live="sourceOpportunityId" label="Preview with opportunity">
                                <flux:select.option value="">Select opportunity…</flux:select.option>
                                @foreach ($opportunityOptions as $option)
                                    <flux:select.option :value="$option['id']">{{ $option['label'] }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        @if ($template->channel === 'email' && $previewSubject !== '')
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $previewSubject }}</div>
                        @endif

                        <div class="prose prose-sm dark:prose-invert max-w-none rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 bg-zinc-50 dark:bg-zinc-900/40">
                            @if ($template->channel === 'email')
                                {!! $previewHtml !!}
                            @else
                                <pre class="whitespace-pre-wrap text-sm">{{ $previewBody }}</pre>
                            @endif
                        </div>
                    </div>
                </x-dense::card>
            </div>
        </div>
    </x-admin.layout>
</section>
