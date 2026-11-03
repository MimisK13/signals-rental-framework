<?php

declare(strict_types=1);

namespace App\Domain\Settings\Presentation\Livewire\Admin;

use App\Support\LogoTransparency;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Branding')]
final class Branding extends Component
{
    use WithFileUploads;

    public string $primaryColour = '#1e3a5f';

    public string $accentColour = '#3b82f6';

    public ?TemporaryUploadedFile $logo = null;

    public ?string $currentLogoPath = null;

    public function mount(): void
    {
        $this->primaryColour = (string) settings('branding.primary_colour', '#1e3a5f');
        $this->accentColour = (string) settings('branding.accent_colour', '#3b82f6');
        $this->currentLogoPath = settings('branding.logo_path');
    }

    public function save(): void
    {
        $this->validate([
            'primaryColour' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accentColour' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'max:2048', 'mimes:png,jpg,jpeg,svg,webp'],
        ]);

        $settings = [
            'branding.primary_colour' => $this->primaryColour,
            'branding.accent_colour' => $this->accentColour,
        ];

        if ($this->logo) {
            $hasTransparency = LogoTransparency::detect($this->logo);

            $path = $this->logo->store('branding', 'public');

            if ($path === false) {
                $this->addError('logo', 'Failed to upload logo. Please try again.');

                return;
            }

            $settings['branding.logo_path'] = $path;
            $settings['branding.logo_has_transparency'] = ['value' => $hasTransparency, 'type' => 'boolean'];
            $this->currentLogoPath = $path;
            $this->logo = null;
        }

        settings()->setMany($settings);

        // Brand colours and logo render in the global header (outside this
        // component); force a full reload so the chrome reflects the change.
        session()->flash('status', 'Branding saved.');

        $this->redirect(route('admin.settings.branding'), navigate: false);
    }

    public function removeLogo(): void
    {
        if ($this->currentLogoPath) {
            Storage::disk('public')->delete($this->currentLogoPath);
        }

        settings()->setMany([
            'branding.logo_path' => null,
            'branding.logo_has_transparency' => ['value' => false, 'type' => 'boolean'],
        ]);
        $this->currentLogoPath = null;

        // The logo is shown in the global header; reload to clear it there too.
        session()->flash('status', 'Logo removed.');

        $this->redirect(route('admin.settings.branding'), navigate: false);
    }

    public function render(): View
    {
        return view('settings::admin.branding');
    }
}
