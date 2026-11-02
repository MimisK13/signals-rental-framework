<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member, 'subpage' => 'Files'])
    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'files'])

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

        @include('attachments::file-browser', ['entityLabel' => 'member'])
    </div>

    <livewire:components.file-upload-modal :model-type="\App\Models\Member::class" :model-id="$member->id" />
</section>
