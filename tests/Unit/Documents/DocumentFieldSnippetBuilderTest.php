<?php

use App\Services\Documents\DocumentFieldSnippetBuilder;

describe('DocumentFieldSnippetBuilder', function () {
    beforeEach(function () {
        $this->builder = new DocumentFieldSnippetBuilder;
    });

    it('builds scalar blade expressions from field paths', function () {
        expect($this->builder->scalarExpression('opportunity.subject'))
            ->toBe('{{ $opportunity->subject }}')
            ->and($this->builder->scalarExpression('member.name'))
            ->toBe('{{ $member->name }}');
    });

    it('builds foreach skeletons for collection fields', function () {
        $snippet = $this->builder->collectionSkeleton('items');

        expect($snippet)->toContain('@foreach ($items as $item)')
            ->and($snippet)->toContain('{{ $item->name }}');
    });

    it('inserts the correct snippet by field type', function () {
        expect($this->builder->insertSnippet('opportunity.grand_total', 'currency'))
            ->toBe('{{ $opportunity->grand_total }}')
            ->and($this->builder->insertSnippet('items', 'collection'))
            ->toContain('@foreach ($items as $item)');
    });
});
