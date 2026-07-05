<?php

namespace App\Data\Products;

use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerCheckinMode;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

/**
 * Input DTO for updating a product's container configuration (P48).
 *
 * @phpstan-type ContainerTemplateSlot array{product_id: int, quantity: int, binding?: string|null}
 * @phpstan-type ContainerTemplate array{slots?: list<ContainerTemplateSlot>, max_items?: int|null}
 */
class UpdateProductContainerConfigData extends Data
{
    /**
     * @param  ContainerTemplate|null  $container_template
     */
    public function __construct(
        public bool $is_containerable,
        public ?ContainerAvailabilityMode $container_availability_mode = null,
        public ?ContainerCheckinMode $container_checkin_mode = null,
        public int $container_max_nesting_depth = 2,
        public bool $container_repack_on_return = false,
        public bool $is_warehouse_addable = false,
        public ?int $warehouse_add_default_charge = null,
        public ?array $container_template = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'is_containerable' => ['required', 'boolean'],
            'container_availability_mode' => ['nullable', new Enum(ContainerAvailabilityMode::class)],
            'container_checkin_mode' => ['nullable', new Enum(ContainerCheckinMode::class)],
            'container_max_nesting_depth' => ['required', 'integer', 'min:1', 'max:3'],
            'container_repack_on_return' => ['required', 'boolean'],
            'is_warehouse_addable' => ['required', 'boolean'],
            'warehouse_add_default_charge' => ['nullable', 'integer', 'min:0'],
            'container_template' => ['nullable', 'array'],
            'container_template.slots' => ['nullable', 'array'],
            'container_template.slots.*.product_id' => ['required_with:container_template.slots', 'integer'],
            'container_template.slots.*.quantity' => ['required_with:container_template.slots', 'integer'],
            'container_template.slots.*.binding' => ['nullable', 'string', Rule::in(['fixed', 'pool'])],
            'container_template.max_items' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
