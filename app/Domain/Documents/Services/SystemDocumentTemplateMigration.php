<?php

declare(strict_types=1);

namespace App\Domain\Documents\Services;

use App\Domain\Documents\Models\DocumentTemplate;
use Illuminate\Support\Facades\DB;

final class SystemDocumentTemplateMigration
{
    /** @param list<string> $types */
    public function snapshot(array $types): void
    {
        $definitions = collect(SystemDocumentTemplates::definitions())->keyBy('document_type');

        DocumentTemplate::query()
            ->where('is_system', true)
            ->whereIn('document_type', $types)
            ->orderBy('id')
            ->each(function (DocumentTemplate $template) use ($definitions): void {
                $definition = $definitions->get($template->document_type);

                if ($definition === null || ! $this->contentDiffers($template, $definition)) {
                    return;
                }

                DB::table('document_template_versions')->updateOrInsert(
                    [
                        'document_template_id' => $template->id,
                        'version' => $template->version,
                    ],
                    [
                        'edited_by_member_id' => null,
                        'body_html' => $template->body_html,
                        'header_html' => $template->header_html,
                        'footer_html' => $template->footer_html,
                        'styles_css' => $template->styles_css,
                        'page_config' => json_encode($template->page_config),
                        'change_note' => 'Pre-migration snapshot before system template refresh',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }

    /** @param list<string> $types */
    public function refresh(array $types, bool $seedMissing = false): void
    {
        $definitions = collect(SystemDocumentTemplates::definitions())->keyBy('document_type');

        DocumentTemplate::query()
            ->where('is_system', true)
            ->whereIn('document_type', $types)
            ->orderBy('id')
            ->each(function (DocumentTemplate $template) use ($definitions): void {
                $definition = $definitions->get($template->document_type);

                if ($definition === null || ! $this->contentDiffers($template, $definition)) {
                    return;
                }

                $template->update([
                    'body_html' => $definition['body_html'],
                    'header_html' => $definition['header_html'],
                    'footer_html' => $definition['footer_html'],
                    'styles_css' => $definition['styles_css'],
                    'version' => $template->version + 1,
                    'last_edited_at' => now(),
                ]);
            });

        if ($seedMissing) {
            $this->seedMissing($types);
        }
    }

    /** @param list<string> $types */
    private function seedMissing(array $types): void
    {
        $storeIds = DocumentTemplate::query()->select('store_id')->distinct()->pluck('store_id');

        foreach ($storeIds as $storeId) {
            foreach ($types as $type) {
                $definition = SystemDocumentTemplates::definition($type);
                DocumentTemplate::query()->firstOrCreate(
                    ['store_id' => $storeId, 'slug' => $definition['slug']],
                    $definition + ['store_id' => $storeId, 'is_default' => true, 'is_system' => true, 'is_active' => true, 'version' => 1],
                );
            }
        }
    }

    /** @param array{body_html: string, header_html: string, footer_html: string, styles_css: string} $definition */
    private function contentDiffers(DocumentTemplate $template, array $definition): bool
    {
        return $template->body_html !== $definition['body_html']
            || (string) ($template->header_html ?? '') !== $definition['header_html']
            || (string) ($template->footer_html ?? '') !== $definition['footer_html']
            || (string) ($template->styles_css ?? '') !== $definition['styles_css'];
    }
}
