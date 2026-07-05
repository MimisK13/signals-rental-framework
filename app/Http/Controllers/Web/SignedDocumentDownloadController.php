<?php

namespace App\Http\Controllers\Web;

use App\Models\Document;
use App\Models\Scopes\StoreScope;
use App\Services\FileService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SignedDocumentDownloadController
{
    public function __construct(
        private readonly FileService $fileService,
    ) {}

    public function __invoke(int $documentId): Response
    {
        $document = StoreScope::withoutScoping(
            fn (): Document => Document::query()->findOrFail($documentId),
        );

        $path = $document->pdf_path ?? $document->html_snapshot_path;

        if ($path === null || $path === '') {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $contents = $this->fileService->getContents($path);
        $mimeType = $this->fileService->mimeTypeForPath($path);
        $filename = $this->fileService->documentFilename($document, $path);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (str_contains($mimeType, 'html')) {
            $headers['Content-Security-Policy'] = "sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:";
            $headers['X-Frame-Options'] = 'DENY';
        }

        return response($contents, HttpResponse::HTTP_OK, $headers);
    }
}
