<?php

namespace App\ValueObjects\Pricing;

use App\Enums\DiscountRuleScope;

/**
 * Line/product context passed to {@see CriteriaMatcher}, extended for quantity
 * thresholds and aggregate scopes (P10 volume discounts).
 */
readonly class DiscountCriteriaContext
{
    /**
     * @param  list<string>  $tag_names
     */
    public function __construct(
        public ?int $product_group_id = null,
        public array $tag_names = [],
        public ?int $product_id = null,
        public ?string $transaction_type = null,
        public ?int $line_quantity = null,
        public ?int $aggregate_product_quantity = null,
        public ?int $aggregate_group_quantity = null,
        public ?DiscountRuleScope $rule_scope = null,
    ) {}

    public function withRuleScope(DiscountRuleScope $scope): self
    {
        return new self(
            product_group_id: $this->product_group_id,
            tag_names: $this->tag_names,
            product_id: $this->product_id,
            transaction_type: $this->transaction_type,
            line_quantity: $this->line_quantity,
            aggregate_product_quantity: $this->aggregate_product_quantity,
            aggregate_group_quantity: $this->aggregate_group_quantity,
            rule_scope: $scope,
        );
    }
}
