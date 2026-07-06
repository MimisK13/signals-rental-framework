<?php

namespace App\Services\Api;

use App\Support\Api\Attributes\PriceWeight;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use ReflectionMethod;

class PriceWeightResolver
{
    private const CACHE_TAG = 'api-price-weights';

    private const CACHE_KEY = 'api:price-weights';

    private const CACHE_TTL = 3600;

    /** @var array<string, int>|null */
    private ?array $memory = null;

    public function get(string $routeName): int
    {
        return $this->all()[$routeName] ?? 1;
    }

    /**
     * @return array<string, int>
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

        $weights = $this->build();

        $this->memory = $weights;
        $this->persistentPut($weights);

        return $weights;
    }

    public function invalidateAll(): void
    {
        $this->memory = null;
        $this->persistentForget();
    }

    public function weightForMethod(string $controller, string $method): int
    {
        if (! method_exists($controller, $method)) {
            return 1;
        }

        $reflection = new ReflectionMethod($controller, $method);
        $attributes = $reflection->getAttributes(PriceWeight::class);

        if ($attributes === []) {
            return 1;
        }

        /** @var PriceWeight $attribute */
        $attribute = $attributes[0]->newInstance();

        return $attribute->weight;
    }

    /**
     * @return array<string, int>
     */
    private function build(): array
    {
        $weights = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if (! is_string($name) || ! Str::startsWith($name, 'api.v1.')) {
                continue;
            }

            $action = $route->getAction('uses');

            if (! is_string($action) || ! str_contains($action, '@')) {
                $weights[$name] = 1;

                continue;
            }

            [$controller, $method] = explode('@', $action, 2);
            $weights[$name] = $this->weightForMethod($controller, $method);
        }

        return $weights;
    }

    /**
     * @return array<string, int>|null
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
     * @param  array<string, int>  $weights
     */
    private function persistentPut(array $weights): void
    {
        try {
            if (Cache::supportsTags()) {
                Cache::tags([self::CACHE_TAG])->put(self::CACHE_KEY, $weights, self::CACHE_TTL);

                return;
            }

            Cache::put(self::CACHE_KEY, $weights, self::CACHE_TTL);
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
