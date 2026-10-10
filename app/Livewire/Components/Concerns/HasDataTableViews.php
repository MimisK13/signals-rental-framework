<?php

namespace App\Livewire\Components\Concerns;

use App\Models\CustomView;
use App\Models\User;
use App\Models\UserViewPreference;
use App\Services\ViewResolver;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

trait HasDataTableViews
{
    #[Url(as: 'view')]
    public ?int $viewId = null;

    #[Locked]
    public ?string $entityType = null;

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $availableViews = [];

    public ?string $activeViewName = null;

    public function switchView(int $viewId): void
    {
        $this->viewId = $viewId;
        $this->resetPage();
        $this->applyActiveView();
        $this->syncSelectAllSelection();
    }

    public function clearView(): void
    {
        $this->viewId = null;
        $this->activeViewName = null;
        $this->resetPage();
        $this->syncSelectAllSelection();
    }

    #[On('view-saved')]
    public function onViewSaved(): void
    {
        $this->loadAvailableViews();
        $this->applyActiveView();
        $this->syncSelectAllSelection();
    }

    public function setDefaultView(): void
    {
        if ($this->viewId === null || $this->entityType === null) {
            return;
        }

        UserViewPreference::updateOrCreate(
            ['user_id' => auth()->id(), 'entity_type' => $this->entityType],
            ['custom_view_id' => $this->viewId],
        );
    }

    private function loadAvailableViews(): void
    {
        $user = auth()->user();

        if (! $user instanceof User || $this->entityType === null) {
            return;
        }

        $views = CustomView::query()
            ->forEntity($this->entityType)
            ->visibleTo($user)
            ->orderBy('visibility')
            ->orderBy('name')
            ->get();

        $this->availableViews = $views->groupBy('visibility')->map(
            fn (Collection $group): array => $group
                ->map(fn (CustomView $view): array => [
                    'id' => $view->id,
                    'name' => $view->name,
                    'is_default' => $view->is_default,
                ])
                ->values()
                ->all(),
        )->all();
    }

    private function applyActiveView(): void
    {
        if ($this->entityType === null) {
            return;
        }

        $user = auth()->user();
        $view = app(ViewResolver::class)->resolve(
            $this->entityType,
            $this->viewId,
            $user instanceof User ? $user : null,
        );

        if ($view !== null) {
            $this->viewId = $view->id;
            $this->activeViewName = $view->name;

            if (! empty($view->columns)) {
                $this->visibleColumnKeys = $view->columns;
            }

            if ($view->sort_column) {
                $this->sortField = $view->sort_column;
                $this->sortDirection = $view->sort_direction ?? 'asc';
            }

            if ($view->per_page && in_array($view->per_page, $this->perPageOptions, true)) {
                $this->perPage = $view->per_page;
            }
        } else {
            $this->visibleColumnKeys = [];
        }
    }
}
