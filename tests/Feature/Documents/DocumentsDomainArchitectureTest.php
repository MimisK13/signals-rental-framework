<?php

declare(strict_types=1);

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentSequence;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Presentation\Livewire\Editor;
use App\Domain\Documents\Presentation\Livewire\Index;

it('owns the canonical document models and native presentation', function (): void {
    expect(class_exists(Document::class))->toBeTrue()
        ->and(class_exists(DocumentTemplate::class))->toBeTrue()
        ->and(class_exists(DocumentSequence::class))->toBeTrue()
        ->and(class_exists(Index::class))->toBeTrue()
        ->and(class_exists(Editor::class))->toBeTrue()
        ->and(class_exists('App\\Models\\Document'))->toBeFalse()
        ->and(class_exists('App\\Models\\DocumentTemplate'))->toBeFalse();

    expect(route('admin.document-templates.index'))->toEndWith('/admin/document-templates')
        ->and(route('api.v1.documents.index'))->toEndWith('/api/v1/documents');
});

arch('Documents presentation is native and has no Volt dependency', function (): void {
    expect('App\\Domain\\Documents\\Presentation')->not->toUse('Livewire\\Volt');
});
