<?php

namespace Tests\Feature\Api;

use App\Models\Assignment;
use App\Models\Category;
use App\Models\Challenge;
use App\Models\GestureResult;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ChallengeService;
use App\Services\Contracts\GestureRecognitionService;
use App\Services\GamificationService;
use App\Services\PracticeService;
use Database\Seeders\BadgeSeeder;
use Database\Seeders\ContentSeeder;
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
        return User::factory()->student()->has(Student::factory()->state([
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_activity_date' => null,
        ]), 'student')->create();
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

    private function spellingLesson(string $title, string $categorySlug = 'word', string $language = Material::LANGUAGE_SIBI): Lesson
    {
        $category = Category::query()->firstWhere('slug', $categorySlug)
            ?? Category::factory()->create(['slug' => $categorySlug, 'name' => ucfirst($categorySlug)]);

        return Lesson::factory()
            ->for(Material::factory()->for($category)->create(['language' => $language]))
            ->create(['title' => $title, 'gesture_label' => strtolower($title), 'xp_reward' => 20]);
    }

    private function attempt(User $user, Lesson $lesson, ?string $progressToken = null): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/practice/attempt', [
            'lesson_id' => $lesson->id,
            'image' => 'data:image/jpeg;base64,'.base64_encode(str_repeat('x', 64)),
            'mirrored' => true,
            'progress_token' => $progressToken,
        ]);
    }

    public function test_api_practice_targets_lists_letters_per_system(): void
    {
        $user = $this->studentUser();
        $this->letterLesson('A', Material::LANGUAGE_SIBI);
        $this->letterLesson('A', Material::LANGUAGE_BISINDO);

        $response = $this->actingAs($user)->getJson('/api/practice/targets');

        $response->assertOk()->assertJsonStructure(['status', 'targets' => [['id', 'letter', 'language', 'category_slug', 'practiced']]]);
        $this->assertEqualsCanonicalizing(['sibi', 'bisindo'], collect($response->json('targets'))->pluck('language')->unique()->all());
    }

    public function test_targets_always_contain_full_alphabet_for_both_systems(): void
    {
        $user = $this->studentUser();

        $targets = collect($this->actingAs($user)->getJson('/api/practice/targets')->assertOk()->json('targets'));

        foreach (['sibi', 'bisindo'] as $language) {
            $this->assertSame(
                range('A', 'Z'),
                $targets->where('language', $language)->pluck('letter')->values()->all(),
            );
        }
    }

    public function test_missing_letters_are_added_without_touching_existing_lessons(): void
    {
        $user = $this->studentUser();
        $existing = $this->letterLesson('C', Material::LANGUAGE_SIBI, xp: 25);
        PracticeSession::factory()->completed()->for($user->student)->for($existing)->create(['score' => 90]);

        $targets = collect($this->actingAs($user)->getJson('/api/practice/targets')->json('targets'));
        $letterC = $targets->where('language', 'sibi')->firstWhere('letter', 'C');

        $this->assertSame($existing->id, $letterC['id']);
        $this->assertSame(25, $letterC['xp_reward']);
        $this->assertTrue($letterC['practiced']);
        $this->assertCount(52, $targets);

        $this->actingAs($user)->getJson('/api/practice/targets')->assertOk();
        $this->assertSame(52, Lesson::count());
    }

    public function test_letter_deleted_from_modules_returns_to_practice_targets(): void
    {
        $user = $this->studentUser();
        $this->actingAs($user)->getJson('/api/practice/targets')->assertOk();

        Lesson::query()
            ->where('title', 'Q')
            ->whereHas('material', fn ($query) => $query->where('language', Material::LANGUAGE_BISINDO))
            ->delete();

        $targets = collect($this->actingAs($user)->getJson('/api/practice/targets')->json('targets'));

        $this->assertSame(range('A', 'Z'), $targets->where('language', 'bisindo')->pluck('letter')->values()->all());
    }

    public function test_targets_ignore_assignments_from_teacher(): void
    {
        $user = $this->studentUser();
        $lesson = $this->letterLesson('B', Material::LANGUAGE_SIBI);
        Assignment::factory()->for(Teacher::factory())->for($lesson)->create();

        $targets = collect($this->actingAs($user)->getJson('/api/practice/targets')->json('targets'));

        $this->assertCount(26, $targets->where('language', 'sibi'));
        $this->assertCount(26, $targets->where('language', 'bisindo'));
    }

    public function test_seeder_creates_full_alphabet_for_both_systems(): void
    {
        $this->seed(ContentSeeder::class);

        foreach ([Material::LANGUAGE_SIBI, Material::LANGUAGE_BISINDO] as $language) {
            $letters = Lesson::query()
                ->whereHas('material', fn ($query) => $query
                    ->where('language', $language)
                    ->whereHas('category', fn ($category) => $category->where('slug', 'alphabet')))
                ->pluck('title')
                ->sort()
                ->values()
                ->all();

            $this->assertSame(range('A', 'Z'), $letters);
        }
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

    public function test_word_lesson_resumes_from_the_last_verified_letter(): void
    {
        $user = $this->studentUser();
        $lesson = $this->spellingLesson('Baba');
        $this->swapRecognizer(['recognized' => 'B', 'confidence' => 90, 'correct' => true]);

        $initial = $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")
            ->assertOk()
            ->assertJsonPath('spelling.letters', ['B', 'A', 'B', 'A'])
            ->assertJsonPath('spelling.current_letter', 'B')
            ->json('spelling');

        $attempt = $this->attempt($user, $lesson, $initial['progress_token'])
            ->assertOk()
            ->assertJson(['correct' => true, 'letters_done' => 1, 'letter_index' => 1, 'current_letter' => 'A', 'word_completed' => false, 'xp_earned' => 0]);

        $this->actingAs($user)->get("/lesson/{$lesson->id}")->assertInertia(fn ($page) => $page
            ->component('Lesson')
            ->where('ai_supported', true)
            ->where('spelling.letters_done', 1)
            ->where('spelling.current_letter', 'A')
            ->where('spelling.progress_token', $attempt->json('progress_token')));

        $this->assertSame(0, PracticeSession::count());
        $this->assertSame(0, $user->student->totalXp());
    }

    public function test_wrong_spelling_letter_keeps_its_position_and_stale_token_only_resynchronizes(): void
    {
        $user = $this->studentUser();
        $lesson = $this->spellingLesson('AA');
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldReceive('recognize')->twice()->andReturn(
            ['recognized' => 'A', 'confidence' => 90, 'correct' => true],
            ['recognized' => 'B', 'confidence' => 90, 'correct' => false],
        );
        $this->swap(GestureRecognitionService::class, $recognizer);
        $initial = $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")->json('spelling');
        $first = $this->attempt($user, $lesson, $initial['progress_token'])->assertOk();

        $wrong = $this->attempt($user, $lesson, $first->json('progress_token'))
            ->assertOk()
            ->assertJson(['correct' => false, 'letters_done' => 1, 'letter_index' => 1, 'current_letter' => 'A', 'word_completed' => false, 'xp_earned' => 0]);

        $this->assertNotSame($first->json('progress_token'), $wrong->json('progress_token'));
        $this->attempt($user, $lesson, $first->json('progress_token'))
            ->assertStatus(409)
            ->assertJson(['correct' => false, 'evaluated' => false, 'letters_done' => 1, 'current_letter' => 'A'])
            ->assertJsonPath('progress_token', $wrong->json('progress_token'));

        $this->assertSame(0, PracticeSession::count());
    }

    public function test_completed_spelling_returns_average_accuracy_and_repeating_it_does_not_duplicate_xp(): void
    {
        $user = $this->studentUser();
        $lesson = $this->spellingLesson('Anna');
        $this->letterLesson('A');
        $this->letterLesson('N');
        $letters = ['A', 'N', 'N', 'A'];
        $confidences = [80, 90, 100, 70];
        $callIndex = 0;
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldReceive('recognize')->times(8)->andReturnUsing(function (string $expected, ?string $image, string $language, bool $mirrored) use (&$callIndex, $letters, $confidences): array {
            $index = $callIndex++ % count($letters);
            $this->assertSame($letters[$index], $expected);
            $this->assertSame(Material::LANGUAGE_SIBI, $language);
            $this->assertTrue($mirrored);

            return ['recognized' => $expected, 'confidence' => $confidences[$index], 'correct' => true];
        });
        $this->swap(GestureRecognitionService::class, $recognizer);

        foreach ([20, 0] as $reward) {
            $token = $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")
                ->assertJsonPath('spelling.letters_done', 0)
                ->json('spelling.progress_token');

            foreach ($letters as $index => $letter) {
                $attempt = $this->attempt($user, $lesson, $token)->assertOk()->assertJsonPath('attempted_index', $index);
                $token = $attempt->json('progress_token');
                if ($index < count($letters) - 1) {
                    $attempt->assertJson(['word_completed' => false, 'score' => 0, 'xp_earned' => 0]);
                }
            }

            $attempt->assertJson([
                'word' => 'ANNA',
                'word_completed' => true,
                'letters_done' => 4,
                'letter_index' => 4,
                'current_letter' => null,
                'progress_token' => null,
                'confidence' => 85,
                'score' => 85,
                'xp_earned' => $reward,
                'already_mastered' => $reward === 0,
            ]);
        }

        $this->assertSame(2, PracticeSession::count());
        $this->assertSame(2, GestureResult::count());
        $this->assertSame([$lesson->id], PracticeSession::query()->distinct()->pluck('lesson_id')->all());
        // XP pelajaran 20 sekali + bonus challenge kosakata 20 (target 1 karena hanya ada 1 kata).
        $this->assertSame(40, $user->student->totalXp());
    }

    public function test_phrase_resume_is_scoped_to_student_and_expired_progress_starts_from_first_letter(): void
    {
        $user = $this->studentUser();
        $other = $this->studentUser();
        $lesson = $this->spellingLesson('Apa Kabar', 'phrase');
        $this->swapRecognizer(['recognized' => 'A', 'confidence' => 90, 'correct' => true]);
        $initial = $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")
            ->assertJsonPath('spelling.letters', ['A', 'P', 'A', 'K', 'A', 'B', 'A', 'R'])
            ->json('spelling');
        $progress = $this->attempt($user, $lesson, $initial['progress_token'])->assertJson(['letters_done' => 1, 'current_letter' => 'P']);

        $this->actingAs($other)->getJson("/api/lessons/{$lesson->id}")->assertJsonPath('spelling.letters_done', 0);
        $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")->assertJsonPath('spelling.letters_done', 1);

        $this->travel(31)->minutes();
        $expired = $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")
            ->assertJsonPath('spelling.letters_done', 0)
            ->assertJsonPath('spelling.current_letter', 'A');

        $this->assertNotSame($progress->json('progress_token'), $expired->json('spelling.progress_token'));
        $this->assertSame(0, PracticeSession::count());
    }

    public function test_spelling_cannot_skip_letters_or_trust_client_scores_and_completion_flags(): void
    {
        $user = $this->studentUser();
        $lesson = $this->spellingLesson('Aku');
        $initial = $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")->assertOk()->json('spelling');
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldReceive('recognize')->once()
            ->with('A', \Mockery::type('string'), Material::LANGUAGE_SIBI, true)
            ->andReturn(['recognized' => 'U', 'confidence' => 99, 'correct' => true]);
        $this->swap(GestureRecognitionService::class, $recognizer);

        foreach ([null, 'invented-token'] as $token) {
            $this->attempt($user, $lesson, $token)
                ->assertStatus(409)
                ->assertJson(['correct' => false, 'evaluated' => false, 'letters_done' => 0, 'current_letter' => 'A']);
        }

        $this->actingAs($user)->postJson('/api/practice/attempt', [
            'lesson_id' => $lesson->id,
            'image' => 'data:image/jpeg;base64,test-frame',
            'mirrored' => true,
            'progress_token' => $initial['progress_token'],
            'system' => Material::LANGUAGE_BISINDO,
            'target_letter' => 'U',
            'letter_index' => 2,
            'letters_done' => 3,
            'word_completed' => true,
            'correct' => true,
            'score' => 100,
            'xp_earned' => 9999,
        ])->assertOk()->assertJson([
            'correct' => false,
            'attempted_letter' => 'A',
            'attempted_index' => 0,
            'letter_index' => 0,
            'letters_done' => 0,
            'word_completed' => false,
            'score' => 0,
            'xp_earned' => 0,
        ]);

        $this->assertSame(0, PracticeSession::count());
        $this->assertSame(0, GestureResult::count());
        $this->assertSame(0, $user->student->totalXp());
    }

    public function test_failed_spelling_attempts_preserve_progress_and_are_excluded_from_final_average(): void
    {
        $user = $this->studentUser();
        $streakBefore = (int) $user->student->current_streak;
        $lesson = $this->spellingLesson('Aku');
        $token = $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")->json('spelling.progress_token');
        $recognitions = [
            ['recognized' => 'A', 'confidence' => 90, 'correct' => true],
            ['recognized' => null, 'confidence' => 0, 'correct' => false, 'detection_status' => 'no_hand'],
            ['recognized' => null, 'confidence' => 0, 'correct' => false, 'detection_status' => 'service_unavailable'],
            ['recognized' => 'K', 'confidence' => PracticeService::PASS_SCORE - 1, 'correct' => true],
            ['recognized' => 'K', 'confidence' => 80, 'correct' => true],
            ['recognized' => 'U', 'confidence' => 70, 'correct' => true],
        ];
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldReceive('recognize')->times(count($recognitions))->andReturn(...$recognitions);
        $this->swap(GestureRecognitionService::class, $recognizer);
        $first = $this->attempt($user, $lesson, $token)->assertOk();
        $token = $first->json('progress_token');

        foreach (array_slice($recognitions, 1, 3) as $recognition) {
            $failed = $this->attempt($user, $lesson, $token)->assertOk()->assertJson([
                'correct' => false,
                'letters_done' => 1,
                'letter_index' => 1,
                'current_letter' => 'K',
                'word_completed' => false,
                'score' => 0,
                'xp_earned' => 0,
            ])->assertJsonPath('evaluated', $recognition['recognized'] !== null);
            $token = $failed->json('progress_token');
            $this->assertSame(0, PracticeSession::count());
            $this->assertSame($streakBefore, (int) $user->student->fresh()->current_streak);
        }

        foreach (['K', 'U'] as $letter) {
            $result = $this->attempt($user, $lesson, $token)->assertOk()->assertJsonPath('attempted_letter', $letter);
            $token = $result->json('progress_token');
        }

        $result->assertJson(['word_completed' => true, 'score' => 80, 'confidence' => 80, 'xp_earned' => 20]);
        $this->assertSame(1, PracticeSession::count());
        $this->assertSame(1, GestureResult::count());
        $this->assertSame(80, (int) GestureResult::sole()->confidence);
    }

    public function test_word_completion_finishes_teacher_assignment_with_only_assignment_xp(): void
    {
        $this->seed(BadgeSeeder::class);
        ChallengeService::ensureTodayChallenges();
        $user = $this->studentUser();
        $lesson = $this->spellingLesson('Aku');
        $assignment = Assignment::factory()->for(Teacher::factory())->for($lesson)->create([
            'title' => 'Eja Aku', 'min_score' => 75, 'xp_reward' => 35,
        ]);
        $submission = $assignment->submissions()->create(['student_id' => $user->student->id]);
        $token = $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")->json('spelling.progress_token');
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldReceive('recognize')->times(3)->andReturnUsing(
            fn (string $expected): array => ['recognized' => $expected, 'confidence' => 90, 'correct' => true],
        );
        $this->swap(GestureRecognitionService::class, $recognizer);

        foreach (['A', 'K', 'U'] as $index => $letter) {
            $usedToken = $token;
            $result = $this->attempt($user, $lesson, $token)->assertOk()->assertJsonPath('attempted_letter', $letter);
            $token = $result->json('progress_token');

            if ($index < 2) {
                $result->assertJson(['xp_earned' => 0, 'assignments_completed' => [], 'new_badges' => []]);
                $this->assertSame('assigned', $submission->fresh()->status);
                $this->assertSame(0, $user->student->totalXp());
                $this->assertSame(0, (int) ChallengeService::challengeStatus($user->id)->firstWhere('category', 'kosakata')->progress);
            }
        }

        // XP tugas 35 + bonus challenge kosakata 20 (target 1 karena hanya ada 1 kata).
        $result->assertJson([
            'word_completed' => true,
            'xp_earned' => 35,
            'total_xp' => 55,
            'challenge_completed' => true,
            'assignments_completed' => [['title' => 'Eja Aku', 'xp_earned' => 35]],
        ]);
        $this->assertSame('completed', $submission->fresh()->status);
        $this->assertSame(90, (int) $submission->fresh()->score);
        $this->assertSame(0, (int) PracticeSession::sole()->xp_earned);
        $this->assertSame(1, (int) $user->student->fresh()->current_streak);
        $this->assertContains('first_practice', GamificationService::unlockedKeys($user->id));

        $this->attempt($user, $lesson, $usedToken)->assertStatus(409)->assertJsonPath('word_completed', false);
        $this->assertSame(1, PracticeSession::count());
        $this->assertSame(55, $user->student->totalXp());
    }

    public function test_phrase_spelling_uses_each_position_and_bumps_vocabulary_challenge_once_per_day(): void
    {
        $user = $this->studentUser();
        $lesson = $this->spellingLesson('Apa Kabar', 'phrase', Material::LANGUAGE_BISINDO);
        $letters = str_split('APAKABAR');
        $calls = 0;
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldReceive('recognize')->times(16)->andReturnUsing(function (string $expected, ?string $image, string $language, bool $mirrored) use (&$calls, $letters): array {
            $this->assertSame($letters[$calls++ % count($letters)], $expected);
            $this->assertSame(Material::LANGUAGE_BISINDO, $language);
            $this->assertTrue($mirrored);

            return ['recognized' => $expected, 'confidence' => 90, 'correct' => true];
        });
        $this->swap(GestureRecognitionService::class, $recognizer);

        foreach ([20, 0] as $reward) {
            $token = $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")->json('spelling.progress_token');

            foreach ($letters as $index => $letter) {
                $result = $this->attempt($user, $lesson, $token)->assertOk()->assertJsonPath('attempted_index', $index);
                $token = $result->json('progress_token');
                $result->assertJsonPath('word_completed', $index === count($letters) - 1);
            }

            $result->assertJson(['word' => 'APA KABAR', 'score' => 90, 'xp_earned' => $reward]);
            $status = ChallengeService::challengeStatus($user->id);
            $this->assertSame(1, (int) $status->firstWhere('category', 'kosakata')->progress);
            $this->assertSame(0, (int) $status->firstWhere('category', 'alfabet')->progress);
        }

        $this->assertSame(2, PracticeSession::count());
        $this->assertSame(2, GestureResult::count());
        // XP frasa 20 sekali + bonus challenge kosakata 20 sekali, walau frasa diulang.
        $this->assertSame(40, $user->student->totalXp());
    }

    public function test_spelling_activity_renews_expiry_but_reading_progress_does_not(): void
    {
        $user = $this->studentUser();
        $lesson = $this->spellingLesson('Aku');
        $initial = $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")->json('spelling');
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldReceive('recognize')->once()->andReturn(['recognized' => 'A', 'confidence' => 90, 'correct' => true]);
        $this->swap(GestureRecognitionService::class, $recognizer);

        $this->travel(20)->minutes();
        $attempt = $this->attempt($user, $lesson, $initial['progress_token'])->assertOk();
        $this->assertNotSame($initial['expires_at'], $attempt->json('expires_at'));

        $this->travel(20)->minutes();
        $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")
            ->assertJsonPath('spelling.letters_done', 1)
            ->assertJsonPath('spelling.expires_at', $attempt->json('expires_at'))
            ->assertJsonPath('spelling.progress_token', $attempt->json('progress_token'));

        $this->travel(11)->minutes();
        $expired = $this->attempt($user, $lesson, $attempt->json('progress_token'))->assertStatus(409)
            ->assertJson(['letters_done' => 0, 'current_letter' => 'A', 'correct' => false, 'evaluated' => false]);
        $this->assertNotSame($attempt->json('progress_token'), $expired->json('progress_token'));
        $this->assertSame(0, PracticeSession::count());
    }

    public function test_spelling_tokens_are_scoped_to_students_lessons_and_current_content(): void
    {
        $user = $this->studentUser();
        $other = $this->studentUser();
        $lesson = $this->spellingLesson('Aku');
        $bisindo = $this->spellingLesson('Aku', 'word', Material::LANGUAGE_BISINDO);
        $this->swapRecognizer(['recognized' => 'A', 'confidence' => 90, 'correct' => true]);
        $initial = $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")->json('spelling');
        $progress = $this->attempt($user, $lesson, $initial['progress_token'])->assertOk();

        $otherStudent = $this->attempt($other, $lesson, $progress->json('progress_token'))->assertStatus(409)
            ->assertJson(['letters_done' => 0, 'current_letter' => 'A']);
        $otherLesson = $this->attempt($user, $bisindo, $progress->json('progress_token'))->assertStatus(409)
            ->assertJson(['letters_done' => 0, 'current_letter' => 'A']);
        $this->assertNotSame($progress->json('progress_token'), $otherStudent->json('progress_token'));
        $this->assertNotSame($progress->json('progress_token'), $otherLesson->json('progress_token'));

        foreach (['language', 'title'] as $changedField) {
            if ($changedField === 'language') {
                $lesson->material->update(['language' => Material::LANGUAGE_BISINDO]);
            } else {
                $lesson->update(['title' => 'Api']);
            }

            $reset = $this->attempt($user, $lesson, $progress->json('progress_token'))->assertStatus(409)
                ->assertJson(['letters_done' => 0, 'current_letter' => 'A']);
            $progress = $this->attempt($user, $lesson, $reset->json('progress_token'))->assertOk()
                ->assertJsonPath('letters_done', 1);
        }

        $this->assertSame(0, PracticeSession::count());
    }

    public function test_inflight_spelling_request_and_replayed_frame_cannot_advance_repeated_letters(): void
    {
        $user = $this->studentUser();
        $lesson = $this->spellingLesson('AA');
        $initial = $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")->json('spelling');
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldReceive('recognize')->once()->andReturnUsing(function (string $expected, ?string $image, string $language, bool $mirrored) use ($user, $lesson, $initial): array {
            $blocked = app(PracticeService::class)->attemptSpelling($user->student, $lesson, $image, $mirrored, $initial['progress_token']);
            $this->assertSame(409, $blocked['status_code']);
            $this->assertSame(0, $blocked['spelling']['letters_done']);

            return ['recognized' => $expected, 'confidence' => 90, 'correct' => true];
        });
        $this->swap(GestureRecognitionService::class, $recognizer);

        $first = $this->attempt($user, $lesson, $initial['progress_token'])->assertOk()
            ->assertJson(['letters_done' => 1, 'current_letter' => 'A', 'word_completed' => false]);
        $this->attempt($user, $lesson, $initial['progress_token'])->assertStatus(409)
            ->assertJsonPath('letters_done', 1)
            ->assertJsonPath('progress_token', $first->json('progress_token'));
        $this->assertSame(0, PracticeSession::count());
        $this->assertSame(0, $user->student->totalXp());
    }

    public function test_invalid_persisted_words_and_phrases_are_not_offered_or_scored(): void
    {
        $user = $this->studentUser();
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldNotReceive('recognize');
        $this->swap(GestureRecognitionService::class, $recognizer);

        foreach (['word', 'phrase'] as $category) {
            foreach (['Aku2', 'Apa?', 'Café', '   '] as $title) {
                $lesson = $this->spellingLesson($title, $category);
                $this->actingAs($user)->getJson("/api/lessons/{$lesson->id}")->assertOk()
                    ->assertJson(['ai_supported' => false, 'spelling' => null]);
                $this->attempt($user, $lesson)->assertStatus(422);
            }
        }

        $this->assertSame(0, PracticeSession::count());
        $this->assertSame(0, GestureResult::count());
    }

    public function test_spelling_cannot_be_completed_through_legacy_practice_or_complete_endpoints(): void
    {
        $user = $this->studentUser();
        $recognizer = \Mockery::mock(GestureRecognitionService::class);
        $recognizer->shouldNotReceive('recognize');
        $this->swap(GestureRecognitionService::class, $recognizer);

        foreach (['word', 'phrase'] as $category) {
            $lesson = $this->spellingLesson('Aku', $category);
            $this->actingAs($user)->get(route('student.practice.start', $lesson))
                ->assertRedirect(route('vue.lesson', ['id' => $lesson->id]));
            $this->assertSame(0, PracticeSession::where('lesson_id', $lesson->id)->count());
            $session = PracticeSession::factory()->for($user->student)->for($lesson)->create([
                'status' => PracticeSession::STATUS_IN_PROGRESS, 'score' => 0, 'xp_earned' => 0,
            ]);

            $this->actingAs($user)->postJson(route('student.practice.attempt', $session), ['image' => 'test'])->assertStatus(422);
            $this->actingAs($user)->postJson(route('student.practice.complete', $session))->assertStatus(422);
            $this->actingAs($user)->postJson("/api/lessons/{$lesson->id}/complete", ['score' => 100])->assertStatus(422);
            $this->assertSame(PracticeSession::STATUS_IN_PROGRESS, $session->fresh()->status);
        }

        $this->assertSame(0, GestureResult::count());
        $this->assertSame(0, $user->student->totalXp());
    }

    public function test_spelling_requires_student_authentication_and_does_not_expand_alphabet_studio(): void
    {
        $lesson = $this->spellingLesson('A');
        $this->getJson("/api/lessons/{$lesson->id}")->assertUnauthorized();
        $this->postJson('/api/practice/attempt', ['lesson_id' => $lesson->id])->assertUnauthorized();

        $teacher = User::factory()->teacher()->has(Teacher::factory(), 'teacher')->create();
        $this->actingAs($teacher)->postJson('/api/practice/attempt', ['lesson_id' => $lesson->id])->assertForbidden();

        $user = $this->studentUser();
        $targets = $this->actingAs($user)->getJson('/api/practice/targets')->assertOk()->json('targets');
        $this->assertCount(52, $targets);
        $this->assertNotContains($lesson->id, array_column($targets, 'id'));
        $this->actingAs($user)->getJson("/api/v1/lessons/{$lesson->id}")->assertOk()
            ->assertJsonPath('spelling.letters', ['A']);
    }
}
