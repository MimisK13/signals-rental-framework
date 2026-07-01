<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\FormatsOpportunityDates;
use App\Actions\Opportunities\Concerns\ValidatesOpportunityMember;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemDatesChanged;
use App\Verbs\Events\Opportunities\OpportunityUpdated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

/**
 * Updates editable header fields on an existing opportunity via the
 * OpportunityUpdated event.
 */
class UpdateOpportunity
{
    use CommitsVerbsEvents, FormatsOpportunityDates, ValidatesOpportunityMember;

    /**
     * The clearable header fields, modelled as {@see Optional} on the DTO so an
     * EXPLICIT null clears the column while an absent key leaves it unchanged.
     */
    private const OPTIONAL_FIELDS = [
        'venue_id', 'reference', 'description', 'external_description',
        'chargeable_days', 'delivery_instructions', 'collection_instructions',
        'delivery_address_id', 'collection_address_id', 'rating', 'tag_list',
    ];

    /**
     * The remaining header fields use plain-nullable semantics: null means
     * "leave unchanged" (they are not clearable through this update).
     */
    private const NULLABLE_FIELDS = [
        'subject', 'member_id', 'store_id', 'owned_by',
        'starts_at', 'ends_at', 'charge_starts_at', 'charge_ends_at',
        'prep_starts_at', 'prep_ends_at', 'load_starts_at', 'load_ends_at',
        'deliver_starts_at', 'deliver_ends_at', 'setup_starts_at', 'setup_ends_at',
        'show_starts_at', 'show_ends_at', 'takedown_starts_at', 'takedown_ends_at',
        'collect_starts_at', 'collect_ends_at', 'unload_starts_at', 'unload_ends_at',
        'deprep_starts_at', 'deprep_ends_at', 'ordered_at', 'quote_invalid_at',
        'use_chargeable_days', 'open_ended_rental', 'customer_collecting', 'customer_returning',
    ];

    /**
     * DTO field names that map to a differently-named event/state field, so the
     * provided set carries the event's field name (which {@see OpportunityUpdated}
     * matches against its FIELDS constant). The `invoiced` DTO field projects to
     * the `is_invoiced` state property.
     *
     * @var array<string, string>
     */
    private const FIELD_ALIASES = ['invoiced' => 'is_invoiced'];

    public function __invoke(Opportunity $opportunity, UpdateOpportunityData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $provided = $this->providedFields($data);

        // Authoritative customer-type guard: when the customer is being changed
        // (member_id supplied, non-null = "set this member"), it must be an
        // Organisation. member_id null = leave unchanged, so no re-check is needed.
        $this->assertMemberIsOrganisation($data->member_id);

        // Authoritative IDOR guard: a supplied delivery/collection address must
        // belong to the opportunity's member. When the member is being changed in
        // this same update, scope to the new member; otherwise the current one. Only
        // address fields actually provided (not their Optional sentinel) are checked.
        $this->assertAddressesBelongToMember($opportunity, $data);

        // D7: when the opportunity hire window changes, the line items that still
        // INHERIT it (their per-item window equals the opportunity's current window —
        // i.e. they were never given a manual per-item override) must follow the new
        // window so their charge days/totals and availability demand re-snapshot to
        // the right dates. Resolve them BEFORE any event fires, while the projection
        // still holds the pre-update dates; the new window is propagated below.
        $itemsToReschedule = $this->itemsInheritingHireWindow($opportunity, $data);
        [$newStartsAtIso, $newEndsAtIso] = $this->resolvedHireWindowIso($opportunity, $data);

        $this->commitVerbs(function () use ($opportunity, $data, $provided, $itemsToReschedule, $newStartsAtIso, $newEndsAtIso): void {
            OpportunityUpdated::fire(
                opportunity_id: $opportunity->state_id,
                provided: $provided,
                subject: $data->subject,
                member_id: $data->member_id,
                venue_id: $this->resolveOptional($data->venue_id),
                store_id: $data->store_id,
                owned_by: $data->owned_by,
                reference: $this->resolveOptional($data->reference),
                description: $this->resolveOptional($data->description),
                external_description: $this->resolveOptional($data->external_description),
                starts_at: $data->starts_at,
                ends_at: $data->ends_at,
                charge_starts_at: $data->charge_starts_at,
                charge_ends_at: $data->charge_ends_at,
                prep_starts_at: $data->prep_starts_at,
                prep_ends_at: $data->prep_ends_at,
                load_starts_at: $data->load_starts_at,
                load_ends_at: $data->load_ends_at,
                deliver_starts_at: $data->deliver_starts_at,
                deliver_ends_at: $data->deliver_ends_at,
                setup_starts_at: $data->setup_starts_at,
                setup_ends_at: $data->setup_ends_at,
                show_starts_at: $data->show_starts_at,
                show_ends_at: $data->show_ends_at,
                takedown_starts_at: $data->takedown_starts_at,
                takedown_ends_at: $data->takedown_ends_at,
                collect_starts_at: $data->collect_starts_at,
                collect_ends_at: $data->collect_ends_at,
                unload_starts_at: $data->unload_starts_at,
                unload_ends_at: $data->unload_ends_at,
                deprep_starts_at: $data->deprep_starts_at,
                deprep_ends_at: $data->deprep_ends_at,
                ordered_at: $data->ordered_at,
                quote_invalid_at: $data->quote_invalid_at,
                use_chargeable_days: $data->use_chargeable_days,
                chargeable_days: $this->resolveOptional($data->chargeable_days),
                open_ended_rental: $data->open_ended_rental,
                customer_collecting: $data->customer_collecting,
                customer_returning: $data->customer_returning,
                delivery_instructions: $this->resolveOptional($data->delivery_instructions),
                collection_instructions: $this->resolveOptional($data->collection_instructions),
                delivery_address_id: $this->resolveOptional($data->delivery_address_id),
                collection_address_id: $this->resolveOptional($data->collection_address_id),
                rating: $this->resolveOptional($data->rating),
                is_invoiced: $data->invoiced,
                tag_list: $this->resolveTagList($data->tag_list),
            );

            // Propagate the new hire window to the inheriting lines in the SAME
            // commit (one DB transaction) as the header update. ItemDatesChanged
            // reprices the line + rolls the totals up and re-snapshots the line's
            // availability demand over the new window. Manual per-item overrides are
            // excluded by itemsInheritingHireWindow().
            foreach ($itemsToReschedule as $item) {
                ItemDatesChanged::fire(
                    opportunity_item_id: $item->state_id,
                    starts_at: $newStartsAtIso,
                    ends_at: $newEndsAtIso,
                );
            }
        });

        // Custom fields live outside the event stream. Sync only when the caller
        // supplied them (null = leave untouched), matching the Members convention.
        if ($data->custom_fields !== null) {
            $opportunity->syncCustomFields($data->custom_fields);
        }

        return OpportunityData::fromModel($opportunity->refresh()->load('customFieldValues'));
    }

    /**
     * The active-version line items whose hire window still equals the
     * opportunity's CURRENT (pre-update) window — lines that have never had a
     * per-item date override applied, so they should follow a hire-period change.
     *
     * Returns an empty collection when the hire period is not actually changing,
     * when the opportunity has no resolvable window (a date-less opportunity bakes
     * a fire-time window onto each line that never matched the header), or when
     * pricing is frozen (a deal price / FX-tax lock preserves the snapshotted line
     * totals, so re-pricing on a date change would corrupt the freeze).
     *
     * @return Collection<int, OpportunityItem>
     */
    private function itemsInheritingHireWindow(Opportunity $opportunity, UpdateOpportunityData $data): Collection
    {
        if (! $this->hireWindowChanging($opportunity, $data) || $opportunity->pricingFrozen()) {
            return collect();
        }

        $oldStart = $opportunity->starts_at;
        $oldEnd = $opportunity->ends_at;

        if ($oldStart === null || $oldEnd === null) {
            return collect();
        }

        return $opportunity->items()->get()
            ->filter(fn (OpportunityItem $item): bool => $item->starts_at !== null
                && $item->ends_at !== null
                && $item->starts_at->equalTo($oldStart)
                && $item->ends_at->equalTo($oldEnd))
            ->values();
    }

    /**
     * Whether this update actually moves the opportunity's hire window: a provided
     * (non-null) start or end that differs from the stored value.
     */
    private function hireWindowChanging(Opportunity $opportunity, UpdateOpportunityData $data): bool
    {
        $startsChanged = $data->starts_at !== null
            && ($opportunity->starts_at === null || ! Carbon::parse($data->starts_at)->equalTo($opportunity->starts_at));

        $endsChanged = $data->ends_at !== null
            && ($opportunity->ends_at === null || ! Carbon::parse($data->ends_at)->equalTo($opportunity->ends_at));

        return $startsChanged || $endsChanged;
    }

    /**
     * The post-update hire window as ISO-8601 strings: each bound takes the
     * provided value when supplied, otherwise the opportunity's unchanged stored
     * value. Used to propagate the window onto the inheriting line items.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function resolvedHireWindowIso(Opportunity $opportunity, UpdateOpportunityData $data): array
    {
        $start = $data->starts_at !== null ? Carbon::parse($data->starts_at) : $opportunity->starts_at;
        $end = $data->ends_at !== null ? Carbon::parse($data->ends_at) : $opportunity->ends_at;

        return [$this->toIso($start), $this->toIso($end)];
    }

    /**
     * Assert that every supplied (non-null) address FK points at an {@see Address}
     * owned by the opportunity's member. When the member is being changed in this
     * same update the new member is authoritative; otherwise the current member is.
     * An absent address field (its Optional sentinel) or an explicit null (clearing
     * the column) is skipped — neither leaks another member's address. A mismatch
     * (including a non-Member-owned address) is surfaced as a 422.
     */
    private function assertAddressesBelongToMember(Opportunity $opportunity, UpdateOpportunityData $data): void
    {
        $memberId = $data->member_id ?? $opportunity->member_id;

        foreach (['delivery_address_id', 'collection_address_id'] as $field) {
            $value = $data->{$field};

            // Skip absent (Optional) and explicit-null (clearing) values; only a
            // newly-set address id can introduce a foreign-member reference.
            if ($value instanceof Optional || $value === null) {
                continue;
            }

            $this->assertAddressBelongsToMember($field, (int) $value, $memberId);
        }
    }

    /**
     * The list of header fields the caller actually supplied. Optional fields
     * count as provided unless still their `Optional` sentinel; nullable fields
     * count as provided unless null (the existing "null = unchanged" rule).
     *
     * @return list<string>
     */
    private function providedFields(UpdateOpportunityData $data): array
    {
        $provided = [];

        foreach (self::OPTIONAL_FIELDS as $field) {
            if (! $data->{$field} instanceof Optional) {
                $provided[] = $field;
            }
        }

        foreach (self::NULLABLE_FIELDS as $field) {
            if ($data->{$field} !== null) {
                $provided[] = $field;
            }
        }

        // `invoiced` is a nullable-bool DTO field (null = unchanged) that projects
        // to the differently-named `is_invoiced` state property.
        if ($data->invoiced !== null) {
            $provided[] = self::FIELD_ALIASES['invoiced'];
        }

        return $provided;
    }

    /**
     * Collapse an Optional value to its underlying value: an unfilled Optional
     * becomes null (the field is absent and will not be in the provided set, so
     * the event ignores it regardless).
     */
    private function resolveOptional(mixed $value): mixed
    {
        return $value instanceof Optional ? null : $value;
    }

    /**
     * Collapse the optional `tag_list` to the event payload value: an unfilled
     * Optional (or explicit null) becomes null, which the event treats as an
     * empty list when `tag_list` is in the provided set; a supplied array is
     * re-indexed to a clean list.
     *
     * @param  array<int, string>|null|Optional  $value
     * @return list<string>|null
     */
    private function resolveTagList(array|null|Optional $value): ?array
    {
        if ($value instanceof Optional || $value === null) {
            return null;
        }

        return array_values($value);
    }
}
