<x-dense::status
    variant="outline"
    :color="match($item->status->value) { 'active' => 'green', 'completed' => 'blue', 'archived' => 'zinc', default => 'amber' }"
    :label="$item->status->label()"
/>
