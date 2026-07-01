<?php

namespace App\Services\Api;

use Dedoc\Scramble\Generator;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class ApiEndpointCatalogue
{
    private const CACHE_TAG = 'api-endpoint-catalogue';

    private const CACHE_KEY = 'api:endpoints';

    private const CACHE_TTL = 3600;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $memory = null;

    public function __construct(
        private readonly PriceWeightResolver $priceWeightResolver,
        private readonly Generator $openApiGenerator,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->memory !== null) {
            return $this->memory;
        }

        $cached = $this->persistentGet();

        if ($cached !== null) {
            $this->memory = $cached;

            return $cached;
        }

        $endpoints = $this->build();

        $this->memory = $endpoints;
        $this->persistentPut($endpoints);

        return $endpoints;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $routeName): ?array
    {
        foreach ($this->all() as $endpoint) {
            if ($endpoint['route_name'] === $routeName) {
                return $endpoint;
            }
        }

        return null;
    }

    public function invalidateAll(): void
    {
        $this->memory = null;
        $this->persistentForget();
        $this->priceWeightResolver->invalidateAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function build(): array
    {
        $openApiOperations = $this->openApiOperationsById();
        $endpoints = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $routeName = $route->getName();

            if (! is_string($routeName) || ! Str::startsWith($routeName, 'api.v1.')) {
                continue;
            }

            $methods = array_values(array_filter(
                $route->methods(),
                static fn (string $method): bool => ! in_array($method, ['HEAD', 'OPTIONS'], true),
            ));

            $method = $methods[0] ?? 'GET';
            $operationId = Str::startsWith($routeName, 'api.')
                ? Str::replaceFirst('api.', '', $routeName)
                : $routeName;
            $openApiOperation = $openApiOperations[$operationId] ?? null;
            [$resource, $operation] = $this->parseRouteName($routeName);
            $requiredAbility = $this->resolveRequiredAbility($route);

            $endpoints[] = [
                'route_name' => $routeName,
                'method' => $method,
                'uri' => '/'.ltrim($route->uri(), '/'),
                'resource' => $resource,
                'operation' => $operation,
                'summary' => $openApiOperation['summary'] ?? null,
                'description' => $openApiOperation['description'] ?? null,
                'params' => $openApiOperation['params'] ?? [],
                'required_ability' => $requiredAbility,
                'price_weight' => $this->priceWeightResolver->get($routeName),
            ];
        }

        usort($endpoints, static fn (array $a, array $b): int => strcmp($a['route_name'], $b['route_name']));

        return $endpoints;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function parseRouteName(string $routeName): array
    {
        $segments = explode('.', $routeName);

        if (count($segments) < 4) {
            return [null, null];
        }

        return [$segments[2], $segments[3]];
    }

    /**
     * @return array<string, array{summary: ?string, description: ?string, params: list<array<string, mixed>>}>
     */
    private function openApiOperationsById(): array
    {
        try {
            $openApi = ($this->openApiGenerator->setThrowExceptions(false))();
            $document = $openApi->toArray();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        $operations = [];

        foreach ($document['paths'] ?? [] as $path => $pathItem) {
            foreach ($pathItem as $httpMethod => $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                $operationId = $operation['operationId'] ?? null;

                if (! is_string($operationId)) {
                    continue;
                }

                $params = [];

                foreach ($operation['parameters'] ?? [] as $parameter) {
                    if (! is_array($parameter)) {
                        continue;
                    }

                    $params[] = [
                        'name' => $parameter['name'] ?? null,
                        'in' => $parameter['in'] ?? null,
                        'required' => (bool) ($parameter['required'] ?? false),
                        'description' => $parameter['description'] ?? null,
                        'schema' => $parameter['schema'] ?? null,
                    ];
                }

                $operations[$operationId] = [
                    'summary' => $operation['summary'] ?? null,
                    'description' => $operation['description'] ?? null,
                    'params' => $params,
                    'path' => $path,
                    'method' => strtoupper($httpMethod),
                ];
            }
        }

        return $operations;
    }

    private function resolveRequiredAbility(Route $route): ?string
    {
        $uses = $route->getAction('uses');

        if (! is_string($uses) || ! str_contains($uses, '@')) {
            return null;
        }

        [$class, $method] = explode('@', $uses, 2);

        if (! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        $source = $this->methodSource($class, $method);

        if (preg_match("/authorizeApi\s*\(\s*'[^']+'\s*,\s*'([^']+)'\s*\)/", $source, $matches) === 1) {
            return $matches[1];
        }

        $resourceAbilityKey = match (true) {
            str_contains($source, 'resourceIndex'), str_contains($source, 'resourceShow') => 'read',
            str_contains($source, 'resourceStore'), str_contains($source, 'resourceUpdate'), str_contains($source, 'resourceDestroy') => 'write',
            default => null,
        };

        if ($resourceAbilityKey === null) {
            return null;
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->hasMethod('abilities')) {
            return null;
        }

        $abilitiesMethod = $reflection->getMethod('abilities');
        $abilitiesMethod->setAccessible(true);
        $instance = $reflection->newInstanceWithoutConstructor();

        /** @var array{read?: string, write?: string} $abilities */
        $abilities = $abilitiesMethod->invoke($instance);

        return $abilities[$resourceAbilityKey] ?? null;
    }

    private function methodSource(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $filename = $reflection->getFileName();

        if ($filename === false) {
            return '';
        }

        $lines = file($filename);

        if ($lines === false) {
            return '';
        }

        return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function persistentGet(): ?array
    {
        try {
            if (Cache::supportsTags()) {
                return Cache::tags([self::CACHE_TAG])->get(self::CACHE_KEY);
            }

            return Cache::get(self::CACHE_KEY);
        } catch (\Exception $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $endpoints
     */
    private function persistentPut(array $endpoints): void
    {
        try {
            if (Cache::supportsTags()) {
                Cache::tags([self::CACHE_TAG])->put(self::CACHE_KEY, $endpoints, self::CACHE_TTL);

                return;
            }

            Cache::put(self::CACHE_KEY, $endpoints, self::CACHE_TTL);
        } catch (\Exception $e) {
            report($e);
        }
    }

    private function persistentForget(): void
    {
        try {
            if (Cache::supportsTags()) {
                Cache::tags([self::CACHE_TAG])->flush();

                return;
            }

            Cache::forget(self::CACHE_KEY);
        } catch (\Exception $e) {
            report($e);
        }
    }
}
