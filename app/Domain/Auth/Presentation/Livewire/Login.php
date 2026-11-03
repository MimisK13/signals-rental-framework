<?php

declare(strict_types=1);

namespace App\Domain\Auth\Presentation\Livewire;

use App\Domain\Administration\Models\User;
use App\Domain\Auth\Actions\RequestMagicLink;
use App\Domain\Auth\Data\RequestMagicLinkData;
use App\Domain\Auth\Services\SsoEnforcement;
use App\Domain\Auth\Services\SsoService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('auth::components.layout')]
final class Login extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * The address entered in the "email me a sign-in link" affordance.
     */
    public string $magicLinkEmail = '';

    /**
     * Whether the neutral magic-link confirmation has been shown.
     *
     * Set after a {@see sendMagicLink()} request regardless of outcome so the
     * page never reveals whether an account exists for the address.
     */
    public bool $magicLinkSent = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureSsoIsNotEnforced();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $lockoutSeconds = max(1, (int) settings('security.lockout_duration', 15)) * 60;
            RateLimiter::hit($this->throttleKey(), $lockoutSeconds);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if (Auth::user()->hasTwoFactorEnabled()) {
            $userId = Auth::id();
            Auth::logout();
            Session::forget('two_factor_confirmed');
            Session::regenerate();
            Session::put('two_factor_user_id', $userId);
            $this->redirect(route('two-factor.challenge'), navigate: true);

            return;
        }

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Request a magic-link sign-in email for the entered address (spec §7, §9).
     *
     * Always shows the SAME neutral confirmation regardless of outcome so the
     * page never reveals whether an account exists (anti-enumeration). The
     * underlying {@see RequestMagicLink} action owns throttling and eligibility
     * (including the feature toggle), so it silently no-ops while the feature is
     * off — no re-check is needed here.
     */
    public function sendMagicLink(): void
    {
        $this->validateOnly('magicLinkEmail', [
            'magicLinkEmail' => 'required|string|email',
        ]);

        app(RequestMagicLink::class)(new RequestMagicLinkData(email: $this->magicLinkEmail));

        $this->magicLinkSent = true;
    }

    /**
     * Reject password login for users whose role requires Single Sign-On.
     *
     * When the entered email belongs to a user with an enforced role (and they
     * are not the Owner), the password is never checked — they must use the SSO
     * buttons instead. Unknown emails fall through so the generic failure path
     * does not leak which addresses exist.
     *
     * The error message adapts to whether any SSO provider is actually available:
     * the "use the Google or Microsoft buttons" guidance is only shown when those
     * buttons render. If enforcement is on but no provider is enabled/configured
     * the user has no self-service path, so they are directed to their administrator
     * rather than to non-existent buttons.
     */
    protected function ensureSsoIsNotEnforced(): void
    {
        $user = User::query()->where('email', $this->email)->first();

        if ($user === null) {
            return;
        }

        if (! app(SsoEnforcement::class)->isEnforcedFor($user)) {
            return;
        }

        $message = empty(app(SsoService::class)->enabledProviders())
            ? __('Your organisation requires single sign-on, but no sign-in provider is currently available. Please contact your administrator.')
            : __('Your organisation requires single sign-on. Please use the Google or Microsoft buttons above.');

        throw ValidationException::withMessages([
            'email' => $message,
        ]);
    }

    /**
     * Ensure the authentication request is not rate limited.
     *
     * Uses SecuritySettings for max attempts and lockout duration.
     */
    protected function ensureIsNotRateLimited(): void
    {
        $maxAttempts = max(1, (int) settings('security.max_login_attempts', 5));

        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $maxAttempts)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    /**
     * Expose view state: enabled SSO providers and the magic-link toggle.
     *
     * @return array{ssoProviders: list<string>, magicLinkEnabled: bool}
     */
    public function with(): array
    {
        return [
            'ssoProviders' => app(SsoService::class)->enabledProviders(),
            'magicLinkEnabled' => (bool) settings('security.magic_link_enabled'),
        ];
    }

    public function render(): View
    {
        return view('auth::login');
    }
}
