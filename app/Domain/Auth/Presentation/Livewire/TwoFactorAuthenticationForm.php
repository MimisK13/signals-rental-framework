<?php

declare(strict_types=1);

namespace App\Domain\Auth\Presentation\Livewire;

use App\Domain\Administration\Models\User;
use App\Domain\Auth\Actions\ConfirmTwoFactor;
use App\Domain\Auth\Actions\DisableTwoFactor;
use App\Domain\Auth\Actions\EnableTwoFactor;
use App\Domain\Auth\Actions\RegenerateTwoFactorRecoveryCodes;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class TwoFactorAuthenticationForm extends Component
{
    /** Current step: idle | confirming_enable | showing_qr | showing_recovery | confirming_disable */
    public string $step = 'idle';

    public string $password = '';

    public string $code = '';

    public string $qrSvg = '';

    public string $secret = '';

    /** @var array<int, string> */
    public array $recoveryCodes = [];

    public function mount(): void
    {
        $this->step = 'idle';
    }

    /**
     * Begin the enable flow — ask for password confirmation.
     */
    public function startEnable(): void
    {
        $this->step = 'confirming_enable';
        $this->password = '';
    }

    /**
     * Verify password, generate secret, and show QR code.
     */
    public function enableTwoFactor(): void
    {
        $this->validate(['password' => ['required', 'string']]);

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($this->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('The password you entered is incorrect.')],
            ]);
        }

        $otpUrl = app(EnableTwoFactor::class)($user);

        $this->secret = (string) $user->two_factor_secret;
        $this->qrSvg = $this->renderQrSvg($otpUrl);
        $this->password = '';
        $this->code = '';
        $this->step = 'showing_qr';
    }

    /**
     * Confirm the TOTP code and activate 2FA.
     */
    public function confirmTwoFactor(): void
    {
        /** @var User $user */
        $user = Auth::user();

        app(ConfirmTwoFactor::class)($user, $this->code);

        $this->recoveryCodes = json_decode((string) $user->two_factor_recovery_codes, true) ?? [];
        $this->code = '';
        $this->step = 'showing_recovery';
    }

    /**
     * Show existing recovery codes (for already-enabled 2FA).
     */
    public function showRecoveryCodes(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->recoveryCodes = json_decode((string) $user->two_factor_recovery_codes, true) ?? [];
        $this->step = 'showing_recovery';
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->recoveryCodes = app(RegenerateTwoFactorRecoveryCodes::class)($user);
    }

    /**
     * Begin the disable flow.
     */
    public function startDisable(): void
    {
        $this->password = '';
        $this->step = 'confirming_disable';
    }

    /**
     * Verify password and disable 2FA.
     */
    public function disableTwoFactor(): void
    {
        $this->validate(['password' => ['required', 'string']]);

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($this->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('The password you entered is incorrect.')],
            ]);
        }

        app(DisableTwoFactor::class)($user);

        $this->password = '';
        $this->step = 'idle';
    }

    /**
     * Cancel any in-progress flow and return to idle.
     */
    public function cancel(): void
    {
        $this->step = 'idle';
        $this->password = '';
        $this->code = '';
        $this->recoveryCodes = [];
    }

    /**
     * Render the OTP URL as an inline SVG string.
     */
    private function renderQrSvg(string $otpUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($otpUrl);
    }

    public function render(): View
    {
        return view('auth::two-factor-authentication-form');
    }
}
