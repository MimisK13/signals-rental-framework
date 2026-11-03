<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Presentation\Livewire;

use App\Domain\Dashboard\Services\DashboardKpiService;
use App\Support\Formatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Dashboard KPI stat cards backed by real aggregate queries.
 *
 * Results are cached for five minutes per authenticated user so repeated
 * dashboard loads stay cheap while still reflecting recent data.
 */
final class KpiCards extends Component
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return app(DashboardKpiService::class)->metrics();
        }

        $canViewOpportunities = Gate::allows('opportunities.access');
        $canViewInvoices = Gate::allows('invoices.view');
        $cacheKey = sprintf(
            'dashboard:%s:widget:kpi-cards:opportunities-%d:invoices-%d',
            $user->id,
            $canViewOpportunities,
            $canViewInvoices,
        );

        /** @var array{cached_at: string, data: array<string, mixed>} $payload */
        $payload = Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn (): array => [
                'cached_at' => now()->toIso8601String(),
                'data' => app(DashboardKpiService::class)->metrics(
                    $user,
                    includeOpportunities: $canViewOpportunities,
                    includeInvoices: $canViewInvoices,
                ),
            ],
        );

        return [
            'cached_at' => Carbon::parse($payload['cached_at']),
            'data' => $payload['data'],
            'can_view_opportunities' => $canViewOpportunities,
            'can_view_invoices' => $canViewInvoices,
        ];
    }

    public function render(): View
    {
        $payload = $this->metrics();
        $metrics = $payload['data'];
        $formatter = app(Formatter::class);

        return view('dashboard::widgets.kpi-cards', [
            'metrics' => $metrics,
            'cachedAt' => $payload['cached_at'],
            'formatter' => $formatter,
            'canViewOpportunities' => $payload['can_view_opportunities'],
            'canViewInvoices' => $payload['can_view_invoices'],
        ]);
    }
}
