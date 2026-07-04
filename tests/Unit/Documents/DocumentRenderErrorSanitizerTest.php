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
