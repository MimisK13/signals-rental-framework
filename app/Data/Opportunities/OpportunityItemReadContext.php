<?php

namespace App\Data\Opportunities;

use Illuminate\Support\Carbon;

/**
 * Resolved, read-only inputs for one OpportunityItem.
 *
 * This object contains no loading behavior. It is created by the line-items
 * read boundary and passed explicitly to consumers that need the same context.
 */
final readonly class OpportunityItemReadContext
{
    public function __construct(
        public int $opportunityItemId,
        public ?int $productId,
        public ?int $storeId,
        public Carbon $from,
        public Carbon $to,
        public int $quantity,
        public int $claimQuantity,
    ) {}

    /**
     * @param  array{product_id: int|null, store_id: int|null, from: Carbon, to: Carbon, quantity: int, claim_quantity: int}  $context
     */
    public static function fromResolved(int $opportunityItemId, array $context): self
    {
        return new self(
            opportunityItemId: $opportunityItemId,
            productId: $context['product_id'],
            storeId: $context['store_id'],
            from: $context['from'],
            to: $context['to'],
            quantity: $context['quantity'],
            claimQuantity: $context['claim_quantity'],
        );
    }

    /**
     * @return array{product_id: int|null, store_id: int|null, from: Carbon, to: Carbon, quantity: int, claim_quantity: int}
     */
    public function toResolvedArray(): array
    {
        return [
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
            'from' => $this->from,
            'to' => $this->to,
            'quantity' => $this->quantity,
            'claim_quantity' => $this->claimQuantity,
        ];
    }
}
