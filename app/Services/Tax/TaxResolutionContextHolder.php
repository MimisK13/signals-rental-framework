<?php

namespace App\Services\Tax;

/**
 * Request-scoped holder for {@see TaxResolutionContext} during document tax runs.
 */
class TaxResolutionContextHolder
{
    private ?TaxResolutionContext $context = null;

    public function set(?TaxResolutionContext $context): void
    {
        $this->context = $context;
    }

    public function get(): ?TaxResolutionContext
    {
        return $this->context;
    }

    public function clear(): void
    {
        $this->context = null;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function using(TaxResolutionContext $context, callable $callback): mixed
    {
        $previous = $this->context;
        $this->context = $context;

        try {
            return $callback();
        } finally {
            $this->context = $previous;
        }
    }
}
