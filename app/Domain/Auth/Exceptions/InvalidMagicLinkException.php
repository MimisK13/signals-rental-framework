<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

/**
 * Thrown when a magic-link sign-in link cannot be consumed (spec §8, §10).
 *
 * Deliberately reasonless: every failure path — unknown/expired/consumed token,
 * inactive user, feature disabled, or SSO now enforced — throws the same
 * exception so the consume controller can surface a single generic message and
 * never leak which check failed.
 */
final class InvalidMagicLinkException extends RuntimeException {}
