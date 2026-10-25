<aside
    x-show="groupPanelOpen"
    x-cloak
    x-transition:enter="transition-[width,opacity] ease-out duration-150"
    x-transition:enter-start="w-0 opacity-0"
    x-transition:enter-end="w-80 opacity-100"
    x-transition:leave="transition-[width,opacity] ease-in duration-100"
    x-transition:leave-start="w-80 opacity-100"
    x-transition:leave-end="w-0 opacity-0"
    class="w-80 shrink-0 overflow-hidden border-l border-[var(--card-border)] bg-[var(--card-bg)]"
    aria-label="Group selection"
>
    <div class="w-80 p-3">
        <div class="flex items-start justify-between gap-3 border-b border-[var(--card-border)] pb-3">
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-wide text-[var(--text-muted)]">Group</div>
                <div class="truncate text-sm font-medium text-[var(--text-primary)]" x-text="groupPanelRowName"></div>
            </div>
            <button type="button" class="s-btn s-btn-ghost s-btn-xs s-btn-icon" title="Close" @click="closeGroupPanel()">
                <flux:icon.x-mark class="!size-4" />
            </button>
        </div>

        <div class="space-y-1 pt-3">
            <template x-if="!groupPanelOptions.length">
                <p class="text-xs text-[var(--text-muted)]">No other groups available.</p>
            </template>
            <template x-for="group in groupPanelOptions" :key="`group-panel-${group.id}`">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded px-2.5 py-2 text-left text-sm text-[var(--text-primary)] hover:bg-[var(--surface-muted)] disabled:cursor-wait disabled:opacity-60"
                    :disabled="groupPanelPending"
                    @click="assignRowFromGroupPanel(group.id)"
                >
                    <flux:icon.folder class="!size-4 shrink-0 text-[var(--text-muted)]" />
                    <span class="truncate" x-text="group.name"></span>
                </button>
            </template>
        </div>
    </div>
</aside>
