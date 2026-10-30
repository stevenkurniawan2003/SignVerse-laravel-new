<?php

namespace Tests\Feature\Teacher;

use App\Models\AssignmentSubmission;
use App\Models\Category;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\ClassroomService;
use App\Services\Contracts\GestureRecognitionService;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur guru → kelas → tugas → peragaan AI siswa → tugas selesai otomatis.
 */
class ClassroomAssignmentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): Teacher
    {
        return User::factory()->teacher()->has(Teacher::factory(), 'teacher')->create()->teacher;
    }

    private function studentUser(): User
    {
        return User::factory()->student()->has(Student::factory(), 'student')->create();
    }

    private function letterLesson(string $title = 'B'): Lesson
    {
        $category = Category::factory()->create(['slug' => 'alphabet']);

        return Lesson::factory()
            ->for(Material::factory()->for($category)->create(['language' => 'sibi']))
            ->create(['title' => $title, 'gesture_label' => strtolower($title), 'xp_reward' => 10]);
    }

    private function swapRecognizer(array $recognition): void
    {
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldReceive('recognize')->andReturn($recognition + ['message' => 'ok']);
        $this->swap(GestureRecognitionService::class, $recognizer);
    }

    public function test_passing_demonstration_completes_class_assignment_with_xp(): void
    {
        $teacher = $this->teacher();
        $classroom = Classroom::factory()->for($teacher)->create();
        $user = $this->studentUser();
        app(ClassroomService::class)->addStudent($classroom, $user->student);
        $lesson = $this->letterLesson();

        app(AssignmentService::class)->createAndDistribute([
            'teacher_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'lesson_id' => $lesson->id,
            'title' => 'Peragakan huruf B',
            'min_score' => 70,
            'xp_reward' => 25,
        ]);

        // Controller di-cache per route selama test, jadi satu mock dengan dua hasil berurutan.
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldReceive('recognize')->andReturn(
            ['recognized' => 'B', 'confidence' => 65, 'correct' => true, 'message' => 'ok'],
            ['recognized' => 'B', 'confidence' => 88, 'correct' => true, 'message' => 'ok'],
        );
        $this->swap(GestureRecognitionService::class, $recognizer);

        // Skor di bawah syarat tugas: latihan lulus, tugas belum selesai.
        $this->actingAs($user)->postJson('/api/practice/attempt', ['lesson_id' => $lesson->id, 'image' => 'x'])
            ->assertOk()
            ->assertJson(['correct' => true, 'assignments_completed' => []]);
        $this->assertSame(AssignmentSubmission::STATUS_IN_PROGRESS, AssignmentSubmission::sole()->status);

        // Skor memenuhi syarat: tugas selesai otomatis + XP tugas masuk total.
        $this->actingAs($user)->postJson('/api/practice/attempt', ['lesson_id' => $lesson->id, 'image' => 'x'])
            ->assertOk()
            ->assertJson(['assignments_completed' => [['title' => 'Peragakan huruf B', 'xp_earned' => 25]]]);

        $this->assertSame(AssignmentSubmission::STATUS_COMPLETED, AssignmentSubmission::sole()->status);
        $this->assertSame(10 + 25, $user->student->totalXp());
    }

    public function test_assignment_is_not_completed_for_students_outside_the_class(): void
    {
        $teacher = $this->teacher();
        $classroom = Classroom::factory()->for($teacher)->create();
        $outsider = $this->studentUser();
        $lesson = $this->letterLesson();

        app(AssignmentService::class)->createAndDistribute([
            'teacher_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'lesson_id' => $lesson->id,
            'title' => 'Tugas kelas',
            'min_score' => 60,
            'xp_reward' => 25,
        ]);

        $this->swapRecognizer(['recognized' => 'B', 'confidence' => 95, 'correct' => true]);
        $this->actingAs($outsider)->postJson('/api/practice/attempt', ['lesson_id' => $lesson->id, 'image' => 'x'])
            ->assertJson(['assignments_completed' => []]);

        $this->assertSame(0, AssignmentSubmission::count());
    }

    public function test_deleting_class_keeps_xp_from_completed_assignments(): void
    {
        $teacher = $this->teacher();
        $classroom = Classroom::factory()->for($teacher)->create();
        $user = $this->studentUser();
        app(ClassroomService::class)->addStudent($classroom, $user->student);
        $lesson = $this->letterLesson();

        $assignments = app(AssignmentService::class);
        $done = $assignments->createAndDistribute([
            'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'lesson_id' => $lesson->id,
            'title' => 'Sudah dikerjakan', 'min_score' => 60, 'xp_reward' => 30,
        ]);
        $open = $assignments->createAndDistribute([
            'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'lesson_id' => $lesson->id,
            'title' => 'Belum dikerjakan', 'min_score' => 60, 'xp_reward' => 30,
        ]);
        $done->submissions()->update(['status' => AssignmentSubmission::STATUS_COMPLETED, 'score' => 90, 'xp_earned' => 30]);

        $this->actingAs($teacher->user)->delete("/teacher/classes/{$classroom->id}")->assertRedirect('/teacher/classes');

        $this->assertModelMissing($classroom);
        $this->assertModelMissing($open);
        $this->assertNull($done->fresh()->classroom_id);
        $this->assertSame(30, $user->student->totalXp());
    }

    public function test_teacher_never_appears_on_leaderboard(): void
    {
        $student = $this->studentUser();
        $teacherUser = User::factory()->teacher()->create();
        Student::factory()->for($teacherUser)->create(); // data lama: guru punya baris siswa

        $names = collect(GamificationService::rankings($student->id)['entries'])->pluck('user_id');

        $this->assertContains($student->id, $names);
        $this->assertNotContains($teacherUser->id, $names);
    }
}
