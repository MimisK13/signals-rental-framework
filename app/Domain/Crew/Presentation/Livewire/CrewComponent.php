<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire;

use App\Domain\Crew\Presentation\Livewire\Concerns\ChecksCrewAccess;
use Livewire\Component;

abstract class CrewComponent extends Component
{
    use ChecksCrewAccess;
}
