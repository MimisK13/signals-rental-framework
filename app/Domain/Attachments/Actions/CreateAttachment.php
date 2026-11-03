<?php

declare(strict_types=1);

namespace App\Domain\Attachments\Actions;

use App\Domain\Assets\Models\AssetInspection;
use App\Domain\Assets\Repairs\Models\Repair;
use App\Domain\Attachments\Data\AttachmentData;
use App\Domain\Attachments\Data\CreateAttachmentData;
use App\Models\Member;
use App\Services\FileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

final class CreateAttachment
{
    /** @var array<string, class-string<Model>> */
    private array $attachableTypes = [
        'Member' => Member::class,
        'Repair' => Repair::class,
        'AssetInspection' => AssetInspection::class,
    ];

    public function __invoke(CreateAttachmentData $data, UploadedFile $file): AttachmentData
    {
        $modelClass = $this->attachableTypes[$data->attachable_type]
            ?? throw new \InvalidArgumentException("Unknown attachable type: {$data->attachable_type}");

        $entity = $modelClass::findOrFail($data->attachable_id);

        Gate::authorize('view', $entity);

        $fileService = app(FileService::class);
        $attachment = $fileService->upload($file, $entity, $data->category, $data->description);

        return AttachmentData::fromModel($attachment);
    }
}
