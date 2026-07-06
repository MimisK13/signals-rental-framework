<?php

namespace App\Services\Documents;

use Illuminate\Support\Str;

class DocumentPublicTokenGenerator
{
    public function generate(): string
    {
        $length = max(32, (int) config('documents.public_token_length', 64));

        return Str::random($length);
    }
}
