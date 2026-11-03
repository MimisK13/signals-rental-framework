<section class="w-full">
    @include('members::partials.member-header', ['member' => $member, 'subpage' => 'Opportunities'])
    @include('members::partials.member-tabs', ['member' => $member, 'activeTab' => 'opportunities'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Domain\Projects\Opportunities\Models\Opportunity::class"
            :searchable="['subject', 'number', 'reference']"
            :with="['member', 'store']"
            :scopes="$scopes"
            default-sort="-created_at"
            empty-message="No opportunities for this member yet."
            actions-view="members::partials.opportunity-row-actions"
            :key="'member-opportunities-' . $member->id"
        />
    </div>
</section>
