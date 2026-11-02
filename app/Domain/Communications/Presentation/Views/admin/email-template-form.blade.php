<section class="w-full">
    <x-admin.layout group="preferences" title="Edit Template" description="Edit the {{ $template->name }} email template.">
        <x-slot:breadcrumbs>
            <x-signals.breadcrumb :items="[
                ['label' => 'Email Templates', 'href' => route('admin.settings.email-templates')],
                ['label' => 'Edit'],
            ]" />
        </x-slot:breadcrumbs>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Editor --}}
            <div class="lg:col-span-2 space-y-6">
                <form wire:submit="save" class="space-y-6">
                    <x-signals.form-section title="Template Content">
                        <div class="space-y-4">
                            <flux:input wire:model="subject" label="Subject Line" required />

                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Body (Markdown)</label>
                                <textarea
                                    wire:model="bodyMarkdown"
                                    rows="16"
                                    class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-sm font-mono p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:text-zinc-100"
                                    required
                                ></textarea>
                            </div>
                        </div>
                    </x-signals.form-section>

                    <div class="flex items-center gap-4">
                        <flux:button variant="primary" type="submit">Save Changes</flux:button>

                        <flux:button wire:click.prevent="preview" variant="ghost">Preview</flux:button>

                        @if ($template->is_system)
                            <flux:button wire:click.prevent="resetToDefault" variant="ghost" wire:confirm="Reset this template to its default content? Your changes will be lost.">
                                Reset to Default
                            </flux:button>
                        @endif

                        <x-action-message on="email-template-saved">Saved.</x-action-message>
                        <x-action-message on="email-template-reset">Reset to default.</x-action-message>
                    </div>
                </form>

                {{-- Preview --}}
                @if ($previewHtml)
                    <x-signals.form-section title="Preview">
                        <iframe
                            class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white"
                            style="height: 600px;"
                            srcdoc="{{ $previewHtml }}"
                            title="Email preview"
                        ></iframe>
                    </x-signals.form-section>
                @endif
            </div>

            {{-- Merge Fields Reference --}}
            <div>
                <x-signals.form-section title="Merge Fields">
                    <div class="space-y-2">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-3">
                            Use <code class="text-xs">@{{ field.path }}</code> syntax to insert dynamic content.
                        </p>
                        @if ($template->available_merge_fields)
                            @foreach ($template->available_merge_fields as $field)
                                <div class="flex items-center gap-2 text-xs">
                                    <code class="bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded font-mono text-zinc-700 dark:text-zinc-300">@{{ {{ $field }} }}</code>
                                </div>
                            @endforeach
                        @else
                            <p class="text-xs text-zinc-400">No merge fields available.</p>
                        @endif

                        <div class="border-t border-zinc-200 dark:border-zinc-700 mt-4 pt-4">
                            <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-2">Filters</p>
                            <div class="space-y-1 text-xs text-zinc-500">
                                <div><code>| upper</code> — uppercase</div>
                                <div><code>| lower</code> — lowercase</div>
                                <div><code>| default:"text"</code> — fallback value</div>
                            </div>
                        </div>
                    </div>
                </x-signals.form-section>

                @if ($template->description)
                    <div class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $template->description }}
                    </div>
                @endif
            </div>
        </div>
    </x-admin.layout>
</section>
