<?php

namespace App\Exceptions\Containers;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Warn-and-resolve packing conflict surfaced as HTTP 409 with structured
 * resolution options (serialised-containers.md §Pack Endpoint Conflict Handling).
 */
class ContainerPackConflictException extends HttpException
{
    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, array{available: bool, requires_permission?: string, description: string}>  $resolutions
     */
    public function __construct(
        public readonly string $conflict,
        public readonly array $details,
        public readonly array $resolutions,
    ) {
        parent::__construct(Response::HTTP_CONFLICT, __('Packing this item requires a resolution.'));
    }

    /**
     * @return array{conflict: string, details: array<string, mixed>, resolutions: array<string, array{available: bool, requires_permission?: string, description: string}>}
     */
    public function payload(): array
    {
        return [
            'conflict' => $this->conflict,
            'details' => $this->details,
            'resolutions' => $this->resolutions,
        ];
    }
}
