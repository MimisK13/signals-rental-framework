<?php

use App\Models\Attachment;
use App\Models\Document;
use App\Models\Member;
use App\Models\User;
use App\Services\FileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // FileService maps 'local' → 'public' for web-accessible storage
    $default = config('filesystems.default', 'local');
    $this->disk = $default === 'local' ? 'public' : $default;
    Storage::fake($this->disk);
    $this->service = new FileService;
    $this->actingAs(User::factory()->create());
});

it('uploads a file and creates an attachment record', function () {
    $member = Member::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $attachment = $this->service->upload($file, $member);

    expect($attachment)->toBeInstanceOf(Attachment::class)
        ->and($attachment->original_name)->toBe('document.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->attachable_type)->toBe($member->getMorphClass())
        ->and($attachment->attachable_id)->toBe($member->id)
        ->and($attachment->uploaded_by)->toBe(auth()->id())
        ->and($attachment->disk)->toBe($this->disk);

    Storage::disk($this->disk)->assertExists($attachment->file_path);
});

it('marks a newly uploaded attachment with a pending scan status', function () {
    $member = Member::factory()->create();
    $file = UploadedFile::fake()->create('scan-me.pdf', 128, 'application/pdf');

    $attachment = $this->service->upload($file, $member);

    // New uploads must default to 'pending' (awaiting virus scan), not 'clean'.
    expect($attachment->fresh()->scan_status)->toBe('pending');
});

it('uploads a file with category and description', function () {
    $member = Member::factory()->create();
    $file = UploadedFile::fake()->create('contract.pdf', 2048, 'application/pdf');

    $attachment = $this->service->upload($file, $member, 'contracts', 'Main contract');

    expect($attachment->category)->toBe('contracts')
        ->and($attachment->description)->toBe('Main contract');
});

it('uploads an icon and generates a thumbnail', function () {
    $member = Member::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);

    $result = $this->service->uploadIcon($file, $member);

    expect($result)->toHaveKeys(['icon_url', 'icon_thumb_url'])
        ->and($result['icon_url'])->toStartWith("icons/members/{$member->id}/")
        ->and($result['icon_thumb_url'])->toStartWith("icons/members/{$member->id}/thumbs/");

    Storage::disk($this->disk)->assertExists($result['icon_url']);
    Storage::disk($this->disk)->assertExists($result['icon_thumb_url']);
});

it('deletes an attachment and its files from storage', function () {
    $member = Member::factory()->create();
    $file = UploadedFile::fake()->create('to-delete.pdf', 512, 'application/pdf');

    $attachment = $this->service->upload($file, $member);
    $path = $attachment->file_path;

    Storage::disk($this->disk)->assertExists($path);

    $this->service->delete($attachment);

    Storage::disk($this->disk)->assertMissing($path);
    expect(Attachment::find($attachment->id))->toBeNull();
});

it('deletes attachment with thumbnail', function () {
    $member = Member::factory()->create();
    Storage::disk($this->disk)->put('attachments/test.pdf', 'content');
    Storage::disk($this->disk)->put('attachments/thumbs/test.jpg', 'thumb');

    $attachment = Attachment::factory()->create([
        'attachable_type' => $member->getMorphClass(),
        'attachable_id' => $member->id,
        'file_path' => 'attachments/test.pdf',
        'thumb_path' => 'attachments/thumbs/test.jpg',
        'disk' => $this->disk,
    ]);

    $this->service->delete($attachment);

    Storage::disk($this->disk)->assertMissing('attachments/test.pdf');
    Storage::disk($this->disk)->assertMissing('attachments/thumbs/test.jpg');
});

it('organises upload path by entity type and id', function () {
    $member = Member::factory()->create();
    $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

    $attachment = $this->service->upload($file, $member);

    expect($attachment->file_path)->toMatch("/^attachments\/member\/{$member->id}\/[0-9a-f\-]+\.pdf$/");
});

it('generates signed url for public disk', function () {
    $path = 'attachments/test-file.pdf';
    Storage::disk($this->disk)->put($path, 'content');

    $url = $this->service->signedUrl($path);

    expect($url)->toBeString()->toContain('test-file.pdf');
});

it('returns a signed url via signedUrlOrNull for a stored path', function () {
    $path = 'attachments/test-file.pdf';
    Storage::disk($this->disk)->put($path, 'content');

    $url = $this->service->signedUrlOrNull($path);

    expect($url)->toBeString()->toContain('test-file.pdf');
});

it('returns null from signedUrlOrNull for null or empty paths', function (?string $path) {
    expect($this->service->signedUrlOrNull($path))->toBeNull();
})->with([
    'null path' => [null],
    'empty path' => [''],
]);

it('returns null from signedUrlOrNull when the underlying signed url throws', function () {
    Storage::shouldReceive('disk')->andReturnSelf();
    Storage::shouldReceive('url')->andThrow(new RuntimeException('S3 unavailable'));

    config(['filesystems.default' => 'public']);

    expect((new FileService)->signedUrlOrNull('attachments/x.pdf'))->toBeNull();
});

it('throws when storage put fails on upload', function () {
    Storage::shouldReceive('disk')->andReturnSelf();
    Storage::shouldReceive('put')->andReturn(false);

    $member = Member::factory()->create();
    $file = UploadedFile::fake()->create('fail.pdf', 100, 'application/pdf');

    $this->service->upload($file, $member);
})->throws(RuntimeException::class, 'Failed to store file');

it('throws when icon storage put fails', function () {
    $putCalls = 0;
    Storage::shouldReceive('disk')->andReturnSelf();
    Storage::shouldReceive('put')->andReturnUsing(function () use (&$putCalls) {
        $putCalls++;

        return $putCalls === 1 ? false : true;
    });

    $member = Member::factory()->create();
    $file = UploadedFile::fake()->image('icon.jpg', 200, 200);

    $this->service->uploadIcon($file, $member);
})->throws(RuntimeException::class, 'Failed to store icon');

it('generates a uuid for the attachment', function () {
    $member = Member::factory()->create();
    $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

    $attachment = $this->service->upload($file, $member);

    expect($attachment->uuid)->not->toBeNull()
        ->and($attachment->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('uses the configured disk directly when it is not local', function () {
    config(['filesystems.default' => 'public']);
    Storage::fake('public');

    $member = Member::factory()->create();
    $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

    $attachment = (new FileService)->upload($file, $member);

    expect($attachment->disk)->toBe('public');
    Storage::disk('public')->assertExists($attachment->file_path);
});

it('cleans up the icon and throws when thumbnail storage fails', function () {
    $putCalls = 0;
    Storage::shouldReceive('disk')->andReturnSelf();
    Storage::shouldReceive('put')->andReturnUsing(function () use (&$putCalls) {
        $putCalls++;

        // Icon stores successfully, thumbnail fails.
        return $putCalls !== 2;
    });
    Storage::shouldReceive('delete')->once();

    $member = Member::factory()->create();
    $file = UploadedFile::fake()->image('icon.jpg', 200, 200);

    (new FileService)->uploadIcon($file, $member);
})->throws(RuntimeException::class, 'Failed to store thumbnail');

it('generates a temporary signed url for non-local disks', function () {
    config(['filesystems.default' => 's3']);

    Storage::shouldReceive('disk')->with('s3')->andReturnSelf();
    Storage::shouldReceive('temporaryUrl')->once()->andReturn('https://s3.example.com/signed-file');

    $url = (new FileService)->signedUrl('attachments/x.pdf');

    expect($url)->toBe('https://s3.example.com/signed-file');
});

it('builds a flat storage path when no entity is provided', function () {
    $service = new FileService;
    $method = new ReflectionMethod($service, 'storePath');

    $path = $method->invoke($service, 'abc-uuid', 'pdf', null);

    expect($path)->toBe('attachments/abc-uuid.pdf');
});

describe('document filenames', function () {
    it('builds inline filenames from document number and type label', function () {
        expect($this->service->inlineFilename('INV-2026-0003', 'invoice', 'path/to/file.pdf'))
            ->toBe('INV-2026-0003-invoice.pdf');
    });

    it('sanitizes weird characters in inline filenames', function () {
        expect($this->service->inlineFilename('INV 2026/0003', 'Credit Note', 'file.pdf'))
            ->toBe('INV-2026-0003-credit-note.pdf');
    });

    it('builds document filenames from numbered documents using registry labels', function () {
        $document = Document::factory()->make([
            'id' => 7,
            'document_type' => 'invoice',
            'document_number' => 'INV-2026-0003',
        ]);

        expect($this->service->documentFilename($document, 'documents/invoice/7/file.pdf'))
            ->toBe('INV-2026-0003-invoice.pdf');
    });

    it('uses fallback filename when document has no number', function () {
        $document = Document::factory()->make([
            'id' => 42,
            'document_type' => 'statement',
            'document_number' => '',
        ]);

        expect($this->service->documentFilename($document, 'documents/statement/42/file.pdf'))
            ->toBe('statement-42.pdf');
    });

    it('generates a temporary signed route for local document downloads', function () {
        $path = 'documents/quotes/1/test.pdf';
        Storage::disk($this->disk)->put($path, 'pdf-content');

        $document = Document::factory()->create([
            'document_type' => 'quote',
            'document_number' => 'QUO-0001',
            'pdf_path' => $path,
        ]);

        $url = $this->service->signedDocumentUrl($document, $path);

        expect($url)->toContain('documents/'.$document->id.'/file')
            ->and($url)->toContain('signature=');

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="QUO-0001-quote.pdf"');
    });

    it('includes content disposition when generating signed document urls for s3', function () {
        config(['filesystems.default' => 's3']);

        $document = Document::factory()->make([
            'id' => 9,
            'document_type' => 'invoice',
            'document_number' => 'INV-2026-0003',
            'pdf_path' => 'documents/invoice/9/test.pdf',
        ]);

        Storage::shouldReceive('disk')->with('s3')->andReturnSelf();
        Storage::shouldReceive('temporaryUrl')->once()->withArgs(function (string $path, $expiry, array $options): bool {
            return $path === 'documents/invoice/9/test.pdf'
                && ($options['ResponseContentDisposition'] ?? '') === 'inline; filename="INV-2026-0003-invoice.pdf"';
        })->andReturn('https://s3.example.com/signed-file');

        $url = (new FileService)->signedDocumentUrl($document, 'documents/invoice/9/test.pdf');

        expect($url)->toBe('https://s3.example.com/signed-file');
    });
});
