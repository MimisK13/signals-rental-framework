<?php

declare(strict_types=1);

namespace App\Domain\Members\Presentation\Http\Controllers\Api\V1;

use App\Domain\Members\Actions\CreateLink;
use App\Domain\Members\Actions\DeleteLink;
use App\Domain\Members\Actions\UpdateLink;
use App\Domain\Members\Data\CreateLinkData;
use App\Domain\Members\Data\LinkData;
use App\Domain\Members\Data\UpdateLinkData;
use App\Domain\Members\Models\Link;
use App\Domain\Members\Models\Member;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemberLinkController extends Controller
{
    /**
     * List links for a member.
     */
    public function index(Member $member): JsonResponse
    {
        $this->authorizeApi('members.view', 'members:read');

        $links = $member->links->map(
            fn (Link $link): array => LinkData::fromModel($link)->toArray()
        )->all();

        return $this->respondWithCollection($links, 'links');
    }

    /**
     * Create a link for a member.
     */
    public function store(Request $request, Member $member): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(CreateLinkData::rules());
        $dto = CreateLinkData::from($validated);

        $result = (new CreateLink)($member, $dto);

        return $this->respondWith(
            $result->toArray(),
            'link',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a link.
     */
    public function update(Request $request, Member $member, Link $link): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        $validated = $request->validate(UpdateLinkData::rules());
        $dto = UpdateLinkData::from($validated);

        $result = (new UpdateLink)($link, $dto);

        return $this->respondWith(
            $result->toArray(),
            'link',
        );
    }

    /**
     * Delete a link.
     */
    public function destroy(Member $member, Link $link): JsonResponse
    {
        $this->authorizeApi('members.edit', 'members:write');

        (new DeleteLink)($link);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
