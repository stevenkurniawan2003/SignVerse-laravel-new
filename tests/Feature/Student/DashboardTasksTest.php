<?php

namespace Tests\Feature\Student;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Challenge;
use App\Models\ChallengeProgress;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar "Tugas & Misi" di dashboard: status selesai berasal dari server
 * (pengumpulan tugas, progres misi), bukan dicentang manual.
 */
class DashboardTasksTest extends TestCase
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

    public function test_student_dashboard_lists_teacher_assignments_with_server_completion_status(): void
    {
        $user = $this->studentUser();
        $lesson = Lesson::factory()->create(['title' => 'Aku']);

        $pending = Assignment::factory()->for($lesson)->create(['title' => 'Eja Aku', 'due_at' => '2026-11-12 15:00:00', 'xp_reward' => 35]);
        $pending->submissions()->create(['student_id' => $user->student->id]);
        $missed = Assignment::factory()->create(['title' => 'Huruf B', 'due_at' => '2026-11-09 15:00:00']);
        $missed->submissions()->create(['student_id' => $user->student->id]);
        $done = Assignment::factory()->create(['title' => 'Huruf A']);
        $done->submissions()->create([
            'student_id' => $user->student->id,
            'status' => AssignmentSubmission::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        $othersTask = Assignment::factory()->create(['title' => 'Bukan Tugasku']);
        $othersTask->submissions()->create(['student_id' => $this->studentUser()->student->id]);

        $assignments = collect($this->actingAs($user)->getJson('/api/dashboard')->assertOk()->json('assignments'))->keyBy('title');

        $this->assertSame(['Huruf B', 'Eja Aku', 'Huruf A'], $assignments->keys()->all());
        $this->assertSame([false, false], [$assignments['Eja Aku']['completed'], $assignments['Eja Aku']['overdue']]);
        $this->assertSame([false, true], [$assignments['Huruf B']['completed'], $assignments['Huruf B']['overdue']]);
        $this->assertSame([true, false], [$assignments['Huruf A']['completed'], $assignments['Huruf A']['overdue']]);
        $this->assertSame('Aku', $assignments['Eja Aku']['lesson_title']);
        $this->assertSame(35, $assignments['Eja Aku']['xp_reward']);
        $this->assertSame("/student/assignments/{$pending->id}", $assignments['Eja Aku']['link']);
    }

    public function test_student_dashboard_shows_only_the_three_latest_completed_assignments(): void
    {
        $user = $this->studentUser();
        foreach (range(1, 5) as $day) {
            Assignment::factory()->create(['title' => "Selesai {$day}"])->submissions()->create([
                'student_id' => $user->student->id,
                'status' => AssignmentSubmission::STATUS_COMPLETED,
                'completed_at' => now()->subDays(6 - $day),
            ]);
        }

        $titles = collect($this->actingAs($user)->getJson('/api/dashboard')->json('assignments'))->pluck('title')->all();

        $this->assertSame(['Selesai 5', 'Selesai 4', 'Selesai 3'], $titles);
    }

    public function test_student_dashboard_challenge_is_completed_once_the_target_is_reached(): void
    {
        $user = $this->studentUser();
        ChallengeService::ensureTodayChallenges();
        $alphabet = Challenge::where('category', 'alfabet')->firstOrFail();
        ChallengeProgress::create([
            'user_id' => $user->id,
            'challenge_id' => $alphabet->id,
            'progress' => $alphabet->target,
            'completed' => true,
            'completed_at' => now(),
        ]);

        $challenges = collect($this->actingAs($user)->getJson('/api/dashboard')->json('challenges'))->keyBy('id');

        $this->assertTrue($challenges[$alphabet->id]['completed']);
        $this->assertSame($alphabet->target, $challenges[$alphabet->id]['progress']);
        $this->assertFalse($challenges->firstWhere('id', '!=', $alphabet->id)['completed']);
    }

    public function test_teacher_dashboard_reports_how_many_students_completed_each_active_assignment(): void
    {
        $teacher = $this->teacherUser();
        $classroom = Classroom::factory()->for($teacher->teacher)->create();
        $assignment = Assignment::factory()->for($teacher->teacher)->for($classroom)->create(['due_at' => now()->addDay()]);
        $first = $this->studentUser();
        $second = $this->studentUser();
        $assignment->submissions()->create(['student_id' => $first->student->id, 'status' => AssignmentSubmission::STATUS_COMPLETED]);
        $assignment->submissions()->create(['student_id' => $second->student->id]);

        $this->actingAs($teacher)->getJson('/teacher/dashboard/live')
            ->assertOk()
            ->assertJsonPath('activeAssignments.0.submissions_total', 2)
            ->assertJsonPath('activeAssignments.0.submissions_completed', 1);
    }
}
