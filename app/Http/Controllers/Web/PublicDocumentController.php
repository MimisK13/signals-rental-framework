<?php

namespace App\Http\Controllers\Web;

use App\Actions\Documents\RecordDocumentPublicView;
use App\Models\Document;
use App\Services\FileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PublicDocumentController
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly RecordDocumentPublicView $recordView,
    ) {}

    public function show(Request $request, string $token): Response|HttpResponse
    {
        $document = RecordDocumentPublicView::findByToken($token);

        if (! $this->isAccessible($document)) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        /** @var Document $document */
        if ($this->isExpired($document)) {
            return response()->view('documents.public.expired', [], HttpResponse::HTTP_GONE);
        }

        if ($this->requiresPin($document) && ! $this->pinVerified($request, $token)) {
            return response()->view('documents.public.pin-form', [
                'token' => $token,
                'error' => session('document_pin_error'),
            ]);
        }

        ($this->recordView)($document);

        return $this->streamDocument($document);
    }

    public function verifyPin(Request $request, string $token): Response|RedirectResponse|HttpResponse
    {
        $document = RecordDocumentPublicView::findByToken($token);

        if (! $this->isAccessible($document)) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        /** @var Document $document */
        if ($this->isExpired($document)) {
            return response()->view('documents.public.expired', [], HttpResponse::HTTP_GONE);
        }

        if (! $this->requiresPin($document)) {
            return redirect()->route('documents.public.show', ['token' => $token]);
        }

        $rateLimitKey = $this->pinRateLimitKey($request, $token);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $this->maxPinAttempts())) {
            return response()->view('documents.public.pin-form', [
                'token' => $token,
                'error' => __('Too many incorrect PIN attempts. Please try again later.'),
            ], HttpResponse::HTTP_TOO_MANY_REQUESTS);
        }

        $validated = $request->validate([
            'pin' => ['required', 'string', 'max:32'],
        ]);

        if (! Hash::check($validated['pin'], (string) $document->public_pin_hash)) {
            RateLimiter::hit($rateLimitKey, $this->pinDecaySeconds());

            return response()->view('documents.public.pin-form', [
                'token' => $token,
                'error' => __('The PIN you entered is incorrect.'),
            ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        RateLimiter::clear($rateLimitKey);
        $request->session()->put($this->sessionKey($token), true);

        return redirect()->route('documents.public.show', ['token' => $token]);
    }

    private function streamDocument(Document $document): Response
    {
        $path = $document->pdf_path ?? $document->html_snapshot_path;

        if ($path === null || $path === '') {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $contents = $this->fileService->getContents($path);
        $mimeType = $this->fileService->mimeTypeForPath($path);
        $filename = $this->fileService->inlineFilename($document->document_number, $path);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (str_contains($mimeType, 'html')) {
            // Stored template HTML is untrusted for the app origin: the CSP
            // sandbox gives it a null origin with scripts disabled so any
            // injected markup cannot reach app cookies or APIs.
            $headers['Content-Security-Policy'] = "sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:";
            $headers['X-Frame-Options'] = 'DENY';
        }

        return response($contents, HttpResponse::HTTP_OK, $headers);
    }

    private function isAccessible(?Document $document): bool
    {
        if ($document === null) {
            return false;
        }

        if ($document->public_token === null || $document->public_token === '') {
            return false;
        }

        if ($document->status->value === 'void') {
            return false;
        }

        return true;
    }

    private function isExpired(Document $document): bool
    {
        return $document->public_expires_at?->isPast() ?? false;
    }

    private function requiresPin(Document $document): bool
    {
        return is_string($document->public_pin_hash) && $document->public_pin_hash !== '';
    }

    private function pinVerified(Request $request, string $token): bool
    {
        return (bool) $request->session()->get($this->sessionKey($token), false);
    }

    private function sessionKey(string $token): string
    {
        return 'document_public_access.'.hash('sha256', $token);
    }

    private function pinRateLimitKey(Request $request, string $token): string
    {
        return 'document-public-pin:'.hash('sha256', $token).':'.$request->ip();
    }

    private function maxPinAttempts(): int
    {
        return max(1, (int) config('documents.public_pin_max_attempts', 5));
    }

    private function pinDecaySeconds(): int
    {
        return max(60, (int) config('documents.public_pin_decay_seconds', 900));
    }
}
