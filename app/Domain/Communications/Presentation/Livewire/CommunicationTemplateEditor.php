<?php

declare(strict_types=1);

namespace App\Domain\Communications\Presentation\Livewire;

use App\Domain\Communications\Actions\DuplicateCommunicationTemplate;
use App\Domain\Communications\Actions\RestoreCommunicationTemplateVersion;
use App\Domain\Communications\Actions\SaveCommunicationTemplate;
use App\Domain\Communications\Data\SaveCommunicationTemplateData;
use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Communications\Models\CommunicationTemplateVersion;
use App\Domain\Communications\Services\MergeFieldRenderer;
use App\Domain\Communications\Services\NotificationPreviewData;
use App\Domain\Communications\Services\SmsSegmentCounter;
use App\Models\NotificationType;
use App\Models\Opportunity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Communication Template')]
final class CommunicationTemplateEditor extends Component
{
    public CommunicationTemplate $template;

    public string $subject = '';

    public string $body = '';

    public string $fieldSearch = '';

    public string $previewSubject = '';

    public string $previewBody = '';

    public string $previewHtml = '';

    public string $previewMode = 'sample';

    public ?int $sourceOpportunityId = null;

    /** @var Collection<int, CommunicationTemplateVersion> */
    public Collection $versions;

    /** @var list<array{label: string, fields: list<array<string, mixed>>}> */
    public array $fieldGroups = [];

    /** @var list<array{id: int, label: string}> */
    public array $opportunityOptions = [];

    public function mount(CommunicationTemplate $communicationTemplate): void
    {
        Gate::authorize('view', $communicationTemplate);

        $this->template = $communicationTemplate->load(['notificationType', 'store']);
        $this->subject = (string) ($communicationTemplate->subject ?? '');
        $this->body = (string) $communicationTemplate->body;
        $this->fieldGroups = $this->buildFieldGroups();
        $this->loadVersions();
        $this->loadOpportunityOptions();
        $this->refreshPreview();
    }

    public function loadVersions(): void
    {
        $this->versions = $this->template->versions()->limit(20)->get();
    }

    public function loadOpportunityOptions(): void
    {
        if ($this->template->store_id === null) {
            $this->opportunityOptions = [];

            return;
        }

        $storeIds = auth()->user()?->accessibleStoreIds();

        if ($storeIds !== null && ! in_array($this->template->store_id, $storeIds, true)) {
            $this->opportunityOptions = [];

            return;
        }

        $this->opportunityOptions = Opportunity::query()
            ->where('store_id', $this->template->store_id)
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get(['id', 'number', 'subject'])
            ->map(fn (Opportunity $opportunity): array => [
                'id' => $opportunity->id,
                'label' => trim($opportunity->number.' · '.$opportunity->subject),
            ])
            ->all();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['subject', 'body', 'previewMode', 'sourceOpportunityId'], true)) {
            $this->refreshPreview();
        }
    }

    public function insertField(string $path): void
    {
        $token = '{{ '.$path.' }}';
        $this->body = rtrim($this->body).($this->body === '' ? '' : ' ').$token;
        $this->refreshPreview();
    }

    public function save(): void
    {
        Gate::authorize('update', $this->template);

        if ($this->template->is_system) {
            $this->addError('template', 'System templates cannot be modified. Duplicate to customise.');

            return;
        }

        (new SaveCommunicationTemplate)($this->template, SaveCommunicationTemplateData::from([
            'name' => $this->template->name,
            'slug' => $this->template->slug,
            'channel' => $this->template->channel,
            'subject' => $this->subject,
            'body' => $this->body,
            'store_id' => $this->template->store_id,
            'notification_type_id' => $this->template->notification_type_id,
            'change_note' => 'Saved from communication template editor',
        ]));

        $this->template->refresh();
        $this->loadVersions();
        $this->refreshPreview();
        $this->dispatch('communication-template-saved');
    }

    public function duplicateTemplate(): void
    {
        Gate::authorize('update', $this->template);

        $duplicate = (new DuplicateCommunicationTemplate)($this->template);

        $this->redirect(route('admin.settings.communication-templates.edit', $duplicate->id), navigate: true);
    }

    public function restoreVersion(int $versionId): void
    {
        Gate::authorize('update', $this->template);

        $version = CommunicationTemplateVersion::query()->findOrFail($versionId);

        (new RestoreCommunicationTemplateVersion)($this->template, $version);

        $this->template->refresh();
        $this->subject = (string) ($this->template->subject ?? '');
        $this->body = (string) $this->template->body;
        $this->loadVersions();
        $this->refreshPreview();
        $this->dispatch('communication-template-version-restored');
    }

    public function refreshPreview(): void
    {
        $previewData = app(NotificationPreviewData::class);
        $mergeFields = app(MergeFieldRenderer::class);

        $data = match ($this->previewMode) {
            'entity' => $this->previewDataFromEntity($previewData),
            default => $this->template->notificationType !== null
                ? $previewData->sampleForType($this->template->notificationType)
                : $previewData->sampleForType(new NotificationType(['data_schema' => ['groups' => []]])),
        };

        $this->previewSubject = $this->subject !== ''
            ? $mergeFields->render($this->subject, $data)
            : '';
        $this->previewBody = $mergeFields->render($this->body, $data);
        $this->previewHtml = $this->template->channel === 'email'
            ? Str::markdown($this->previewBody)
            : '';
    }

    /**
     * @return array{characters: int, segments: int, per_segment: int}
     */
    public function smsStats(): array
    {
        return app(SmsSegmentCounter::class)->analyse($this->body);
    }

    public function updatedFieldSearch(): void
    {
        $this->fieldGroups = $this->buildFieldGroups();
    }

    /**
     * @return list<array{label: string, fields: list<array<string, mixed>>}>
     */
    public function buildFieldGroups(): array
    {
        $notificationType = $this->template->notificationType;

        if ($notificationType === null) {
            return [];
        }

        $schema = $notificationType->data_schema;

        if (! is_array($schema)) {
            return [];
        }

        $schemaGroups = $schema['groups'] ?? [];

        if (! is_array($schemaGroups)) {
            return [];
        }

        $groups = [];

        foreach ($schemaGroups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $fields = [];
            $groupFields = $group['fields'] ?? [];

            if (! is_array($groupFields)) {
                continue;
            }

            foreach ($groupFields as $field) {
                if (! is_array($field) || ! isset($field['path']) || ! is_string($field['path'])) {
                    continue;
                }

                $label = isset($field['label']) && is_string($field['label']) ? $field['label'] : '';

                if ($this->fieldSearch !== '' && ! str_contains(strtolower($field['path']), strtolower($this->fieldSearch))
                    && ! str_contains(strtolower($label), strtolower($this->fieldSearch))) {
                    continue;
                }

                $fields[] = $field;
            }

            if ($fields !== []) {
                $label = isset($group['label']) && is_string($group['label']) ? $group['label'] : 'Fields';

                $groups[] = [
                    'label' => $label,
                    'fields' => $fields,
                ];
            }
        }

        return $groups;
    }

    /**
     * @return array<string, mixed>
     */
    private function previewDataFromEntity(NotificationPreviewData $previewData): array
    {
        if ($this->sourceOpportunityId === null || $this->template->store_id === null) {
            return $this->template->notificationType !== null
                ? $previewData->sampleForType($this->template->notificationType)
                : [];
        }

        $storeIds = auth()->user()?->accessibleStoreIds();

        $opportunity = Opportunity::query()
            ->where('store_id', $this->template->store_id)
            ->when($storeIds !== null, fn ($query) => $query->whereIn('store_id', $storeIds))
            ->whereKey($this->sourceOpportunityId)
            ->first();

        if ($opportunity === null) {
            return $this->template->notificationType !== null
                ? $previewData->sampleForType($this->template->notificationType)
                : [];
        }

        return $previewData->fromOpportunity($opportunity);
    }

    public function render(): View
    {
        return view('communications::admin.communication-template-editor');
    }
}
