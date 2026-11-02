<?php

declare(strict_types=1);

namespace App\Domain\Rates\Actions;

use App\Domain\Rates\Actions\Concerns\ValidatesRateConfig;
use App\Domain\Rates\Data\CreateRateDefinitionData;
use App\Domain\Rates\Data\RateDefinitionData;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Rates\Services\RateEngine\RateEngineRegistry;
use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateRateDefinition
{
    use ValidatesRateConfig;

    public function __invoke(CreateRateDefinitionData $data): RateDefinitionData
    {
        Gate::authorize('rates.create');

        $strategy = $data->calculation_strategy->value;

        $this->validateStrategyCompatibility($data->calculation_strategy, $data->base_period, $data->enabled_modifiers);
        $this->validateRateConfig($strategy, $data->enabled_modifiers, $data->strategy_config, $data->modifier_configs);

        $basePeriod = $data->calculation_strategy->requiresBasePeriod() ? $data->base_period : null;

        return DB::transaction(function () use ($data, $strategy, $basePeriod): RateDefinitionData {
            $registry = app(RateEngineRegistry::class);

            $definition = RateDefinition::create([
                'name' => $data->name,
                'description' => $data->description,
                'calculation_strategy' => $data->calculation_strategy,
                'base_period' => $basePeriod,
                'enabled_modifiers' => $data->enabled_modifiers,
                'strategy_config' => $registry->sanitiseStrategyConfig($strategy, $data->strategy_config),
                'modifier_configs' => $registry->sanitiseModifierConfigs($data->enabled_modifiers, $data->modifier_configs),
                'is_preset' => false,
            ]);

            event(new AuditableEvent($definition, 'rate_definition.created'));

            app(WebhookService::class)->dispatch('rate_definition.created', [
                'rate_definition' => RateDefinitionData::fromModel($definition)->toArray(),
            ]);

            return RateDefinitionData::fromModel($definition);
        });
    }
}
