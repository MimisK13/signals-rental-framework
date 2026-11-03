<section class="w-full min-w-0">
    <x-api::layout title="API Console" description="Monitor API activity, explore endpoints, and manage webhooks.">
        <x-signals.stat-grid class="mb-8">
            <div class="s-stat-card">
                <div class="s-stat-icon s-stat-icon-blue">
                    <flux:icon.arrow-path />
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div class="s-stat-label">Requests (7 days)</div>
                    <div class="s-stat-value">{{ number_format($requestCount) }}</div>
                </div>
            </div>

            <div class="s-stat-card">
                <div class="s-stat-icon s-stat-icon-green">
                    <flux:icon.scale />
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div class="s-stat-label">Weighted units (7 days)</div>
                    <div class="s-stat-value">{{ number_format($weightedUnits) }}</div>
                </div>
            </div>

            <div class="s-stat-card">
                <div class="s-stat-icon s-stat-icon-violet">
                    <flux:icon.key />
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div class="s-stat-label">Your API tokens</div>
                    <div class="s-stat-value">{{ number_format($tokenCount) }}</div>
                </div>
            </div>

            <div class="s-stat-card">
                <div class="s-stat-icon s-stat-icon-amber">
                    <flux:icon.signal />
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div class="s-stat-label">Active webhooks</div>
                    <div class="s-stat-value">{{ number_format($activeWebhookCount) }}</div>
                    <div class="s-stat-label" style="margin-top: 2px; text-transform: none; letter-spacing: 0;">
                        {{ number_format($inboundSourceCount) }} inbound {{ str('source')->plural($inboundSourceCount) }}
                    </div>
                </div>
            </div>
        </x-signals.stat-grid>

        <div>
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-[var(--text-primary)]">Console areas</h2>
                <p class="text-xs text-[var(--text-secondary)] mt-0.5">Jump into explorer, logs, usage, and webhook management.</p>
            </div>

            <div class="s-admin-landing-grid">
                @foreach($this->areas as $area)
                    @if(isset($area['gate']))
                        @can($area['gate'])
                            <a href="{{ route($area['route']) }}" wire:navigate.hover class="s-module-card enabled">
                                <div class="s-module-icon">
                                    <flux:icon :name="$area['icon']" class="!size-5" />
                                </div>
                                <div class="s-module-info">
                                    <div class="s-module-name">{{ $area['label'] }}</div>
                                    <div class="s-module-desc">{{ $area['description'] }}</div>
                                </div>
                                <flux:icon.chevron-right class="!size-4 text-[var(--text-muted)]" />
                            </a>
                        @endcan
                    @else
                        <a href="{{ route($area['route']) }}" wire:navigate.hover class="s-module-card enabled">
                            <div class="s-module-icon">
                                <flux:icon :name="$area['icon']" class="!size-5" />
                            </div>
                            <div class="s-module-info">
                                <div class="s-module-name">{{ $area['label'] }}</div>
                                <div class="s-module-desc">{{ $area['description'] }}</div>
                            </div>
                            <flux:icon.chevron-right class="!size-4 text-[var(--text-muted)]" />
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </x-api::layout>
</section>
