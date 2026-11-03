<?php

declare(strict_types=1);

namespace App\Domain\API\Presentation\Livewire;

use App\Domain\API\Enums\MeteringChannel;
use App\Domain\API\Jobs\ExportApiRequestLog;
use App\Domain\API\Models\RequestLog;
use App\Domain\API\Support\DateWindow;
use App\Domain\API\Support\MeteringStatusClass;
use App\Domain\Webhooks\Models\InboundWebhookLog;
use App\Domain\Webhooks\Models\WebhookLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
#[Title('Request Logs')]
final class Logs extends Component
{
    use WithPagination;

    #[Url]
    public string $channel = 'api';

    #[Url]
    public string $filterDatePreset = '7d';

    #[Url]
    public string $filterDateFrom = '';

    #[Url]
    public string $filterDateTo = '';

    #[Url]
    public string $filterStatusClass = '';

    #[Url]
    public string $filterToken = '';

    #[Url]
    public string $filterMethod = '';

    public ?int $expandedRow = null;

    public function mount(): void
    {
        Gate::authorize('api.logs.view');
    }

    public function updatedChannel(): void
    {
        $this->filterMethod = '';
        $this->filterToken = '';
        $this->expandedRow = null;
        $this->resetPage();
    }

    public function updatedFilterDatePreset(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatusClass(): void
    {
        $this->resetPage();
    }

    public function updatedFilterToken(): void
    {
        $this->resetPage();
    }

    public function updatedFilterMethod(): void
    {
        $this->resetPage();
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
        $this->updatedChannel();
    }

    public function setDatePreset(string $preset): void
    {
        $this->filterDatePreset = $preset;

        if ($preset !== 'custom') {
            $this->filterDateFrom = '';
            $this->filterDateTo = '';
        }

        $this->resetPage();
    }

    public function toggleRow(int $id): void
    {
        $this->expandedRow = $this->expandedRow === $id ? null : $id;
    }

    public function clearFilters(): void
    {
        $this->filterDatePreset = '7d';
        $this->filterDateFrom = '';
        $this->filterDateTo = '';
        $this->filterStatusClass = '';
        $this->filterToken = '';
        $this->filterMethod = '';
        $this->expandedRow = null;
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        $job = new ExportApiRequestLog((int) auth()->id(), $this->channel, $this->exportFilters());
        $filename = 'api-logs-'.$this->channel.'-'.now()->format('Ymd-His').'.csv';

        $this->dispatch('toast', type: 'success', message: 'Export ready. Downloading…');

        return response()->streamDownload(function () use ($job): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            $job->writeCsv($handle);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function statusBadgeClass(?int $statusCode): string
    {
        return match (MeteringStatusClass::fromStatusCode($statusCode)) {
            '2xx' => 's-badge-green',
            '4xx' => 's-badge-amber',
            default => 's-badge-red',
        };
    }

    public function formatJson(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(empty)';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        $decoded = json_decode((string) $value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        return (string) $value;
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
            'status_class' => $this->filterStatusClass,
            'token_id' => $this->channel === 'api' ? $this->filterToken : null,
            'method' => $this->channel === 'api' ? $this->filterMethod : null,
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

    /**
     * @param  Builder<RequestLog>|Builder<WebhookLog>|Builder<InboundWebhookLog>  $query
     */
    private function applyStatusClassFilter(Builder $query, string $column): void
    {
        if ($this->filterStatusClass === '') {
            return;
        }

        match ($this->filterStatusClass) {
            '2xx' => $query->whereBetween($column, [200, 399]),
            '4xx' => $query->whereBetween($column, [400, 499]),
            '5xx' => $query->where(function (Builder $inner) use ($column) {
                $inner->where($column, '>=', 500)
                    ->orWhere($column, 0)
                    ->orWhereNull($column);
            }),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $window = $this->dateWindow();

        if ($this->channel === 'webhook_sent') {
            $query = WebhookLog::query()
                ->whereBetween('created_at', [$window->from, $window->to])
                ->latest('created_at');

            $this->applyStatusClassFilter($query, 'response_code');

            return [
                'logs' => $query->paginate(25),
                'methods' => collect(),
                'tokens' => collect(),
            ];
        }

        if ($this->channel === 'webhook_received') {
            $query = InboundWebhookLog::query()
                ->with('inboundWebhookSource')
                ->whereBetween('created_at', [$window->from, $window->to])
                ->latest('created_at');

            $this->applyStatusClassFilter($query, 'status_code');

            return [
                'logs' => $query->paginate(25),
                'methods' => collect(),
                'tokens' => collect(),
            ];
        }

        $query = RequestLog::query()
            ->with('token')
            ->where('channel', MeteringChannel::Api->value)
            ->whereBetween('created_at', [$window->from, $window->to])
            ->latest('created_at');

        if ($this->filterMethod !== '') {
            $query->where('method', $this->filterMethod);
        }

        if ($this->filterToken !== '') {
            $query->where('personal_access_token_id', (int) $this->filterToken);
        }

        $this->applyStatusClassFilter($query, 'status_code');

        $tokenIds = RequestLog::query()
            ->where('channel', MeteringChannel::Api->value)
            ->whereNotNull('personal_access_token_id')
            ->distinct()
            ->pluck('personal_access_token_id');

        $tokens = RequestLog::query()
            ->with('token')
            ->whereIn('personal_access_token_id', $tokenIds)
            ->get()
            ->pluck('token')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return [
            'logs' => $query->paginate(25),
            'methods' => RequestLog::query()
                ->where('channel', MeteringChannel::Api->value)
                ->distinct()
                ->orderBy('method')
                ->pluck('method'),
            'tokens' => $tokens,
        ];
    }

    public function render(): View
    {
        return view('api::logs', $this->with());
    }
}
