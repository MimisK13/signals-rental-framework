<?php

declare(strict_types=1);

namespace App\Domain\Administration\Presentation\Livewire\Settings;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Appearance')]
final class Appearance extends Component
{
    //

    public function render(): View
    {
        return view('administration::settings.appearance');
    }
}
