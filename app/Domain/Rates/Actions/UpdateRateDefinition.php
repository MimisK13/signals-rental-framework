<?php

declare(strict_types=1);

namespace App\Domain\Rates\Actions;

use App\Domain\Rates\Actions\Concerns\ValidatesRateConfig;
use App\Domain\Rates\Data\RateDefinitionData;
use App\Domain\Rates\Data\UpdateRateDefinitionData;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Rates\Services\RateEngine\RateEngineRegistry;
use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateRateDefinition
{
    use ValidatesRateConfig;

    public function __invoke(RateDefinition $definition, UpdateRateDefinitionData $data): RateDefinitionData
    {
        Gate::authorize('rates.edit');

        // Resolve the effective strategy, modifiers and config (incoming values
        // override the persisted ones) so the whole config validates together.
        $strategyType = $data->calculation_strategy ?? $definition->calculation_strategy;
        $strategy = $strategyType->value;
        $basePeriod = $data->base_period ?? $definition->base_period;
        $enabledModifiers = $data->enabled_modifiers ?? $definition->enabled_modifiers ?? [];
        $strategyConfig = $data->strategy_config ?? $definition->strategy_config ?? [];
        $modifierConfigs = $data->modifier_configs ?? $definition->modifier_configs ?? [];

        $this->validateStrategyCompatibility($strategyType, $basePeriod, $enabledModifiers);
        $this->validateRateConfig($strategy, $enabledModifiers, $strategyConfig, $modifierConfigs);

        return DB::transaction(function () use ($definition, $data, $strategyType, $strategy, $basePeriod, $enabledModifiers, $strategyConfig, $modifierConfigs): RateDefinitionData {
            $registry = app(RateEngineRegistry::class);

            if ($data->name !== null) {
                $definition->name = $data->name;
            }

            if ($data->description !== null) {
                $definition->description = $data->description === '' ? null : $data->description;
            }

            if ($data->calculation_strategy !== null) {
                $definition->calculation_strategy = $data->calculation_strategy;
            }

            // Strategies that do not use a base period (e.g. fixed) have it cleared
            // so switching strategy never leaves a stale period behind.
            $definition->base_period = $strategyType->requiresBasePeriod() ? $basePeriod : null;

            $definition->enabled_modifiers = $enabledModifiers;
            $definition->strategy_config = $registry->sanitiseStrategyConfig($strategy, $strategyConfig);
            $definition->modifier_configs = $registry->sanitiseModifierConfigs($enabledModifiers, $modifierConfigs);
            $definition->save();

            event(new AuditableEvent($definition, 'rate_definition.updated'));

            app(WebhookService::class)->dispatch('rate_definition.updated', [
                'rate_definition' => RateDefinitionData::fromModel($definition)->toArray(),
            ]);

            return RateDefinitionData::fromModel($definition);
        });
    }
}
