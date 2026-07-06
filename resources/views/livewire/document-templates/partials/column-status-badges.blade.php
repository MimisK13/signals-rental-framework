<div class="flex flex-wrap gap-1">
    @if($item->is_default)
        <span class="s-badge s-badge-green">Default</span>
    @endif
    @if($item->is_system)
        <span class="s-badge">System</span>
    @endif
    @if($item->is_active)
        <span class="s-badge s-badge-blue">Active</span>
    @else
        <span class="s-badge">Inactive</span>
    @endif
</div>
