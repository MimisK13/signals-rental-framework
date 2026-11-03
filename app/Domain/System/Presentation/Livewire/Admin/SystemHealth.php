<?php

declare(strict_types=1);

namespace App\Domain\System\Presentation\Livewire\Admin;

use App\Domain\System\Services\SystemHealthService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('System Health')]
final class SystemHealth extends Component
{
    /** @var array<int, array{name: string, status: string, details: array<string, mixed>}> */
    public array $checks = [];

    public function mount(): void
    {
        $this->runChecks();
    }

    public function refresh(): void
    {
        $this->runChecks();
    }

    private function runChecks(): void
    {
        $this->checks = app(SystemHealthService::class)->check();
    }

    public function render(): View
    {
        return view('system::admin.system-health');
    }
}
