<?php

declare(strict_types=1);

namespace App\Domain\Communications\Services;

/**
 * Context passed to merge-field filters during rendering.
 */
final class MergeFieldRenderContext
{
    public function __construct(
        public readonly ?string $currencyCode = null,
        public readonly string $unknownFieldBehaviour = 'blank',
    ) {}

    public static function defaults(): self
    {
        return new self(
            currencyCode: settings('company.base_currency') ?? 'GBP',
            unknownFieldBehaviour: 'blank',
        );
    }
}
