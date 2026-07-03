<?php

namespace App\Data\Documents;

use App\Enums\DocumentOutputFormat;
use App\Models\Member;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Data;

class GenerateDocumentData extends Data
{
    public function __construct(
        #[Exists('document_templates', 'id')]
        public ?int $document_template_id = null,
        public ?int $source_id = null,
        public ?int $documentable_id = null,
        public ?string $documentable_type = null,
        public ?string $document_type = null,
        public DocumentOutputFormat $output_format = DocumentOutputFormat::Pdf,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'document_template_id' => ['nullable', 'integer', 'exists:document_templates,id', 'required_without:document_type'],
            'document_type' => ['nullable', 'string', 'max:100', 'required_without:document_template_id'],
            'source_id' => ['nullable', 'integer', 'required_without:documentable_id'],
            'documentable_id' => ['nullable', 'integer', 'required_without:source_id'],
            'documentable_type' => ['nullable', 'string', Rule::in(['Opportunity', 'opportunity', 'Member', 'member'])],
            'output_format' => ['nullable', 'string', Rule::enum(DocumentOutputFormat::class)],
        ];
    }

    public function resolvedSourceId(): int
    {
        return $this->documentable_id ?? $this->source_id
            ?? throw new \InvalidArgumentException('A source_id or documentable_id is required.');
    }

    /**
     * @return class-string<Model>
     */
    public function resolvedDocumentableClass(): string
    {
        $type = $this->documentable_type ?? 'opportunity';

        if ($type === Opportunity::class || strtolower($type) === 'opportunity' || str_ends_with($type, 'Opportunity')) {
            return Opportunity::class;
        }

        if ($type === Member::class || strtolower($type) === 'member' || str_ends_with($type, 'Member')) {
            return Member::class;
        }

        throw new \InvalidArgumentException("Unsupported documentable type [{$type}].");
    }
}
