<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Presentation\Livewire;

use App\Domain\API\Services\RansackFilter;
use App\Domain\Currency\Models\Currency;
use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Presentation\Concerns\LoadsCustomFieldValues;
use App\Domain\CustomFields\Presentation\Concerns\ReKeysCustomFieldErrors;
use App\Domain\Lists\Models\ListName;
use App\Domain\Members\Actions\CreateAddress;
use App\Domain\Members\Data\CreateAddressData;
use App\Domain\Members\Enums\MembershipType;
use App\Domain\Members\Models\Address;
use App\Domain\Members\Models\Member;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityParticipant;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\RemoveOpportunityParticipant;
use App\Domain\Projects\Opportunities\Actions\UpdateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityParticipantData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\UpdateOpportunityData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityParticipant;
use App\Domain\ReferenceData\Models\Country;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Services\What3WordsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Opportunity create/edit form — HEADER fields only (M8-3a).
 *
 * Serves both opportunities.create and opportunities.edit. Builds a Create/Update
 * DTO from the form state and calls the SAME action classes the API uses
 * (CreateOpportunity / UpdateOpportunity), which own the Gate::authorize() check.
 * The line-item editor is a separate chunk (M8-3b) and attaches to the Show /
 * Line Items tab, not this form.
 *
 * Field → DTO mapping (only fields the DTOs accept are surfaced):
 *   subject               → subject
 *   member picker         → member_id
 *   venue picker          → venue_id
 *   owner select          → owned_by
 *   store select          → store_id
 *   currency select       → currency            (CREATE-ONLY; UpdateOpportunityData has no currency)
 *   reference             → reference
 *   description           → description
 *   external_description  → external_description
 *   starts_at/ends_at     → starts_at / ends_at
 *   charge dates          → charge_starts_at / charge_ends_at
 *   tag input             → tag_list
 *   prices_include_tax    → prices_include_tax  (CREATE-ONLY; rendered read-only on edit)
 *   custom fields editor  → custom_fields
 *
 * The opportunity NUMBER is auto-generated (OpportunityNumberGenerator /
 * OpportunitySettings) and is never exposed as an input.
 */
#[Layout('components.layouts.app')] final class Form extends Component
{
    use LoadsCustomFieldValues;
    use ReKeysCustomFieldErrors;

    public ?int $opportunityId = null;

    public string $subject = '';

    public ?int $memberId = null;

    public ?int $venueId = null;

    public ?int $storeId = null;

    public ?int $ownedBy = null;

    public string $currency = 'GBP';

    public ?string $reference = null;

    public string $description = '';

    public string $externalDescription = '';

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public ?string $planningStartsAt = null;

    public ?string $planningEndsAt = null;

    public ?string $chargeStartsAt = null;

    public ?string $chargeEndsAt = null;

    public int $reservationPriority = 0;

    // Event-logistics lifecycle dates (C3a).
    public ?string $prepStartsAt = null;

    public ?string $prepEndsAt = null;

    public ?string $loadStartsAt = null;

    public ?string $loadEndsAt = null;

    public ?string $deliverStartsAt = null;

    public ?string $deliverEndsAt = null;

    public ?string $setupStartsAt = null;

    public ?string $setupEndsAt = null;

    public ?string $showStartsAt = null;

    public ?string $showEndsAt = null;

    public ?string $takedownStartsAt = null;

    public ?string $takedownEndsAt = null;

    public ?string $collectStartsAt = null;

    public ?string $collectEndsAt = null;

    public ?string $unloadStartsAt = null;

    public ?string $unloadEndsAt = null;

    public ?string $deprepStartsAt = null;

    public ?string $deprepEndsAt = null;

    public ?string $orderedAt = null;

    public ?string $quoteInvalidAt = null;

    // Chargeable-days + open-ended-rental controls (C3b).
    public bool $useChargeableDays = false;

    public ?string $chargeableDays = null;

    public bool $openEndedRental = false;

    // Customer collecting/returning flags (C3c).
    public bool $customerCollecting = false;

    public bool $customerReturning = false;

    public string $deliveryInstructions = '';

    public string $collectionInstructions = '';

    // Delivery/collection address FKs (C-data-2) — one of the member's addresses.
    public ?int $deliveryAddressId = null;

    public ?int $collectionAddressId = null;

    // "Add new address" modal state. `addressTarget` is 'delivery' or 'collection'
    // and tells save which FK to set once the address is created on the member.
    public bool $showAddressModal = false;

    public string $addressTarget = 'delivery';

    public string $newAddressName = '';

    public string $newAddressStreet = '';

    public string $newAddressCity = '';

    public string $newAddressCounty = '';

    public string $newAddressPostcode = '';

    public ?int $newAddressCountryId = null;

    public ?int $newAddressTypeId = null;

    public string $newAddressWhat3words = '';

    public bool $pricesIncludeTax = false;

    /** @var array<int, string> */
    public array $tags = [];

    // Member-picker autocomplete state.
    public string $memberSearch = '';

    public string $memberSelectedName = '';

    // Venue-picker autocomplete state.
    public string $venueSearch = '';

    public string $venueSelectedName = '';

    /** @var array<string, mixed> */
    public array $customFieldValues = [];

    // Participant add panel (#6) — only rendered in edit mode. Distinct property
    // names from the member/venue pickers so they never collide; mirrors the
    // participants tab's add flow and calls the SAME AddOpportunityParticipant action.
    public string $participantMemberSearch = '';

    public ?int $participantMemberId = null;

    public ?string $participantMemberSelectedName = null;

    public string $participantRole = '';

    public bool $participantMute = false;

    /**
     * The free-text roles the participant add panel suggests (the column is a plain
     * string, so any value is accepted by the action).
     *
     * @var list<string>
     */
    public const PARTICIPANT_SUGGESTED_ROLES = [
        'Primary contact',
        'Secondary contact',
        'Account manager',
        'Site contact',
    ];

    public function mount(?Opportunity $opportunity = null): void
    {
        if ($opportunity?->exists) {
            Gate::authorize('opportunities.edit');

            $this->opportunityId = $opportunity->id;
            $this->subject = $opportunity->subject;
            $this->memberId = $opportunity->member_id;
            $this->venueId = $opportunity->venue_id;
            $this->storeId = $opportunity->store_id;
            $this->ownedBy = $opportunity->owned_by;
            $this->currency = $opportunity->currency_code ?? 'GBP';
            $this->reference = $opportunity->reference;
            $this->description = $opportunity->description ?? '';
            $this->externalDescription = $opportunity->external_description ?? '';
            $this->startsAt = $opportunity->starts_at?->format('Y-m-d H:i');
            $this->endsAt = $opportunity->ends_at?->format('Y-m-d H:i');
            $this->planningStartsAt = $opportunity->planning_starts_at?->format('Y-m-d H:i');
            $this->planningEndsAt = $opportunity->planning_ends_at?->format('Y-m-d H:i');
            $this->chargeStartsAt = $opportunity->charge_starts_at?->format('Y-m-d H:i');
            $this->chargeEndsAt = $opportunity->charge_ends_at?->format('Y-m-d H:i');
            $this->reservationPriority = (int) $opportunity->reservation_priority;
            $this->prepStartsAt = $opportunity->prep_starts_at?->format('Y-m-d H:i');
            $this->prepEndsAt = $opportunity->prep_ends_at?->format('Y-m-d H:i');
            $this->loadStartsAt = $opportunity->load_starts_at?->format('Y-m-d H:i');
            $this->loadEndsAt = $opportunity->load_ends_at?->format('Y-m-d H:i');
            $this->deliverStartsAt = $opportunity->deliver_starts_at?->format('Y-m-d H:i');
            $this->deliverEndsAt = $opportunity->deliver_ends_at?->format('Y-m-d H:i');
            $this->setupStartsAt = $opportunity->setup_starts_at?->format('Y-m-d H:i');
            $this->setupEndsAt = $opportunity->setup_ends_at?->format('Y-m-d H:i');
            $this->showStartsAt = $opportunity->show_starts_at?->format('Y-m-d H:i');
            $this->showEndsAt = $opportunity->show_ends_at?->format('Y-m-d H:i');
            $this->takedownStartsAt = $opportunity->takedown_starts_at?->format('Y-m-d H:i');
            $this->takedownEndsAt = $opportunity->takedown_ends_at?->format('Y-m-d H:i');
            $this->collectStartsAt = $opportunity->collect_starts_at?->format('Y-m-d H:i');
            $this->collectEndsAt = $opportunity->collect_ends_at?->format('Y-m-d H:i');
            $this->unloadStartsAt = $opportunity->unload_starts_at?->format('Y-m-d H:i');
            $this->unloadEndsAt = $opportunity->unload_ends_at?->format('Y-m-d H:i');
            $this->deprepStartsAt = $opportunity->deprep_starts_at?->format('Y-m-d H:i');
            $this->deprepEndsAt = $opportunity->deprep_ends_at?->format('Y-m-d H:i');
            $this->orderedAt = $opportunity->ordered_at?->format('Y-m-d H:i');
            $this->quoteInvalidAt = $opportunity->quote_invalid_at?->format('Y-m-d H:i');
            $this->useChargeableDays = (bool) $opportunity->use_chargeable_days;
            $this->chargeableDays = $opportunity->chargeable_days !== null ? (string) $opportunity->chargeable_days : null;
            $this->openEndedRental = (bool) $opportunity->open_ended_rental;
            $this->customerCollecting = (bool) $opportunity->customer_collecting;
            $this->customerReturning = (bool) $opportunity->customer_returning;
            $this->deliveryInstructions = $opportunity->delivery_instructions ?? '';
            $this->collectionInstructions = $opportunity->collection_instructions ?? '';
            $this->deliveryAddressId = $opportunity->delivery_address_id;
            $this->collectionAddressId = $opportunity->collection_address_id;
            $this->pricesIncludeTax = (bool) $opportunity->prices_include_tax;
            $this->tags = $opportunity->tag_list ?? [];

            $this->customFieldValues = $this->loadCustomFieldValuesFrom($opportunity);
        } else {
            Gate::authorize('opportunities.create');

            /** @var User|null $currentUser */
            $currentUser = auth()->user();
            $this->ownedBy = $currentUser?->member_id;
            $this->currency = settings('company.base_currency', 'GBP');

            $defaultStore = Store::query()->where('is_default', true)->value('id');
            if (is_int($defaultStore)) {
                $this->storeId = $defaultStore;
            }
        }

        $this->resolveMemberName();
        $this->resolveVenueName();
    }

    /**
     * Member autocomplete results — Organisation members only, since an
     * opportunity's customer must be an organisation (enforced in the action/DTO).
     *
     * @return Collection<int, Member>
     */
    public function getMemberOptionsProperty(): Collection
    {
        if (trim($this->memberSearch) === '') {
            return collect();
        }

        $like = (string) config('database.connections.'.config('database.default').'.driver') === 'pgsql' ? 'ilike' : 'like';

        return Member::query()
            ->ofType(MembershipType::Organisation)
            ->where('is_active', true)
            ->where('name', $like, '%'.RansackFilter::escapeLike($this->memberSearch).'%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    public function selectMember(int $id): void
    {
        $member = Member::query()
            ->ofType(MembershipType::Organisation)
            ->where('is_active', true)
            ->find($id);

        if ($member === null) {
            return;
        }

        $this->memberId = $member->id;
        $this->memberSelectedName = $member->name;
        $this->memberSearch = '';

        $this->syncAddressSelections();
    }

    public function clearMember(): void
    {
        $this->memberId = null;
        $this->memberSelectedName = '';
        $this->memberSearch = '';

        $this->syncAddressSelections();
    }

    /**
     * Refresh the member-addresses cache and drop any delivery/collection
     * address selection that no longer belongs to the current member.
     */
    private function syncAddressSelections(): void
    {
        unset($this->memberAddresses);

        $validIds = $this->memberAddresses()->pluck('id')->all();

        if ($this->deliveryAddressId !== null && ! in_array($this->deliveryAddressId, $validIds, true)) {
            $this->deliveryAddressId = null;
        }

        if ($this->collectionAddressId !== null && ! in_array($this->collectionAddressId, $validIds, true)) {
            $this->collectionAddressId = null;
        }
    }

    /**
     * Venue autocomplete results.
     *
     * @return Collection<int, Member>
     */
    public function getVenueOptionsProperty(): Collection
    {
        if (trim($this->venueSearch) === '') {
            return collect();
        }

        $like = (string) config('database.connections.'.config('database.default').'.driver') === 'pgsql' ? 'ilike' : 'like';

        return Member::query()
            ->where('is_active', true)
            ->where('name', $like, '%'.RansackFilter::escapeLike($this->venueSearch).'%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    public function selectVenue(int $id): void
    {
        $venue = Member::query()->where('is_active', true)->find($id);

        if ($venue === null) {
            return;
        }

        $this->venueId = $venue->id;
        $this->venueSelectedName = $venue->name;
        $this->venueSearch = '';
    }

    public function clearVenue(): void
    {
        $this->venueId = null;
        $this->venueSelectedName = '';
        $this->venueSearch = '';
    }

    /**
     * The selected member's addresses, used to populate both address pickers.
     * Empty when no member is selected (the pickers render disabled).
     *
     * @return Collection<int, Address>
     */
    #[Computed]
    public function memberAddresses(): Collection
    {
        if ($this->memberId === null) {
            return collect();
        }

        return Address::query()
            ->where('addressable_type', Member::class)
            ->where('addressable_id', $this->memberId)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    /**
     * Open the "add new address" modal for the given target ('delivery' or
     * 'collection'), resetting the modal fields.
     */
    public function openAddressModal(string $target): void
    {
        if ($this->memberId === null) {
            return;
        }

        $this->addressTarget = $target === 'collection' ? 'collection' : 'delivery';
        $this->reset([
            'newAddressName', 'newAddressStreet', 'newAddressCity', 'newAddressCounty',
            'newAddressPostcode', 'newAddressCountryId', 'newAddressTypeId', 'newAddressWhat3words',
        ]);
        $this->resetValidation();
        $this->showAddressModal = true;
    }

    /**
     * Create a new address on the selected member via the shared CreateAddress
     * action (which owns the members.edit gate + validation) and select it into
     * the target FK.
     */
    public function saveNewAddress(): void
    {
        if ($this->memberId === null) {
            return;
        }

        $this->validate([
            'newAddressStreet' => ['required', 'string'],
            'newAddressName' => ['nullable', 'string', 'max:255'],
            'newAddressCity' => ['nullable', 'string', 'max:255'],
            'newAddressCounty' => ['nullable', 'string', 'max:255'],
            'newAddressPostcode' => ['nullable', 'string', 'max:20'],
            'newAddressCountryId' => ['nullable', 'integer', 'exists:countries,id'],
            'newAddressTypeId' => ['nullable', 'integer', 'exists:list_values,id'],
            'newAddressWhat3words' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Member $member */
        $member = Member::query()->findOrFail($this->memberId);

        $address = (new CreateAddress)($member, CreateAddressData::from([
            'street' => $this->newAddressStreet,
            'name' => $this->newAddressName ?: null,
            'city' => $this->newAddressCity ?: null,
            'county' => $this->newAddressCounty ?: null,
            'postcode' => $this->newAddressPostcode ?: null,
            'country_id' => $this->newAddressCountryId,
            'type_id' => $this->newAddressTypeId,
            'what3words' => $this->newAddressWhat3words ?: null,
        ]));

        // Select the freshly-created address into the target FK.
        if ($this->addressTarget === 'collection') {
            $this->collectionAddressId = $address->id;
        } else {
            $this->deliveryAddressId = $address->id;
        }

        unset($this->memberAddresses);
        $this->showAddressModal = false;
    }

    public function save(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
        ]);

        try {
            if ($this->opportunityId) {
                $opportunity = Opportunity::findOrFail($this->opportunityId);
                $result = (new UpdateOpportunity)($opportunity, UpdateOpportunityData::from($this->updatePayload()));
            } else {
                $result = (new CreateOpportunity)(CreateOpportunityData::from($this->createPayload()));
            }

            $this->redirect(route('opportunities.show', $result->id), navigate: true);
        } catch (ValidationException $e) {
            $this->reKeyCustomFieldErrors($e, $this->customFields()->pluck('name')->all());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(): array
    {
        return [
            'subject' => $this->subject,
            'member_id' => $this->memberId,
            'venue_id' => $this->venueId,
            'store_id' => $this->storeId,
            'owned_by' => $this->ownedBy,
            'reference' => $this->reference ?: null,
            'description' => $this->description ?: null,
            'external_description' => $this->externalDescription ?: null,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'planning_starts_at' => $this->planningStartsAt,
            'planning_ends_at' => $this->planningEndsAt,
            'charge_starts_at' => $this->chargeStartsAt,
            'charge_ends_at' => $this->chargeEndsAt,
            'reservation_priority' => $this->reservationPriority,
            'currency' => $this->currency,
            'prices_include_tax' => $this->pricesIncludeTax,
            'tag_list' => $this->tags !== [] ? array_values($this->tags) : null,
            'custom_fields' => $this->customFieldValues,
            ...$this->scheduleAndLogisticsPayload(),
        ];
    }

    /**
     * The C-data-1 schedule + logistics fields shared by the create and update
     * payloads. Empty instruction strings collapse to null so the column clears.
     *
     * @return array<string, mixed>
     */
    private function scheduleAndLogisticsPayload(): array
    {
        return [
            'prep_starts_at' => $this->prepStartsAt,
            'prep_ends_at' => $this->prepEndsAt,
            'load_starts_at' => $this->loadStartsAt,
            'load_ends_at' => $this->loadEndsAt,
            'deliver_starts_at' => $this->deliverStartsAt,
            'deliver_ends_at' => $this->deliverEndsAt,
            'setup_starts_at' => $this->setupStartsAt,
            'setup_ends_at' => $this->setupEndsAt,
            'show_starts_at' => $this->showStartsAt,
            'show_ends_at' => $this->showEndsAt,
            'takedown_starts_at' => $this->takedownStartsAt,
            'takedown_ends_at' => $this->takedownEndsAt,
            'collect_starts_at' => $this->collectStartsAt,
            'collect_ends_at' => $this->collectEndsAt,
            'unload_starts_at' => $this->unloadStartsAt,
            'unload_ends_at' => $this->unloadEndsAt,
            'deprep_starts_at' => $this->deprepStartsAt,
            'deprep_ends_at' => $this->deprepEndsAt,
            'ordered_at' => $this->orderedAt,
            'quote_invalid_at' => $this->quoteInvalidAt,
            'use_chargeable_days' => $this->useChargeableDays,
            'chargeable_days' => $this->chargeableDays !== null && $this->chargeableDays !== '' ? $this->chargeableDays : null,
            'open_ended_rental' => $this->openEndedRental,
            'customer_collecting' => $this->customerCollecting,
            'customer_returning' => $this->customerReturning,
            'delivery_instructions' => $this->deliveryInstructions ?: null,
            'collection_instructions' => $this->collectionInstructions ?: null,
            'delivery_address_id' => $this->deliveryAddressId,
            'collection_address_id' => $this->collectionAddressId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updatePayload(): array
    {
        // currency + prices_include_tax are create-only on the write surface, so
        // they are deliberately omitted from the update payload.
        return [
            'subject' => $this->subject,
            'member_id' => $this->memberId,
            'venue_id' => $this->venueId,
            'store_id' => $this->storeId,
            'owned_by' => $this->ownedBy,
            'reference' => $this->reference ?: null,
            'description' => $this->description ?: null,
            'external_description' => $this->externalDescription ?: null,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'planning_starts_at' => $this->planningStartsAt,
            'planning_ends_at' => $this->planningEndsAt,
            'charge_starts_at' => $this->chargeStartsAt,
            'charge_ends_at' => $this->chargeEndsAt,
            'reservation_priority' => $this->reservationPriority,
            'tag_list' => array_values($this->tags),
            'custom_fields' => $this->customFieldValues,
            ...$this->scheduleAndLogisticsPayload(),
        ];
    }

    /**
     * Active custom field definitions for the Opportunity module.
     *
     * @return Collection<int, CustomField>
     */
    #[Computed]
    public function customFields(): Collection
    {
        return CustomField::query()
            ->forModule('Opportunity')
            ->active()
            ->with(['group', 'listName.values'])
            ->orderBy('sort_order')
            ->get();
    }

    // -------------------------------------------------------------------------
    // Participant add panel (#6) — edit mode only
    // -------------------------------------------------------------------------

    /**
     * Search organisation/contact members not already attached, for the participant
     * picker. Mirrors the participants tab's getMemberOptionsProperty.
     *
     * @return Collection<int, Member>
     */
    #[Computed]
    public function participantMemberOptions(): Collection
    {
        if ($this->opportunityId === null || trim($this->participantMemberSearch) === '') {
            return collect();
        }

        $like = (string) config('database.connections.'.config('database.default').'.driver') === 'pgsql' ? 'ilike' : 'like';

        return Member::query()
            ->where('is_active', true)
            ->whereIn('membership_type', [MembershipType::Organisation, MembershipType::Contact])
            ->whereNotIn('id', OpportunityParticipant::query()->where('opportunity_id', $this->opportunityId)->pluck('member_id'))
            ->where('name', $like, '%'.RansackFilter::escapeLike($this->participantMemberSearch).'%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'membership_type']);
    }

    /**
     * The opportunity's current participants (edit mode), member eager-loaded.
     *
     * @return Collection<int, OpportunityParticipant>
     */
    #[Computed]
    public function participants(): Collection
    {
        if ($this->opportunityId === null) {
            return collect();
        }

        return OpportunityParticipant::query()
            ->where('opportunity_id', $this->opportunityId)
            ->with('member')
            ->get();
    }

    public function selectParticipantMember(int $id): void
    {
        $member = Member::query()
            ->where('is_active', true)
            ->whereIn('membership_type', [MembershipType::Organisation, MembershipType::Contact])
            ->find($id);

        if ($member === null) {
            return;
        }

        $this->participantMemberId = $member->id;
        $this->participantMemberSelectedName = $member->name;
        $this->participantMemberSearch = '';
    }

    public function clearParticipantMember(): void
    {
        $this->participantMemberId = null;
        $this->participantMemberSelectedName = null;
        $this->participantMemberSearch = '';
    }

    /**
     * Attach the selected member to the opportunity as a participant, via the SAME
     * AddOpportunityParticipant action the participants tab + API use. Edit-mode only.
     */
    public function addParticipant(): void
    {
        if ($this->opportunityId === null) {
            return;
        }

        Gate::authorize('opportunities.edit');

        if ($this->participantMemberId === null) {
            throw ValidationException::withMessages([
                'participantMemberId' => 'Select a member to add.',
            ]);
        }

        $opportunity = Opportunity::findOrFail($this->opportunityId);

        (new AddOpportunityParticipant)($opportunity, AddOpportunityParticipantData::from([
            'member_id' => $this->participantMemberId,
            'role' => trim($this->participantRole) === '' ? null : trim($this->participantRole),
            'mute' => $this->participantMute,
        ]));

        $this->reset(['participantMemberId', 'participantMemberSelectedName', 'participantMemberSearch', 'participantRole', 'participantMute']);
        unset($this->participants);
    }

    public function removeParticipant(int $participantId): void
    {
        if ($this->opportunityId === null) {
            return;
        }

        $participant = OpportunityParticipant::query()
            ->where('opportunity_id', $this->opportunityId)
            ->findOrFail($participantId);

        (new RemoveOpportunityParticipant)($participant);

        unset($this->participants);
    }

    /**
     * Set the browser/page title so the create + edit forms read
     * "<Name> | Signals" like the show/list/assets/versions pages, rather than
     * the bare "Signals" fallback.
     */
    public function rendering(View $view): void
    {
        $view->title($this->opportunityId !== null
            ? $this->subject.' — '.__('Edit')
            : __('New Opportunity'));
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $addressTypes = ListName::query()->where('name', 'Address Type')->first()
            ?->values()->where('is_active', true)->orderBy('sort_order')->get() ?? collect();

        return [
            'isEditing' => $this->opportunityId !== null,
            'opportunity' => $this->opportunityId ? Opportunity::find($this->opportunityId) : null,
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::query()->where('is_enabled', true)->orderBy('code')->get(['code', 'name']),
            'owners' => Member::query()->ofType(MembershipType::User)->orderBy('name')->get(['id', 'name']),
            'groupedCustomFields' => $this->customFields()->groupBy(fn (CustomField $f): string => (string) (data_get($f, 'group.name') ?? 'General')),
            'memberAddresses' => $this->memberAddresses(),
            'countries' => Country::query()->active()->orderBy('name')->get(['id', 'name']),
            'addressTypes' => $addressTypes,
            'what3wordsConfigured' => app(What3WordsService::class)->isConfigured(),
        ];
    }

    /**
     * Resolve the display name for the currently selected member.
     */
    private function resolveMemberName(): void
    {
        if ($this->memberId === null) {
            return;
        }

        $this->memberSelectedName = Member::query()->where('id', $this->memberId)->value('name') ?? '';
    }

    /**
     * Resolve the display name for the currently selected venue.
     */
    private function resolveVenueName(): void
    {
        if ($this->venueId === null) {
            return;
        }

        $this->venueSelectedName = Member::query()->where('id', $this->venueId)->value('name') ?? '';
    }

    public function render(): View
    {
        return view('project-opportunities::form');
    }
}
