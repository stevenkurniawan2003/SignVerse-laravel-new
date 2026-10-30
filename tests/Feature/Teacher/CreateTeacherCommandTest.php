<?php

namespace Tests\Feature\Teacher;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * signtech:create-teacher — membuat akun guru asli dari terminal server.
 */
class CreateTeacherCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_verified_teacher_with_profile(): void
    {
        $this->artisan('signtech:create-teacher', ['--name' => 'Rafi Kurniawan', '--email' => 'Rafi@Example.com'])
            ->expectsQuestion('Kata sandi guru (min. 8 karakter, masukan tersembunyi)', 'rahasia123')
            ->assertSuccessful();

        $user = User::firstWhere('email', 'rafi@example.com');

        $this->assertNotNull($user);
        $this->assertTrue($user->isTeacher());
        $this->assertNotNull($user->teacher);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_command_rejects_short_password(): void
    {
        $this->artisan('signtech:create-teacher', ['--name' => 'Guru', '--email' => 'guru@example.com'])
            ->expectsQuestion('Kata sandi guru (min. 8 karakter, masukan tersembunyi)', 'pendek')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'guru@example.com']);
    }
}
