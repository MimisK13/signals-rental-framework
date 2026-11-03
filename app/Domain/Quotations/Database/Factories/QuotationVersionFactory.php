<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Database\Factories;

use App\Domain\Numbering\Services\SequenceAllocator;
use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Enums\QuotationVersionType;
use App\Domain\Quotations\Models\QuotationVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuotationVersion> */
final class QuotationVersionFactory extends Factory
{
    protected $model = QuotationVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => app(SequenceAllocator::class)->next('quotation_versions'),
            'state_id' => snowflake_id(),
            'quotation_id' => QuotationFactory::new(),
            'version_number' => 1,
            'version_type' => QuotationVersionType::Original,
            'label' => 'Version 1',
            'is_active' => true,
            'status' => QuotationVersionStatus::Draft,
            'currency_code' => 'EUR',
            'exchange_rate' => '1',
            'prices_include_tax' => false,
        ];
    }
}
