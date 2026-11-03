<?php

declare(strict_types=1);

namespace App\Domain\Rates\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Rates\Data\RateDefinitionData;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DuplicateRateDefinition
{
    public function __invoke(RateDefinition $definition): RateDefinitionData
    {
        Gate::authorize('rates.create');

        return DB::transaction(function () use ($definition): RateDefinitionData {
            $copy = RateDefinition::create([
                'name' => $definition->name.' (Copy)',
                'description' => $definition->description,
                'calculation_strategy' => $definition->calculation_strategy,
                'base_period' => $definition->base_period,
                'enabled_modifiers' => $definition->enabled_modifiers,
                'strategy_config' => $definition->strategy_config,
                'modifier_configs' => $definition->modifier_configs,
                'is_preset' => false,
                'preset_slug' => null,
                'cloned_from_id' => $definition->id,
            ]);

            event(new AuditableEvent($copy, 'rate_definition.created'));

            app(WebhookService::class)->dispatch('rate_definition.created', [
                'rate_definition' => RateDefinitionData::fromModel($copy)->toArray(),
            ]);

            return RateDefinitionData::fromModel($copy);
        });
    }
}
