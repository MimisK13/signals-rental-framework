<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Services;

use App\Domain\Availability\Services\DemandSourceDefinition;
use App\Domain\Availability\Shortages\Contracts\ShortageResolverContract;

/**
 * Declarative definition of a shortage resolver registered in the
 * {@see ShortageResolverRegistry}. Mirrors {@see DemandSourceDefinition}:
 * a small readonly value object pairing the resolver key with the class to
 * resolve from the container.
 */
final readonly class ShortageResolverDefinition
{
    /**
     * @param  class-string<ShortageResolverContract>  $resolverClass
     */
    public function __construct(
        public string $key,
        public string $resolverClass,
    ) {}
}
