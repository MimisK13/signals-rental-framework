<?php

namespace Tests\Feature\Settings;

use App\Domain\Administration\Models\User;
use App\Domain\Administration\Presentation\Livewire\Settings\Appearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppearanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['signals.installed' => true, 'signals.setup_complete' => true]);
    }

    public function test_appearance_page_is_displayed_for_authenticated_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.appearance'))
            ->assertOk()
            ->assertSee('Appearance');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('settings.appearance'))->assertRedirect(route('login'));
    }

    public function test_appearance_component_offers_all_theme_modes(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Appearance::class)
            ->assertSee('Light')
            ->assertSee('Dark')
            ->assertSee('System');
    }
}
