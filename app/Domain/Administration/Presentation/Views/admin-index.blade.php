<section class="w-full min-w-0">
    <div class="s-admin-layout" style="display: block;">
        <div class="s-admin-main">
            <div class="s-admin-section-header">
                <h1 class="s-admin-section-title">System Administration</h1>
                <p class="s-admin-section-desc">Manage your application settings, users, and system configuration.</p>
            </div>

            <div class="space-y-8">
                @foreach($this->sections as $key => $section)
                    <div>
                        <div class="mb-3">
                            <h2 class="text-sm font-semibold text-[var(--text-primary)]">{{ $section['title'] }}</h2>
                            <p class="text-xs text-[var(--text-secondary)] mt-0.5">{{ $section['description'] }}</p>
                        </div>
                        <div class="s-admin-landing-grid">
                            @foreach($section['items'] as $item)
                                @if(isset($item['gate']))
                                    @can($item['gate'])
                                        <a href="{{ route($item['route']) }}" wire:navigate.hover class="s-module-card enabled">
                                            <div class="s-module-icon">
                                                <flux:icon :name="$item['icon']" class="!size-5" />
                                            </div>
                                            <div class="s-module-info">
                                                <div class="s-module-name">{{ $item['label'] }}</div>
                                                <div class="s-module-desc">{{ $item['description'] }}</div>
                                            </div>
                                            <flux:icon.chevron-right class="!size-4 text-[var(--text-muted)]" />
                                        </a>
                                    @endcan
                                @else
                                    <a href="{{ route($item['route']) }}" wire:navigate.hover class="s-module-card enabled">
                                        <div class="s-module-icon">
                                            <flux:icon :name="$item['icon']" class="!size-5" />
                                        </div>
                                        <div class="s-module-info">
                                            <div class="s-module-name">{{ $item['label'] }}</div>
                                            <div class="s-module-desc">{{ $item['description'] }}</div>
                                        </div>
                                        <flux:icon.chevron-right class="!size-4 text-[var(--text-muted)]" />
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
