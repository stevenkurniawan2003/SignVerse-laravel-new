<?php

namespace Tests\Feature\Teacher;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur kelas guru: buat kelas → kode → siswa bergabung / dibuatkan akun →
 * tugas kelas muncul bagi siswa anggota, tidak bagi siswa luar.
 */
class TeacherClassroomTest extends TestCase
{
    use RefreshDatabase;

    private function teacherUser(): User
    {
        return User::factory()->teacher()->has(Teacher::factory(), 'teacher')->create();
    }

    public function test_teacher_can_create_classroom_and_receives_join_code(): void
    {
        $teacher = $this->teacherUser();

        $response = $this->actingAs($teacher)->post(route('teacher.classes.store'), [
            'name' => 'Kelas 7A',
            'description' => 'SIBI Dasar',
        ]);

        $classroom = Classroom::query()->where('name', 'Kelas 7A')->firstOrFail();

        $response->assertRedirect(route('teacher.classes.show', $classroom));
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $classroom->code);
        $this->assertSame($teacher->teacher->id, $classroom->teacher_id);
    }

    public function test_student_can_join_by_code_and_guru_adds_registered_student_by_email(): void
    {
        $teacher = $this->teacherUser();
        $classroom = Classroom::factory()->for($teacher->teacher)->create();
        $student = User::factory()->student()->has(Student::factory(), 'student')->create();

        // 1) Siswa bergabung sendiri lewat kode.
        $this->actingAs($student)->post(route('student.classes.join'), ['code' => $classroom->code])
            ->assertSessionHas('status');
        $this->assertTrue($classroom->students()->whereKey($student->student->id)->exists());

        // 2) Guru menambahkan siswa terdaftar lain lewat email.
        $second = User::factory()->student()->has(Student::factory(), 'student')->create();
        $this->actingAs($teacher)->post(route('teacher.classes.students.add', $classroom), ['email' => $second->email])
            ->assertSessionHas('status');
        $this->assertTrue($classroom->students()->whereKey($second->student->id)->exists());

        // Kode salah → ditolak dengan pesan.
        $third = User::factory()->student()->has(Student::factory(), 'student')->create();
        $this->actingAs($third)->post(route('student.classes.join'), ['code' => 'XXXXXX'])
            ->assertSessionHasErrors('code');
    }

    public function test_guru_can_create_student_account_who_joins_the_class(): void
    {
        $teacher = $this->teacherUser();
        $classroom = Classroom::factory()->for($teacher->teacher)->create();

        $this->actingAs($teacher)->post(route('teacher.students.store'), [
            'classroom_id' => $classroom->id,
            'name' => 'Siswa Baru',
            'email' => 'siswa-baru@example.com',
            'password' => 'sandiaman8',
        ])->assertSessionHas('status');

        $user = User::where('email', 'siswa-baru@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_STUDENT, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($classroom->students()->whereKey($user->student->id)->exists());

        // Sandi bisa dipakai login.
        $this->post('/login', ['email' => 'siswa-baru@example.com', 'password' => 'sandiaman8']);
        $this->assertAuthenticated();
    }

    public function test_guru_can_reset_student_password(): void
    {
        $teacher = $this->teacherUser();
        $classroom = Classroom::factory()->for($teacher->teacher)->create();
        $student = User::factory()->student()->has(Student::factory(), 'student')->create();
        $classroom->students()->attach($student->student);

        $this->actingAs($teacher)->put(route('teacher.students.password', $student->student), ['password' => 'sandidiperbarui1'])
            ->assertSessionHas('status');

        $student->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('sandidiperbarui1', $student->password));
    }

    public function test_assignment_distributed_to_class_appears_only_for_members(): void
    {
        $teacher = $this->teacherUser();
        $classroom = Classroom::factory()->for($teacher->teacher)->create();
        $member = User::factory()->student()->has(Student::factory(), 'student')->create();
        $outsider = User::factory()->student()->has(Student::factory(), 'student')->create();
        $classroom->students()->attach($member->student);

        $lesson = Lesson::factory()->for(\App\Models\Material::factory()->for(\App\Models\Category::factory()))->create();

        $this->actingAs($teacher)->post(route('teacher.assignments.store'), [
            'classroom_id' => $classroom->id,
            'lesson_id' => $lesson->id,
            'title' => 'Tugas Kelas 7A',
            'min_score' => 60,
            'xp_reward' => 40,
        ])->assertRedirect();

        $assignment = Assignment::where('title', 'Tugas Kelas 7A')->firstOrFail();

        // Anggota kelas menerima tugas; siswa luar tidak.
        $this->assertSame(1, $assignment->submissions()->count());
        $this->assertNotNull(AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $member->student->id)->first());
        $this->assertNull(AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $outsider->student->id)->first());

        // Tugas aktif juga otomatis dibagikan saat siswa luar bergabung belakangan.
        $this->actingAs($outsider)->post(route('student.classes.join'), ['code' => $classroom->code]);
        $this->assertNotNull($assignment->submissions()->where('student_id', $outsider->student->id)->first());
    }

    public function test_teacher_pages_are_scoped_to_the_logged_in_guru(): void
    {
        $owner = $this->teacherUser();
        $other = $this->teacherUser();
        $classroom = Classroom::factory()->for($owner->teacher)->create();

        // Kelas milik guru lain: akses ditolak tanpa membocorkan isinya.
        $this->actingAs($other)->get(route('teacher.classes.show', $classroom))->assertForbidden();

        $assignment = Assignment::factory()->for($owner->teacher)
            ->for(Lesson::factory()->for(\App\Models\Material::factory()->for(\App\Models\Category::factory())))
            ->create(['classroom_id' => $classroom->id]);
        $this->actingAs($other)->get(route('teacher.assignments.show', $assignment))->assertForbidden();
    }
}
