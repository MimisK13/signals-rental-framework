<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Currency\Services\CurrencyService;
use App\Domain\Invoices\Actions\SendMemberStatement;
use App\Domain\Invoices\Data\SendMemberStatementData;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Members\Models\Member;
use App\Domain\Stores\Models\Store;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class Invoices extends Component
{
    public Member $member;

    public ?int $statementStoreId = null;

    public function mount(Member $member): void
    {
        Gate::authorize('invoices.view');

        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
        $this->statementStoreId = (int) (Store::query()->orderBy('id')->value('id') ?? 0);
    }

    public function emailStatement(): void
    {
        Gate::authorize('sendCommunication', $this->member);

        (new SendMemberStatement)($this->member, SendMemberStatementData::from([
            'member_id' => $this->member->id,
            'store_id' => $this->statementStoreId,
        ]));
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name.' — Invoices');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $invoices = Invoice::query()
            ->where('member_id', $this->member->id)
            ->with('store')
            ->orderByDesc('created_at')
            ->get();

        $outstandingMinor = $invoices
            ->whereNotIn('status', ['draft', 'void', 'credited', 'paid'])
            ->sum('amount_outstanding');

        return [
            'invoices' => $invoices,
            'outstandingMinor' => $outstandingMinor,
            'currencyCode' => $invoices->first()->currency_code ?? app(CurrencyService::class)->baseCurrencyCode(),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function render(): View
    {
        return view('members::invoices', $this->with());
    }
}
