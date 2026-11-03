<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Presentation\Livewire;

use App\Domain\CustomFields\Models\CustomField;
use App\Domain\Products\Models\Product;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ProductCustomFields extends Component
{
    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Custom Fields');
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $fields = CustomField::query()
            ->forModule('Product')
            ->active()
            ->with('group')
            ->orderBy('sort_order')
            ->get();

        $values = $this->product->customFieldValues()
            ->with('customField')
            ->get()
            ->keyBy('custom_field_id');

        $grouped = $fields->groupBy(
            fn (CustomField $field): string => $field->custom_field_group_id !== null
                ? $field->group->name
                : 'General',
        );

        return [
            'grouped' => $grouped,
            'values' => $values,
        ];
    }

    public function render(): View
    {
        return view('custom-fields::product-custom-fields');
    }
}
