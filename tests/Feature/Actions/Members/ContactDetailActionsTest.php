<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Members\Actions\CreateAddress;
use App\Domain\Members\Actions\CreateEmail;
use App\Domain\Members\Actions\CreateLink;
use App\Domain\Members\Actions\CreatePhone;
use App\Domain\Members\Actions\DeleteAddress;
use App\Domain\Members\Actions\DeleteEmail;
use App\Domain\Members\Actions\DeleteLink;
use App\Domain\Members\Actions\DeletePhone;
use App\Domain\Members\Actions\UpdateAddress;
use App\Domain\Members\Actions\UpdateEmail;
use App\Domain\Members\Actions\UpdateLink;
use App\Domain\Members\Actions\UpdatePhone;
use App\Domain\Members\Data\CreateAddressData;
use App\Domain\Members\Data\CreateEmailData;
use App\Domain\Members\Data\CreateLinkData;
use App\Domain\Members\Data\CreatePhoneData;
use App\Domain\Members\Data\UpdateAddressData;
use App\Domain\Members\Data\UpdateEmailData;
use App\Domain\Members\Data\UpdateLinkData;
use App\Domain\Members\Data\UpdatePhoneData;
use App\Domain\Members\Models\Address;
use App\Domain\Members\Models\Email;
use App\Domain\Members\Models\Link;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\Phone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->member = Member::factory()->organisation()->create();
});

// Addresses

it('creates an address for a member', function () {
    Event::fake([AuditableEvent::class]);

    $data = CreateAddressData::from([
        'street' => '123 Main St',
        'city' => 'London',
        'postcode' => 'SW1A 1AA',
    ]);

    $result = (new CreateAddress)($this->member, $data);

    expect($result->street)->toBe('123 Main St');
    expect($result->city)->toBe('London');
    expect($this->member->addresses()->count())->toBe(1);

    Event::assertDispatched(AuditableEvent::class);
});

it('updates an address', function () {
    Event::fake([AuditableEvent::class]);

    $address = Address::factory()->for($this->member, 'addressable')->create();

    $data = UpdateAddressData::from(['city' => 'Manchester']);

    $result = (new UpdateAddress)($address, $data);

    expect($result->city)->toBe('Manchester');

    Event::assertDispatched(AuditableEvent::class);
});

it('deletes an address', function () {
    Event::fake([AuditableEvent::class]);

    $address = Address::factory()->for($this->member, 'addressable')->create();

    (new DeleteAddress)($address);

    expect(Address::find($address->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

// Emails

it('creates an email for a member', function () {
    Event::fake([AuditableEvent::class]);

    $data = CreateEmailData::from([
        'address' => 'test@example.com',
        'is_primary' => true,
    ]);

    $result = (new CreateEmail)($this->member, $data);

    expect($result->address)->toBe('test@example.com');
    expect($result->is_primary)->toBeTrue();

    Event::assertDispatched(AuditableEvent::class);
});

it('updates an email', function () {
    Event::fake([AuditableEvent::class]);

    $email = Email::factory()->for($this->member, 'emailable')->create();

    $data = UpdateEmailData::from(['address' => 'new@example.com']);

    $result = (new UpdateEmail)($email, $data);

    expect($result->address)->toBe('new@example.com');

    Event::assertDispatched(AuditableEvent::class);
});

it('deletes an email', function () {
    Event::fake([AuditableEvent::class]);

    $email = Email::factory()->for($this->member, 'emailable')->create();

    (new DeleteEmail)($email);

    expect(Email::find($email->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

// Phones

it('creates a phone for a member', function () {
    Event::fake([AuditableEvent::class]);

    $data = CreatePhoneData::from([
        'number' => '+44 20 7946 0958',
        'is_primary' => true,
    ]);

    $result = (new CreatePhone)($this->member, $data);

    expect($result->number)->toBe('+44 20 7946 0958');
    expect($result->is_primary)->toBeTrue();

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a phone', function () {
    Event::fake([AuditableEvent::class]);

    $phone = Phone::factory()->for($this->member, 'phoneable')->create();

    $data = UpdatePhoneData::from(['number' => '+1 555 0199']);

    $result = (new UpdatePhone)($phone, $data);

    expect($result->number)->toBe('+1 555 0199');

    Event::assertDispatched(AuditableEvent::class);
});

it('deletes a phone', function () {
    Event::fake([AuditableEvent::class]);

    $phone = Phone::factory()->for($this->member, 'phoneable')->create();

    (new DeletePhone)($phone);

    expect(Phone::find($phone->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

// Links

it('creates a link for a member', function () {
    Event::fake([AuditableEvent::class]);

    $data = CreateLinkData::from([
        'url' => 'https://example.com',
        'name' => 'Website',
    ]);

    $result = (new CreateLink)($this->member, $data);

    expect($result->url)->toBe('https://example.com');
    expect($result->name)->toBe('Website');

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a link', function () {
    Event::fake([AuditableEvent::class]);

    $link = Link::factory()->for($this->member, 'linkable')->create();

    $data = UpdateLinkData::from(['name' => 'Updated Website']);

    $result = (new UpdateLink)($link, $data);

    expect($result->name)->toBe('Updated Website');

    Event::assertDispatched(AuditableEvent::class);
});

it('deletes a link', function () {
    Event::fake([AuditableEvent::class]);

    $link = Link::factory()->for($this->member, 'linkable')->create();

    (new DeleteLink)($link);

    expect(Link::find($link->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

// Authorization

it('rejects unauthorized contact detail creation', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $data = CreateAddressData::from(['street' => '123 Main St']);

    (new CreateAddress)($this->member, $data);
})->throws(AuthorizationException::class);
