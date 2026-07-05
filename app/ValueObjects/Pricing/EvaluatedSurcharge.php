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
        public string $surcharge_type,
        public ?string $percent_value,
        public ?int $fixed_value_minor,
        public int $amount_minor,
        public ?int $product_tax_class_id,
    ) {}

    /**
     * @return array{
     *     name: string,
     *     surcharge_type: string,
     *     percent_value: string|null,
     *     fixed_value_minor: int|null
     * }
     */
    public function snapshotAttributes(): array
    {
        return [
            'name' => $this->name,
            'surcharge_type' => $this->surcharge_type,
            'percent_value' => $this->percent_value,
            'fixed_value_minor' => $this->fixed_value_minor,
        ];
    }
}
