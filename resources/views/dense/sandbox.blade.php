<x-layouts.app :title="__('Dense UI Sandbox')">
    <main data-density="condensed" class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
        <x-dense::page-header title="Dense UI foundation">
            <x-slot:meta>packages/ui/dense · dense:: namespace · all available components</x-slot:meta>
            <x-slot:actions>
                <x-dense::button size="sm" variant="ghost">Component sandbox</x-dense::button>
            </x-slot:actions>
        </x-dense::page-header>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="dense-panel">
                <div class="dense-panel-header"><span class="dense-panel-title">Buttons</span></div>
                <div class="dense-panel-body flex flex-col gap-3">
                <p class="text-xs text-zinc-500">Variants, sizes and grouped actions.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <x-dense::button>Primary</x-dense::button>
                    <x-dense::button variant="secondary">Secondary</x-dense::button>
                    <x-dense::button variant="danger">Danger</x-dense::button>
                    <x-dense::button variant="ghost">Ghost</x-dense::button>
                    <x-dense::button size="sm">Small</x-dense::button>
                    <x-dense::button size="lg">Large</x-dense::button>
                </div>
                <x-dense::button-group>
                    <x-dense::button variant="secondary" size="sm">Day</x-dense::button>
                    <x-dense::button variant="secondary" size="sm">Week</x-dense::button>
                    <x-dense::button variant="secondary" size="sm">Month</x-dense::button>
                </x-dense::button-group>
                <div class="flex items-center gap-2">
                    <x-dense::icon-button icon="plus" label="Add" />
                    <x-dense::icon-button icon="x-mark" label="Close" variant="danger" />
                    <span class="text-sm text-zinc-500">Icon buttons</span>
                </div>
                </div>
            </div>

            <div class="dense-panel">
                <div class="dense-panel-header"><span class="dense-panel-title">Form controls</span></div>
                <div class="dense-panel-body flex flex-col gap-3">
                <p class="text-xs text-zinc-500">Inputs remain native and attribute-friendly.</p>
                <x-dense::field>
                    <x-dense::label for="dense-name">Name</x-dense::label>
                    <x-dense::input id="dense-name" placeholder="Jane Smith" />
                </x-dense::field>
                <x-dense::field>
                    <x-dense::label for="dense-notes">Notes</x-dense::label>
                    <x-dense::textarea id="dense-notes" rows="3" placeholder="Add a note…" />
                </x-dense::field>
                <x-dense::field>
                    <x-dense::label for="dense-status">Status</x-dense::label>
                    <x-dense::select id="dense-status">
                        <option>Draft</option>
                        <option selected>Active</option>
                        <option>Archived</option>
                    </x-dense::select>
                </x-dense::field>
                </div>
            </div>

            <div class="dense-panel">
                <div class="dense-panel-header"><span class="dense-panel-title">Selection controls</span></div>
                <div class="dense-panel-body flex flex-col gap-3">
                <p class="text-xs text-zinc-500">Native checkbox, radio and switch primitives.</p>
                <x-dense::fieldset>
                    <x-dense::label>Notification preferences</x-dense::label>
                    <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                        <x-dense::checkbox checked /> Email notifications
                    </label>
                    <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                        <x-dense::checkbox /> SMS notifications
                    </label>
                </x-dense::fieldset>
                <x-dense::fieldset>
                    <x-dense::label>Density</x-dense::label>
                    <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                        <x-dense::radio name="density" value="comfortable" checked /> Comfortable
                    </label>
                    <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                        <x-dense::radio name="density" value="compact" /> Compact
                    </label>
                </x-dense::fieldset>
                <label class="flex items-center gap-3 text-sm text-zinc-700 dark:text-zinc-300">
                    <x-dense::switch :on="true" /> Enable live updates
                </label>
                </div>
            </div>

            <div class="dense-panel">
                <div class="dense-panel-header"><span class="dense-panel-title">Labels and status</span></div>
                <div class="dense-panel-body flex flex-col gap-3">
                <p class="text-xs text-zinc-500">Compact metadata primitives.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <x-dense::badge>Draft</x-dense::badge>
                    <x-dense::badge color="green">Active</x-dense::badge>
                    <x-dense::badge color="red">Blocked</x-dense::badge>
                    <x-dense::badge color="amber">Pending</x-dense::badge>
                    <x-dense::badge color="blue" variant="pill">Scheduled</x-dense::badge>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-dense::status color="green" label="Available" />
                    <x-dense::status color="amber" label="Awaiting inspection" />
                    <x-dense::status color="red" label="Unavailable" />
                </div>
                <div class="flex items-center gap-3">
                    <x-dense::icon name="check"><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-7.25 9a.75.75 0 0 1-1.127.075l-4.25-4.25a.75.75 0 0 1 1.06-1.06l3.66 3.659 6.72-8.344a.75.75 0 0 1 1.044-.132Z" clip-rule="evenodd" /></svg></x-dense::icon>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Slot-based icon content with a semantic name.</span>
                </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <x-dense::card title="Cards and empty states">
                <div class="flex flex-col gap-4">
                    <p class="text-xs text-zinc-500">Generic card containers and empty-state presentation.</p>
                    <x-dense::empty-state title="No reservations yet" description="New reservations will appear here." />
                </div>
            </x-dense::card>

            <x-dense::table-card title="Table card" count="3" count-class="badge-amber">
                <x-dense::table compact>
                    <thead><tr><th>Item</th><th>Status</th><th class="text-right">Qty</th></tr></thead>
                    <tbody>
                        <tr><td>LED panel</td><td><x-dense::status color="green" label="Ready" /></td><td class="text-right">12</td></tr>
                        <tr><td>Wireless kit</td><td><x-dense::status color="amber" label="Review" /></td><td class="text-right">4</td></tr>
                    </tbody>
                </x-dense::table>
            </x-dense::table-card>

            <x-dense::card title="Table variants">
                <x-dense::table striped bordered compact>
                    <thead><tr><th>Reference</th><th>Type</th><th>State</th></tr></thead>
                    <tbody>
                        <tr><td class="font-mono">EQ-001</td><td>Serialized</td><td>Available</td></tr>
                        <tr><td class="font-mono">EQ-002</td><td>Bulk</td><td>Outstanding</td></tr>
                    </tbody>
                </x-dense::table>
            </x-dense::card>

            <x-dense::card title="Toolbar">
                <x-dense::toolbar class="flex-wrap">
                    <x-dense::search-input placeholder="Search components" />
                    <x-dense::button size="sm">Filter</x-dense::button>
                    <x-dense::button size="sm" variant="ghost">Reset</x-dense::button>
                </x-dense::toolbar>
            </x-dense::card>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <x-dense::card title="Date and store controls">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1">
                        <x-dense::field-label for="sandbox-store">Store select</x-dense::field-label>
                        <x-dense::store-select id="sandbox-store" :options="[['value' => 'athens', 'label' => 'Athens'], ['value' => 'thessaloniki', 'label' => 'Thessaloniki']]" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <x-dense::field-label for="sandbox-picker">Store picker</x-dense::field-label>
                        <x-dense::store-picker id="sandbox-picker" :suggestions="['Athens', 'Thessaloniki', 'Patras']" single />
                    </div>
                    <div class="flex flex-col gap-1">
                        <x-dense::field-label>Date picker</x-dense::field-label>
                        <x-dense::date-picker placeholder="Pick a date" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <x-dense::field-label>Calendar</x-dense::field-label>
                        <x-dense::calendar />
                    </div>
                </div>
            </x-dense::card>

            <x-dense::card title="Primitive composition">
                <div class="flex flex-col gap-4">
                    <x-dense::field label="Email" help="We'll never share your email." required>
                        <x-dense::input type="email" placeholder="operator@example.com" autocomplete="email" />
                    </x-dense::field>
                    <x-dense::fieldset>
                        <legend>Delivery mode</legend>
                        <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300"><x-dense::radio name="delivery" value="standard" checked /> Standard</label>
                        <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300"><x-dense::radio name="delivery" value="express" /> Express</label>
                    </x-dense::fieldset>
                </div>
            </x-dense::card>
        </section>
    </main>
</x-layouts.app>
