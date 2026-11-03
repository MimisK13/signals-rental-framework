<section class="w-full">
    @include('members::partials.member-header', ['member' => $member, 'subpage' => 'Contacts'])
    @include('members::partials.member-tabs', ['member' => $member, 'activeTab' => 'contacts'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Domain\Members\Models\Member::class"
            :searchable="['name']"
            :with="['emails', 'phones']"
            :scopes="$scopes"
            default-sort="name"
            :empty-message="'No ' . $label . ' found.'"
            actions-view="members::partials.row-actions"
            :key="'member-contacts-' . $member->id"
        />
    </div>
</section>
