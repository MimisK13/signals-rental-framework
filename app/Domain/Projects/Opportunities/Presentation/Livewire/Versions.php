<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Presentation\Livewire;

use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\AuditLog\Presentation\Concerns\HasAuditTimeline;
use App\Domain\Projects\Opportunities\Actions\AcceptVersion;
use App\Domain\Projects\Opportunities\Actions\ActivateVersion;
use App\Domain\Projects\Opportunities\Actions\ChangeVersionLabel;
use App\Domain\Projects\Opportunities\Actions\CreateVersion;
use App\Domain\Projects\Opportunities\Actions\DeclineVersion;
use App\Domain\Projects\Opportunities\Actions\DeleteVersion;
use App\Domain\Projects\Opportunities\Actions\DiffVersions;
use App\Domain\Projects\Opportunities\Actions\SendVersion;
use App\Domain\Projects\Opportunities\Data\ChangeVersionLabelData;
use App\Domain\Projects\Opportunities\Data\CreateVersionData;
use App\Domain\Projects\Opportunities\Data\VersionDiffData;
use App\Domain\Projects\Opportunities\Enums\OpportunityState;
use App\Domain\Projects\Opportunities\Enums\VersionStatus;
use App\Domain\Projects\Opportunities\Enums\VersionType;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityVersion;
use App\Domain\Projects\Opportunities\Presentation\Concerns\HasOpportunityActions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Opportunity Versions tab (M8-5) — the quote-versioning UI
 * (opportunity-lifecycle.md §8).
 *
 * Renders the opportunity's quote versions as a tree — revisions (the supersede
 * chain) and alternatives, the ACTIVE version marked — using the shared
 * `<x-signals.version-tree>` component. Every mutation calls the SAME version
 * action classes the API controller uses ({@see CreateVersion}, {@see
 * ActivateVersion}, {@see AcceptVersion}, {@see DeclineVersion}, {@see SendVersion},
 * {@see ChangeVersionLabel}, {@see DeleteVersion}) — each authorises internally and
 * runs the atomic Verbs commit; this component never self-HTTPs. Two versions can be
 * selected to diff via {@see DiffVersions}.
 *
 * Lifecycle actions are only offered where they are legal for the version's status
 * and the opportunity's state (a Quotation): the legality mirrors the validate()
 * guards on the underlying version events, so an illegal move is never surfaced and,
 * if raced, surfaces as a flashed 422 rather than a crash.
 *
 * The RIGHT panel is the opportunity's own lifecycle event history (B4) — the
 * model-event timeline as originally spec'd (opportunity-lifecycle.md §"event stream
 * provides full audit trail"). It is rendered READ-ONLY from the opportunity's
 * `action_logs` rows: every state-mutating Verbs event records one human-readable
 * audit row (action key + actor + timestamp + old→new) via {@see RecordsOpportunityAudit},
 * replay-stable and carrying the firing actor that the raw `verb_events.metadata`
 * does not round-trip after replay (M2). There is NO ability to add timeline entries.
 */
#[Layout('components.layouts.app')] final class Versions extends Component
{
    use HasAuditTimeline;
    use HasOpportunityActions;

    public Opportunity $opportunity;

    /** Whether the actor may mutate versions (vs a read-only view). */
    public bool $canEdit = false;

    /** The version the rename modal is editing + its draft label. */
    public ?int $labelVersionId = null;

    public ?string $labelDraft = null;

    /** The version the decline modal is declining + its reason. */
    public ?int $declineVersionId = null;

    public ?string $declineReason = null;

    /** The two versions selected for the diff panel (projection ids). */
    public ?int $diffFromId = null;

    public ?int $diffToId = null;

    /** Create-version modal inputs. */
    public int $createType = 0;

    public ?int $createSourceId = null;

    public ?string $createLabel = null;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
        $this->canEdit = Gate::allows('opportunities.edit');
        $this->createType = VersionType::Revision->value;
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Versions & Timeline');
    }

    /**
     * Open the create-version modal, defaulting the source to the active version.
     */
    public function openCreate(int $type): void
    {
        $this->createType = $type;
        $this->createSourceId = $this->opportunity->active_version_id > 0
            ? $this->opportunity->active_version_id
            : null;
        $this->createLabel = null;
    }

    /**
     * Create a revision or an alternative, cloning the source version's line items.
     */
    public function createVersion(): void
    {
        try {
            (new CreateVersion)($this->opportunity->refresh(), CreateVersionData::from([
                'version_type' => $this->createType,
                'label' => $this->createLabel,
                'source_version_id' => $this->createSourceId,
            ]));

            session()->flash('version_status', __('Version created.'));
            $this->resetCreateState();
            $this->dispatch('version-modal-close', name: 'create-version');
        } catch (AuthorizationException) {
            $this->flashDenied();
        } catch (ValidationException $e) {
            $this->flashValidation($e, __('Unable to create this version.'));
        }

        $this->opportunity->refresh();
    }

    /**
     * Switch the active version. The action handles the one-active flip, totals
     * re-roll, and the availability demand swap atomically.
     */
    public function activate(int $versionId): void
    {
        $this->run($versionId, fn (OpportunityVersion $version) => (new ActivateVersion)($version), __('Active version switched.'));
    }

    public function send(int $versionId): void
    {
        $this->run($versionId, fn (OpportunityVersion $version) => (new SendVersion)($version), __('Version marked as sent.'));
    }

    public function accept(int $versionId): void
    {
        $this->run($versionId, fn (OpportunityVersion $version) => (new AcceptVersion)($version), __('Version accepted.'));
    }

    /**
     * Open the decline modal (the reason is optional, captured before declining).
     */
    public function promptDecline(int $versionId): void
    {
        $this->declineVersionId = $versionId;
        $this->declineReason = null;
    }

    public function submitDecline(): void
    {
        if ($this->declineVersionId === null) {
            return;
        }

        $reason = $this->declineReason;
        $this->run($this->declineVersionId, fn (OpportunityVersion $version) => (new DeclineVersion)($version, $reason), __('Version declined.'));

        $this->declineVersionId = null;
        $this->declineReason = null;
        $this->dispatch('version-modal-close', name: 'decline-version');
    }

    /**
     * Open the rename modal seeded with the current label.
     */
    public function promptRename(int $versionId): void
    {
        $version = $this->findVersion($versionId);
        $this->labelVersionId = $versionId;
        $this->labelDraft = $version?->label;
    }

    public function submitRename(): void
    {
        if ($this->labelVersionId === null) {
            return;
        }

        $label = $this->labelDraft;
        $this->run($this->labelVersionId, fn (OpportunityVersion $version) => (new ChangeVersionLabel)($version, ChangeVersionLabelData::from(['label' => $label])), __('Version renamed.'));

        $this->labelVersionId = null;
        $this->labelDraft = null;
        $this->dispatch('version-modal-close', name: 'rename-version');
    }

    public function deleteVersion(int $versionId): void
    {
        $this->run($versionId, fn (OpportunityVersion $version) => (new DeleteVersion)($version), __('Version deleted.'));
    }

    /**
     * Run a single-version action, catching the auth/422 failures the action
     * classes raise (an illegal §8 move is a 422) and flashing the first message.
     *
     * @param  \Closure(OpportunityVersion): mixed  $action
     */
    protected function run(int $versionId, \Closure $action, string $success): void
    {
        try {
            $version = $this->findVersionOrFail($versionId);
            $action($version);
            session()->flash('version_status', $success);
        } catch (AuthorizationException) {
            $this->flashDenied();
        } catch (ValidationException $e) {
            $this->flashValidation($e, __('This action is not permitted.'));
        }

        $this->opportunity->refresh();
    }

    protected function findVersion(int $versionId): ?OpportunityVersion
    {
        return OpportunityVersion::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKey($versionId)
            ->first();
    }

    protected function findVersionOrFail(int $versionId): OpportunityVersion
    {
        return OpportunityVersion::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKey($versionId)
            ->firstOrFail();
    }

    protected function resetCreateState(): void
    {
        $this->createType = VersionType::Revision->value;
        $this->createSourceId = null;
        $this->createLabel = null;
    }

    protected function flashDenied(): void
    {
        session()->flash('error', __('You do not have permission to manage versions.'));
    }

    protected function flashValidation(ValidationException $e, string $fallback): void
    {
        session()->flash('error', collect($e->errors())->flatten()->first() ?? $fallback);
    }

    /**
     * Whether a version may legally be sent / accepted / declined / activated /
     * renamed / deleted, mirroring the validate() guards on the version events so
     * the UI only offers legal moves. All require the opportunity to be a Quotation.
     */
    public function isQuotation(): bool
    {
        return $this->opportunity->state === OpportunityState::Quotation;
    }

    public function canSend(OpportunityVersion $version): bool
    {
        return $this->isQuotation() && $version->status === VersionStatus::Draft;
    }

    public function canAcceptOrDecline(OpportunityVersion $version): bool
    {
        return $this->isQuotation()
            && in_array($version->status, [VersionStatus::Draft, VersionStatus::Sent], true);
    }

    public function canActivate(OpportunityVersion $version): bool
    {
        return $this->isQuotation() && ! $version->is_active;
    }

    public function canRename(OpportunityVersion $version): bool
    {
        return $this->isQuotation();
    }

    public function canDelete(OpportunityVersion $version, int $versionCount): bool
    {
        return $this->isQuotation() && ! $version->is_active && $versionCount > 1;
    }

    /**
     * The on-demand diff between the two selected versions, or null until both are
     * chosen. Computed fresh from the projections every render (never stored).
     */
    /** @param Collection<int, OpportunityVersion> $versions */
    protected function diff(Collection $versions): ?VersionDiffData
    {
        if ($this->diffFromId === null || $this->diffToId === null || $this->diffFromId === $this->diffToId) {
            return null;
        }

        $from = $versions->firstWhere('id', $this->diffFromId);
        $to = $versions->firstWhere('id', $this->diffToId);

        if (! $from instanceof OpportunityVersion || ! $to instanceof OpportunityVersion) {
            return null;
        }

        try {
            return (new DiffVersions)($from, $to);
        } catch (AuthorizationException|ValidationException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $versions = $this->opportunity->versions()
            ->with('author')
            ->orderBy('version_number')
            ->get();

        return [
            ...$this->opportunityActionData(),

            'versions' => $versions,
            'versionCount' => $versions->count(),
            'diff' => $this->diff($versions),
            // The opportunity's lifecycle event history (B4) — read-only, newest-first.
            'timeline' => $this->lifecycleTimeline(),
        ];
    }

    /**
     * The opportunity's own lifecycle event history, newest-first, shaped for the
     * read-only timeline render. Sourced from `action_logs` (each Verbs event records
     * one replay-stable audit row with the firing actor), capped at
     * {@see HasAuditTimeline::$auditTimelineLimit}.
     *
     * @return Collection<int, array{title: string, color: ?string, icon: string, actor: ?string, at: Carbon|null, detail: ?string}>
     */
    protected function lifecycleTimeline(): Collection
    {
        return ActionLog::query()
            ->with('user')
            ->forEntity($this->opportunity->getMorphClass(), (int) $this->opportunity->id)
            ->latest('created_at')
            ->latest('id')
            ->limit($this->auditTimelineLimit)
            ->get()
            ->map(fn (ActionLog $log): array => [
                'title' => $this->lifecycleLabel($log->action),
                'color' => $this->timelineColor($log->action),
                'icon' => $this->lifecycleIcon($log->action),
                'actor' => $log->user?->name,
                'at' => $log->created_at,
                'detail' => $this->lifecycleDetail($log),
            ])
            ->values();
    }

    /**
     * Human label for a lifecycle action key. A small explicit map covers the keys
     * whose default headline reads awkwardly; everything else falls back to a
     * headline of the suffix (e.g. `opportunity.item_added` → "Item added").
     */
    protected function lifecycleLabel(string $action): string
    {
        $suffix = Str::after($action, 'opportunity.');

        return $this->lifecycleLabelMap()[$suffix]
            ?? Str::of($suffix)->replace('_', ' ')->ucfirst()->toString();
    }

    /**
     * Explicit suffix → label overrides for the lifecycle timeline.
     *
     * @return array<string, string>
     */
    protected function lifecycleLabelMap(): array
    {
        return [
            'created' => __('Opportunity created'),
            'updated' => __('Details updated'),
            'quoted' => __('Converted to quotation'),
            'converted_to_order' => __('Converted to order'),
            'status_changed' => __('Status changed'),
            'status_promoted' => __('Status promoted'),
            'reverted_to_quotation' => __('Reverted to quotation'),
            'reinstated' => __('Reinstated'),
            'restored' => __('Restored'),
            'deleted' => __('Deleted'),
            'cloned' => __('Cloned'),
            'item_added' => __('Item added'),
            'item_removed' => __('Item removed'),
            'item_quantity_changed' => __('Item quantity changed'),
            'item_price_overridden' => __('Item price overridden'),
            'item_discount_set' => __('Item discount set'),
            'item_dates_changed' => __('Item dates changed'),
            'item_substituted' => __('Item substituted'),
            'items_reordered' => __('Items reordered'),
            'deal_price_set' => __('Deal price set'),
            'deal_price_cleared' => __('Deal price cleared'),
            'cost_added' => __('Cost added'),
            'cost_updated' => __('Cost updated'),
            'cost_removed' => __('Cost removed'),
            'asset_allocated' => __('Asset allocated'),
            'asset_deallocated' => __('Asset de-allocated'),
            'asset_prepared' => __('Asset prepared'),
            'asset_dispatched' => __('Asset dispatched'),
            'asset_on_hire' => __('Asset on hire'),
            'asset_returned' => __('Asset returned'),
            'asset_checked' => __('Asset checked'),
            'asset_substituted' => __('Asset substituted'),
            'bulk_dispatched' => __('Bulk dispatched'),
            'bulk_returned' => __('Bulk returned'),
            'version_created' => __('Version created'),
            'version_activated' => __('Version activated'),
            'version_sent' => __('Version sent'),
            'version_accepted' => __('Version accepted'),
            'version_declined' => __('Version declined'),
            'version_relabelled' => __('Version relabelled'),
            'version_deleted' => __('Version deleted'),
            'version_superseded' => __('Version superseded'),
        ];
    }

    /**
     * Flux icon name for a lifecycle action, grouped by domain (status, items,
     * assets/dispatch, versions, lifecycle), for at-a-glance scanning.
     */
    protected function lifecycleIcon(string $action): string
    {
        $suffix = Str::after($action, 'opportunity.');

        return match (true) {
            $suffix === 'created' => 'sparkles',
            $suffix === 'deleted' => 'trash',
            in_array($suffix, ['converted_to_order', 'quoted', 'status_changed', 'status_promoted', 'reverted_to_quotation', 'reinstated', 'restored', 'cloned'], true) => 'arrow-path-rounded-square',
            in_array($suffix, ['asset_dispatched', 'bulk_dispatched', 'asset_on_hire'], true) => 'truck',
            in_array($suffix, ['asset_returned', 'bulk_returned', 'asset_checked'], true) => 'arrow-uturn-left',
            Str::startsWith($suffix, 'asset_') => 'cube',
            Str::startsWith($suffix, 'item') => 'list-bullet',
            Str::startsWith($suffix, 'version_') => 'document-duplicate',
            Str::contains($suffix, ['price', 'cost', 'deal']) => 'currency-pound',
            default => 'clock',
        };
    }

    /**
     * A compact old→new detail line where the audit row carries a meaningful
     * before/after (e.g. a status change), else null. Scalar values only — nested
     * payloads are summarised as a key list rather than dumped.
     */
    protected function lifecycleDetail(ActionLog $log): ?string
    {
        $new = is_array($log->new_values) ? $log->new_values : [];
        $old = is_array($log->old_values) ? $log->old_values : [];

        if (array_key_exists('status', $new)) {
            $from = $this->scalarLabel($old['status'] ?? null);
            $to = $this->scalarLabel($new['status'] ?? null);

            if ($to !== null) {
                return $from !== null ? "{$from} → {$to}" : $to;
            }
        }

        return null;
    }

    /**
     * Render a scalar audit value as a short label, or null when not scalar/empty.
     */
    protected function scalarLabel(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }

        return Str::of((string) $value)->replace('_', ' ')->ucfirst()->toString();
    }

    /**
     * Opportunity lifecycle colours: creation/restoration green, deletion red,
     * order/dispatch transitions blue, returns amber.
     *
     * @return array<string, list<string>>
     */
    protected function timelineColorMap(): array
    {
        return [
            'green' => ['.created', '.restored', '.reinstated', '.version_accepted', '.asset_returned'],
            'red' => ['.deleted', '.version_declined', '.item_removed', '.asset_deallocated'],
            'blue' => ['.converted_to_order', '.status_promoted', '.asset_dispatched', '.bulk_dispatched', '.asset_on_hire', '.version_activated'],
            'amber' => ['.status_changed', '.reverted_to_quotation', '.bulk_returned'],
        ];
    }

    public function render(): View
    {
        return view('project-opportunities::versions');
    }
}
