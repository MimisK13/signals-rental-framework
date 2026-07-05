<?php

use App\Services\Documents\DocumentRenderErrorSanitizer;

it('sanitises render exceptions without file paths or stack traces', function () {
    $exception = new ErrorException(
        'Undefined variable $credit_note in /var/www/app/storage/framework/views/documents/abc.php:42',
    );

    $message = DocumentRenderErrorSanitizer::sanitize($exception);

    expect($message)->toBe('Undefined variable $credit_note')
        ->and($message)->not->toContain('/var/www')
        ->and($message)->not->toContain('Stack trace');
});

it('returns the fallback message for empty exceptions', function () {
    expect(DocumentRenderErrorSanitizer::sanitize(new Exception('')))
        ->toBe('Unable to render document template.');
});

it('truncates very long messages', function () {
    $sanitized = DocumentRenderErrorSanitizer::sanitize(new Exception(str_repeat('x', 600)));

    expect(mb_strlen($sanitized))->toBe(500)
        ->and(str_ends_with($sanitized, '…'))->toBeTrue();
});
