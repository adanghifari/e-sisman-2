<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Security;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
    }

    public function test_security_settings_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response->assertOk();

        $response->assertSee('Update password');
        $response->assertSee('Two-factor authentication');
        $response->assertSee('Enable');
    }

    public function test_security_settings_page_requires_password_confirmation_when_enabled(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('security.edit'));

        $response->assertRedirect(route('password.confirm'));
    }

    public function test_security_settings_page_renders_without_two_factor_when_feature_is_disabled(): void
    {
        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertSee('Update password')
            ->assertDontSee('Two-factor authentication is not enabled yet.');
    }

    public function test_two_factor_authentication_disabled_when_confirmation_abandoned_between_requests(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->actingAs($user);

        $component = Livewire::test(Security::class);

        $component->assertSet('twoFactorEnabled', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $response = Livewire::test(Security::class)
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $response->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $response = Livewire::test(Security::class)
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $response->assertHasErrors(['current_password']);
    }

    public function test_security_appearance_and_two_factor_components_resolve_by_aliases(): void
    {
        $this->assertSame(Security::class, Livewire::getClass('settings.security'));
        $this->assertSame(Security::class, Livewire::getClass('settings.security.edit'));
        $this->assertSame(Security::class, Livewire::getClass('security.edit'));
        $this->assertSame(Security::class, Livewire::getClass('security'));
        $this->assertSame(Security::class, Livewire::getClass('app.livewire.settings.security'));

        $this->assertSame(\App\Livewire\Settings\Appearance::class, Livewire::getClass('settings.appearance'));
        $this->assertSame(\App\Livewire\Settings\Appearance::class, Livewire::getClass('settings.appearance.edit'));
        $this->assertSame(\App\Livewire\Settings\Appearance::class, Livewire::getClass('appearance.edit'));
        $this->assertSame(\App\Livewire\Settings\Appearance::class, Livewire::getClass('appearance'));
        $this->assertSame(\App\Livewire\Settings\Appearance::class, Livewire::getClass('app.livewire.settings.appearance'));

        $this->assertSame(\App\Livewire\Settings\TwoFactor\RecoveryCodes::class, Livewire::getClass('settings.two-factor.recovery-codes'));
        $this->assertSame(\App\Livewire\Settings\TwoFactor\RecoveryCodes::class, Livewire::getClass('app.livewire.settings.two-factor.recovery-codes'));
    }
}
