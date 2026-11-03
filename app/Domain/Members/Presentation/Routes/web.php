<?php

declare(strict_types=1);

use App\Domain\Members\Presentation\Livewire\Addresses;
use App\Domain\Members\Presentation\Livewire\AddressForm;
use App\Domain\Members\Presentation\Livewire\CustomFields;
use App\Domain\Members\Presentation\Livewire\EmailForm;
use App\Domain\Members\Presentation\Livewire\Emails;
use App\Domain\Members\Presentation\Livewire\Form;
use App\Domain\Members\Presentation\Livewire\Index;
use App\Domain\Members\Presentation\Livewire\Information;
use App\Domain\Members\Presentation\Livewire\Invoices;
use App\Domain\Members\Presentation\Livewire\LinkForm;
use App\Domain\Members\Presentation\Livewire\Links;
use App\Domain\Members\Presentation\Livewire\MemberContacts;
use App\Domain\Members\Presentation\Livewire\Opportunities;
use App\Domain\Members\Presentation\Livewire\PhoneForm;
use App\Domain\Members\Presentation\Livewire\Phones;
use App\Domain\Members\Presentation\Livewire\Quotes;
use App\Domain\Members\Presentation\Livewire\RelationshipForm;
use App\Domain\Members\Presentation\Livewire\Relationships;
use App\Domain\Members\Presentation\Livewire\Show;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signals.setup-complete', 'auth', '2fa', 'signals.session-timeout'])->group(function (): void {
    Route::livewire('members', Index::class)->name('members.index');
    Route::livewire('members/create', Form::class)->name('members.create');
    Route::livewire('members/{member}', Show::class)->name('members.show');
    Route::livewire('members/{member}/edit', Form::class)->name('members.edit');
    Route::livewire('members/{member}/addresses', Addresses::class)->name('members.addresses');
    Route::livewire('members/{member}/addresses/create', AddressForm::class)->name('members.addresses.create');
    Route::livewire('members/{member}/addresses/{address}/edit', AddressForm::class)->name('members.addresses.edit');
    Route::livewire('members/{member}/emails', Emails::class)->name('members.emails');
    Route::livewire('members/{member}/emails/create', EmailForm::class)->name('members.emails.create');
    Route::livewire('members/{member}/emails/{email}/edit', EmailForm::class)->name('members.emails.edit');
    Route::livewire('members/{member}/phones', Phones::class)->name('members.phones');
    Route::livewire('members/{member}/phones/create', PhoneForm::class)->name('members.phones.create');
    Route::livewire('members/{member}/phones/{phone}/edit', PhoneForm::class)->name('members.phones.edit');
    Route::livewire('members/{member}/links', Links::class)->name('members.links');
    Route::livewire('members/{member}/links/create', LinkForm::class)->name('members.links.create');
    Route::livewire('members/{member}/links/{link}/edit', LinkForm::class)->name('members.links.edit');
    Route::livewire('members/{member}/custom-fields', CustomFields::class)->name('members.custom-fields');
    Route::livewire('members/{member}/relationships', Relationships::class)->name('members.relationships');
    Route::livewire('members/{member}/relationships/create', RelationshipForm::class)->name('members.relationships.create');
    Route::livewire('members/{member}/quotes', Quotes::class)->name('members.quotes');
    Route::livewire('members/{member}/opportunities', Opportunities::class)->name('members.opportunities');
    Route::livewire('members/{member}/invoices', Invoices::class)->name('members.invoices');
    Route::livewire('members/{member}/information', Information::class)->name('members.information');
    Route::livewire('members/{member}/member-contacts', MemberContacts::class)->name('members.contacts');
});
