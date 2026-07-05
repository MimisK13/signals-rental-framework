<?php

namespace App\Actions\Notifications\Concerns;

use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Models\User;

trait SnapshotsCommunicationTemplateVersions
{
    protected function snapshotVersion(CommunicationTemplate $template, ?string $changeNote = null): void
    {
        CommunicationTemplateVersion::query()->updateOrCreate(
            [
                'communication_template_id' => $template->id,
                'version' => $template->version,
            ],
            [
                'edited_by_member_id' => $this->editedByMemberId(),
                'subject' => $template->subject,
                'body' => $template->body,
                'change_note' => $changeNote,
            ],
        );
    }

    protected function editedByMemberId(): ?int
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->member_id;
    }
}
