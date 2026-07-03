<?php

namespace App\Exceptions\Tax;

use RuntimeException;

/**
 * Raised when the VIES registry cannot be reached or returns a service error.
 */
class ViesServiceUnavailableException extends RuntimeException {}
