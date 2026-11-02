<?php

declare(strict_types=1);

namespace App\Domain\Rates\Presentation\Livewire;

use App\Domain\Rates\Actions\CreateRateDefinition;
use App\Domain\Rates\Actions\UpdateRateDefinition;
use App\Domain\Rates\Data\CreateRateDefinitionData;
use App\Domain\Rates\Data\UpdateRateDefinitionData;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\Rates\Services\RateEngine\Presets\RatePresets;
use App\Domain\Rates\Services\RateEngine\RateEngineRegistry;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Rate Definition')]
final class RateDefinitionForm extends Component
{
    public ?int $definitionId = null;

    public bool $presetChosen = false;

    public string $name = '';

    public string $description = '';

    public string $calculationStrategy = 'period';

    public ?string $basePeriod = null;

    /** @var list<string> */
    public array $enabledModifiers = [];

    /** @var array<string, mixed> */
    public array $strategyConfig = [];

    /** @var array<string, array<string, mixed>> */
    public array $modifierConfigs = [];

    public function mount(?RateDefinition $rateDefinition = null): void
    {
        if ($rateDefinition?->exists) {
            $this->definitionId = $rateDefinition->id;
            $this->presetChosen = true;
            $this->name = $rateDefinition->name;
            $this->description = $rateDefinition->description ?? '';
            $this->calculationStrategy = $rateDefinition->calculation_strategy->value;
            $this->basePeriod = $rateDefinition->base_period?->value;
            $this->enabledModifiers = $rateDefinition->enabled_modifiers ?? [];
            $this->strategyConfig = $rateDefinition->strategy_config ?? [];
            $this->modifierConfigs = $rateDefinition->modifier_configs ?? [];
        }
    }

    public function choosePreset(string $slug): void
    {
        $preset = collect(RatePresets::all())->firstWhere('slug', $slug);

        if ($preset === null) {
            return;
        }

        $this->name = $preset['name'];
        $this->description = $preset['description'];
        $this->calculationStrategy = $preset['calculation_strategy']->value;
        $this->basePeriod = $preset['base_period']?->value;
        $this->enabledModifiers = $preset['enabled_modifiers'];
        $this->strategyConfig = $this->registry()->strategy($this->calculationStrategy)->configSchema()->defaults();
        $this->modifierConfigs = [];

        foreach ($this->enabledModifiers as $id) {
            $this->modifierConfigs[$id] = $this->registry()->modifier($id)->configSchema()->defaults();
        }

        $this->presetChosen = true;
    }

    public function fromScratch(): void
    {
        $this->calculationStrategy = 'period';
        $this->applyStrategyDefaults();
        $this->presetChosen = true;
    }

    public function updatedCalculationStrategy(): void
    {
        $this->enabledModifiers = array_values(array_filter(
            $this->enabledModifiers,
            fn (string $id): bool => $this->strategySupportsModifier($id),
        ));

        $this->applyStrategyDefaults(keepModifiers: true);
    }

    public function updatedEnabledModifiers(): void
    {
        $this->enabledModifiers = array_values(array_filter(
            $this->enabledModifiers,
            fn (string $id): bool => $this->strategySupportsModifier($id),
        ));

        foreach (array_keys($this->modifierConfigs) as $id) {
            if (! in_array($id, $this->enabledModifiers, true)) {
                unset($this->modifierConfigs[$id]);
            }
        }

        foreach ($this->enabledModifiers as $id) {
            if (! isset($this->modifierConfigs[$id])) {
                $this->modifierConfigs[$id] = $this->registry()->modifier($id)->configSchema()->defaults();
            }
        }
    }

    public function addRow(string $path): void
    {
        [$property, $rest] = $this->splitPath($path);
        $state = $this->stateFor($property);
        $rows = Arr::get($state, $rest, []);
        $rows = is_array($rows) ? $rows : [];
        $rows[] = [];
        Arr::set($state, $rest, array_values($rows));
        $this->putState($property, $state);
    }

    public function removeRow(string $path, int $index): void
    {
        [$property, $rest] = $this->splitPath($path);
        $state = $this->stateFor($property);
        $rows = array_values(Arr::get($state, $rest, []));
        unset($rows[$index]);
        Arr::set($state, $rest, array_values($rows));
        $this->putState($property, $state);
    }

    public function moveRow(string $path, int $index, int $direction): void
    {
        [$property, $rest] = $this->splitPath($path);
        $state = $this->stateFor($property);
        $rows = array_values(Arr::get($state, $rest, []));
        $target = $index + $direction;

        if ($target < 0 || $target >= count($rows)) {
            return;
        }

        [$rows[$index], $rows[$target]] = [$rows[$target], $rows[$index]];
        Arr::set($state, $rest, $rows);
        $this->putState($property, $state);
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'calculationStrategy' => ['required', 'string'],
        ]);

        $payload = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'calculation_strategy' => $this->calculationStrategy,
            'base_period' => $this->basePeriod,
            'enabled_modifiers' => $this->enabledModifiers,
            'strategy_config' => $this->strategyConfig,
            'modifier_configs' => $this->modifierConfigs,
        ];

        try {
            if ($this->definitionId !== null) {
                $definition = RateDefinition::findOrFail($this->definitionId);
                (new UpdateRateDefinition)($definition, UpdateRateDefinitionData::from($payload));
            } else {
                (new CreateRateDefinition)(CreateRateDefinitionData::from($payload));
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError($this->mapConfigErrorKey($key), $message);
                }
            }

            return;
        }

        $this->redirect(route('admin.settings.rate-definitions'), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(): array
    {
        $registry = $this->registry();
        $strategy = $registry->strategy($this->calculationStrategy);

        return [
            'isEditing' => $this->definitionId !== null,
            'presets' => RatePresets::all(),
            'strategies' => $registry->strategies(),
            'basePeriodOptions' => $strategy->allowedBasePeriods(),
            'availableModifiers' => array_values(array_filter(
                $registry->modifiers(),
                fn ($modifier): bool => $this->strategySupportsModifier($modifier->identifier()),
            )),
            'sections' => $this->presetChosen
                ? $registry->composeSections($this->calculationStrategy, $this->enabledModifiers)
                : [],
        ];
    }

    private function registry(): RateEngineRegistry
    {
        return app(RateEngineRegistry::class);
    }

    private function strategySupportsModifier(string $id): bool
    {
        $strategy = $this->registry()->strategy($this->calculationStrategy);

        return match ($id) {
            'multiplier' => $strategy->supportsMultiplier(),
            'factor' => $strategy->supportsFactor(),
            default => false,
        };
    }

    private function applyStrategyDefaults(bool $keepModifiers = false): void
    {
        $strategy = $this->registry()->strategy($this->calculationStrategy);
        $allowed = array_map(fn ($period): string => $period->value, $strategy->allowedBasePeriods());

        if (! in_array($this->basePeriod, $allowed, true)) {
            $this->basePeriod = $allowed[0] ?? null;
        }

        $this->strategyConfig = $strategy->configSchema()->defaults();

        if (! $keepModifiers) {
            $this->enabledModifiers = [];
        }

        $configs = [];
        foreach ($this->enabledModifiers as $id) {
            $configs[$id] = $this->modifierConfigs[$id] ?? $this->registry()->modifier($id)->configSchema()->defaults();
        }
        $this->modifierConfigs = $configs;
    }

    /**
     * @return array<string, mixed>
     */
    private function stateFor(string $property): array
    {
        return $property === 'strategyConfig' ? $this->strategyConfig : $this->modifierConfigs;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function putState(string $property, array $state): void
    {
        if ($property === 'strategyConfig') {
            $this->strategyConfig = $state;
        } else {
            $this->modifierConfigs = $state;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitPath(string $path): array
    {
        $property = strtok($path, '.') ?: $path;

        return [$property, substr($path, strlen($property) + 1)];
    }

    private function mapConfigErrorKey(string $key): string
    {
        if (str_starts_with($key, 'strategy_config')) {
            return 'strategyConfig'.substr($key, strlen('strategy_config'));
        }

        if (str_starts_with($key, 'modifier_configs')) {
            return 'modifierConfigs'.substr($key, strlen('modifier_configs'));
        }

        return $key;
    }

    public function render(): View
    {
        return view('rates::rate-definition-form', $this->viewData());
    }
}
