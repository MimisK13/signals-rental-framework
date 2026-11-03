
<section class="w-full">
    @include('project-opportunities::partials.opportunity-header', ['opportunity' => $opportunity, 'subpage' => 'Custom Fields', 'showActions' => true, 'canChangeStatus' => $canChangeStatus])
    @include('project-opportunities::partials.opportunity-tabs', ['opportunity' => $opportunity, 'activeTab' => 'custom-fields'])

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        <div class="max-w-2xl space-y-8">
            <x-signals.custom-fields-display :grouped="$grouped" :values="$values" emptyMessage="No custom fields have been configured for opportunities." />
        </div>
    </div>
    @include('project-opportunities::partials.opportunity-action-modals')
</section>
