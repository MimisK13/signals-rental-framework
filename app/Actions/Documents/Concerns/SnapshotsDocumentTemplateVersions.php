<?php

namespace App\Actions\Documents\Concerns;

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\User;

trait SnapshotsDocumentTemplateVersions
{
    protected function snapshotVersion(DocumentTemplate $template, ?string $changeNote = null): void
    {
        DocumentTemplateVersion::query()->updateOrCreate(
            [
                'document_template_id' => $template->id,
                'version' => $template->version,
            ],
            [
                'edited_by_member_id' => $this->editedByMemberId(),
                'body_html' => $template->body_html,
                'header_html' => $template->header_html,
                'footer_html' => $template->footer_html,
                'styles_css' => $template->styles_css,
                'page_config' => $template->page_config,
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
