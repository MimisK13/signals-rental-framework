<?php

declare(strict_types=1);

namespace App\Domain\API\Presentation\Livewire;

use App\Domain\API\Actions\ExecuteExplorerRequest;
use App\Domain\API\Services\ApiEndpointCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<string, Collection<int, array<string, mixed>>> $groupedEndpoints
 * @property-read list<array<string, mixed>> $operationsForSelectedResource
 * @property-read list<string> $pathParameterNames
 * @property-read array<string, mixed>|null $selectedEndpoint
 * @property-read string $resolvedUrl
 */
#[Layout('components.layouts.app')]
#[Title('API Explorer')]
final class Explorer extends Component
{
    public string $selectedApi = 'signals';

    public string $selectedResource = '';

    public string $selectedRouteName = '';

    /** @var list<array{key: string, value: string}> */
    public array $queryParams = [];

    /** @var array<string, string> */
    public array $pathParams = [];

    /** @var list<array{key: string, value: string}> */
    public array $customHeaders = [];

    public ?int $selectedTokenId = null;

    public string $rawToken = '';

    /** @var array<string, mixed>|null */
    public ?array $response = null;

    public function mount(): void
    {
        Gate::authorize('api.explorer.use');

        $grouped = collect(app(ApiEndpointCatalogue::class)->all())
            ->groupBy(static fn (array $endpoint): string => (string) ($endpoint['resource'] ?? 'other'))
            ->sortKeys();

        if ($grouped->isEmpty()) {
            return;
        }

        $this->selectedResource = (string) $grouped->keys()->first();
        $firstEndpoint = $grouped->get($this->selectedResource)?->first();
        $this->selectedRouteName = (string) ($firstEndpoint['route_name'] ?? '');
        $this->pathParams = $this->pathParamsForUri((string) ($firstEndpoint['uri'] ?? ''));

        $firstToken = auth()->user()->tokens()->orderByDesc('created_at')->first();

        if ($firstToken !== null) {
            $this->selectedTokenId = $firstToken->id;
        }
    }

    /**
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    #[Computed]
    public function groupedEndpoints(): Collection
    {
        return collect(app(ApiEndpointCatalogue::class)->all())
            ->groupBy(static fn (array $endpoint): string => (string) ($endpoint['resource'] ?? 'other'))
            ->sortKeys();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function resources(): array
    {
        return $this->groupedEndpoints->keys()->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function operationsForSelectedResource(): array
    {
        if ($this->selectedResource === '') {
            return [];
        }

        $group = $this->groupedEndpoints[$this->selectedResource] ?? null;

        if ($group === null) {
            return [];
        }

        return $group
            ->sortBy(fn (array $operation): string => strtolower($this->operationDisplayLabel($operation)))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    public function operationDisplayLabel(array $operation): string
    {
        $method = strtoupper((string) ($operation['method'] ?? 'GET'));
        $summary = (string) ($operation['summary'] ?? $operation['operation'] ?? $operation['route_name'] ?? '');

        return $method.' — '.$summary;
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function pathParameterNames(): array
    {
        $uri = (string) ($this->selectedEndpoint['uri'] ?? '');

        if ($uri === '') {
            return [];
        }

        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        return $matches[1];
    }

    #[Computed]
    public function hasPathParameters(): bool
    {
        return $this->pathParameterNames !== [];
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function selectedEndpoint(): ?array
    {
        if ($this->selectedRouteName === '') {
            return null;
        }

        foreach ($this->groupedEndpoints->flatten(1) as $endpoint) {
            if ($endpoint['route_name'] === $this->selectedRouteName) {
                return $endpoint;
            }
        }

        return null;
    }

    #[Computed]
    public function resolvedUrl(): string
    {
        $endpoint = $this->selectedEndpoint;

        if ($endpoint === null) {
            return '/api/v1/…';
        }

        return $this->substitutePathParameters((string) $endpoint['uri']);
    }

    #[Computed]
    public function formattedResolvedUrlHtml(): string
    {
        $escaped = e($this->resolvedUrl);

        return (string) preg_replace(
            '/(\{[^}]+\})/',
            '<span class="s-explorer-url-placeholder">$1</span>',
            $escaped,
        );
    }

    private function substitutePathParameters(string $uri): string
    {
        foreach ($this->pathParameterNames as $name) {
            $value = trim((string) ($this->pathParams[$name] ?? ''));
            $replacement = $value !== '' ? rawurlencode($value) : '{'.$name.'}';

            $uri = str_replace('{'.$name.'}', $replacement, $uri);
        }

        return $uri;
    }

    /**
     * @return array<string, string>
     */
    private function pathParamsForUri(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        $params = [];

        foreach ($matches[1] as $name) {
            $params[$name] = '';
        }

        return $params;
    }

    #[Computed]
    public function resolvedMethod(): string
    {
        return strtoupper((string) ($this->selectedEndpoint['method'] ?? 'GET'));
    }

    /**
     * @return list<PersonalAccessToken>
     */
    #[Computed]
    public function userTokens(): array
    {
        return auth()->user()->tokens()->orderByDesc('created_at')->get()->all();
    }

    public function updatedSelectedResource(): void
    {
        $operations = $this->operationsForSelectedResource;

        $this->selectedRouteName = (string) ($operations[0]['route_name'] ?? '');
        $this->pathParams = $this->pathParamsForUri((string) ($operations[0]['uri'] ?? ''));
        $this->queryParams = [];
    }

    public function updatedSelectedRouteName(): void
    {
        $this->pathParams = $this->pathParamsForUri((string) ($this->selectedEndpoint['uri'] ?? ''));
        $this->queryParams = [];
    }

    public function addQueryParam(): void
    {
        $this->queryParams[] = ['key' => '', 'value' => ''];
    }

    public function removeQueryParam(int $index): void
    {
        if (! array_key_exists($index, $this->queryParams)) {
            return;
        }

        unset($this->queryParams[$index]);
        $this->queryParams = array_values($this->queryParams);
    }

    public function addHeader(): void
    {
        $this->customHeaders[] = ['key' => '', 'value' => ''];
    }

    public function removeHeader(int $index): void
    {
        if (! array_key_exists($index, $this->customHeaders)) {
            return;
        }

        unset($this->customHeaders[$index]);
        $this->customHeaders = array_values($this->customHeaders);
    }

    public function makeRequest(): void
    {
        $endpoint = $this->selectedEndpoint;

        if ($endpoint === null) {
            $this->addError('request', 'Select an API operation before sending a request.');

            return;
        }

        foreach ($this->pathParameterNames as $name) {
            if (trim((string) ($this->pathParams[$name] ?? '')) === '') {
                $this->addError('pathParams.'.$name, 'Provide a value for {'.$name.'}.');

                return;
            }
        }

        $bearerToken = trim($this->rawToken);
        $ephemeralToken = null;

        if ($bearerToken === '') {
            if ($this->selectedTokenId === null) {
                $this->addError('auth', 'Select a token or paste a bearer token.');

                return;
            }

            $token = auth()->user()->tokens()->find($this->selectedTokenId);

            if ($token === null) {
                $this->addError('auth', 'Selected token was not found.');

                return;
            }

            $ephemeralToken = auth()->user()->createToken('api-explorer', $token->abilities ?? []);
            $bearerToken = $ephemeralToken->plainTextToken;
        }

        try {
            $this->response = app(ExecuteExplorerRequest::class)(
                method: (string) $endpoint['method'],
                path: $this->resolvedUrl,
                query: $this->pairsFromRows($this->queryParams),
                headers: $this->pairsFromRows($this->customHeaders),
                bearerToken: $bearerToken,
            );
        } finally {
            if ($ephemeralToken !== null) {
                $ephemeralToken->accessToken->delete();
            }
        }
    }

    public function statusBadgeClass(?string $statusClass): string
    {
        return match ($statusClass) {
            '2xx' => 's-badge-green',
            '4xx' => 's-badge-amber',
            default => 's-badge-red',
        };
    }

    public function methodBadgeClass(string $method): string
    {
        return match (strtoupper($method)) {
            'GET' => 's-badge-blue',
            'POST' => 's-badge-green',
            'PUT', 'PATCH' => 's-badge-amber',
            'DELETE' => 's-badge-red',
            default => 's-badge-zinc',
        };
    }

    /**
     * @param  list<array{key: string, value: string}>  $rows
     * @return array<string, string>
     */
    private function pairsFromRows(array $rows): array
    {
        $pairs = [];

        foreach ($rows as $row) {
            $key = trim($row['key']);

            if ($key === '') {
                continue;
            }

            $pairs[$key] = $row['value'];
        }

        return $pairs;
    }

    public function render(): View
    {
        return view('api::explorer');
    }
}
