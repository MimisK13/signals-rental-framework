<?php

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardKpiService;
use App\Support\Formatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * Dashboard KPI stat cards backed by real aggregate queries.
 *
 * Results are cached for five minutes per authenticated user so repeated
 * dashboard loads stay cheap while still reflecting recent data.
 */
class KpiCards extends Component
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

        $cacheKey = sprintf('dashboard:%s:widget:kpi-cards', $user->id);

        /** @var array{cached_at: string, data: array<string, mixed>} $payload */
        $payload = Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn (): array => [
                'cached_at' => now()->toIso8601String(),
                'data' => app(DashboardKpiService::class)->metrics($user),
            ],
        );

        return [
            'cached_at' => Carbon::parse($payload['cached_at']),
            'data' => $payload['data'],
        ];
    }

    public function render(): View
    {
        $payload = $this->metrics();
        $metrics = $payload['data'];
        $formatter = app(Formatter::class);

        return view('livewire.dashboard.kpi-cards', [
            'metrics' => $metrics,
            'cachedAt' => $payload['cached_at'],
            'formatter' => $formatter,
        ]);
    }
}
