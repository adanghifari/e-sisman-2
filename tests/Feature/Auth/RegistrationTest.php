<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_new_users_can_not_self_register(): void
    {
        $this->post('/register', [
            'name' => 'John Doe',
            'nik' => '12345678',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertNotFound();
    }

    public function test_create_user_action_stores_department(): void
    {
        $department = Department::create([
            'kode_department' => 'QA',
            'nama_department' => 'Quality Assurance',
        ]);

        $user = app(CreateNewUser::class)->create([
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'm_department_id' => $department->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertSame($department->id, $user->m_department_id);
        $this->assertTrue($user->department()->whereKey($department->id)->exists());
    }

    public function test_create_user_action_resolves_department_from_sso_code(): void
    {
        $department = Department::create([
            'kode_department' => 'ITSM',
            'nama_department' => 'IT & System Management',
        ]);

        $user = app(CreateNewUser::class)->create([
            'name' => 'SSO User',
            'email' => 'sso@example.com',
            'department_code' => 'ITSM',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertSame($department->id, $user->m_department_id);
    }

    public function test_create_user_action_keeps_department_empty_when_sso_department_is_unknown(): void
    {
        $user = app(CreateNewUser::class)->create([
            'name' => 'Unknown SSO User',
            'email' => 'unknown-sso@example.com',
            'department_code' => 'UNKNOWN',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertNull($user->m_department_id);
    }
}
