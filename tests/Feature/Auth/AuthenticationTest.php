<?php

namespace Tests\Feature\Auth;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'nik' => $user->nik,
            'password' => 'Password123!',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_user_without_department_gets_warning_after_login(): void
    {
        $user = User::factory()->create(['m_department_id' => null]);

        $response = $this->post(route('login.store'), [
            'nik' => $user->nik,
            'password' => 'Password123!',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('department_warning.title', 'Department Belum Terdaftar')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_user_with_department_does_not_get_department_warning_after_login(): void
    {
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);
        $user = User::factory()->create(['m_department_id' => $department->id]);

        $response = $this->post(route('login.store'), [
            'nik' => $user->nik,
            'password' => 'Password123!',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('department_warning')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_developer_without_department_does_not_get_department_warning_after_login(): void
    {
        $developer = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
            'm_department_id' => null,
        ]);

        $response = $this->post(route('login.store'), [
            'nik' => $developer->nik,
            'password' => 'Password123!',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('department_warning')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'nik' => $user->nik,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrorsIn('nik');

        $this->assertGuest();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login.store'), [
            'nik' => $user->nik,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
