<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Livewire;

use App\Domain\AuditLog\Presentation\Concerns\HasAuditTimeline;
use App\Domain\Members\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')] final class Show extends Component
{
    use HasAuditTimeline;

    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount([
            'addresses', 'emails', 'phones', 'links', 'organisations', 'contacts', 'attachments',
        ]);
        $this->member->load(['saleTaxClass', 'purchaseTaxClass', 'organisations', 'contacts']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        // Resolve related members across both relationship directions so a link
        // appears on each member regardless of how it was created.
        $relationships = $this->member->allRelationships();

        $relatedMembers = $relationships
            ->map(fn ($r) => tap($this->member->counterpartIn($r), function ($other) use ($r): void {
                if ($other) {
                    $other->setRelation('pivot', (object) [
                        'relationship_type' => $r->relationship_type,
                        'is_primary' => $r->is_primary,
                    ]);
                }
            }))
            ->filter()
            ->values();

        return [
            'relatedMembers' => $relatedMembers,
            'relatedCount' => $relatedMembers->count(),
            'timeline' => $this->auditTimelineFor($this->member),
        ];
    }

    /**
     * Member-specific timeline colours: archive/anonymise read as destructive
     * (red), merges as a change (blue).
     *
     * @return array<string, list<string>>
     */
    protected function timelineColorMap(): array
    {
        return [
            'green' => ['.created', '.restored'],
            'red' => ['.deleted', '.archived', '.anonymised'],
            'blue' => ['.updated', '.merged'],
        ];
    }

    public function render(): View
    {
        return view('members::show', $this->with());
    }
}
