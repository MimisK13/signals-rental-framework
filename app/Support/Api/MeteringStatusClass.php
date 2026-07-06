<?php

namespace App\Support\Api;

class MeteringStatusClass
{
    public static function fromStatusCode(?int $statusCode): string
    {
        if ($statusCode === null || $statusCode === 0) {
            return '5xx';
        }

        if ($statusCode >= 500) {
            return '5xx';
        }

        if ($statusCode >= 400) {
            return '4xx';
        }

        return '2xx';
    }
}
