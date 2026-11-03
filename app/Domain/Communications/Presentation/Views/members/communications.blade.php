<section class="w-full">
    @include('members::partials.member-header', ['member' => $member])
    @include('members::partials.member-tabs', ['member' => $member, 'activeTab' => 'communications'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        @include('communications::partials.communication-log-table', [
            'logs' => $logs,
            'statuses' => $statuses,
        ])

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</section>
