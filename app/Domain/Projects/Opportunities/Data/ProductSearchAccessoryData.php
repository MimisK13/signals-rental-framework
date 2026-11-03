<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Data;

use App\Domain\Products\Models\Accessory;
use Spatie\LaravelData\Data;

/**
 * A linked accessory exposed alongside a product search hit, so the line-item
 * editor can ripple ratio-based accessory quantities when the primary product is
 * added.
 *
 * `ratio` is the accessory quantity per one unit of the parent product (the
 * {@see Accessory::$quantity} `decimal:2` value) as a string.
 */
class ProductSearchAccessoryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $sku,
        public string $ratio,
        public bool $included,
        public bool $zero_priced,
    ) {}
}
