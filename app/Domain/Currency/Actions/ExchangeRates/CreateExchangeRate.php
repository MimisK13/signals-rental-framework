<?php

declare(strict_types=1);

namespace App\Domain\Currency\Actions\ExchangeRates;

use App\Domain\Currency\Data\ExchangeRates\CreateExchangeRateData;
use App\Domain\Currency\Data\ExchangeRates\ExchangeRateData;
use App\Domain\Currency\Models\ExchangeRate;
use Illuminate\Support\Facades\Gate;

final class CreateExchangeRate
{
    public function __invoke(CreateExchangeRateData $data): ExchangeRateData
    {
        Gate::authorize('settings.manage');

        if ($data->inverse_rate === null && bccomp($data->rate, '0', 8) === 0) {
            throw new \InvalidArgumentException('Exchange rate cannot be zero.');
        }

        $inverseRate = $data->inverse_rate ?? bcdiv('1', $data->rate, 8);

        $exchangeRate = ExchangeRate::create([
            'source_currency_code' => $data->source_currency_code,
            'target_currency_code' => $data->target_currency_code,
            'rate' => $data->rate,
            'inverse_rate' => $inverseRate,
            'source' => $data->source,
            'effective_at' => $data->effective_at ?? now(),
            'expires_at' => $data->expires_at,
        ]);

        return ExchangeRateData::fromModel($exchangeRate);
    }
}
