<?php

declare(strict_types=1);

namespace App\Domain\Attachments\Actions;

use App\Domain\Attachments\Models\Attachment;
use App\Domain\Attachments\Services\FileService;
use Illuminate\Support\Facades\Gate;

final class DeleteAttachment
{
    public function __invoke(Attachment $attachment): void
    {
        Gate::authorize('delete', $attachment);

        app(FileService::class)->delete($attachment);
    }
}
