<?php

namespace App\Services\Documents;

/**
 * Builds Blade snippets for document template field browser insertions.
 */
class DocumentFieldSnippetBuilder
{
    /** @var array<string, string> */
    private const COLLECTION_LOOP_VARIABLES = [
        'items' => 'item',
        'costs' => 'cost',
        'tax_summary.groups' => 'group',
    ];

    public function scalarExpression(string $fieldName): string
    {
        return '{{ '.$this->toBladeAccess($fieldName).' }}';
    }

    public function collectionSkeleton(string $fieldName): string
    {
        $collectionAccess = $this->toBladeAccess($fieldName);
        $loopVariable = self::COLLECTION_LOOP_VARIABLES[$fieldName]
            ?? str($fieldName)->afterLast('.')->singular()->toString();
        $loopRef = '$'.$loopVariable;

        return "@foreach ({$collectionAccess} as {$loopRef})\n    {{ {$loopRef}->name }}\n@endforeach";
    }

    public function insertSnippet(string $fieldName, string $type): string
    {
        if ($type === 'collection') {
            return $this->collectionSkeleton($fieldName);
        }

        return $this->scalarExpression($fieldName);
    }

    private function toBladeAccess(string $fieldName): string
    {
        if (! str_contains($fieldName, '.')) {
            return '$'.$fieldName;
        }

        [$root, $path] = explode('.', $fieldName, 2);

        return '$'.$root.'->'.str_replace('.', '->', $path);
    }
}
