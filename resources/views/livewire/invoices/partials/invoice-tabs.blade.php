<x-signals.module-tabs
    :tabs="[
        ['name' => 'overview', 'label' => 'Overview', 'route' => route('invoices.show', $invoice)],
        ['name' => 'communications', 'label' => 'Communications', 'route' => route('invoices.communications', $invoice)],
    ]"
    :active="$activeTab"
/>
