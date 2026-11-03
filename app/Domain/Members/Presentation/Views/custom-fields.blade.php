<section class="w-full">
    @include('members::partials.member-header', ['member' => $member, 'subpage' => 'Custom Fields'])
    @include('members::partials.member-tabs', ['member' => $member, 'activeTab' => 'custom-fields'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="max-w-2xl space-y-8">
            <x-signals.custom-fields-display :grouped="$grouped" :values="$values" emptyMessage="No custom fields have been configured for members." />
        </div>
    </div>
</section>
