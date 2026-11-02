<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Actions\Members\CreateEmail;
use App\Actions\Members\UpdateEmail;
use App\Data\Members\CreateEmailData;
use App\Data\Members\UpdateEmailData;
use App\Domain\Lists\Models\ListName;
use App\Models\Email;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class EmailForm extends Component
{
    public Member $member;

    public ?int $emailId = null;

    public string $address = '';

    public ?int $typeId = null;

    public bool $isPrimary = false;

    public function mount(Member $member, ?Email $email = null): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);

        if ($email?->exists) {
            $this->emailId = $email->id;
            $this->address = $email->address;
            $this->typeId = $email->type_id;
            $this->isPrimary = $email->is_primary;
        }
    }

    public function save(): void
    {
        $this->validate([
            'address' => ['required', 'email', 'max:255'],
            'typeId' => ['nullable', 'integer', 'exists:list_values,id'],
            'isPrimary' => ['boolean'],
        ]);

        $data = [
            'address' => $this->address,
            'type_id' => $this->typeId,
            'is_primary' => $this->isPrimary,
        ];

        DB::transaction(function () use ($data) {
            if ($this->isPrimary) {
                $this->member->emails()->update(['is_primary' => false]);
            }

            if ($this->emailId) {
                $email = $this->member->emails()->findOrFail($this->emailId);
                (new UpdateEmail)($email, UpdateEmailData::from($data));
            } else {
                (new CreateEmail)($this->member, CreateEmailData::from($data));
            }
        });

        $this->redirect(route('members.information', $this->member), navigate: true);
    }

    /** @return array<string, mixed> */
    private function viewData(): array
    {
        $emailTypes = ListName::where('name', 'Email Type')->first()?->values()->where('is_active', true)->orderBy('sort_order')->get() ?? collect();

        return [
            'isEditing' => $this->emailId !== null,
            'emailTypes' => $emailTypes,
        ];
    }

    public function render(): View
    {
        return view('livewire.members.email-form', $this->viewData());
    }
}
