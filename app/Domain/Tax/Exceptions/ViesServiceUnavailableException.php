<?php

declare(strict_types=1);

namespace App\Domain\Tax\Exceptions;

use RuntimeException;

/**
 * Raised when the VIES registry cannot be reached or returns a service error.
 */
final class ViesServiceUnavailableException extends RuntimeException {}
