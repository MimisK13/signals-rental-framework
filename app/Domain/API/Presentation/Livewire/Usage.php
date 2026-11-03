<?php

declare(strict_types=1);

namespace App\Domain\API\Presentation\Livewire;

use App\Domain\API\Enums\MeteringChannel;
use App\Domain\API\Jobs\ExportApiUsage;
use App\Domain\API\Queries\UsageQuery;
use App\Domain\API\Support\DateWindow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
#[Title('API Usage')]
final class Usage extends Component
{
    #[Url]
    public string $filterDatePreset = '7d';

    #[Url]
    public string $filterDateFrom = '';

    #[Url]
    public string $filterDateTo = '';

    #[Url]
    public string $channel = '';

    public function mount(): void
    {
        Gate::authorize('api.usage.view');
    }

    public function updatedFilterDatePreset(): void
    {
        if ($this->filterDatePreset !== 'custom') {
            $this->filterDateFrom = '';
            $this->filterDateTo = '';
        }
    }

    public function setDatePreset(string $preset): void
    {
        $this->filterDatePreset = $preset;

        if ($preset !== 'custom') {
            $this->filterDateFrom = '';
            $this->filterDateTo = '';
        }
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    public function exportCsv(): StreamedResponse
    {
        $job = new ExportApiUsage((int) auth()->id(), $this->exportFilters());
        $usageQuery = app(UsageQuery::class);
        $filename = 'api-usage-'.now()->format('Ymd-His').'.csv';

        $this->dispatch('toast', type: 'success', message: 'Export ready. Downloading…');

        return response()->streamDownload(function () use ($job, $usageQuery): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            $job->writeCsv($handle, $usageQuery);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function exportFilters(): array
    {
        return array_filter([
            'window' => $this->filterDatePreset,
            'from' => $this->filterDateFrom,
            'to' => $this->filterDateTo,
            'channel' => $this->channel !== '' ? $this->channel : null,
            'dimension' => 'endpoint',
        ], fn ($value) => $value !== '' && $value !== null);
    }

    private function dateWindow(): DateWindow
    {
        if ($this->filterDatePreset === 'custom') {
            if ($this->filterDateFrom === '' || $this->filterDateTo === '') {
                return DateWindow::fromRequest('7d');
            }

            return DateWindow::fromRequest('custom', $this->filterDateFrom, $this->filterDateTo);
        }

        return DateWindow::fromRequest($this->filterDatePreset);
    }

    private function resolvedChannel(): ?MeteringChannel
    {
        if ($this->channel === '') {
            return null;
        }

        return MeteringChannel::from($this->channel);
    }

    /**
     * @param  list<array{key: string, label: string|null, request_count: int, weighted_units: int}>  $rows
     */
    public function statusCount(array $rows, string $statusClass): int
    {
        foreach ($rows as $row) {
            if ($row['key'] === $statusClass) {
                return $row['request_count'];
            }
        }

        return 0;
    }

    /**
     * @param  list<array{key: string, label: string|null, request_count: int, weighted_units: int}>  $rows
     */
    public function statusBarPercent(array $rows, string $statusClass): int
    {
        $total = array_sum(array_column($rows, 'request_count'));

        if ($total === 0) {
            return 0;
        }

        return (int) round(($this->statusCount($rows, $statusClass) / $total) * 100);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $window = $this->dateWindow();
        $channel = $this->resolvedChannel();
        $usageQuery = app(UsageQuery::class);

        $summary = $usageQuery($window, 'endpoint', $channel);
        $dailyRows = collect($usageQuery($window, 'day', $channel)->rows)
            ->sortBy('key')
            ->values()
            ->all();
        $tokenRows = collect($usageQuery($window, 'token', $channel)->rows)
            ->sortByDesc('weighted_units')
            ->values()
            ->all();
        $endpointRows = $usageQuery($window, 'endpoint', $channel)->rows;
        $resourceRows = $usageQuery($window, 'resource', $channel)->rows;
        $statusRows = $usageQuery($window, 'status', $channel)->rows;

        $maxDailyRequests = max(collect($dailyRows)->max('request_count') ?: 0, 1);

        return [
            'totals' => $summary->totals,
            'dailyRows' => $dailyRows,
            'maxDailyRequests' => $maxDailyRequests,
            'tokenRows' => $tokenRows,
            'endpointRows' => $endpointRows,
            'resourceRows' => $resourceRows,
            'statusRows' => $statusRows,
        ];
    }

    public function render(): View
    {
        return view('api::usage', $this->with());
    }
}
