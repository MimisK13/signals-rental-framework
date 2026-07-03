<?php

use App\Services\Documents\BladeExpressionExtractor;

describe('BladeExpressionExtractor', function () {
    beforeEach(function () {
        $this->extractor = new BladeExpressionExtractor;
    });

    it('extracts echo expressions from benign templates without hanging', function () {
        $expressions = $this->extractor->extract(
            '<p>{{ $opportunity->subject }} ({{ count($items) }})</p>',
        );

        expect($expressions)->toBe([
            '$opportunity->subject',
            'count($items)',
        ]);
    });

    it('extracts raw echo and directive expressions', function () {
        $expressions = $this->extractor->extract(
            '@if($opportunity->total > 0){!! $opportunity->subject !!}@endif',
        );

        expect($expressions)->toContain('$opportunity->total > 0')
            ->and($expressions)->toContain('$opportunity->subject');
    });
});
