<?php

namespace App\Support\Api\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class PriceWeight
{
    public function __construct(public int $weight) {}
}
