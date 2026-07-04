<?php

namespace App\Exceptions\Containers;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Transport container dispatch requires user-approved opportunity amendments
 * (serialised-containers.md §"Dispatch Endpoint Detail" / 409 response).
 */
class ContainerDispatchAmendmentRequiredException extends HttpException
{
    /**
     * @param  list<array<string, mixed>>  $amendments
     * @param  list<array<string, mixed>>  $validation
     */
    public function __construct(
        public readonly array $amendments,
        public readonly array $validation,
    ) {
        parent::__construct(
            Response::HTTP_CONFLICT,
            __('Container dispatch requires opportunity amendments before it can proceed.'),
        );
    }

    /**
     * @return array{message: string, amendments: list<array<string, mixed>>, validation: list<array<string, mixed>>}
     */
    public function payload(): array
    {
        return [
            'message' => $this->getMessage(),
            'amendments' => $this->amendments,
            'validation' => $this->validation,
        ];
    }
}
