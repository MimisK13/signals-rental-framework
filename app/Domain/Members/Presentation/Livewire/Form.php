<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Administration\Models\User;
use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Presentation\Concerns\LoadsCustomFieldValues;
use App\Domain\CustomFields\Presentation\Concerns\ReKeysCustomFieldErrors;
use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Models\ListValue;
use App\Domain\Members\Actions\CreateMember;
use App\Domain\Members\Actions\UpdateMember;
use App\Domain\Members\Data\CreateMemberData;
use App\Domain\Members\Data\UpdateMemberData;
use App\Domain\Members\Enums\MembershipType;
use App\Domain\Members\Models\Member;
use App\Domain\Pricing\Models\DiscountCategory;
use App\Domain\Pricing\Models\PriceCategory;
use App\Domain\Pricing\Models\PriceCategoryTier;
use App\Domain\Tax\Actions\RequestMemberVatValidation;
use App\Domain\Tax\Models\OrganisationTaxClass;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class Form extends Component
{
    use LoadsCustomFieldValues;
    use ReKeysCustomFieldErrors;

    public ?int $memberId = null;

    // Basic info
    public string $name = '';

    public string $membershipType = 'contact';

    public bool $isActive = true;

    public string $description = '';

    public ?string $locale = null;

    public ?string $defaultCurrencyCode = null;

    // Resource fields
    public bool $bookable = false;

    public ?int $locationTypeId = null;

    public int $dayCost = 0;

    public int $hourCost = 0;

    public int $distanceCost = 0;

    public int $flatRateCost = 0;

    // Tax & legal
    public ?int $lawfulBasisTypeId = null;

    public ?int $saleTaxClassId = null;

    public ?int $purchaseTaxClassId = null;

    // Organisation membership fields
    public ?string $accountNumber = null;

    public ?string $taxNumber = null;

    public ?bool $taxNumberValidated = null;

    public ?string $taxNumberValidatedAt = null;

    public bool $isCash = false;

    public bool $isOnStop = false;

    public ?int $ratingId = null;

    public ?int $ownedBy = null;

    public ?int $priceCategoryId = null;

    public ?int $priceCategoryTierId = null;

    public ?int $discountCategoryId = null;

    public ?int $invoiceTermId = null;

    public int $invoiceTermLength = 0;

    // Contact membership fields
    public ?string $title = null;

    public ?string $department = null;

    // Custom fields
    /** @var array<string, mixed> */
    public array $customFieldValues = [];

    public function mount(?Member $member = null): void
    {
        /** @var User|null $currentUser */
        $currentUser = auth()->user();
        $this->ownedBy = $currentUser?->member_id;

        // Pre-populate membership type from ?type= query param. User-type
        // members are created via invitation only, so 'user' is not accepted.
        $type = request()->query('type');
        if (is_string($type) && in_array($type, ['organisation', 'contact', 'venue'])) {
            $this->membershipType = $type;
        }

        if ($member?->exists) {
            $this->memberId = $member->id;
            $this->name = $member->name;
            $this->membershipType = $member->membership_type->value;
            $this->isActive = $member->is_active;
            $this->description = $member->description ?? '';
            $this->locale = $member->locale;
            $this->defaultCurrencyCode = $member->default_currency_code;
            $this->bookable = $member->bookable ?? false;
            $this->locationTypeId = $member->location_type;
            $this->dayCost = $member->day_cost ?? 0;
            $this->hourCost = $member->hour_cost ?? 0;
            $this->distanceCost = $member->distance_cost ?? 0;
            $this->flatRateCost = $member->flat_rate_cost ?? 0;
            $this->lawfulBasisTypeId = $member->lawful_basis_type_id;
            $this->saleTaxClassId = $member->sale_tax_class_id;
            $this->purchaseTaxClassId = $member->purchase_tax_class_id;
            $this->accountNumber = $member->account_number;
            $this->taxNumber = $member->tax_number;
            $this->taxNumberValidated = $member->tax_number_validated;
            $this->taxNumberValidatedAt = $member->tax_number_validated_at?->toIso8601String();
            $this->isCash = $member->is_cash ?? false;
            $this->isOnStop = $member->is_on_stop ?? false;
            $this->ratingId = $member->rating;
            $this->ownedBy = $member->owned_by;
            $this->priceCategoryId = $member->price_category_id;
            $this->priceCategoryTierId = $member->price_category_tier_id;
            $this->discountCategoryId = $member->discount_category_id;
            $this->invoiceTermId = $member->invoice_term_id;
            $this->invoiceTermLength = $member->invoice_term_length ?? 0;
            $this->title = $member->title;
            $this->department = $member->department;

            // Load existing custom field values
            $this->customFieldValues = $this->loadCustomFieldValuesFrom($member);
        }
    }

    public function recheckVatValidation(): void
    {
        if ($this->memberId === null) {
            return;
        }

        $member = Member::query()->findOrFail($this->memberId);

        app(RequestMemberVatValidation::class)($member);

        $member->refresh();

        $this->taxNumberValidated = $member->tax_number_validated;
        $this->taxNumberValidatedAt = $member->tax_number_validated_at?->toIso8601String();
    }

    public function updatedPriceCategoryId(): void
    {
        $this->priceCategoryTierId = null;
    }

    public function save(): void
    {
        $nameUniqueRule = Rule::unique('members', 'name')
            ->where('membership_type', $this->membershipType);

        if ($this->memberId) {
            $nameUniqueRule->ignore($this->memberId);
        }

        // Creatable types exclude User — users are created via invitation only.
        $creatableTypes = array_values(array_filter(
            array_column(MembershipType::cases(), 'value'),
            fn (string $value): bool => $value !== MembershipType::User->value,
        ));

        $member = $this->memberId ? Member::find($this->memberId) : null;

        // When editing, the existing (immutable) type is always allowed even if
        // it is User — only the create surface forbids selecting User.
        $allowedTypes = $member
            ? array_values(array_unique([...$creatableTypes, $member->membership_type->value]))
            : $creatableTypes;

        $rules = [
            'name' => ['required', 'string', 'max:255', $nameUniqueRule],
            'membershipType' => ['required', 'string', Rule::in($allowedTypes)],
        ];

        // Membership type cannot be changed after creation
        if ($member) {
            if ($member->membership_type->value !== $this->membershipType) {
                throw ValidationException::withMessages([
                    'membershipType' => ['Membership type cannot be changed after creation.'],
                ]);
            }

            // A user-type member's name, profile image, active state and location
            // type are managed from the user's own profile — not from this internal
            // edit screen. Ignore any submitted changes so they stay read-only here.
            if ($member->membership_type === MembershipType::User) {
                $this->name = $member->name;
                $this->isActive = $member->is_active;
                $this->locationTypeId = $member->location_type;
            }
        }

        $this->validate($rules);

        $payload = [
            'name' => $this->name,
            'membership_type' => $this->membershipType,
            'is_active' => $this->isActive,
            'description' => $this->description ?: null,
            'locale' => $this->locale,
            'default_currency_code' => $this->defaultCurrencyCode,
            'bookable' => $this->bookable,
            'location_type' => (int) ($this->locationTypeId ?? 0),
            'day_cost' => $this->dayCost,
            'hour_cost' => $this->hourCost,
            'distance_cost' => $this->distanceCost,
            'flat_rate_cost' => $this->flatRateCost,
            'lawful_basis_type_id' => $this->lawfulBasisTypeId,
            'sale_tax_class_id' => $this->saleTaxClassId,
            'purchase_tax_class_id' => $this->purchaseTaxClassId,
            'account_number' => $this->accountNumber,
            'tax_number' => $this->taxNumber,
            'is_cash' => $this->isCash,
            'is_on_stop' => $this->isOnStop,
            'rating' => (int) ($this->ratingId ?? 0),
            'owned_by' => $this->ownedBy,
            'price_category_id' => $this->priceCategoryId,
            'price_category_tier_id' => $this->priceCategoryTierId,
            'discount_category_id' => $this->discountCategoryId,
            'invoice_term_id' => $this->invoiceTermId,
            'invoice_term_length' => $this->invoiceTermLength,
            'title' => $this->title,
            'department' => $this->department,
            'custom_fields' => $this->customFieldValues,
        ];

        try {
            if ($this->memberId) {
                $member = Member::findOrFail($this->memberId);
                $result = (new UpdateMember)($member, UpdateMemberData::from($payload));
            } else {
                $result = (new CreateMember)(CreateMemberData::from($payload));
            }

            $this->redirect(route('members.show', $result->id), navigate: true);
        } catch (ValidationException $e) {
            $this->reKeyCustomFieldErrors($e, $this->referenceData()['customFields']->pluck('name')->all());
        }
    }

    /**
     * @return Collection<int, ListValue>
     */
    private function listValues(string $listName): Collection
    {
        return ListName::query()
            ->where('name', $listName)
            ->first()
            ?->values()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get() ?? collect();
    }

    /** @return Collection<int, PriceCategoryTier> */
    #[Computed]
    public function priceCategoryTierOptions(): Collection
    {
        if ($this->priceCategoryId === null) {
            return collect();
        }

        return PriceCategoryTier::query()
            ->where('price_category_id', $this->priceCategoryId)
            ->orderBy('sort_order')
            ->orderBy('tier_number')
            ->get();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function referenceData(): array
    {
        return [
            'taxClasses' => OrganisationTaxClass::query()->orderBy('name')->get(),
            'priceCategories' => PriceCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'discountCategories' => DiscountCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'ownerOptions' => Member::query()->ofType(MembershipType::User)->orderBy('name')->get(['id', 'name']),
            'lawfulBasisTypes' => $this->listValues('Lawful Basis Type'),
            'locationTypes' => $this->listValues('Location Type'),
            'ratings' => $this->listValues('Rating'),
            'invoiceTerms' => $this->listValues('Invoice Term'),
            'locales' => $this->listValues('Locale'),
            'currencies' => $this->listValues('Currency'),
            'customFields' => CustomField::query()
                ->forModule('Member')
                ->active()
                ->with(['group', 'listName.values'])
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $isEditing = $this->memberId !== null;
        $ref = $this->referenceData();

        return [
            'isEditing' => $isEditing,
            'member' => $isEditing ? Member::find($this->memberId)?->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']) : null,
            // User-type members are created via invitation, so 'User' is never
            // offered as a selectable type in the create/edit form.
            'membershipTypes' => array_values(array_filter(
                MembershipType::cases(),
                fn (MembershipType $t): bool => $t !== MembershipType::User,
            )),
            'isUserMember' => $this->membershipType === MembershipType::User->value,
            'taxClasses' => $ref['taxClasses'],
            'priceCategories' => $ref['priceCategories'],
            'priceCategoryTierOptions' => $this->priceCategoryTierOptions(),
            'discountCategories' => $ref['discountCategories'],
            'ownerOptions' => $ref['ownerOptions'],
            'lawfulBasisTypes' => $ref['lawfulBasisTypes'],
            'locationTypes' => $ref['locationTypes'],
            'ratings' => $ref['ratings'],
            'invoiceTerms' => $ref['invoiceTerms'],
            'locales' => $ref['locales'],
            'currencies' => $ref['currencies'],
            'isOrg' => in_array($this->membershipType, ['organisation', 'venue']),
            'isContact' => $this->membershipType === 'contact',
            'customFields' => $ref['customFields'],
            'groupedCustomFields' => $ref['customFields']->groupBy(fn ($f) => $f->group->name ?? 'General'),
        ];
    }

    public function render(): View
    {
        return view('members::form', $this->with());
    }
}
