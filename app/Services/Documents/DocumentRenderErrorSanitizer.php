<?php

namespace App\Services\Documents;

use Throwable;

/**
 * Produces user-facing render error messages without paths or stack traces.
 */
class DocumentRenderErrorSanitizer
{
    private const int MAX_LENGTH = 500;

    public static function sanitize(Throwable $exception): string
    {
        $message = trim(strtok($exception->getMessage(), "\n"));

        if ($message === '') {
            return 'Unable to render document template.';
        }

        $message = preg_replace('/\s*\(View:.*?\)\s*$/', '', $message) ?? $message;
        $message = preg_replace('#\s+in\s+/[^\s]+(\.php:\d+)?#', '', $message) ?? $message;
        $message = preg_replace('#/[\w./-]+\.php:\d+#', '', $message) ?? $message;
        $message = preg_replace('#/[\w./-]+/#', '', $message) ?? $message;
        $message = trim(preg_replace('/\s{2,}/', ' ', $message) ?? $message);

        if ($message === '') {
            return 'Unable to render document template.';
        }

        if (strlen($message) > self::MAX_LENGTH) {
            return substr($message, 0, self::MAX_LENGTH - 1).'…';
        }

        return $message;
    }
}
