<?php

declare(strict_types=1);

namespace App\Domain\CustomViews\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class UpdateCustomViewData extends Data
{
    /**
     * @param  list<string>|Optional  $columns
     * @param  list<array{field: string, predicate: string, value: mixed}>|Optional  $filters
     * @param  list<int>|Optional  $role_ids
     */
    public function __construct(
        public string|Optional $name = new Optional,
        public string|null|Optional $description = new Optional,
        public string|Optional $visibility = new Optional,
        public array|Optional $columns = new Optional,
        public array|Optional $filters = new Optional,
        public string|null|Optional $sort_column = new Optional,
        public string|Optional $sort_direction = new Optional,
        public int|Optional $per_page = new Optional,
        public array|Optional $role_ids = new Optional,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'visibility' => ['sometimes', 'in:personal,shared'],
            'columns' => ['sometimes', 'array', 'min:1'],
            'columns.*' => ['string'],
            'filters' => ['sometimes', 'array'],
            'filters.*.field' => ['required_with:filters', 'string'],
            'filters.*.predicate' => ['required_with:filters', 'string'],
            'filters.*.value' => ['required_with:filters'],
            'filters.*.logic' => ['sometimes', 'in:and,or,nand,nor'],
            'sort_column' => ['nullable', 'string', 'max:100'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ];
    }
}
