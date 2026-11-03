<section class="w-full min-w-0">
    <x-admin.layout group="system" title="Database Seeders" description="View seeder status and populate the database with default or demo data.">
        @php
            // The bulk button runs the two core access-control seeders. Other defaults may already
            // be populated by migrations or can be run individually below.
            $coreSeeded = ($seeders['permissions']['seeded'] ?? false) && ($seeders['roles']['seeded'] ?? false);
        @endphp

        <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
            <p class="text-sm text-zinc-500">
                Run the core PermissionSeeder and RoleSeeder together, or run any other seeder individually below.
            </p>
            @unless($coreSeeded)
                <flux:button wire:click="seedAll" wire:confirm="Run the core PermissionSeeder and RoleSeeder?">
                    Run Default Seeders
                </flux:button>
            @endunless
        </div>

        <div class="space-y-4">
            @foreach($seeders as $key => $seeder)
                <div wire:key="seeder-{{ $key }}" class="s-card" x-data="{ open: {{ $seeder['seeded'] ? 'false' : 'true' }} }">
                    <div class="s-card-body flex flex-col items-start justify-between gap-4 sm:flex-row">
                        <div class="min-w-0 flex-1">
                            <button type="button" @click="open = !open" class="flex items-center gap-2 mb-1 text-left w-full">
                                <flux:icon.chevron-right class="size-4 text-zinc-400 transition-transform" ::class="open && 'rotate-90'" />
                                <h3 class="text-sm font-medium text-zinc-900 dark:text-white">{{ $seeder['name'] }}</h3>
                                @if($seeder['seeded'])
                                    <span class="s-status s-status-green">Seeded</span>
                                @else
                                    <span class="s-status s-status-zinc">Not Seeded</span>
                                @endif
                            </button>
                            <div x-show="open" x-collapse>
                                <p class="text-sm text-zinc-500 mb-2 ml-6">{{ $seeder['description'] }}</p>
                                <ul class="text-xs text-zinc-400 space-y-0.5 list-disc list-inside ml-6">
                                    @foreach($seeder['items'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <flux:button size="sm" variant="{{ $seeder['seeded'] ? 'ghost' : 'filled' }}" wire:click="seed('{{ $key }}')" wire:confirm="Run {{ $seeder['name'] }}?">
                            {{ $seeder['seeded'] ? 'Re-run' : 'Run' }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        @error('seeder') <p class="text-sm text-red-600 mt-4">{{ $message }}</p> @enderror

        <x-action-message class="mt-4" on="seeder-completed">
            Seeder completed successfully.
        </x-action-message>
    </x-admin.layout>
</section>
