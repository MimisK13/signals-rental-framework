<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire\Concerns;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Support\Timezone;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait ChecksCrewAccess
{
    /** @return array{0: string, 1: string} */
    protected function crewRangeToUtc(string $start, string $end, string $startField = 'starts_at', string $endField = 'ends_at'): array
    {
        try {
            [$from, $to] = StaffingGuard::dates($start, $end, app(Timezone::class)->current());
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $errors[match ($field) {
                    'starts_at' => $startField,
                    'ends_at' => $endField,
                    default => $field,
                }] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        return [$from->toIso8601String(), $to->toIso8601String()];
    }

    public function bootChecksCrewAccess(): void
    {
        abort_unless(settings()->moduleEnabled('crew'), 404);
        Gate::authorize('crew.access');
    }

    protected function performCrewAction(Closure $action): bool
    {
        $this->resetValidation();
        try {
            $action();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return false;
        }
        $this->dispatch('toast', type: 'success', message: 'Crew record saved.');

        return true;
    }
}
