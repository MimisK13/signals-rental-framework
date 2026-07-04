<?php

namespace App\Data\Casts;

use App\Enums\TaxBuyerType;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class TaxBuyerTypeCast implements Cast
{
    /**
     * @param  array<string, mixed>  $properties
     * @param  CreationContext<Data>  $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): ?TaxBuyerType
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof TaxBuyerType) {
            return $value;
        }

        $coerced = TaxBuyerType::coerce($value);

        if (! is_string($coerced)) {
            return null;
        }

        return TaxBuyerType::from($coerced);
    }
}
