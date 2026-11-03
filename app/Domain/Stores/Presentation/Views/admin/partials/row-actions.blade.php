<div
    x-data="{
        open: false,
        toggle() {
            if (this.open) return this.close($refs.button)
            this.$refs.button.focus()
            this.open = true
        },
        close(focusAfter) {
            if (! this.open) return
            this.open = false
            focusAfter && focusAfter.focus()
        },
    }"
    x-on:keydown.escape.prevent.stop="close($refs.button)"
    x-on:focusin.window="! $refs.panel.contains($event.target) && close()"
    x-id="['store-row-actions']"
    class="relative inline-flex"
>
    <button
        x-ref="button"
        x-on:click="toggle()"
        :aria-expanded="open"
        :aria-controls="$id('store-row-actions')"
        type="button"
        class="s-btn-ghost s-btn-xs"
    >
        <span class="sr-only">Actions</span>
        <svg viewBox="0 0 20 20" fill="currentColor" class="size-5" aria-hidden="true">
            <path d="M3 10a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Zm5.5 0a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Zm7-1.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z" />
        </svg>
    </button>

    <div
        x-ref="panel"
        x-show="open"
        x-transition.origin.top.right
        x-on:click.outside="close($refs.button)"
        :id="$id('store-row-actions')"
        x-cloak
        class="absolute right-0 z-20 mt-2 min-w-36 origin-top-right rounded-lg border border-[var(--card-border)] bg-[var(--card)] p-1.5 shadow-lg"
    >
        @unless($store->is_default)
            <button
                type="button"
                wire:click="setDefault({{ $store->id }})"
                x-on:click="close($refs.button)"
                class="flex w-full items-center rounded-md px-2 py-2 text-left text-sm hover:bg-[var(--surface-hover)] focus-visible:bg-[var(--surface-hover)]"
            >
                Set Default
            </button>
        @endunless
        <button
            type="button"
            wire:click="openEditModal({{ $store->id }})"
            x-on:click="close($refs.button)"
            class="flex w-full items-center rounded-md px-2 py-2 text-left text-sm hover:bg-[var(--surface-hover)] focus-visible:bg-[var(--surface-hover)]"
        >
            Edit
        </button>
        @unless($store->is_default)
            <button
                type="button"
                wire:click="deleteStore({{ $store->id }})"
                wire:confirm="Are you sure you want to delete this store?"
                class="flex w-full items-center rounded-md px-2 py-2 text-left text-sm text-[var(--red)] hover:bg-red-50 focus-visible:bg-red-50 dark:hover:bg-red-950/30 dark:focus-visible:bg-red-950/30"
            >
                Delete
            </button>
        @endunless
    </div>
</div>
