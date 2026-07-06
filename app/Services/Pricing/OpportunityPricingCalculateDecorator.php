<?php

namespace App\Services\Pricing;

use App\Models\Opportunity;
use Closure;

/**
 * Decorator seam for `opportunity.pricing.calculate` — wraps the full repricing
 * pipeline with plugin before/after hooks.
 */
class OpportunityPricingCalculateDecorator
{
    /** @var array<string, callable(Opportunity, Closure): void> */
    private array $beforeHooks = [];

    /** @var array<string, callable(Opportunity, Closure): void> */
    private array $afterHooks = [];

    /**
     * @param  callable(Opportunity, Closure): void  $hook
     */
    public function registerBefore(string $key, callable $hook): void
    {
        $this->beforeHooks[$key] = $hook;
    }

    /**
     * @param  callable(Opportunity, Closure): void  $hook
     */
    public function registerAfter(string $key, callable $hook): void
    {
        $this->afterHooks[$key] = $hook;
    }

    public function run(Opportunity $opportunity, Closure $calculate): void
    {
        $pipeline = $calculate;

        foreach (array_reverse($this->beforeHooks) as $hook) {
            $next = $pipeline;
            $pipeline = static function () use ($hook, $opportunity, $next): void {
                $hook($opportunity, $next);
            };
        }

        foreach ($this->afterHooks as $hook) {
            $next = $pipeline;
            $pipeline = static function () use ($hook, $opportunity, $next): void {
                $next();
                $hook($opportunity, $next);
            };
        }

        $pipeline();
    }
}
