<?php

namespace App\Data\Concerns;

use App\Support\CostVisibility;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;

/**
 * Strips declared cost field keys from Spatie Data serialisation output when the
 * current user lacks `costs.view`.
 *
 * Apply to any response DTO that exposes purchase/cost figures. C12 PO/VSI DTOs
 * should declare their cost keys via {@see costFieldKeys()} and use this trait.
 */
trait StripsCostFields
{
    /**
     * @return list<string>
     */
    abstract protected static function costFieldKeys(): array;

    /**
     * @return array<string, mixed>
     */
    public function transform(
        null|TransformationContextFactory|TransformationContext $transformationContext = null,
    ): array {
        /** @var array<string, mixed> $data */
        $data = parent::transform($transformationContext);

        return CostVisibility::stripFields($data, static::costFieldKeys());
    }
}
