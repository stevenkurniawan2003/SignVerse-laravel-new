<?php

namespace Tests\Feature\Student;

use App\Models\Category;
use App\Models\GestureResult;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * signtech:clean-practice-data — koreksi data latihan dari logika lama.
 */
class PracticeDataCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $email): Student
    {
        $user = User::factory()->student()->has(Student::factory(), 'student')->create(['email' => $email]);

        return $user->student;
    }

    private function lesson(): Lesson
    {
        $category = Category::factory()->create(['slug' => 'alphabet']);

        return Lesson::factory()
            ->for(Material::factory()->for($category)->create(['language' => 'sibi']))
            ->create(['title' => 'A', 'gesture_label' => 'a', 'xp_reward' => 10]);
    }

    private function practiceSession(Student $student, Lesson $lesson, int $score, int $xp, array $results): PracticeSession
    {
        $session = PracticeSession::factory()->for($student)->for($lesson)->create([
            'status' => PracticeSession::STATUS_COMPLETED,
            'score' => $score,
            'xp_earned' => $xp,
        ]);

        foreach ($results as [$recognized, $confidence]) {
            GestureResult::factory()->for($session)->create([
                'lesson_id' => $lesson->id,
                'expected_gesture' => 'a',
                'recognized_gesture' => $recognized,
                'confidence' => $confidence,
                'correct' => true,
            ]);
        }

        return $session;
    }

    public function test_cleanup_removes_fake_sessions_and_duplicate_xp(): void
    {
        $this->seed(BadgeSeeder::class);
        $lesson = $this->lesson();
        $student = $this->student('murid@gmail.com');

        $fake = $this->practiceSession($student, $lesson, 100, 10, []);
        $noHand = $this->practiceSession($student, $lesson, 0, 0, [['none', 0]]);
        $first = $this->practiceSession($student, $lesson, 100, 10, [['a', 92]]);
        $duplicate = $this->practiceSession($student, $lesson, 100, 10, [['a', 95]]);
        $lowConfidence = $this->practiceSession($student, $lesson, 100, 10, [['a', 52]]);
        $wrongLabel = $this->practiceSession($student, $lesson, 88, 0, [['b', 88]]);

        $this->artisan('signtech:clean-practice-data')->assertSuccessful();

        $this->assertModelMissing($fake);
        $this->assertModelMissing($noHand);
        $this->assertSame([92, 10], [(int) $first->fresh()->score, (int) $first->fresh()->xp_earned]);
        $this->assertSame([95, 0], [(int) $duplicate->fresh()->score, (int) $duplicate->fresh()->xp_earned]);
        $this->assertSame([52, 0], [(int) $lowConfidence->fresh()->score, (int) $lowConfidence->fresh()->xp_earned]);
        $this->assertSame(0, (int) $wrongLabel->fresh()->score);
        $this->assertSame(10, $student->fresh()->totalXp());
    }

    public function test_dry_run_and_demo_accounts_are_left_untouched(): void
    {
        $lesson = $this->lesson();
        $student = $this->student('murid@gmail.com');
        $demo = $this->student('siswa@signteach.id');
        $fake = $this->practiceSession($student, $lesson, 100, 10, []);
        $demoFake = $this->practiceSession($demo, $lesson, 100, 10, []);

        $this->artisan('signtech:clean-practice-data --dry-run')->assertSuccessful();
        $this->assertModelExists($fake);

        $this->artisan('signtech:clean-practice-data')->assertSuccessful();
        $this->assertModelMissing($fake);
        $this->assertModelExists($demoFake);
    }
}
