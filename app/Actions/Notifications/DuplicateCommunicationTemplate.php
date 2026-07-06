<?php

namespace App\Actions\Notifications;

use App\Data\Notifications\CommunicationTemplateData;
use App\Models\CommunicationTemplate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DuplicateCommunicationTemplate
{
    public function __invoke(CommunicationTemplate $template): CommunicationTemplateData
    {
        Gate::authorize('update', $template);

        $baseSlug = Str::slug($template->slug.'-copy');
        $slug = $this->uniqueSlug($template->store_id, $baseSlug);

        $duplicate = CommunicationTemplate::query()->create([
            'notification_type_id' => $template->notification_type_id,
            'store_id' => $template->store_id,
            'name' => 'Copy of '.$template->name,
            'slug' => $slug,
            'channel' => $template->channel,
            'subject' => $template->subject,
            'body' => $template->body,
            'attachments_config' => $template->attachments_config,
            'is_default' => false,
            'is_system' => false,
            'is_active' => $template->is_active,
            'version' => 1,
            'last_edited_at' => now(),
        ]);

        return CommunicationTemplateData::fromModel($duplicate);
    }

    private function uniqueSlug(?int $storeId, string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while (CommunicationTemplate::query()
            ->where('store_id', $storeId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
