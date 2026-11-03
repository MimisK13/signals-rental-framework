<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Communications\Mail\TemplatedEmail;
use App\Domain\Settings\Presentation\Livewire\Admin\Email;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the email settings page', function () {
    $this->get(route('admin.settings.email'))
        ->assertOk()
        ->assertSee('Email');
});

it('loads current settings with registry defaults', function () {
    Livewire::test(Email::class)
        ->assertSet('mailer', 'log')
        ->assertSet('smtpPort', 587)
        ->assertSet('smtpEncryption', 'tls')
        ->assertSet('sesRegion', 'eu-west-1');
});

it('loads stored settings overriding defaults', function () {
    settings()->setMany([
        'email.mailer' => 'smtp',
        'email.smtp_host' => 'mail.example.com',
        'email.from_address' => 'noreply@example.com',
    ]);

    Livewire::test(Email::class)
        ->assertSet('mailer', 'smtp')
        ->assertSet('smtpHost', 'mail.example.com')
        ->assertSet('fromAddress', 'noreply@example.com');
});

it('saves email settings', function () {
    Livewire::test(Email::class)
        ->set('mailer', 'smtp')
        ->set('smtpHost', 'smtp.example.com')
        ->set('smtpPort', 465)
        ->set('smtpUsername', 'user')
        ->set('smtpPassword', 'secret')
        ->set('smtpEncryption', 'ssl')
        ->set('fromAddress', 'noreply@example.com')
        ->set('fromName', 'Test App')
        ->set('replyToAddress', 'support@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('email-settings-saved');

    expect(settings('email.mailer'))->toBe('smtp');
    expect(settings('email.smtp_host'))->toBe('smtp.example.com');
    expect(settings('email.from_address'))->toBe('noreply@example.com');
    expect(settings('email.from_name'))->toBe('Test App');
});

it('validates mailer is required', function () {
    Livewire::test(Email::class)
        ->set('mailer', '')
        ->call('save')
        ->assertHasErrors(['mailer']);
});

it('validates mailer must be a valid option', function () {
    Livewire::test(Email::class)
        ->set('mailer', 'invalid')
        ->call('save')
        ->assertHasErrors(['mailer']);
});

it('validates from_address must be an email', function () {
    Livewire::test(Email::class)
        ->set('mailer', 'log')
        ->set('fromAddress', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['fromAddress']);
});

it('sends a test email', function () {
    Mail::fake();

    Livewire::test(Email::class)
        ->set('testEmailAddress', 'test@example.com')
        ->call('sendTestEmail')
        ->assertHasNoErrors()
        ->assertDispatched('test-email-sent');

    Mail::assertSent(TemplatedEmail::class, function ($mail) {
        return $mail->hasTo('test@example.com');
    });
});

it('validates test email address is required', function () {
    Livewire::test(Email::class)
        ->set('testEmailAddress', '')
        ->call('sendTestEmail')
        ->assertHasErrors(['testEmailAddress']);
});

it('validates test email address must be an email', function () {
    Livewire::test(Email::class)
        ->set('testEmailAddress', 'not-valid')
        ->call('sendTestEmail')
        ->assertHasErrors(['testEmailAddress']);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.email'))
        ->assertForbidden();
});
