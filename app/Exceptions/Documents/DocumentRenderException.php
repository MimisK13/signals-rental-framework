<?php

namespace App\Exceptions\Documents;

use RuntimeException;

/**
 * Raised when a document template cannot be compiled or rendered safely.
 *
 * These failures are user-facing (template editor preview, generation) and must
 * never bubble up as unhandled 500 errors.
 */
class DocumentRenderException extends RuntimeException
{
    public static function forbidden(string $reason): self
    {
        return new self($reason);
    }

    public static function compilationFailed(string $detail = ''): self
    {
        $message = 'Unable to render document template.';

        if ($detail !== '') {
            $message .= ' '.$detail;
        }

        return new self($message);
    }
}
