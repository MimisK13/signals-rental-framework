<?php

declare(strict_types=1);

namespace App\Domain\Attachments\Presentation\Livewire;

use App\Domain\Attachments\Presentation\Concerns\HasFileActions;
use App\Domain\Products\Models\Product;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class ProductFiles extends Component
{
    use HasFileActions;

    public Product $product;

    public ?int $deleteAttachmentId = null;

    public function mount(Product $product): void
    {
        $this->product = $product->loadCount(['stockLevels', 'accessories', 'attachments']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Files');
    }

    protected function getFileableModel(): Product
    {
        return $this->product;
    }

    public function render(): View
    {
        return view('attachments::product-files', $this->fileData());
    }
}
