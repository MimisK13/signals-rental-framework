<?php

use App\Models\DocumentTemplate;
use App\Services\Documents\SystemDocumentTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = collect(SystemDocumentTemplates::definitions())->keyBy('document_type');

        DocumentTemplate::query()
            ->where('is_system', true)
            ->orderBy('id')
            ->each(function (DocumentTemplate $template) use ($definitions): void {
                $definition = $definitions->get($template->document_type);

                if ($definition === null) {
                    return;
                }

                if (! $this->contentDiffers($template, $definition)) {
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
                        'change_note' => 'Pre-migration snapshot before receipt template refresh',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                $template->update([
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'body_html' => $definition['body_html'],
                    'header_html' => $definition['header_html'],
                    'footer_html' => $definition['footer_html'],
                    'styles_css' => $definition['styles_css'],
                    'page_config' => $definition['page_config'],
                    'version' => $template->version + 1,
                    'last_edited_at' => now(),
                ]);
            });

        $storeIds = DocumentTemplate::query()
            ->where('is_system', true)
            ->distinct()
            ->pluck('store_id');

        foreach ($storeIds as $storeId) {
            $definition = $definitions->get('receipt');

            if ($definition === null) {
                continue;
            }

            DocumentTemplate::query()->firstOrCreate(
                [
                    'store_id' => $storeId,
                    'slug' => $definition['slug'],
                ],
                [
                    'created_by_member_id' => null,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'document_type' => $definition['document_type'],
                    'model_type' => $definition['model_type'],
                    'body_html' => $definition['body_html'],
                    'header_html' => $definition['header_html'],
                    'footer_html' => $definition['footer_html'],
                    'styles_css' => $definition['styles_css'],
                    'page_config' => $definition['page_config'],
                    'branding_config' => null,
                    'is_default' => true,
                    'is_system' => true,
                    'is_active' => true,
                    'version' => 1,
                    'last_edited_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        // no-op — prior content remains in document_template_versions for manual restore.
    }

    /**
     * @param  array{
     *     body_html: string,
     *     header_html: string,
     *     footer_html: string,
     *     styles_css: string,
     *     name: string,
     *     description: string
     * }  $definition
     */
    private function contentDiffers(DocumentTemplate $template, array $definition): bool
    {
        return $template->body_html !== $definition['body_html']
            || (string) ($template->header_html ?? '') !== $definition['header_html']
            || (string) ($template->footer_html ?? '') !== $definition['footer_html']
            || (string) ($template->styles_css ?? '') !== $definition['styles_css']
            || $template->name !== $definition['name']
            || (string) $template->description !== $definition['description'];
    }
};
