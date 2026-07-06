<?php

use App\Models\InboundWebhookSource;
use App\Models\Webhook;
use App\Queries\Api\UsageQuery;
use App\Support\DateWindow;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('API Console')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('api.access');
    }

    /**
     * @return list<array{label: string, description: string, icon: string, route: string, gate?: string}>
     */
    public function getAreasProperty(): array
    {
        return [
            ['label' => 'Explorer', 'description' => 'Execute API requests interactively', 'icon' => 'command-line', 'route' => 'api.explorer', 'gate' => 'api.explorer.use'],
            ['label' => 'Request Log', 'description' => 'Browse API and webhook request history', 'icon' => 'clipboard-document-list', 'route' => 'api.logs', 'gate' => 'api.logs.view'],
            ['label' => 'Usage', 'description' => 'Review aggregated volume and weighted units', 'icon' => 'chart-bar', 'route' => 'api.usage', 'gate' => 'api.usage.view'],
            ['label' => 'Webhooks', 'description' => 'Manage outbound subscriptions and inbound sources', 'icon' => 'signal', 'route' => 'api.webhooks', 'gate' => 'webhooks.manage'],
        ];
    }

    public function with(): array
    {
        $window = DateWindow::fromRequest('7d');
        $totals = app(UsageQuery::class)($window, 'endpoint', null)->totals;

        return [
            'requestCount' => $totals['request_count'],
            'weightedUnits' => $totals['weighted_units'],
            'tokenCount' => auth()->user()->tokens()->count(),
            'activeWebhookCount' => Webhook::query()->where('is_active', true)->count(),
            'inboundSourceCount' => InboundWebhookSource::query()->count(),
        ];
    }
}; ?>

<section class="w-full">
    <x-api.layout title="API Console" description="Monitor API activity, explore endpoints, and manage webhooks.">
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
                            <a href="{{ route($area['route']) }}" wire:navigate class="s-module-card enabled">
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
                        <a href="{{ route($area['route']) }}" wire:navigate class="s-module-card enabled">
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
    </x-api.layout>
</section>
