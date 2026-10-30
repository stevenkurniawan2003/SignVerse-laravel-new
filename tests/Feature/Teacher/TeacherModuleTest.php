<?php

namespace Tests\Feature\Teacher;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Category;
use App\Models\ChallengeProgress;
use App\Models\Classroom;
use App\Models\GestureResult;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ChallengeService;
use App\Services\GamificationService;
use App\Services\TeacherStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7 — Teacher module: statistik kelas, monitoring siswa,
 * analisis error gesture, dan guard antar role.
 */
class TeacherModuleTest extends TestCase
{
    use RefreshDatabase;

    private function teacherUser(): User
    {
        return User::factory()->teacher()->has(Teacher::factory(), 'teacher')->create();
    }

    private function studentUser(): Student
    {
        return User::factory()->student()->has(Student::factory(), 'student')->create()->student;
    }

    private function lesson(): Lesson
    {
        return Lesson::factory()->for(Material::factory()->for(Category::factory()))->create([
            'gesture_label' => 'halo',
        ]);
    }

    /**
     * Guru + kelas dengan siswa di dalamnya (arsitektur kelas baru).
     *
     * @return array{0: User, 1: Student, 2: Classroom}
     */
    private function teacherWithStudent(?string $name = null): array
    {
        $teacher = $this->teacherUser();
        $student = $this->studentUser();
        if ($name !== null) {
            $student->user->update(['name' => $name]);
        }

        $classroom = Classroom::factory()->for($teacher->teacher)->create();
        $classroom->students()->attach($student);

        return [$teacher, $student, $classroom];
    }

    /*
    |--------------------------------------------------------------------------
    | Service — statistik kelas
    |--------------------------------------------------------------------------
    */

    public function test_class_overview_counts_students_sessions_score_and_assignments(): void
    {
        $service = app(TeacherStatsService::class);
        $teacher = Teacher::factory()->create();

        $students = Student::factory()->count(3)->create();

        // Sesi eksplisit per student agar factory tidak membuat student lain.
        PracticeSession::factory()->completed()->for($students[0])->create(['score' => 80]);
        PracticeSession::factory()->completed()->for($students[1])->create(['score' => 80]);
        PracticeSession::factory()->completed()->for($students[2])->create([
            'score' => 60,
            'completed_at' => now()->subDays(10),
        ]);

        Assignment::factory()->for($teacher)->for($this->lesson())->create(['due_at' => now()->addDays(3)]);
        Assignment::factory()->for($teacher)->for($this->lesson())->create(['due_at' => now()->subDays(1)]);

        $overview = $service->classOverview($teacher);

        $this->assertSame(3, $overview['students']);
        $this->assertSame(2, $overview['weekly_sessions']);
        $this->assertSame(73.3, $overview['avg_class_score']); // (80+80+60)/3
        $this->assertSame(1, $overview['active_assignments']);
    }

    public function test_active_assignments_excludes_past_deadline(): void
    {
        $service = app(TeacherStatsService::class);
        $teacher = Teacher::factory()->create();

        $active = Assignment::factory()->for($teacher)->for($this->lesson())->create(['due_at' => now()->addWeek()]);
        Assignment::factory()->for($teacher)->for($this->lesson())->create(['due_at' => now()->subDay()]);
        $noDeadline = Assignment::factory()->for($teacher)->for($this->lesson())->create(['due_at' => null]);

        $result = $service->activeAssignments($teacher);

        $this->assertSame(
            [$active->id, $noDeadline->id],
            $result->pluck('id')->values()->all(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Service — monitoring siswa
    |--------------------------------------------------------------------------
    */

    public function test_students_overview_returns_per_student_summary(): void
    {
        $service = app(TeacherStatsService::class);
        $student = $this->studentUser();

        PracticeSession::factory()->completed()->for($student)->count(2)->create(['score' => 70]);

        $rows = $service->studentsOverview();

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame($student->id, $row['student']->id);
        $this->assertSame($student->user->name, $row['name']);
        $this->assertSame(2, $row['sessions']);
        $this->assertSame(70.0, $row['avg_score']);
        $this->assertSame($student->totalXp(), $row['xp']);
        $this->assertSame(
            GamificationService::levelFromXp($student->totalXp())['name'],
            $row['level'],
        );
    }

    public function test_student_detail_aggregates_progress_and_sessions(): void
    {
        $service = app(TeacherStatsService::class);
        $student = $this->studentUser();
        $lesson = $this->lesson();

        PracticeSession::factory()->completed()->for($student)->for($lesson)->create(['score' => 90]);

        $detail = $service->studentDetail($student);

        $this->assertSame(1, $detail['summary']['sessions']);
        $this->assertSame(90.0, $detail['summary']['avg_score']);
        $this->assertCount(1, $detail['recent_sessions']);
        $this->assertSame($lesson->id, $detail['recent_sessions'][0]->lesson_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Service — analisis error
    |--------------------------------------------------------------------------
    */

    public function test_error_analysis_finds_most_failed_gestures(): void
    {
        $service = app(TeacherStatsService::class);
        $lessonA = $this->lesson(); // gesture_label: halo
        $session = PracticeSession::factory()->completed()->create();

        // Lesson A "halo": 3 salah dari 4 → akurasi 25%.
        GestureResult::factory()->for($session)->create([
            'lesson_id' => $lessonA->id,
            'expected_gesture' => 'halo',
            'recognized_gesture' => 'halo',
            'correct' => true,
        ]);
        GestureResult::factory()->for($session)->count(3)->sequence(
            ['recognized_gesture' => 'hai'],
            ['recognized_gesture' => 'oke'],
            ['recognized_gesture' => 'bye'],
        )->create([
            'lesson_id' => $lessonA->id,
            'expected_gesture' => 'halo',
            'correct' => false,
        ]);

        // Lesson B: semua benar → tidak muncul di analisis error.
        $lessonB = $this->lesson();
        GestureResult::factory()->for($session)->count(2)->create([
            'lesson_id' => $lessonB->id,
            'expected_gesture' => 'terima kasih',
            'recognized_gesture' => 'terima kasih',
            'correct' => true,
        ]);

        $rows = $service->errorAnalysisForLesson();

        $this->assertCount(1, $rows);
        $row = $rows->first();
        $this->assertSame('halo', $row['gesture']);
        $this->assertSame(3, $row['wrong']);
        $this->assertSame(4, $row['total']);
        $this->assertSame(25, $row['accuracy']);
    }

    public function test_error_analysis_for_student_scopes_to_that_student(): void
    {
        $service = app(TeacherStatsService::class);
        $student = Student::factory()->create();
        $other = Student::factory()->create();
        $lesson = $this->lesson();

        $ownSession = PracticeSession::factory()->completed()->for($student)->create();
        $otherSession = PracticeSession::factory()->completed()->for($other)->create();

        GestureResult::factory()->for($ownSession)->create([
            'lesson_id' => $lesson->id,
            'expected_gesture' => 'halo',
            'correct' => false,
        ]);
        GestureResult::factory()->for($otherSession)->create([
            'lesson_id' => $lesson->id,
            'expected_gesture' => 'halo',
            'correct' => false,
        ]);

        $rows = $service->errorAnalysisForStudent($student);

        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows->first()['wrong']);
        $this->assertSame(1, $rows->first()['total']);
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP — dashboard & monitoring
    |--------------------------------------------------------------------------
    */

    public function test_teacher_dashboard_shows_class_statistics(): void
    {
        [$teacher, $student, $classroom] = $this->teacherWithStudent('Rina Putri');

        PracticeSession::factory()->completed()->for($student)->create(['score' => 85]);

        $response = $this->actingAs($teacher)->get(route('teacher.dashboard'));

        $response->assertOk();
        // Monitoring guru hanya menampilkan siswa kelas miliknya sendiri.
        $response->assertSee('Rina Putri', false);
        $response->assertSee($classroom->name, false);
    }

    public function test_teacher_can_view_student_monitoring_list(): void
    {
        [$teacher, $student] = $this->teacherWithStudent();

        $response = $this->actingAs($teacher)->get(route('teacher.students.index'));

        $response->assertOk();
        $response->assertSee($student->user->name);
    }

    public function test_student_outside_classroom_is_hidden_from_teacher(): void
    {
        $teacher = $this->teacherUser();
        $outsider = $this->studentUser();

        $this->actingAs($teacher)->get(route('teacher.students.index'))->assertOk();

        // Siswa di kelas teacher lain tidak boleh bocor ke teacher ini.
        $anotherTeacher = $this->teacherUser();
        $classroom = Classroom::factory()->for($anotherTeacher->teacher)->create();
        $classroom->students()->attach($outsider->id);

        $response = $this->actingAs($teacher)->get(route('teacher.students.index'));

        $response->assertOk();
        $response->assertDontSee($outsider->user->name, false);
    }

    public function test_teacher_can_view_student_detail_with_error_analysis(): void
    {
        [$teacher, $student] = $this->teacherWithStudent();
        $lesson = $this->lesson();

        $session = PracticeSession::factory()->completed()->for($student)->for($lesson)->create(['score' => 40]);
        GestureResult::factory()->for($session)->create([
            'lesson_id' => $lesson->id,
            'expected_gesture' => 'halo',
            'correct' => false,
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.students.show', $student));

        $response->assertOk();
        $response->assertSee($student->user->name);
        $response->assertSee('halo', false);
    }

    public function test_teacher_cannot_view_student_outside_their_classrooms(): void
    {
        $teacher = $this->teacherUser();
        $student = $this->studentUser(); // Belum tergabung di kelas teacher manapun.

        $this->actingAs($teacher)->get(route('teacher.students.show', $student))->assertForbidden();
    }

    public function test_student_last_activity_comes_from_latest_completed_session(): void
    {
        $student = $this->studentUser();
        $student->update(['last_activity_date' => null]);

        PracticeSession::factory()->completed()->for($student)->create(['completed_at' => now()->subDays(3)]);
        PracticeSession::factory()->completed()->for($student)->create(['completed_at' => now()->subDay()]);

        $summary = app(TeacherStatsService::class)->studentSummary($student);

        $this->assertTrue($summary['last_activity']->isSameDay(now()->subDay()));
    }

    public function test_teacher_student_detail_shows_assignments_of_student(): void
    {
        [$teacher, $student] = $this->teacherWithStudent();

        $assignment = Assignment::factory()->for($teacher->teacher)->for($this->lesson())->create([
            'title' => 'Tugas Sapaan',
            'classroom_id' => Classroom::factory()->for($teacher->teacher)->create()->id,
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'status' => AssignmentSubmission::STATUS_COMPLETED,
            'score' => 88,
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.students.show', $student));

        $response->assertOk();
        $response->assertSee('Tugas Sapaan');
        $response->assertSee('"status":"completed"', false);
        $response->assertSee('"score":88', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Guard antar role
    |--------------------------------------------------------------------------
    */

    public function test_student_cannot_access_teacher_monitoring_pages(): void
    {
        $user = $this->studentUser()->user;

        $this->actingAs($user)->get(route('teacher.students.index'))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('error');

        $this->actingAs($user)->get(route('teacher.students.show', Student::factory()->create()))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_guest_is_redirected_to_login_for_teacher_pages(): void
    {
        $this->get(route('teacher.students.index'))->assertRedirect(route('login'));
    }
}
