<div class="flex flex-col gap-6">
    @if (! $infrastructurePassed)
        {{-- Infrastructure pre-flight checks failed --}}
        <div class="flex flex-col gap-6">
            <div class="flex w-full flex-col gap-2">
                <h1 class="s-auth-heading">Infrastructure Check</h1>
                <p class="s-auth-description">The following services must be running before setup can begin.</p>
            </div>

            <div class="flex flex-col gap-3">
                @foreach ($infrastructureChecks as $name => $check)
                    <div @class([
                        'flex items-center gap-3 rounded-lg border px-4 py-3',
                        'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950' => $check['passed'],
                        'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950' => ! $check['passed'],
                    ])>
                        @if ($check['passed'])
                            <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400" />
                        @else
                            <flux:icon name="x-circle" class="size-5 text-red-600 dark:text-red-400" />
                        @endif
                        <div>
                            <div class="text-sm font-medium capitalize text-zinc-800 dark:text-zinc-200">{{ $name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $check['message'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <flux:callout variant="danger">
                <flux:callout.heading>Infrastructure not ready</flux:callout.heading>
                <flux:callout.text>Fix the failing checks above, then retry. Run <code>php artisan signals:install</code> if you haven't already.</flux:callout.text>
            </flux:callout>

            <div>
                <flux:button wire:click="retryInfrastructureChecks" variant="primary" size="sm">
                    Retry Checks
                </flux:button>
            </div>
        </div>
    @else
        {{-- Step indicator --}}
        <x-signals.stepper>
            @for ($i = 1; $i <= 6; $i++)
                <div class="s-stepper-stage">
                    @if ($i > 1)
                        <div @class(['s-stepper-line', 'done' => $i <= $currentStep])></div>
                    @endif
                    <button
                        wire:click="goToStep({{ $i }})"
                        @class([
                            's-stepper-circle',
                            'done' => $i < $currentStep,
                            'active' => $i === $currentStep,
                        ])
                        @if($i > $currentStep) disabled @endif
                    >
                        @if ($i < $currentStep)
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        @else
                            {{ $i }}
                        @endif
                    </button>
                </div>
            @endfor
        </x-signals.stepper>

        {{-- Step content — keyed to force full DOM replacement between steps --}}
        {{-- (prevents wire:ignore on Flux file inputs from persisting across steps) --}}
        <div wire:key="step-{{ $currentStep }}">
            @if ($currentStep === 1)
                @include('setup::steps.company')
            @elseif ($currentStep === 2)
                @include('setup::steps.stores')
            @elseif ($currentStep === 3)
                @include('setup::steps.profile')
            @elseif ($currentStep === 4)
                @include('setup::steps.branding')
            @elseif ($currentStep === 5)
                @include('setup::steps.admin')
            @elseif ($currentStep === 6)
                @include('setup::steps.review')
            @endif
        </div>

        {{-- Navigation --}}
        <div class="flex items-center justify-between border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <div>
                @if ($currentStep > 1)
                    <flux:button wire:click="previousStep" variant="ghost" size="sm">
                        Back
                    </flux:button>
                @endif
            </div>

            <div>
                @if ($currentStep < 6)
                    <flux:button wire:click="nextStep" variant="primary" size="sm">
                        Continue
                    </flux:button>
                @else
                    <flux:button wire:click="completeSetup" variant="primary" size="sm">
                        Complete Setup
                    </flux:button>
                @endif
            </div>
        </div>
    @endif
</div>
