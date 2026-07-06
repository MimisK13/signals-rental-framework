<?php

namespace App\Actions\Members;

use App\Data\Members\MemberData;
use App\Data\Members\UpdateMemberData;
use App\Enums\MembershipType;
use App\Events\AuditableEvent;
use App\Models\Member;
use App\Services\Api\WebhookService;
use App\Services\CustomFieldValidator;
use App\Services\Tax\MemberVatValidationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateMember
{
    public function __invoke(Member $member, UpdateMemberData $data): MemberData
    {
        Gate::authorize('members.edit');

        $attributes = array_filter($data->toArray(), fn ($v) => $v !== null);

        if (array_key_exists('price_category_id', $attributes) && $attributes['price_category_id'] !== $member->price_category_id) {
            if (! array_key_exists('price_category_tier_id', $attributes)) {
                $attributes['price_category_tier_id'] = null;
            }
        }

        if (array_key_exists('price_category_id', $attributes)
            || array_key_exists('price_category_tier_id', $attributes)) {
            $effectiveCategoryId = $attributes['price_category_id'] ?? $member->price_category_id;
            $effectiveTierId = array_key_exists('price_category_tier_id', $attributes)
                ? $attributes['price_category_tier_id']
                : $member->price_category_tier_id;

            MemberPriceCategoryTierValidator::assertTierBelongsToCategory($effectiveTierId, $effectiveCategoryId);
        }

        // A user-type member's name, active state and location type are managed
        // from the linked user's profile and are read-only here. Reject any
        // attempt to change them; other updates (e.g. custom fields) are still
        // permitted.
        if ($member->membership_type === MembershipType::User) {
            $protected = [
                'name' => 'name',
                'is_active' => 'active state',
                'location_type' => 'location type',
            ];

            foreach ($protected as $field => $label) {
                if (array_key_exists($field, $attributes) && $attributes[$field] != $member->{$field}) {
                    throw ValidationException::withMessages([
                        $field => ["A user-type member's {$label} is managed from the user's profile and cannot be edited here."],
                    ]);
                }
            }
        }

        $previousTaxNumber = $member->tax_number;

        $member->update($attributes);

        if ($data->custom_fields !== null) {
            app(CustomFieldValidator::class)->validate('Member', $data->custom_fields);
            $member->syncCustomFields($data->custom_fields);
        }

        $member->refresh();

        if (array_key_exists('tax_number', $attributes)) {
            app(MemberVatValidationService::class)->handleTaxNumberChange($member, $previousTaxNumber);
            $member->refresh();
        }

        event(new AuditableEvent($member, 'member.updated'));

        app(WebhookService::class)->dispatch('member.updated', [
            'member' => MemberData::fromModel($member)->toArray(),
        ]);

        return MemberData::fromModel($member);
    }
}
