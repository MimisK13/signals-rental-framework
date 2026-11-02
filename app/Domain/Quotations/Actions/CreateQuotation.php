<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Currency\Services\CurrencyService;
use App\Domain\Projects\Models\Project;
use App\Domain\Quotations\Data\CreateQuotationData;
use App\Domain\Quotations\Data\QuotationData;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Rules\EnsureQuotationCurrencyIsValid;
use App\Domain\Quotations\Services\QuotationNumberGenerator;
use App\Domain\Quotations\Verbs\Events\QuotationCreated;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Store;
use App\Services\SequenceAllocator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CreateQuotation
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly CurrencyService $currencyService,
        private readonly EnsureQuotationCurrencyIsValid $ensureCurrencyIsValid,
        private readonly QuotationNumberGenerator $numberGenerator,
        private readonly SequenceAllocator $sequenceAllocator,
    ) {}

    public function __invoke(CreateQuotationData $data): QuotationData
    {
        Gate::authorize('create', Quotation::class);

        $project = $data->project_id !== null
            ? Project::query()->findOrFail($data->project_id)
            : null;

        $memberId = $this->resolveCustomerId($data, $project);
        $venueId = $data->venue_id ?? $project?->venue_id;
        $ownerId = $data->owned_by ?? $project?->owned_by;
        $startsAt = $data->starts_at ?? $project?->starts_at?->toIso8601String();
        $endsAt = $data->ends_at ?? $project?->ends_at?->toIso8601String();

        Store::query()->findOrFail($data->store_id);
        $this->validateMemberTypes($memberId, $venueId, $ownerId);
        $this->validatePeriod($startsAt, $endsAt);

        $currency = ($this->ensureCurrencyIsValid)(
            $data->currency_code ?? $this->currencyService->baseCurrencyCode(),
        );
        $exchangeRate = $this->resolveExchangeRate($currency);
        $pricesIncludeTax = $data->prices_include_tax
            ?? (bool) settings('finance.prices_include_tax_default', false);

        $quotationId = $this->commitVerbs(function () use (
            $data,
            $project,
            $memberId,
            $venueId,
            $ownerId,
            $startsAt,
            $endsAt,
            $currency,
            $exchangeRate,
            $pricesIncludeTax,
        ): int {
            $quotationId = $this->sequenceAllocator->next('quotations');
            $versionId = $this->sequenceAllocator->next('quotation_versions');
            $number = $this->numberGenerator->next($data->store_id);

            QuotationCreated::fire(
                quotation_id: $quotationId,
                version_id: $versionId,
                project_id: $project?->id,
                member_id: $memberId,
                venue_id: $venueId,
                store_id: $data->store_id,
                owned_by: $ownerId,
                number: $number,
                subject: $data->subject,
                reference: $data->reference,
                description: $data->description,
                starts_at: $startsAt,
                ends_at: $endsAt,
                valid_until: $data->valid_until,
                currency_code: $currency,
                exchange_rate: $exchangeRate,
                prices_include_tax: $pricesIncludeTax,
                version_label: $data->version_label,
                version_notes: $data->version_notes,
                created_by: Auth::id(),
            );

            return $quotationId;
        });

        return QuotationData::fromModel(
            Quotation::query()->with('activeVersion')->findOrFail($quotationId),
        );
    }

    private function resolveCustomerId(CreateQuotationData $data, ?Project $project): int
    {
        if ($project?->member_id !== null && $data->member_id !== null && $project->member_id !== $data->member_id) {
            throw ValidationException::withMessages([
                'member_id' => 'A project quotation must use the project customer.',
            ]);
        }

        $memberId = $project !== null ? $project->member_id : $data->member_id;

        if ($memberId === null) {
            throw ValidationException::withMessages([
                'member_id' => 'Choose a customer before creating the quotation.',
            ]);
        }

        return $memberId;
    }

    private function validateMemberTypes(int $memberId, ?int $venueId, ?int $ownerId): void
    {
        $memberIds = array_values(array_unique(array_filter([$memberId, $venueId, $ownerId])));
        $members = Member::query()->whereIn('id', $memberIds)->get()->keyBy('id');

        if ($members->get($memberId)?->membership_type !== MembershipType::Organisation) {
            throw ValidationException::withMessages([
                'member_id' => 'The quotation customer must be an organisation.',
            ]);
        }

        if ($venueId !== null && $members->get($venueId)?->membership_type !== MembershipType::Venue) {
            throw ValidationException::withMessages([
                'venue_id' => 'The quotation venue must be a venue member.',
            ]);
        }

        if ($ownerId !== null && $members->get($ownerId)?->membership_type !== MembershipType::User) {
            throw ValidationException::withMessages([
                'owned_by' => 'The quotation owner must be a user member.',
            ]);
        }
    }

    private function validatePeriod(?string $startsAt, ?string $endsAt): void
    {
        if ($startsAt === null || $endsAt === null) {
            return;
        }

        if (Carbon::parse($endsAt)->lt(Carbon::parse($startsAt))) {
            throw ValidationException::withMessages([
                'ends_at' => 'The quotation end must be after or equal to its start.',
            ]);
        }
    }

    private function resolveExchangeRate(string $currency): string
    {
        $baseCurrency = $this->currencyService->baseCurrencyCode();

        if ($currency === $baseCurrency) {
            return '1.00000000';
        }

        try {
            return $this->currencyService->getRate($currency, $baseCurrency);
        } catch (\RuntimeException) {
            throw ValidationException::withMessages([
                'currency_code' => "No exchange rate is configured from {$currency} to {$baseCurrency}.",
            ]);
        }
    }
}
