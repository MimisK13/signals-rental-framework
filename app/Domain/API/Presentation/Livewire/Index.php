<?php

declare(strict_types=1);

namespace App\Domain\API\Presentation\Livewire;

use App\Domain\API\Queries\UsageQuery;
use App\Domain\API\Support\DateWindow;
use App\Domain\Webhooks\Models\InboundWebhookSource;
use App\Domain\Webhooks\Models\Webhook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('API Console')]
final class Index extends Component
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

    /** @return array<string, mixed> */
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

    public function render(): View
    {
        return view('api::index', $this->with());
    }
}
