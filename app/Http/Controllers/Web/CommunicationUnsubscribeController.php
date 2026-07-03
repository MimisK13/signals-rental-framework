<?php

namespace App\Http\Controllers\Web;

use App\Actions\Notifications\OptOutMemberCommunication;
use App\Models\Member;
use App\Services\Notifications\CommunicationPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CommunicationUnsubscribeController
{
    public function __construct(
        private readonly CommunicationPreferences $preferences,
        private readonly OptOutMemberCommunication $optOut,
    ) {}

    public function show(Request $request, int $member): Response|HttpResponse
    {
        $notificationTypeKey = $this->validatedType($request);

        if ($notificationTypeKey === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $memberModel = $this->resolveMember($member);

        if ($memberModel === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        return response()->view('communications.unsubscribe', [
            'maskedEmail' => $this->preferences->maskedEmail($memberModel),
            'notificationTypeKey' => $notificationTypeKey,
            'confirmUrl' => $request->fullUrl(),
            'unsubscribed' => false,
        ]);
    }

    public function confirm(Request $request, int $member): RedirectResponse|HttpResponse
    {
        $notificationTypeKey = $this->validatedType($request);

        if ($notificationTypeKey === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $memberModel = $this->resolveMember($member);

        if ($memberModel === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        ($this->optOut)($memberModel, $notificationTypeKey);

        return response()->view('communications.unsubscribe', [
            'maskedEmail' => $this->preferences->maskedEmail($memberModel),
            'notificationTypeKey' => $notificationTypeKey,
            'confirmUrl' => $request->fullUrl(),
            'unsubscribed' => true,
        ]);
    }

    private function validatedType(Request $request): ?string
    {
        $type = $request->query('type');

        if (! is_string($type) || $type === '' || strlen($type) > 191) {
            return null;
        }

        return $type;
    }

    private function resolveMember(int $memberId): ?Member
    {
        return Member::query()->find($memberId);
    }
}
