<?php

declare(strict_types=1);

namespace App\Domain\Lists\Rules;

use App\Domain\Lists\Models\ListValue;
use Illuminate\Validation\ValidationException;

final class EnsureListValueParentIsValid
{
    public function __invoke(int $listNameId, ?int $parentId, ?int $listValueId = null): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = ListValue::query()->find($parentId);

        if ($parent === null || $parent->list_name_id !== $listNameId) {
            $this->fail('The selected parent must belong to the same list.');
        }

        if ($listValueId === null) {
            return;
        }

        $visited = [];
        $current = $parent;

        while (true) {
            if ($current->id === $listValueId || isset($visited[$current->id])) {
                $this->fail('The selected parent would create a hierarchy cycle.');
            }

            $visited[$current->id] = true;

            if ($current->parent_id === null) {
                return;
            }

            $current = ListValue::query()->find($current->parent_id);

            if ($current === null || $current->list_name_id !== $listNameId) {
                $this->fail('The selected parent has an invalid hierarchy.');
            }
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['parent_id' => $message]);
    }
}
