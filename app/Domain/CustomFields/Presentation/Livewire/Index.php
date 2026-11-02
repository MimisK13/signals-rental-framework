<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Presentation\Livewire;

use App\Domain\CustomFields\Actions\DeleteCustomField;
use App\Domain\CustomFields\Models\CustomField;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Custom Fields')]
final class Index extends Component
{
    public string $moduleFilter = '';

    public function delete(int $fieldId): void
    {
        $field = CustomField::findOrFail($fieldId);
        (new DeleteCustomField)($field);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'fields' => CustomField::query()
                ->with('group')
                ->when($this->moduleFilter, fn ($q, $m) => $q->forModule($m))
                ->orderBy('module_type')
                ->orderBy('sort_order')
                ->get(),
            'availableModules' => CustomField::query()
                ->distinct()
                ->pluck('module_type')
                ->sort()
                ->values(),
        ];
    }

    public function render(): View
    {
        return view('custom-fields::index', $this->with());
    }
}
