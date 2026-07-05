<?php

use App\Services\Documents\PageDimensions;

it('resolves standard page sizes in millimetres', function () {
    expect(PageDimensions::sizeMm(['page_size' => 'A4', 'page_orientation' => 'portrait']))
        ->toBe(['width' => 210.0, 'height' => 297.0])
        ->and(PageDimensions::sizeMm(['page_size' => 'A5', 'page_orientation' => 'portrait']))
        ->toBe(['width' => 148.0, 'height' => 210.0])
        ->and(PageDimensions::sizeMm(['page_size' => 'A5', 'page_orientation' => 'landscape']))
        ->toBe(['width' => 210.0, 'height' => 148.0]);
});

it('resolves custom page sizes from canonical millimetre storage', function () {
    expect(PageDimensions::sizeMm([
        'page_size' => 'Custom',
        'page_width_mm' => 100,
        'page_height_mm' => 200,
        'page_orientation' => 'portrait',
    ]))->toBe(['width' => 100.0, 'height' => 200.0]);
});

it('converts between display inches and canonical millimetres', function () {
    expect(PageDimensions::mmToDisplay(25.4, 'in'))->toBe(1.0)
        ->and(PageDimensions::displayToMm(1, 'in'))->toBe(25.4)
        ->and(PageDimensions::mmToDisplay(10, 'mm'))->toBe(10.0)
        ->and(PageDimensions::displayToMm(10, 'mm'))->toBe(10.0);
});

it('builds dompdf paper specs for named and custom sizes', function () {
    expect(PageDimensions::dompdfPaper(['page_size' => 'A5', 'page_orientation' => 'portrait']))
        ->toBe(['a5', 'portrait']);

    [$paper, $orientation] = PageDimensions::dompdfPaper([
        'page_size' => 'Custom',
        'page_width_mm' => 254,
        'page_height_mm' => 508,
        'page_orientation' => 'portrait',
    ]);

    expect($orientation)->toBe('portrait')
        ->and($paper)->toBeArray()
        ->and($paper[0])->toEqual(0)
        ->and($paper[1])->toEqual(0)
        ->and(round($paper[2], 2))->toBe(720.0)
        ->and(round($paper[3], 2))->toBe(1440.0);
});

it('builds browsershot paper specs for named and custom sizes', function () {
    expect(PageDimensions::browsershotPaper(['page_size' => 'A5']))
        ->toBe([
            'format' => 'A5',
            'width' => null,
            'height' => null,
            'unit' => null,
        ])
        ->and(PageDimensions::browsershotPaper([
            'page_size' => 'Custom',
            'page_width_mm' => 120,
            'page_height_mm' => 180,
        ]))->toBe([
            'format' => null,
            'width' => 120.0,
            'height' => 180.0,
            'unit' => 'mm',
        ]);
});
