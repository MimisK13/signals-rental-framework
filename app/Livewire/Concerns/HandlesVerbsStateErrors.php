<?php

namespace App\Livewire\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Exceptions\EventNotValid;

/**
 * Surfaces Verbs state-guard and authorization failures as operator toasts instead of 500s.
 */
trait HandlesVerbsStateErrors
{
    /**
     * @param  \Closure(): mixed  $action
     * @param  \Closure(): void|null  $onSuccess
     */
    protected function runVerbsTransition(\Closure $action, ?\Closure $onSuccess = null): void
    {
        try {
            $action();

            if ($onSuccess !== null) {
                $onSuccess();
            }
        } catch (AuthorizationException) {
            $this->dispatch('notify', message: __('You do not have permission to perform this action.'));
        } catch (EventNotValid $e) {
            $this->dispatch('notify', message: $e->getMessage() ?: __('This action could not be completed.'));
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();

            $this->dispatch('notify', message: $message ?: __('This action could not be completed.'));
        }
    }
}
