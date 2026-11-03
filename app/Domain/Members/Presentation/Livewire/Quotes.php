<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Members\Models\Member;
use App\Domain\Quotations\Models\Quotation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')] final class Quotes extends Component
{
    use WithPagination;

    public Member $member;

    public function mount(Member $member): void
    {
        Gate::authorize('viewAny', Quotation::class);

        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name.' — Quotes');
    }

    public function render(): View
    {
        return view('members::quotes', [
            'quotations' => $this->quotations(),
        ]);
    }

    /** @return LengthAwarePaginator<int, Quotation> */
    private function quotations(): LengthAwarePaginator
    {
        return Quotation::query()
            ->with('project:id,name')
            ->where('member_id', $this->member->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);
    }
}
