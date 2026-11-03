<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\Members\Actions\ArchiveMember;
use App\Domain\Members\Actions\RestoreMember;
use App\Domain\Members\Enums\MembershipType;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Presentation\Columns\MemberColumnRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Members')] final class Index extends Component
{
    #[Url(as: 'type')]
    public string $typeFilter = '';

    #[Url(as: 'archive')]
    public string $archiveFilter = 'active';

    /** @var Collection<string, int> */
    public Collection $typeCounts;

    public int $totalCount = 0;

    public function mount(): void
    {
        $type = request()->query('type', '');
        if ($type !== '' && MembershipType::tryFrom($type)) {
            $this->typeFilter = $type;
        }

        $this->refreshTypeCounts();
    }

    public function setTypeFilter(string $type): void
    {
        if ($type !== '' && MembershipType::tryFrom($type) === null) {
            return;
        }

        $this->typeFilter = $type;
    }

    public function archiveMember(int $memberId): void
    {
        $member = Member::findOrFail($memberId);
        (new ArchiveMember)($member);
        $this->refreshTypeCounts();
        $this->dispatch('member-archived');
    }

    public function restoreMember(int $memberId): void
    {
        $member = Member::withTrashed()->findOrFail($memberId);
        (new RestoreMember)($member);
        $this->refreshTypeCounts();
        $this->dispatch('member-restored');
    }

    /**
     * @param  array<int, int>  $ids
     */
    public function archiveSelected(array $ids): void
    {
        $members = Member::whereIn('id', $ids)->get();

        DB::transaction(function () use ($members): void {
            foreach ($members as $member) {
                (new ArchiveMember)($member);
            }
        });

        $this->refreshTypeCounts();
        $this->dispatch('member-archived');
    }

    #[On('member-archived')]
    #[On('member-restored')]
    public function refreshTypeCounts(): void
    {
        $query = match ($this->archiveFilter) {
            'archived' => Member::onlyTrashed(),
            'all' => Member::withTrashed(),
            default => Member::query(),
        };

        $this->typeCounts = $query
            ->selectRaw('membership_type, count(*) as count')
            ->groupBy('membership_type')
            ->pluck('count', 'membership_type');

        $this->totalCount = $this->typeCounts->sum();
    }

    public function setArchiveFilter(string $filter): void
    {
        if (! in_array($filter, ['active', 'archived', 'all'])) {
            return;
        }

        $this->archiveFilter = $filter;
        $this->refreshTypeCounts();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $typeOptions = collect(MembershipType::cases())
            ->mapWithKeys(fn (MembershipType $t): array => [$t->value => $t->label()])
            ->all();

        // Derive sortable/filterable flags from the canonical MemberColumnRegistry
        // rather than duplicating them inline, so the table column metadata cannot
        // drift from the registry that drives custom views and the column picker.
        // Only columns that map 1:1 to a real `members` column take their flags from
        // the registry — the DataTable sorts/filters directly on the column key, so
        // relation-backed display columns (email/phone) stay flagless here even
        // though Ransack/custom-views can resolve them through relationships.
        $registry = app(MemberColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)->sortable ?? false,
            'filterable' => $registry->get($key)->filterable ?? false,
        ];

        return [
            'membershipTypes' => [
                MembershipType::Organisation,
                MembershipType::Venue,
                MembershipType::Contact,
            ],
            'totalCount' => $this->totalCount,
            'typeCounts' => $this->typeCounts,
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'avatar', 'label' => '', 'view' => 'members::partials.column-avatar'],
                ['key' => 'name', 'label' => 'Name', ...$columnMeta('name'), 'filter_type' => 'text', 'view' => 'members::partials.column-name'],
                ['key' => 'membership_type', 'label' => 'Type', ...$columnMeta('membership_type'), 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'members::partials.column-type'],
                ['key' => 'email', 'label' => 'Primary Email', 'view' => 'members::partials.column-email'],
                ['key' => 'phone', 'label' => 'Primary Phone', 'view' => 'members::partials.column-phone'],
                ['key' => 'is_active', 'label' => 'Status', ...$columnMeta('is_active'), 'filter_type' => 'select', 'filter_options' => ['1' => 'Active', '0' => 'Inactive'], 'view' => 'livewire.partials.column-active-status'],
                ['key' => 'tag_list', 'label' => 'Tags', 'view' => 'members::partials.column-tags'],
                ['key' => 'created_at', 'label' => 'Created', ...$columnMeta('created_at'), 'view' => 'members::partials.column-created'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                ...($this->typeFilter !== '' ? ['ofType' => MembershipType::from($this->typeFilter)] : []),
                ...match ($this->archiveFilter) {
                    'archived' => ['archived' => true],
                    'all' => ['withArchived' => true],
                    default => [],
                },
            ],
        ];
    }

    public function render(): View
    {
        return view('members::index', $this->with());
    }
}
