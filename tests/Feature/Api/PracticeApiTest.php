<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Challenge;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\User;
use App\Services\ChallengeService;
use App\Services\Contracts\GestureRecognitionService;
use App\Services\GamificationService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * API latihan untuk frontend Vue: peragaan kamera dinilai AI lalu
 * gamifikasi (XP sekali per lesson, streak, challenge, badge, level).
 */
class PracticeApiTest extends TestCase
{
    use RefreshDatabase;

    private function studentUser(): User
    {
        return User::factory()->student()->has(Student::factory(), 'student')->create();
    }

    private function letterLesson(string $title = 'A', string $language = Material::LANGUAGE_SIBI, int $xp = 10): Lesson
    {
        $category = Category::query()->firstWhere('slug', 'alphabet')
            ?? Category::factory()->create(['slug' => 'alphabet', 'name' => 'Alfabet', 'order' => 1]);
        $material = Material::factory()->for($category)->create(['language' => $language]);

        return Lesson::factory()->for($material)->create([
            'title' => $title,
            'gesture_label' => strtolower($title),
            'xp_reward' => $xp,
        ]);
    }

    /**
     * @param  array{recognized: ?string, confidence: int, correct: bool}  $recognition
     */
    private function swapRecognizer(array $recognition): void
    {
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldReceive('recognize')->andReturn($recognition + ['message' => 'ok']);
        $this->swap(GestureRecognitionService::class, $recognizer);
    }

    private function attempt(User $user, Lesson $lesson): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/practice/attempt', [
            'lesson_id' => $lesson->id,
            'image' => 'data:image/jpeg;base64,'.base64_encode(str_repeat('x', 64)),
            'mirrored' => true,
        ]);
    }

    public function test_api_practice_targets_lists_letters_per_system(): void
    {
        $user = $this->studentUser();
        $this->letterLesson('A', Material::LANGUAGE_SIBI);
        $this->letterLesson('A', Material::LANGUAGE_BISINDO);

        $response = $this->actingAs($user)->getJson('/api/practice/targets');

        $response->assertOk()->assertJsonStructure(['status', 'targets' => [['id', 'letter', 'language', 'category_slug', 'practiced']]]);
        $this->assertEqualsCanonicalizing(['sibi', 'bisindo'], collect($response->json('targets'))->pluck('language')->all());
    }

    public function test_attempt_without_detected_hand_is_not_recorded(): void
    {
        $this->swapRecognizer(['recognized' => null, 'confidence' => 0, 'correct' => false, 'detection_status' => 'no_hand']);
        $user = $this->studentUser();
        $lesson = $this->letterLesson();

        $this->attempt($user, $lesson)
            ->assertOk()
            ->assertJson(['correct' => false, 'evaluated' => false, 'xp_earned' => 0]);

        $this->assertSame(0, PracticeSession::count());
        $this->assertSame(0, $user->student->totalXp());
    }

    public function test_correct_demonstration_awards_xp_streak_challenge_and_badge(): void
    {
        $this->seed(BadgeSeeder::class);
        ChallengeService::ensureTodayChallenges();
        $this->swapRecognizer(['recognized' => 'A', 'confidence' => 92, 'correct' => true]);
        $user = $this->studentUser();
        $lesson = $this->letterLesson(xp: 15);

        $this->attempt($user, $lesson)
            ->assertOk()
            ->assertJson([
                'correct' => true,
                'score' => 92,
                'xp_earned' => 15,
                'already_mastered' => false,
                'total_xp' => 15,
                'streak' => 1,
            ])
            ->assertJsonFragment(['new_badges' => ['Langkah Pertama']]);

        $session = PracticeSession::sole();
        $this->assertSame(92, (int) $session->score);
        $this->assertSame(1, (int) ChallengeService::challengeStatus($user->id)->firstWhere('category', 'alfabet')->progress);
        $this->assertContains('first_practice', GamificationService::unlockedKeys($user->id));
    }

    public function test_repeating_a_mastered_lesson_gives_no_extra_xp(): void
    {
        $this->swapRecognizer(['recognized' => 'A', 'confidence' => 90, 'correct' => true]);
        $user = $this->studentUser();
        $lesson = $this->letterLesson(xp: 10);

        $this->attempt($user, $lesson)->assertJson(['xp_earned' => 10]);
        $this->attempt($user, $lesson)->assertJson(['correct' => true, 'xp_earned' => 0, 'already_mastered' => true]);

        $this->assertSame(10, $user->student->totalXp());
    }

    public function test_wrong_gesture_with_high_confidence_fails_with_zero_score(): void
    {
        $this->seed(BadgeSeeder::class);
        $this->swapRecognizer(['recognized' => 'B', 'confidence' => 88, 'correct' => false]);
        $user = $this->studentUser();
        $lesson = $this->letterLesson();
        $streakBefore = (int) $user->student->current_streak;

        $this->attempt($user, $lesson)->assertOk()->assertJson(['correct' => false, 'score' => 0, 'xp_earned' => 0]);

        $this->assertSame(0, (int) PracticeSession::sole()->score);
        $this->assertSame([], GamificationService::unlockedKeys($user->id));
        $this->assertSame($streakBefore, (int) $user->student->fresh()->current_streak);
    }

    public function test_correct_label_below_pass_score_does_not_pass(): void
    {
        $this->swapRecognizer(['recognized' => 'A', 'confidence' => 45, 'correct' => true]);
        $user = $this->studentUser();
        $lesson = $this->letterLesson();

        $this->attempt($user, $lesson)->assertOk()->assertJson(['correct' => false, 'xp_earned' => 0]);
        $this->assertSame(0, $user->student->totalXp());
    }

    public function test_target_letter_is_resolved_within_selected_system(): void
    {
        $this->swapRecognizer(['recognized' => 'A', 'confidence' => 90, 'correct' => true]);
        $user = $this->studentUser();
        $this->letterLesson('A', Material::LANGUAGE_SIBI);
        $bisindo = $this->letterLesson('A', Material::LANGUAGE_BISINDO);

        $this->actingAs($user)->postJson('/api/practice/attempt', [
            'target_letter' => 'A',
            'system' => 'bisindo',
            'image' => 'data:image/jpeg;base64,'.base64_encode(str_repeat('x', 64)),
        ])->assertOk()->assertJson(['lesson_id' => $bisindo->id]);

        $this->actingAs($user)->postJson('/api/practice/attempt', [
            'target_letter' => 'Q',
            'system' => 'bisindo',
        ])->assertStatus(422);
    }

    public function test_number_signs_are_not_offered_or_scored_because_ai_only_knows_letters(): void
    {
        $this->swapRecognizer(['recognized' => '1', 'confidence' => 99, 'correct' => true]);
        $user = $this->studentUser();
        $numbers = Category::factory()->create(['slug' => 'number', 'name' => 'Angka']);
        $one = Lesson::factory()
            ->for(Material::factory()->for($numbers)->create(['language' => Material::LANGUAGE_BISINDO]))
            ->create(['title' => '1', 'gesture_label' => '1']);

        $targets = $this->actingAs($user)->getJson('/api/practice/targets')->json('targets');
        $this->assertNotContains($one->id, collect($targets)->pluck('id')->all());

        $this->attempt($user, $one)->assertStatus(422);
        $this->assertSame(0, PracticeSession::count());

        $this->actingAs($user)->getJson("/api/lessons/{$one->id}")->assertJson(['ai_supported' => false]);
    }

    public function test_lesson_cannot_be_completed_without_passing_demonstration(): void
    {
        $user = $this->studentUser();
        $lesson = $this->letterLesson();

        $this->actingAs($user)->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertStatus(422)
            ->assertJson(['completed' => false]);
        $this->assertSame(0, PracticeSession::count());

        $this->swapRecognizer(['recognized' => 'A', 'confidence' => 90, 'correct' => true]);
        $this->attempt($user, $lesson);

        $this->actingAs($user)->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertOk()
            ->assertJson(['completed' => true, 'best_score' => 90]);
        $this->assertSame(1, PracticeSession::count());
    }

    public function test_challenge_cannot_be_claimed_before_target_is_reached(): void
    {
        $user = $this->studentUser();
        $challenge = Challenge::create([
            'challenge_date' => today(),
            'title' => 'Praktik 5 huruf',
            'category' => 'alfabet',
            'target' => 5,
            'reward_xp' => 20,
        ]);

        $this->actingAs($user)->postJson('/api/challenges/claim', ['challenge_id' => $challenge->id])
            ->assertStatus(422)
            ->assertJson(['claimed' => false]);

        $this->assertSame(0, $user->student->totalXp());
    }
}
