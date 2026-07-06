<?php

use App\Services\Documents\QrCodeGenerator;

describe('QrCodeGenerator', function () {
    beforeEach(function () {
        $this->generator = new QrCodeGenerator;
    });

    it('returns svg markup for a url', function () {
        $url = 'https://signals.test/d/example-public-token';

        $svg = $this->generator->generate($url);

        expect($svg)
            ->toStartWith('<?xml')
            ->and($svg)->toContain('<svg')
            ->and($svg)->toContain('</svg>');
    });

    it('produces different output for different urls', function () {
        $first = $this->generator->generate('https://signals.test/d/first-token');
        $second = $this->generator->generate('https://signals.test/d/second-token');

        expect($first)->not->toBe($second);
    });
});
