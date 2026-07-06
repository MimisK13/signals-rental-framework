<?php

namespace App\Actions\Notifications;

use App\Actions\Notifications\Concerns\SnapshotsCommunicationTemplateVersions;
use App\Data\Notifications\CommunicationTemplateData;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Services\Notifications\CommunicationTemplateRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RestoreCommunicationTemplateVersion
{
    use SnapshotsCommunicationTemplateVersions;

    public function __invoke(CommunicationTemplate $template, CommunicationTemplateVersion $version): CommunicationTemplateData
    {
        Gate::authorize('update', $template);

        if ($version->communication_template_id !== $template->id) {
            abort(404);
        }

        return DB::transaction(function () use ($template, $version): CommunicationTemplateData {
            $renderer = app(CommunicationTemplateRenderer::class);
            $previousVersion = $template->version;

            $this->snapshotVersion($template, 'Before restore of version '.$version->version);

            $template->update([
                'subject' => $version->subject,
                'body' => $version->body,
                'version' => $template->version + 1,
                'last_edited_at' => now(),
            ]);

            $template->refresh();
            $renderer->invalidateCompiledCache($template, $previousVersion);
            $renderer->invalidateCompiledCache($template);

            return CommunicationTemplateData::fromModel($template);
        });
    }
}
