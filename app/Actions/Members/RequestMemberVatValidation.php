<?php

namespace App\Actions\Members;

use App\Models\Member;
use App\Services\Tax\MemberVatValidationService;
use Illuminate\Support\Facades\Gate;

class RequestMemberVatValidation
{
    public function __construct(
        private readonly MemberVatValidationService $validationService,
    ) {}

    public function __invoke(Member $member): void
    {
        Gate::authorize('members.edit');

        $this->validationService->requestValidation($member);
    }
}
