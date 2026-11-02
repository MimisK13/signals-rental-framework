<?php

declare(strict_types=1);

namespace App\Domain\Lists\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class UpdateListNameData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $is_hierarchical = null,
        /**
         * The id of the list name being updated. Supplied explicitly by the
         * caller so the unique rule can ignore the current record on rename
         * without reaching into the HTTP request/route.
         */
        public ?int $list_name_id = null,
    ) {}

    /**
     * Validation rules for updating a list name.
     *
     * The id of the record being updated is passed explicitly by the caller so
     * the unique rule can ignore the current row on rename without reading the
     * route — keeping the DTO usable outside an HTTP request.
     *
     * @return array<string, mixed>
     */
    public static function rules(?int $listNameId = null): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('list_names', 'name')->ignore($listNameId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_hierarchical' => ['sometimes', 'boolean'],
        ];
    }
}
