{{--
    Shared multi-store chip picker for planning filter rows.
    Expects selectedStoreTags(), storeNameSuggestions(), syncStoreIdsFromTags() on the host.
--}}
<div class="flex flex-col gap-1 overflow-visible">
    <x-dense::field-label>
        {{ __('Stores') }}
    </x-dense::field-label>

    <x-dense::store-picker
        wire:key="store-tags-{{ md5(json_encode($this->selectedStoreTags())) }}"
        :value="$this->selectedStoreTags()"
        :suggestions="$this->storeNameSuggestions()"
        placeholder="{{ __('All stores') }}"
        class="s-planning-filter-tag-input min-w-52"
        x-on:tags-changed="$wire.syncStoreIdsFromTags($event.detail)"
    />
</div>
