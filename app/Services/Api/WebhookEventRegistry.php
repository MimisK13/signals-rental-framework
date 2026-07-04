<?php

namespace App\Services\Api;

class WebhookEventRegistry
{
    /**
     * Canonical webhook event names — single source of truth for the allow-list.
     *
     * @var list<string>
     */
    public const CORE_EVENT_NAMES = [
        'user.created',
        'user.updated',
        'user.deactivated',
        'user.deleted',
        'member.created',
        'member.updated',
        'member.archived',
        'member.restored',
        'member.deleted',
        'member.merged',
        'member.anonymised',
        'member.vat_validated',
        'settings.updated',
        'role.created',
        'role.updated',
        'role.deleted',
        'tax_rate.created',
        'tax_rate.updated',
        'tax_rate.deleted',
        'tax_rule.created',
        'tax_rule.updated',
        'tax_rule.deleted',
        'product.created',
        'product.updated',
        'product.archived',
        'product.restored',
        'product.deleted',
        'product.merged',
        'product_group.created',
        'product_group.updated',
        'product_group.deleted',
        'stock_level.created',
        'stock_level.updated',
        'stock_level.deleted',
        'stock_transaction.created',
        'stock_transaction.deleted',
        'container.created',
        'container.item.packed',
        'container.item.unpacked',
        'container.item.transferred',
        'container.nested',
        'container.sealed',
        'container.unsealed',
        'container.dissolved',
        'container.dispatched',
        'container.returned',
        'activity.created',
        'activity.updated',
        'activity.deleted',
        'activity.completed',
        'rate_definition.created',
        'rate_definition.updated',
        'rate_definition.deleted',
        'product_rate.created',
        'product_rate.updated',
        'product_rate.deleted',
        'opportunity.created',
        'opportunity.updated',
        'opportunity.quoted',
        'opportunity.converted_to_order',
        'opportunity.reverted_to_quotation',
        'opportunity.reverted_to_draft',
        'opportunity.reinstated',
        'opportunity.reopened',
        'opportunity.locks_applied',
        'opportunity.locks_released',
        'opportunity.status_changed',
        'opportunity.status_promoted',
        'opportunity.cloned',
        'opportunity.deleted',
        'opportunity.restored',
        'opportunity.deal_price_set',
        'opportunity.deal_price_cleared',
        'opportunity.group_deal_price_set',
        'opportunity.pricing_recalculated',
        'opportunity.discount_reset',
        'opportunity.item_added',
        'opportunity.item_details_updated',
        'opportunity.item_renamed',
        'opportunity.item_removed',
        'opportunity.items_restructured',
        'opportunity.item_quantity_changed',
        'opportunity.item_dates_changed',
        'opportunity.item_discount_set',
        'opportunity.discount_category_applied',
        'opportunity.discount_manually_overridden',
        'opportunity.item_optional_toggled',
        'opportunity.item_price_overridden',
        'opportunity.item_substituted',
        'opportunity.item_surcharge_overridden',
        'opportunity.items_reordered',
        'opportunity.surcharge_group_toggled',
        'opportunity.cost_added',
        'opportunity.cost_updated',
        'opportunity.cost_removed',
        'opportunity.version_created',
        'opportunity.version_activated',
        'opportunity.version_accepted',
        'opportunity.version_declined',
        'opportunity.version_sent',
        'opportunity.version_relabelled',
        'opportunity.version_superseded',
        'opportunity.version_deleted',
        'opportunity.asset_allocated',
        'opportunity.asset_deallocated',
        'opportunity.asset_substituted',
        'opportunity.asset_prepared',
        'opportunity.asset_preparation_reverted',
        'opportunity.asset_checked',
        'opportunity.asset_dispatched',
        'opportunity.asset_on_hire',
        'opportunity.asset_returned',
        'opportunity.asset_status_reverted',
        'opportunity.asset_container_set',
        'opportunity.asset_container_cleared',
        'opportunity.bulk_dispatched',
        'opportunity.bulk_returned',
        'opportunity.bulk_quantity_adjusted',
        'availability.changed',
        'shortage.detected',
        'shortage.cleared',
        'shortage.acknowledged',
        'shortage.resolution.created',
        'shortage.resolution.confirmed',
        'shortage.resolution.in_progress',
        'shortage.resolution.fulfilled',
        'shortage.resolution.failed',
        'shortage.resolution.cancelled',
        'shortage.waitlist.created',
        'shortage.waitlist.matched',
        'shortage.waitlist.expired',
        'document.created',
        'document.finalised',
        'document.voided',
        'document.emailed',
        'document.public_link_created',
        'document.public_link_accessed',
        'document.public_link_revoked',
        'document.public_link_configured',
        'document_template.created',
        'document_template.updated',
        'document_template.deleted',
        'document_template.restored',
        'document_template.set_as_default',
        'document_sequence.reset',
        'invoice.created',
        'invoice.issued',
        'invoice.voided',
        'invoice.overdue',
        'payment.recorded',
        'payment.refunded',
        'credit_note.issued',
    ];

    /**
     * @var array<string, array{name: string, label: string, group: string, weight: int}>
     */
    private array $events = [];

    /**
     * @param  array{label?: string, group?: string, weight?: int}  $definition
     */
    public function register(string $name, array $definition = []): void
    {
        $group = $definition['group'] ?? str($name)->before('.')->toString();

        $this->events[$name] = [
            'name' => $name,
            'label' => $definition['label'] ?? (string) str(str($name)->after('.'))->headline(),
            'group' => $group,
            'weight' => $definition['weight'] ?? 1,
        ];
    }

    /**
     * @param  array<string, array{label?: string, group?: string, weight?: int}>  $events
     */
    public function registerMany(array $events): void
    {
        foreach ($events as $name => $definition) {
            $this->register($name, $definition);
        }
    }

    /**
     * @return array{name: string, label: string, group: string, weight: int}|null
     */
    public function get(string $name): ?array
    {
        return $this->events[$name] ?? null;
    }

    /**
     * @return array<string, array{name: string, label: string, group: string, weight: int}>
     */
    public function all(): array
    {
        return $this->events;
    }

    /**
     * @return array<string, array{label: string, events: list<string>}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->events as $event) {
            $groups[$event['group']]['label'] = (string) str($event['group'])->headline();
            $groups[$event['group']]['events'][] = $event['name'];
        }

        ksort($groups);

        return $groups;
    }

    /**
     * Flat list of registered event names (backward-compatible allow-list output).
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_map(
            static fn (array $event): string => $event['name'],
            $this->events,
        ));
    }

    public function has(string $name): bool
    {
        return isset($this->events[$name]);
    }

    public function weight(string $name): int
    {
        return $this->events[$name]['weight'] ?? 1;
    }
}
