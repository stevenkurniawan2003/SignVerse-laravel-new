<?php

namespace Tests\Feature\Api;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kalender dashboard: titik latihan dan tenggat tugas per tanggal.
 */
class CalendarApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-10 10:00:00');
    }

    private function studentUser(): User
    {
        return User::factory()->student()->has(Student::factory(), 'student')->create();
    }

    private function teacherUser(): User
    {
        return User::factory()->teacher()->has(Teacher::factory(), 'teacher')->create();
    }

    private function week(): string
    {
        return '/api/calendar?start=2026-11-08&end=2026-11-14';
    }

    public function test_student_sees_own_practice_days_and_visible_assignment_deadlines(): void
    {
        $user = $this->studentUser();
        $other = $this->studentUser();
        $lesson = Lesson::factory()->create(['title' => 'Aku']);

        PracticeSession::factory()->completed()->for($user->student)->for($lesson)->create([
            'score' => 85, 'xp_earned' => 10, 'completed_at' => '2026-11-09 08:00:00',
        ]);
        PracticeSession::factory()->completed()->for($user->student)->for($lesson)->create([
            'score' => 40, 'xp_earned' => 0, 'completed_at' => '2026-11-09 09:00:00',
        ]);
        PracticeSession::factory()->inProgress()->for($user->student)->create(['started_at' => '2026-11-10 09:00:00']);
        PracticeSession::factory()->completed()->for($other->student)->create(['completed_at' => '2026-11-11 09:00:00']);

        $dueSoon = Assignment::factory()->for($lesson)->create(['title' => 'Eja Aku', 'due_at' => '2026-11-12 15:30:00']);
        $dueSoon->submissions()->create(['student_id' => $user->student->id]);
        $notYetAvailable = Assignment::factory()->create(['available_at' => '2026-11-11 00:00:00', 'due_at' => '2026-11-13 10:00:00']);
        $notYetAvailable->submissions()->create(['student_id' => $user->student->id]);
        $otherStudentsTask = Assignment::factory()->create(['due_at' => '2026-11-12 10:00:00']);
        $otherStudentsTask->submissions()->create(['student_id' => $other->student->id]);

        $response = $this->actingAs($user)->getJson($this->week())->assertOk();

        $this->assertSame(['2026-11-09', '2026-11-12'], array_keys($response->json('days')));
        $response->assertJsonPath('days.2026-11-09.practice', [
            'sessions' => 2, 'passed' => 1, 'xp' => 10, 'lessons' => ['Aku'],
        ]);
        $response->assertJsonPath('days.2026-11-09.deadlines', []);
        $response->assertJsonPath('days.2026-11-12.practice', null);
        $response->assertJsonPath('days.2026-11-12.deadlines.0', [
            'id' => $dueSoon->id,
            'title' => 'Eja Aku',
            'lesson_title' => 'Aku',
            'due_time' => '15:30',
            'completed' => false,
            'overdue' => false,
            'status_label' => 'Belum dikerjakan',
            'link' => "/student/assignments/{$dueSoon->id}",
        ]);
    }

    public function test_student_deadline_marks_completed_and_overdue_assignments(): void
    {
        $user = $this->studentUser();
        $done = Assignment::factory()->create(['due_at' => '2026-11-09 10:00:00']);
        $done->submissions()->create(['student_id' => $user->student->id, 'status' => AssignmentSubmission::STATUS_COMPLETED]);
        $missed = Assignment::factory()->create(['due_at' => '2026-11-09 11:00:00']);
        $missed->submissions()->create(['student_id' => $user->student->id]);

        $deadlines = $this->actingAs($user)->getJson($this->week())->assertOk()->json('days.2026-11-09.deadlines');

        $this->assertSame([[true, false, 'Selesai'], [false, true, 'Terlewat']], array_map(
            fn (array $deadline) => [$deadline['completed'], $deadline['overdue'], $deadline['status_label']],
            $deadlines,
        ));
    }

    public function test_teacher_sees_class_students_practice_and_own_assignment_deadlines(): void
    {
        $teacher = $this->teacherUser();
        $inClass = $this->studentUser();
        $outsider = $this->studentUser();
        Classroom::factory()->for($teacher->teacher)->create()->students()->attach($inClass->student);

        PracticeSession::factory()->completed()->for($inClass->student)->create([
            'score' => 90, 'xp_earned' => 10, 'completed_at' => '2026-11-10 08:00:00',
        ]);
        PracticeSession::factory()->completed()->for($outsider->student)->create(['completed_at' => '2026-11-10 08:00:00']);

        $mine = Assignment::factory()->for($teacher->teacher)->create(['title' => 'Tugas Kelas', 'due_at' => '2026-11-13 09:00:00']);
        $mine->submissions()->create(['student_id' => $inClass->student->id, 'status' => AssignmentSubmission::STATUS_COMPLETED]);
        $mine->submissions()->create(['student_id' => $outsider->student->id]);
        Assignment::factory()->create(['due_at' => '2026-11-13 09:00:00']);

        $response = $this->actingAs($teacher)->getJson($this->week())->assertOk();

        $response->assertJsonPath('days.2026-11-10.practice.sessions', 1);
        $response->assertJsonCount(1, 'days.2026-11-13.deadlines');
        $response->assertJsonPath('days.2026-11-13.deadlines.0.title', 'Tugas Kelas');
        $response->assertJsonPath('days.2026-11-13.deadlines.0.status_label', '1/2 siswa selesai');
        $response->assertJsonPath('days.2026-11-13.deadlines.0.link', "/teacher/assignments/{$mine->id}");
    }

    public function test_empty_range_returns_an_empty_object(): void
    {
        $this->actingAs($this->studentUser())->getJson($this->week())
            ->assertOk()
            ->assertExactJson(['status' => 'success', 'start' => '2026-11-08', 'end' => '2026-11-14', 'days' => []]);
    }

    public function test_calendar_validates_the_requested_range(): void
    {
        $user = $this->studentUser();

        $this->actingAs($user)->getJson('/api/calendar?start=2026-11-08')->assertUnprocessable()->assertJsonValidationErrors('end');
        $this->actingAs($user)->getJson('/api/calendar?start=2026-11-14&end=2026-11-08')->assertUnprocessable()->assertJsonValidationErrors('end');
        $this->actingAs($user)->getJson('/api/calendar?start=2026-11-01&end=2026-12-31')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Rentang kalender maksimal 42 hari.');
    }

    public function test_calendar_requires_login(): void
    {
        $this->getJson($this->week())->assertUnauthorized();
    }
}
