<?php

declare(strict_types=1);

namespace App\Domain\Attachments\Presentation\Http\Controllers\Api\V1;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\Attachments\Actions\CreateAttachment;
use App\Domain\Attachments\Actions\DeleteAttachment;
use App\Domain\Attachments\Data\AttachmentData;
use App\Domain\Attachments\Data\CreateAttachmentData;
use App\Domain\Attachments\Models\Attachment;
use App\Domain\Members\Models\Member;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class AttachmentController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'mime_type',
        'category',
        'original_name',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'created_at',
        'original_name',
        'file_size',
    ];

    /**
     * List attachments for a member.
     *
     * @response array{attachments: list<AttachmentData>, meta: array{total: int, per_page: int, page: int}}
     */
    public function indexForMember(Request $request, Member $member): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        $query = $member->attachments()->getQuery();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $attachments = $paginator->getCollection()->map(
            fn (Attachment $attachment): array => AttachmentData::fromModel($attachment)->toArray()
        )->all();

        return $this->respondWithCollection($attachments, 'attachments', $paginator);
    }

    /**
     * Show a single attachment.
     *
     * @response array{attachment: AttachmentData}
     */
    public function show(Attachment $attachment): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        return $this->respondWith(
            AttachmentData::fromModel($attachment)->toArray(),
            'attachment',
        );
    }

    /**
     * Upload a new attachment.
     */
    #[IgnoreResponse(200)]
    #[ApiResponse(status: 201, type: 'array{attachment: AttachmentData}')]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(CreateAttachmentData::rules());
        $dto = CreateAttachmentData::from($validated);

        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            return response()->json([
                'message' => 'File upload failed. The file may exceed server size limits.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = (new CreateAttachment)($dto, $file);

        return response()->json([
            'attachment' => $result->toArray(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Delete an attachment.
     */
    public function destroy(Attachment $attachment): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        (new DeleteAttachment)($attachment);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
