<section class="w-full">
    @include('livewire.opportunities.partials.opportunity-header', [
        'opportunity' => $opportunity,
        'subpage' => 'Files',
        'showActions' => true,
        'canChangeStatus' => $canChangeStatus,
    ])
    @include('livewire.opportunities.partials.opportunity-tabs', [
        'opportunity' => $opportunity,
        'activeTab' => 'files',
    ])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[var(--text-secondary)]"
                style="font-family: var(--font-display); text-transform: uppercase; letter-spacing: 0.04em;">
                Files ({{ $totalCount }})
            </h3>
            <button x-data x-on:click="$dispatch('open-file-upload')" class="s-btn s-btn-sm s-btn-primary">
                Upload File
            </button>
        </div>

        @include('attachments::file-browser', ['entityLabel' => 'opportunity'])
    </div>

    <livewire:components.file-upload-modal :model-type="\App\Models\Opportunity::class" :model-id="$opportunity->id" />
    @include('livewire.opportunities.partials.opportunity-action-modals')
</section>
