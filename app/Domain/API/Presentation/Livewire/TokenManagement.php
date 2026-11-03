<?php

declare(strict_types=1);

namespace App\Domain\API\Presentation\Livewire;

use App\Domain\API\Services\AbilityRegistry;
use Illuminate\Contracts\View\View;
use Laravel\Sanctum\NewAccessToken;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('API')]
final class TokenManagement extends Component
{
    public string $tokenName = '';

    /** @var list<string> */
    public array $selectedAbilities = [];

    public bool $showCreateModal = false;

    public bool $showTokenValue = false;

    public string $plainTextToken = '';

    public ?int $confirmingRevocation = null;

    public bool $showEditModal = false;

    public ?int $editingTokenId = null;

    /** @var list<string> */
    public array $editAbilities = [];

    /**
     * @return array<string, string>
     */
    public function availableAbilities(): array
    {
        return app(AbilityRegistry::class)->labels();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'tokens' => auth()->user()->tokens()->orderByDesc('created_at')->get(),
            'abilityGroups' => app(AbilityRegistry::class)->groups(),
        ];
    }

    /**
     * Group a token's granted abilities by resource for compact display.
     *
     * @param  list<string>  $abilities
     * @return array<string, list<string>>
     */
    public function groupedAbilities(array $abilities): array
    {
        $grouped = [];

        foreach ($abilities as $ability) {
            if ($ability === '*') {
                $grouped['All'][] = 'all';

                continue;
            }

            $key = str($ability)->before(':')->toString();
            $action = str($ability)->contains(':') ? str($ability)->after(':')->toString() : $ability;
            $grouped[(string) str($key)->headline()][] = $action;
        }

        return $grouped;
    }

    public function openCreateModal(): void
    {
        $this->reset('tokenName', 'selectedAbilities', 'showTokenValue', 'plainTextToken');
        $this->showCreateModal = true;
    }

    public function createToken(): void
    {
        $this->validate([
            'tokenName' => ['required', 'string', 'max:255'],
            'selectedAbilities' => ['required', 'array', 'min:1'],
            'selectedAbilities.*' => ['string', 'in:'.implode(',', array_keys($this->availableAbilities()))],
        ]);

        /** @var NewAccessToken $token */
        $token = auth()->user()->createToken($this->tokenName, $this->selectedAbilities);

        $this->plainTextToken = $token->plainTextToken;
        $this->showCreateModal = false;
        $this->showTokenValue = true;

        $this->dispatch('token-created');
    }

    public function closeTokenDisplay(): void
    {
        $this->showTokenValue = false;
        $this->plainTextToken = '';
    }

    public function openEditModal(int $tokenId): void
    {
        $token = auth()->user()->tokens()->find($tokenId);

        if ($token === null) {
            return;
        }

        $this->editingTokenId = $token->id;
        $this->editAbilities = $token->abilities ?? [];
        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function updateToken(): void
    {
        $this->validate([
            'editAbilities' => ['required', 'array', 'min:1'],
            'editAbilities.*' => ['string', 'in:'.implode(',', array_keys($this->availableAbilities()))],
        ]);

        $token = auth()->user()->tokens()->find($this->editingTokenId);

        if ($token === null) {
            return;
        }

        $token->update(['abilities' => $this->editAbilities]);

        $this->showEditModal = false;
        $this->editingTokenId = null;
        $this->editAbilities = [];

        $this->dispatch('token-updated');
    }

    public function revokeToken(int $tokenId): void
    {
        auth()->user()->tokens()->where('id', $tokenId)->delete();
        $this->confirmingRevocation = null;
        $this->dispatch('token-revoked');
    }

    public function render(): View
    {
        return view('api::token-management', $this->with());
    }
}
