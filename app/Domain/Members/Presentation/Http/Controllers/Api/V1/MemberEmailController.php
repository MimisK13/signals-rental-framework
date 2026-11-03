<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Http\Controllers\Api\V1;

use App\Domain\Members\Actions\CreateEmail;
use App\Domain\Members\Actions\DeleteEmail;
use App\Domain\Members\Actions\UpdateEmail;
use App\Domain\Members\Data\CreateEmailData;
use App\Domain\Members\Data\EmailData;
use App\Domain\Members\Data\UpdateEmailData;
use App\Domain\Members\Models\Email;
use App\Domain\Members\Models\Member;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemberEmailController extends Controller
{
    /**
     * List emails for a member.
     */
    public function index(Member $member): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        $emails = $member->emails->map(
            fn (Email $email): array => EmailData::fromModel($email)->toArray()
        )->all();

        return $this->respondWithCollection($emails, 'emails');
    }

    /**
     * Create an email for a member.
     */
    public function store(Request $request, Member $member): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(CreateEmailData::rules());
        $dto = CreateEmailData::from($validated);

        $result = (new CreateEmail)($member, $dto);

        return $this->respondWith(
            $result->toArray(),
            'email',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an email.
     */
    public function update(Request $request, Member $member, Email $email): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(UpdateEmailData::rules());
        $dto = UpdateEmailData::from($validated);

        $result = (new UpdateEmail)($email, $dto);

        return $this->respondWith(
            $result->toArray(),
            'email',
        );
    }

    /**
     * Delete an email.
     */
    public function destroy(Member $member, Email $email): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        (new DeleteEmail)($email);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
