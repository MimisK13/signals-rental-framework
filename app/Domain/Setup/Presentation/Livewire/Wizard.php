<?php

declare(strict_types=1);

namespace App\Domain\Setup\Presentation\Livewire;

use App\Domain\ReferenceData\Data\CountryData;
use App\Domain\Setup\Actions\CheckInfrastructure;
use App\Domain\Setup\Actions\CompleteSetup;
use App\Domain\Setup\Data\CompleteSetupData;
use App\Domain\Setup\Enums\FeatureProfile;
use App\Support\LogoTransparency;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('setup::layout')] final class Wizard extends Component
{
    use WithFileUploads;

    public int $currentStep = 1;

    /** @var array<string, array{passed: bool, message: string}> */
    public array $infrastructureChecks = [];

    public bool $infrastructurePassed = false;

    // Step 1: Company
    public string $countryCode = '';

    public string $companyName = '';

    public string $timezone = '';

    public string $currency = '';

    public string $taxRate = '';

    public string $taxLabel = '';

    public string $dateFormat = '';

    public string $timeFormat = '';

    public int $fiscalYearStart = 1;

    // Step 2: Stores
    /** @var array<int, array{name: string, street: string, city: string, county: string, postcode: string, country_code: string, is_default: bool}> */
    public array $stores = [];

    // Step 3: Profile
    public string $profile = 'general';

    // Step 4: Branding
    public string $primaryColour = '#1e3a5f';

    public string $accentColour = '#3b82f6';

    public ?TemporaryUploadedFile $logo = null;

    // Step 5: Admin
    public string $adminName = '';

    public string $adminEmail = '';

    public string $adminPassword = '';

    public string $adminPassword_confirmation = '';

    public function mount(): void
    {
        $result = app(CheckInfrastructure::class)();
        $this->infrastructureChecks = $result['checks'];
        $this->infrastructurePassed = $result['passed'];

        $this->addStore();
    }

    public function retryInfrastructureChecks(): void
    {
        $result = app(CheckInfrastructure::class)();
        $this->infrastructureChecks = $result['checks'];
        $this->infrastructurePassed = $result['passed'];
    }

    public function updatedCountryCode(string $value): void
    {
        $defaults = CountryData::defaults($value);

        if ($defaults) {
            $this->timezone = $defaults['timezone'];
            $this->currency = $defaults['currency'];
            $this->taxRate = $defaults['tax_rate'];
            $this->taxLabel = $defaults['tax_label'];
            $this->dateFormat = $defaults['date_format'];
            $this->timeFormat = $defaults['time_format'];

            foreach ($this->stores as $i => $store) {
                if (empty($store['country_code'])) {
                    $this->stores[$i]['country_code'] = $value;
                }
            }
        }
    }

    public function addStore(): void
    {
        $this->stores[] = [
            'name' => '',
            'street' => '',
            'city' => '',
            'county' => '',
            'postcode' => '',
            'country_code' => $this->countryCode,
            'is_default' => count($this->stores) === 0,
        ];
    }

    public function removeStore(int $index): void
    {
        if (count($this->stores) <= 1) {
            return;
        }

        $wasDefault = $this->stores[$index]['is_default'] ?? false;
        unset($this->stores[$index]);
        $this->stores = array_values($this->stores);

        if ($wasDefault && count($this->stores) > 0) {
            $this->stores[0]['is_default'] = true;
        }
    }

    public function setDefaultStore(int $index): void
    {
        foreach ($this->stores as $i => $store) {
            $this->stores[$i]['is_default'] = ($i === $index);
        }
    }

    public function nextStep(): void
    {
        $this->validateStep($this->currentStep);
        $this->currentStep = min($this->currentStep + 1, 6);
    }

    public function previousStep(): void
    {
        $this->currentStep = max($this->currentStep - 1, 1);
    }

    public function goToStep(int $step): void
    {
        if ($step < $this->currentStep) {
            $this->currentStep = $step;
        }
    }

    public function completeSetup(): void
    {
        $this->validateStep(5);

        $logoPath = null;
        $logoHasTransparency = false;
        if ($this->logo) {
            $logoHasTransparency = LogoTransparency::detect($this->logo);
            $logoPath = $this->logo->store('branding', 'public');
        }

        $data = new CompleteSetupData(
            companyName: $this->companyName,
            countryCode: $this->countryCode,
            timezone: $this->timezone,
            currency: $this->currency,
            taxRate: $this->taxRate,
            taxLabel: $this->taxLabel,
            dateFormat: $this->dateFormat,
            timeFormat: $this->timeFormat,
            fiscalYearStart: $this->fiscalYearStart,
            profile: FeatureProfile::from($this->profile),
            stores: $this->stores,
            primaryColour: $this->primaryColour,
            accentColour: $this->accentColour,
            logoPath: $logoPath,
            logoHasTransparency: $logoHasTransparency,
            adminName: $this->adminName,
            adminEmail: $this->adminEmail,
            adminPassword: $this->adminPassword,
        );

        $user = (new CompleteSetup)($data);

        auth()->login($user);

        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * @return array<string, string>
     */
    public function countryOptions(): array
    {
        return CountryData::options();
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function profileOptions(): array
    {
        $options = [];
        foreach (FeatureProfile::cases() as $profile) {
            $options[$profile->value] = [
                'label' => $profile->label(),
                'description' => $profile->description(),
            ];
        }

        return $options;
    }

    /**
     * @return array<string, bool>
     */
    public function selectedProfileModules(): array
    {
        $profile = FeatureProfile::tryFrom($this->profile);

        return $profile ? $profile->modules() : FeatureProfile::General->modules();
    }

    private function validateStep(int $step): void
    {
        match ($step) {
            1 => $this->validate([
                'countryCode' => ['required', 'string', 'size:2'],
                'companyName' => ['required', 'string', 'max:255'],
                'timezone' => ['required', 'string', 'max:100'],
                'currency' => ['required', 'string', 'size:3'],
                'taxRate' => ['required', 'numeric', 'min:0', 'max:100'],
                'taxLabel' => ['required', 'string', 'max:50'],
                'dateFormat' => ['required', 'string', 'max:20'],
                'timeFormat' => ['required', 'string', 'max:20'],
                'fiscalYearStart' => ['required', 'integer', 'min:1', 'max:12'],
            ]),
            2 => $this->validate([
                'stores' => ['required', 'array', 'min:1'],
                'stores.*.name' => ['required', 'string', 'max:255'],
            ]),
            3 => $this->validate([
                'profile' => ['required', 'string', 'in:'.implode(',', array_column(FeatureProfile::cases(), 'value'))],
            ]),
            4 => $this->validate([
                'primaryColour' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'accentColour' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'logo' => ['nullable', 'image', 'max:2048'],
            ]),
            5 => $this->validate([
                'adminName' => ['required', 'string', 'max:255'],
                'adminEmail' => ['required', 'email', 'max:255'],
                'adminPassword' => ['required', 'string', 'min:12', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'regex:/[\W_]/', 'confirmed'],
            ]),
            default => null,
        };
    }

    public function render(): View
    {
        return view('setup::wizard');
    }
}
