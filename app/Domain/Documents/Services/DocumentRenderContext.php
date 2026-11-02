<?php

declare(strict_types=1);

namespace App\Domain\Documents\Services;

/**
 * Request-scoped rendering context for resolvers that need template/store metadata
 * beyond the source model (Octane-safe via scoped binding).
 */
class DocumentRenderContext
{
    public function __construct(
        public ?int $storeId = null,
    ) {}
}
