<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Presentation\Livewire;

use App\Domain\Pricing\Actions\DeleteBundleRule;
use App\Domain\Pricing\Models\BundleRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Bundle Rules')] final class BundleRules extends Component
{
    /** @var Collection<int, BundleRule> */
    public $rules;

    public function mount(): void
    {
        $this->loadRules();
    }

    public function loadRules(): void
    {
        $this->rules = BundleRule::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    public function deleteRule(int $ruleId): void
    {
        $rule = BundleRule::findOrFail($ruleId);

        (new DeleteBundleRule)($rule);
        $this->loadRules();
    }

    public function render(): View
    {
        return view('pricing::admin.bundle-rules');
    }
}
