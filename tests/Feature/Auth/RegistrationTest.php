<?php

namespace Tests\Feature\Auth;

use App\Models\Assignment;
use App\Models\Category;
use App\Models\Classroom;
use App\Models\User;
use App\Services\ClassroomService;
use App\Services\TeacherStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_register_as_student_with_student_profile(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_STUDENT, $user->role);
        $this->assertNotNull($user->student, 'Profil student 1:1 harus dibuat saat registrasi.');

        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHas('status');
    }

    public function test_new_student_receives_active_assignments_but_not_expired_ones(): void
    {
        // Tugas kelas guru: aktif, tanpa deadline, dan sudah lewat deadline.
        $teacher = User::factory()->teacher()->create();
        $teacherModel = app(TeacherStatsService::class)->forUser($teacher);
        $classroom = Classroom::factory()->for($teacherModel)->create();

        $lessonProps = fn () => \App\Models\Lesson::factory()->for(\App\Models\Material::factory()->for(Category::factory()));

        $active = Assignment::factory()->for($teacherModel)->for($lessonProps())->create([
            'classroom_id' => $classroom->id,
            'due_at' => now()->addDays(3),
        ]);
        $noDeadline = Assignment::factory()->for($teacherModel)->for($lessonProps())->create([
            'classroom_id' => $classroom->id,
            'due_at' => null,
        ]);
        Assignment::factory()->for($teacherModel)->for($lessonProps())->create([
            'classroom_id' => $classroom->id,
            'due_at' => now()->subDay(),
        ]);

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $student = User::where('email', 'test@example.com')->first()->student;

        // Siswa baru belum punya kelas → belum menerima tugas apa pun.
        $this->assertSame([], $student->submissions()->pluck('assignment_id')->all());

        // Bergabung ke kelas (kode dari guru) → hanya tugas aktif yang dibagikan.
        app(ClassroomService::class)->joinByCode($student, $classroom->code);

        $this->assertEqualsCanonicalizing(
            [$active->id, $noDeadline->id],
            $student->submissions()->pluck('assignment_id')->all(),
        );
    }

    public function test_registered_user_can_log_in_after_registration(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->get(route('login'))->assertSee('Registrasi berhasil!');

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('student.dashboard', absolute: false));
    }
}
