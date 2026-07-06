<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\Store;
use App\Services\Documents\DocumentSequenceDefaults;
use App\Services\Documents\SystemDocumentTemplates;
use App\Services\Plugins\PluginDocumentTemplateSeederRegistry;
use Illuminate\Database\Seeder;

class DocumentTemplateSeeder extends Seeder
{
    public function __construct(
        private readonly PluginDocumentTemplateSeederRegistry $pluginSeeders,
    ) {}

    /**
     * Seed system templates for every existing store.
     */
    public function run(): void
    {
        Store::query()->each(function (Store $store): void {
            $this->seedForStore($store);
        });
    }

    /**
     * Seed (or skip) the system template set for one store. Idempotent.
     */
    public function seedForStore(Store $store): void
    {
        foreach (SystemDocumentTemplates::definitions() as $definition) {
            DocumentTemplate::query()->firstOrCreate(
                [
                    'store_id' => $store->id,
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

        $this->pluginSeeders->seedForStore($store);
        DocumentSequenceDefaults::seedForStore($store);
    }
}
