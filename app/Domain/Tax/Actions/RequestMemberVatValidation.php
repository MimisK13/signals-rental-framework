<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Members\Models\Member;
use App\Domain\Tax\Services\MemberVatValidationService;
use Illuminate\Support\Facades\Gate;

final class RequestMemberVatValidation
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
