<?php

namespace App\Data\Documents;

use Spatie\LaravelData\Data;

class BatchGenerateDocumentsData extends Data
{
    /**
     * @param  list<array<string, mixed>>  $sources
     */
    public function __construct(
        public array $sources,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'sources' => ['required', 'array', 'min:1', 'max:50'],
            'sources.*.documentable_type' => ['required', 'string'],
            'sources.*.documentable_id' => ['required', 'integer'],
            'sources.*.document_template_id' => ['nullable', 'integer', 'exists:document_templates,id'],
            'sources.*.document_type' => ['nullable', 'string', 'max:100'],
            'sources.*.output_format' => ['nullable', 'string'],
            'sources.*.source_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return list<GenerateDocumentData>
     */
    public function toGenerateRequests(): array
    {
        return array_map(
            fn (array $source): GenerateDocumentData => GenerateDocumentData::from($source),
            $this->sources,
        );
    }
}
