<?php

namespace App\Actions\Notifications;

use App\Actions\Notifications\Concerns\SnapshotsCommunicationTemplateVersions;
use App\Data\Notifications\CommunicationTemplateData;
use App\Data\Notifications\SaveCommunicationTemplateData;
use App\Models\CommunicationTemplate;
use App\Services\Notifications\CommunicationTemplateRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveCommunicationTemplate
{
    use SnapshotsCommunicationTemplateVersions;

    public function __invoke(?CommunicationTemplate $template, SaveCommunicationTemplateData $data): CommunicationTemplateData
    {
        if ($template === null) {
            Gate::authorize('create', CommunicationTemplate::class);

            return $this->create($data);
        }

        Gate::authorize('update', $template);

        if ($template->is_system) {
            throw ValidationException::withMessages([
                'template' => ['System templates cannot be modified directly. Duplicate the template to customise it.'],
            ]);
        }

        if (CommunicationTemplate::query()
            ->where('store_id', $data->store_id ?? $template->store_id)
            ->where('slug', $data->slug)
            ->whereKeyNot($template->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['The slug has already been taken for this store.'],
            ]);
        }

        return DB::transaction(function () use ($template, $data): CommunicationTemplateData {
            $renderer = app(CommunicationTemplateRenderer::class);
            $previousVersion = $template->version;

            $this->snapshotVersion($template, $data->change_note);

            $template->update([
                'notification_type_id' => $data->notification_type_id ?? $template->notification_type_id,
                'store_id' => $data->store_id ?? $template->store_id,
                'name' => $data->name,
                'slug' => $data->slug,
                'channel' => $data->channel,
                'subject' => $data->subject,
                'body' => $data->body,
                'attachments_config' => $data->attachments_config,
                'is_default' => $data->is_default,
                'is_active' => $data->is_active,
                'version' => $template->version + 1,
                'last_edited_at' => now(),
            ]);

            $template->refresh();
            $renderer->invalidateCompiledCache($template, $previousVersion);
            $renderer->invalidateCompiledCache($template);

            return CommunicationTemplateData::fromModel($template);
        });
    }

    private function create(SaveCommunicationTemplateData $data): CommunicationTemplateData
    {
        if (CommunicationTemplate::query()
            ->where('store_id', $data->store_id)
            ->where('slug', $data->slug)
            ->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['The slug has already been taken for this store.'],
            ]);
        }

        return DB::transaction(function () use ($data): CommunicationTemplateData {
            $template = CommunicationTemplate::query()->create([
                'notification_type_id' => $data->notification_type_id,
                'store_id' => $data->store_id,
                'name' => $data->name,
                'slug' => $data->slug,
                'channel' => $data->channel,
                'subject' => $data->subject,
                'body' => $data->body,
                'attachments_config' => $data->attachments_config,
                'is_default' => $data->is_default,
                'is_system' => false,
                'is_active' => $data->is_active,
                'version' => 1,
                'last_edited_at' => now(),
            ]);

            return CommunicationTemplateData::fromModel($template);
        });
    }
}
