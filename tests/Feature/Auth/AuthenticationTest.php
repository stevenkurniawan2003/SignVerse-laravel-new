<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_students_can_authenticate_and_are_redirected_to_student_dashboard(): void
    {
        $user = User::factory()->student()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('student.dashboard', absolute: false));
    }

    public function test_teachers_can_authenticate_and_are_redirected_to_teacher_dashboard(): void
    {
        $user = User::factory()->teacher()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('teacher.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_authenticated_student_visiting_login_is_redirected_to_student_dashboard(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)->get('/login')->assertRedirect(route('student.dashboard'));
    }

    public function test_authenticated_teacher_visiting_login_is_redirected_to_teacher_dashboard(): void
    {
        $user = User::factory()->teacher()->create();

        $this->actingAs($user)->get('/login')->assertRedirect(route('teacher.dashboard'));
    }

    public function test_login_never_sets_remember_cookie(): void
    {
        $user = User::factory()->student()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $this->assertAuthenticated();
        $this->assertSame($user->remember_token, $user->fresh()->remember_token);
        $response->assertCookieMissing(auth()->guard('web')->getRecallerName());
    }

    public function test_session_cookie_expires_when_browser_is_closed(): void
    {
        $this->assertTrue(config('session.expire_on_close'));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
