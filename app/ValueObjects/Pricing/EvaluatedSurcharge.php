<?php

namespace App\ValueObjects\Pricing;

/**
 * A surcharge evaluated for a single opportunity line.
 */
readonly class EvaluatedSurcharge
{
    public function __construct(
        public int $surcharge_id,
        public string $name,
        public int $amount_minor,
        public ?int $product_tax_class_id,
    ) {}
}
