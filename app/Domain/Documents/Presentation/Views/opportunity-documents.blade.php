
<section class="w-full">
    @include('project-opportunities::partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Documents', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('project-opportunities::partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'documents'])

    <div class="flex-1 space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        @if($canCreate)
            <x-dense::card>
                <h3 class="text-sm font-semibold text-[var(--text-secondary)]" style="font-family: var(--font-display); text-transform: uppercase; letter-spacing: 0.04em;">
                    {{ __('Generate document') }}
                </h3>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label class="s-label" for="selectedTemplateId">{{ __('Template') }}</label>
                        <select id="selectedTemplateId" wire:model="selectedTemplateId" class="s-input mt-1 w-full">
                            <option value="">{{ __('Select a template…') }}</option>
                            @foreach($templates as $template)
                                <option wire:key="template-option-{{ $template->id }}" value="{{ $template->id }}">
                                    {{ $template->name }} ({{ $template->document_type }})
                                </option>
                            @endforeach
                        </select>
                        @error('selectedTemplateId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="s-label" for="outputFormat">{{ __('Output') }}</label>
                        <select id="outputFormat" wire:model="outputFormat" class="s-input mt-1 w-full">
                            <option value="pdf">PDF</option>
                            <option value="html_download">HTML</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="button" wire:click="generateDocument" wire:loading.attr="disabled" class="s-btn s-btn-primary">
                        <span wire:loading.remove wire:target="generateDocument">{{ __('Generate') }}</span>
                        <span wire:loading wire:target="generateDocument">{{ __('Generating…') }}</span>
                    </button>
                </div>
            </x-dense::card>
        @endif

        @if($documents->isNotEmpty())
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>{{ __('Number') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Created') }}</th>
                            <th>{{ __('First viewed') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($documents as $document)
                            @php
                                $downloadUrl = $fileService->signedDocumentUrlOrNull($document);
                                $publicUrl = $document->public_token
                                    ? route('documents.public.show', ['token' => $document->public_token])
                                    : null;
                            @endphp
                            <tr wire:key="document-{{ $document->id }}">
                                <td style="font-family: var(--font-mono);">{{ $document->document_number }}</td>
                                <td>{{ $document->document_type }}</td>
                                <td><span class="s-badge s-badge-zinc">{{ ucfirst($document->status->value) }}</span></td>
                                <td>@localdatetime($document->created_at)</td>
                                <td>
                                    @if($document->first_viewed_at)
                                        @localdatetime($document->first_viewed_at)
                                    @else
                                        <span class="text-[var(--text-muted)]">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if($downloadUrl)
                                            <a href="{{ $downloadUrl }}" target="_blank" rel="noopener" class="s-btn s-btn-sm s-btn-outline">
                                                {{ __('Download') }}
                                            </a>
                                        @endif
                                        @if($canShare)
                                            @if($publicUrl)
                                                <div class="flex flex-wrap items-center justify-end gap-2">
                                                    <button type="button"
                                                            x-data="{ copied: false }"
                                                            x-on:click="navigator.clipboard.writeText(@js($publicUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                                                            class="s-btn s-btn-sm s-btn-outline">
                                                        <span x-show="!copied">{{ __('Copy link') }}</span>
                                                        <span x-cloak x-show="copied">{{ __('Copied') }}</span>
                                                    </button>
                                                    <div
                                                        wire:key="document-share-qr-{{ $document->id }}"
                                                        class="inline-flex rounded-lg border border-[var(--border)] bg-white p-1.5"
                                                        title="{{ __('Scan to open public link') }}"
                                                        aria-label="{{ __('QR code for public document link') }}"
                                                    >
                                                        {!! $qrCodeGenerator->generate($publicUrl) !!}
                                                    </div>
                                                </div>
                                                <button type="button" wire:click="openShareModal({{ $document->id }})" class="s-btn s-btn-sm s-btn-outline">
                                                    {{ __('Configure') }}
                                                </button>
                                                <button type="button"
                                                        wire:click="revokeShareLink({{ $document->id }})"
                                                        wire:confirm="{{ __('Revoke this public link?') }}"
                                                        class="s-btn s-btn-sm s-btn-outline-red">
                                                    {{ __('Revoke') }}
                                                </button>
                                            @else
                                                <button type="button" wire:click="openShareModal({{ $document->id }})" class="s-btn s-btn-sm s-btn-outline">
                                                    {{ __('Share') }}
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-signals.table-wrap>
        @else
            <x-dense::empty-state
                title="{{ __('No documents') }}"
                description="{{ __('Generate a document from a template to share or download it.') }}"
            />
        @endif
    </div>

    @if($shareDocumentId)
        @php
            $shareDocument = $documents->firstWhere('id', $shareDocumentId);
            $sharePublicUrl = $shareDocument?->public_token
                ? route('documents.public.show', ['token' => $shareDocument->public_token])
                : null;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <x-dense::card class="w-full max-w-md">
                <h3 class="text-base font-semibold text-[var(--text-primary)]">{{ __('Public link settings') }}</h3>
                @if($sharePublicUrl)
                    <div class="mt-4 flex flex-col items-center gap-3 rounded-lg border border-[var(--border)] bg-[var(--bg-subtle)] p-4 sm:flex-row sm:items-start">
                        <div
                            wire:key="share-modal-qr-{{ $shareDocumentId }}"
                            class="inline-flex shrink-0 rounded-lg border border-[var(--border)] bg-white p-1.5"
                            title="{{ __('Scan to open public link') }}"
                            aria-label="{{ __('QR code for public document link') }}"
                        >
                            {!! $qrCodeGenerator->generate($sharePublicUrl) !!}
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <p class="text-xs font-medium text-[var(--text-secondary)]">{{ __('Public link') }}</p>
                            <p class="break-all text-sm font-mono text-[var(--text-primary)]">{{ $sharePublicUrl }}</p>
                            <button type="button"
                                    x-data="{ copied: false }"
                                    x-on:click="navigator.clipboard.writeText(@js($sharePublicUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="s-btn s-btn-sm s-btn-outline">
                                <span x-show="!copied">{{ __('Copy link') }}</span>
                                <span x-cloak x-show="copied">{{ __('Copied') }}</span>
                            </button>
                        </div>
                    </div>
                @endif
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="s-label" for="shareExpiresAt">{{ __('Expires at (optional)') }}</label>
                        <input id="shareExpiresAt" type="datetime-local" wire:model="shareExpiresAt" class="s-input mt-1 w-full" />
                    </div>
                    <div>
                        <label class="s-label" for="sharePin">{{ __('PIN (optional)') }}</label>
                        <input id="sharePin" type="password" wire:model="sharePin" class="s-input mt-1 w-full" autocomplete="new-password" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancelShareModal" class="s-btn s-btn-outline">{{ __('Cancel') }}</button>
                    <button type="button" wire:click="saveShareLink" class="s-btn s-btn-primary">{{ __('Save link') }}</button>
                </div>
            </x-dense::card>
        </div>
    @endif

    @include('project-opportunities::partials.opportunity-action-modals')
</section>
