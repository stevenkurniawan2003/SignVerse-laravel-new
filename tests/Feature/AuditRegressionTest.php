<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Category;
use App\Models\Challenge;
use App\Models\ChallengeProgress;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ChallengeService;
use App\Services\ClassroomService;
use App\Services\Contracts\GestureRecognitionService;
use App\Services\GamificationService;
use App\Services\PracticeService;
use App\Services\StudentStatsService;
use App\Services\TeacherReportService;
use App\Services\TeacherStatsService;
use Carbon\CarbonImmutable;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi dari audit sistem: tiap test mewakili satu bug yang pernah terjadi.
 */
class AuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function studentUser(): User
    {
        return User::factory()->student()->has(Student::factory(), 'student')->create();
    }

    private function teacherUser(): User
    {
        return User::factory()->teacher()->has(Teacher::factory(), 'teacher')->create();
    }

    private function letterLesson(string $title = 'A', string $language = Material::LANGUAGE_SIBI, int $xp = 10): Lesson
    {
        $category = Category::query()->firstWhere('slug', 'alphabet')
            ?? Category::factory()->create(['slug' => 'alphabet', 'name' => 'Alphabet']);

        return Lesson::factory()
            ->for(Material::factory()->for($category)->create(['language' => $language]))
            ->create(['title' => $title, 'gesture_label' => strtolower($title), 'xp_reward' => $xp]);
    }

    private function numberLesson(): Lesson
    {
        $category = Category::query()->firstWhere('slug', 'number')
            ?? Category::factory()->create(['slug' => 'number', 'name' => 'Number']);

        return Lesson::factory()
            ->for(Material::factory()->for($category))
            ->create(['title' => '1', 'gesture_label' => '1', 'difficulty' => 1, 'order' => 0]);
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

    private function passAttempt(Student $student, Lesson $lesson): array
    {
        return app(PracticeService::class)->recordVerifiedAttempt($student, $lesson->load('material.category'), [
            'recognized' => $lesson->title,
            'confidence' => 90,
            'correct' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Streak
    |--------------------------------------------------------------------------
    */

    public function test_second_pass_on_the_same_day_does_not_raise_streak(): void
    {
        $this->travelTo('2026-11-02 09:00:00');
        $student = $this->studentUser()->student;
        $student->forceFill(['current_streak' => 0, 'longest_streak' => 0, 'last_activity_date' => '2026-11-01'])->save();
        $lesson = $this->letterLesson();

        $this->passAttempt($student, $lesson);
        $this->passAttempt($student->fresh(), $lesson);
        $this->passAttempt($student->fresh(), $lesson);

        $student->refresh();
        $this->assertSame(1, (int) $student->current_streak);
        $this->assertSame('2026-11-02', $student->last_activity_date->toDateString());
    }

    public function test_streak_continues_on_consecutive_days_and_resets_after_a_gap(): void
    {
        $student = $this->studentUser()->student;
        $student->forceFill(['current_streak' => 0, 'longest_streak' => 0, 'last_activity_date' => null])->save();
        $lesson = $this->letterLesson();

        foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $day) {
            $this->travelTo($day.' 10:00:00');
            $this->passAttempt($student->fresh(), $lesson);
        }
        $this->assertSame(3, (int) $student->fresh()->current_streak);

        $this->travelTo('2026-11-08 10:00:00');
        $this->passAttempt($student->fresh(), $lesson);

        $this->assertSame(1, (int) $student->fresh()->current_streak);
        $this->assertSame(3, (int) $student->fresh()->longest_streak);
    }

    public function test_stale_streak_is_not_shown_after_student_stops_practicing(): void
    {
        $this->travelTo('2026-11-10 10:00:00');
        $user = $this->studentUser();
        $user->student->forceFill(['current_streak' => 6, 'longest_streak' => 6, 'last_activity_date' => '2026-11-01'])->save();
        $teacher = $this->teacherUser();
        $classroom = Classroom::factory()->for($teacher->teacher)->create();
        $classroom->students()->attach($user->student);

        $this->assertSame(0, GamificationService::rankings($user->id)['me']['streak']);
        $this->assertSame(0, app(TeacherStatsService::class)->studentsTable($teacher->teacher)->first()['streak']);
    }

    public function test_longest_streak_is_never_lower_than_active_streak(): void
    {
        $this->travelTo('2026-11-02 10:00:00');
        $student = $this->studentUser()->student;
        $student->forceFill(['longest_streak' => 0])->save();
        PracticeSession::factory()->completed()->for($student)->create(['score' => 80, 'created_at' => now()]);

        $this->assertSame(1, app(StudentStatsService::class)->dashboardSummary($student)['longest_streak']);
    }

    /*
    |--------------------------------------------------------------------------
    | XP & tugas
    |--------------------------------------------------------------------------
    */

    public function test_legacy_session_flow_does_not_award_xp_for_mastered_lesson(): void
    {
        $this->swapRecognizer(['recognized' => 'A', 'confidence' => 95, 'correct' => true]);
        $service = app(PracticeService::class);
        $student = $this->studentUser()->student;
        $lesson = $this->letterLesson(xp: 10);

        foreach ([10, 0] as $expectedXp) {
            $session = $service->startSession($student, $lesson);
            $service->attempt($session, 'frame');
            $this->assertSame($expectedXp, $service->complete($session)['xp_earned']);
        }

        $this->assertSame(10, $student->totalXp());
    }

    public function test_assignment_cannot_be_completed_with_session_of_another_lesson(): void
    {
        $user = $this->studentUser();
        $target = $this->letterLesson('A');
        $other = $this->letterLesson('B');
        $assignment = Assignment::factory()->for(Teacher::factory())->for($target)->create(['min_score' => 60, 'xp_reward' => 50, 'due_at' => null]);
        AssignmentSubmission::create(['assignment_id' => $assignment->id, 'student_id' => $user->student->id]);
        $session = PracticeSession::factory()->completed()->for($user->student)->for($other)->create(['score' => 100]);

        $this->actingAs($user)
            ->post(route('student.assignments.submit', $assignment), ['practice_session_id' => $session->id])
            ->assertSessionHas('error');

        $this->assertNotSame(AssignmentSubmission::STATUS_COMPLETED, $assignment->submissions()->first()->status);
        $this->assertSame(0, (int) $assignment->submissions()->first()->xp_earned);
    }

    public function test_assignment_cannot_be_submitted_after_deadline(): void
    {
        $user = $this->studentUser();
        $lesson = $this->letterLesson('A');
        $assignment = Assignment::factory()->for(Teacher::factory())->for($lesson)->create(['min_score' => 60, 'xp_reward' => 50, 'due_at' => now()->subDay()]);
        AssignmentSubmission::create(['assignment_id' => $assignment->id, 'student_id' => $user->student->id]);
        $session = PracticeSession::factory()->completed()->for($user->student)->for($lesson)->create(['score' => 100]);

        $this->actingAs($user)
            ->post(route('student.assignments.submit', $assignment), ['practice_session_id' => $session->id])
            ->assertSessionHas('error');

        $this->assertNotSame(AssignmentSubmission::STATUS_COMPLETED, $assignment->submissions()->first()->status);
    }

    public function test_deleting_classroom_revokes_unfinished_work_of_archived_assignment(): void
    {
        $teacher = $this->teacherUser();
        $classroom = Classroom::factory()->for($teacher->teacher)->create();
        $done = $this->studentUser()->student;
        $pending = $this->studentUser()->student;
        $assignment = Assignment::factory()->for($teacher->teacher)->for($this->letterLesson())->create(['classroom_id' => $classroom->id, 'due_at' => null]);
        AssignmentSubmission::create(['assignment_id' => $assignment->id, 'student_id' => $done->id])
            ->forceFill(['status' => AssignmentSubmission::STATUS_COMPLETED, 'xp_earned' => 20])->save();
        AssignmentSubmission::create(['assignment_id' => $assignment->id, 'student_id' => $pending->id]);

        $this->actingAs($teacher)->delete(route('teacher.classes.destroy', $classroom))->assertRedirect();

        $this->assertSame(1, $assignment->submissions()->where('student_id', $done->id)->count());
        $this->assertSame(0, $assignment->submissions()->where('student_id', $pending->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Tantangan harian
    |--------------------------------------------------------------------------
    */

    public function test_challenge_progress_never_exceeds_target(): void
    {
        $user = $this->studentUser();
        $challenge = Challenge::create([
            'challenge_date' => today()->toDateString(),
            'title' => 'Praktik 2 huruf',
            'category' => 'alfabet',
            'target' => 2,
            'reward_xp' => 20,
        ]);

        foreach (range(1, 4) as $attempt) {
            ChallengeService::bumpChallenge($user->id, 'alphabet');
        }

        $this->assertSame(2, (int) ChallengeProgress::where('challenge_id', $challenge->id)->value('progress'));
    }

    public function test_pass_counts_for_challenge_even_if_student_opens_practice_first_today(): void
    {
        $user = $this->studentUser();
        $this->assertSame(0, Challenge::count());

        $this->passAttempt($user->student, $this->letterLesson());

        $this->assertSame(1, (int) ChallengeService::challengeStatus($user->id)->first()->progress);
    }

    /*
    |--------------------------------------------------------------------------
    | Akses data & halaman
    |--------------------------------------------------------------------------
    */

    public function test_student_cannot_read_classroom_monitoring_api(): void
    {
        $this->teacherUser();
        $this->studentUser();

        $this->actingAs($this->studentUser())->getJson('/api/classroom')->assertForbidden();
    }

    public function test_teacher_classroom_api_only_lists_own_students(): void
    {
        $teacher = $this->teacherUser();
        $classroom = Classroom::factory()->for($teacher->teacher)->create();
        $mine = $this->studentUser()->student;
        $classroom->students()->attach($mine);
        $outsider = $this->studentUser()->student;
        PracticeSession::factory()->completed()->for($outsider)->create(['score' => 100]);

        $response = $this->actingAs($teacher)->getJson('/api/classroom')->assertOk();

        $this->assertSame([$mine->id], collect($response->json('students'))->pluck('id')->all());
        $this->assertSame(1, $response->json('overview.students'));
        $this->assertSame(0, $response->json('overview.weekly_sessions'));
    }

    public function test_unknown_lesson_returns_404_instead_of_first_lesson(): void
    {
        $user = $this->studentUser();
        $this->letterLesson();

        $this->actingAs($user)->getJson('/api/lessons/99999')->assertNotFound();
        $this->actingAs($user)->get('/lesson/99999')->assertNotFound();
    }

    public function test_forgot_password_link_is_not_an_inertia_visit(): void
    {
        $login = file_get_contents(resource_path('js/Pages/Auth/Login.vue'));

        $this->assertDoesNotMatchRegularExpression('/<Link\s+v-if="canResetPassword"/', $login);
        $this->get('/forgot-password')->assertOk();
    }

    public function test_verified_user_opening_verify_email_page_reaches_own_dashboard(): void
    {
        $user = $this->studentUser();

        $this->actingAs($user)->get('/verify-email')->assertRedirect(route('student.dashboard'));
    }

    public function test_expired_csrf_on_inertia_request_redirects_back_with_message(): void
    {
        $user = $this->studentUser();

        $this->app['env'] = 'production';
        $this->actingAs($user)
            ->from('/profile')
            ->withHeader('X-Inertia', 'true')
            ->withSession(['_token' => 'server-token'])
            ->patch('/profile', ['_token' => 'stale-token', 'name' => 'Baru', 'email' => $user->email])
            ->assertRedirect('/profile')
            ->assertSessionHas('error');
    }

    public function test_every_page_opens_for_seeded_student_and_teacher(): void
    {
        $this->seed();
        $student = User::where('email', 'siswa@signteach.id')->firstOrFail();
        $teacher = User::where('email', 'guru@signteach.id')->firstOrFail();
        $classroom = Classroom::factory()->for($teacher->teacher)->create();
        $classroom->students()->attach($student->student);
        $lesson = Lesson::query()->firstOrFail();
        $assignment = Assignment::factory()->for($teacher->teacher)->for($lesson)->create(['classroom_id' => $classroom->id]);

        $studentPages = ['/dashboard', '/learn', "/lesson/{$lesson->id}", '/practice', '/challenge', '/achievement', '/progress', '/profile', '/student/dashboard', '/student/materials', "/student/materials/{$lesson->id}", '/student/practice', '/student/challenge', '/student/progress', '/student/achievements', '/student/assignments'];
        foreach ($studentPages as $page) {
            $this->actingAs($student)->get($page)->assertOk();
        }

        $teacherPages = ['/teacher/dashboard', '/teacher/dashboard/live', '/teacher/classes', "/teacher/classes/{$classroom->id}", "/teacher/classes/{$classroom->id}/live", '/teacher/students', '/teacher/students/live', "/teacher/students/{$student->student->id}", '/teacher/modules', '/teacher/assignments', "/teacher/assignments/{$assignment->id}", '/teacher/reports', '/teacher/reports/export', '/profile'];
        foreach ($teacherPages as $page) {
            $this->actingAs($teacher)->get($page)->assertOk();
        }
    }

    public function test_application_day_follows_indonesian_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 18:30:00', 'UTC'));

        $this->assertSame('2026-11-03', today()->toDateString());
    }

    public function test_same_letter_counts_once_per_day_for_daily_challenge(): void
    {
        $user = $this->studentUser();
        $letterA = $this->letterLesson('A');
        $letterB = $this->letterLesson('B');

        foreach (range(1, 5) as $attempt) {
            $this->passAttempt($user->student->fresh(), $letterA);
        }
        $this->assertSame(1, (int) ChallengeService::challengeStatus($user->id)->first()->progress);

        $this->passAttempt($user->student->fresh(), $letterB);
        $this->assertSame(2, (int) ChallengeService::challengeStatus($user->id)->first()->progress);

        $this->travel(1)->days();
        $this->passAttempt($user->student->fresh(), $letterA);
        $this->assertSame(1, (int) ChallengeService::challengeStatus($user->id)->first()->progress);
    }

    public function test_failed_legacy_session_does_not_raise_daily_challenge(): void
    {
        $this->swapRecognizer(['recognized' => 'B', 'confidence' => 90, 'correct' => false]);
        $user = $this->studentUser();
        ChallengeService::ensureTodayChallenges();
        $service = app(PracticeService::class);
        $session = $service->startSession($user->student, $this->letterLesson());

        $service->attempt($session, 'frame');
        $service->complete($session);

        $this->assertSame(0, (int) ChallengeService::challengeStatus($user->id)->first()->progress);
    }

    public function test_student_progress_alias_sends_same_shape_as_progress_page(): void
    {
        $user = $this->studentUser();
        $this->letterLesson();

        $this->actingAs($user)->get('/student/progress')->assertInertia(fn ($page) => $page
            ->component('Progress')
            ->has('weekActivity', 7)
            ->where('progress.0.name', 'Alphabet'));
    }

    public function test_alphabet_letters_are_locked_but_xp_stays_editable(): void
    {
        $teacher = $this->teacherUser();
        $lesson = $this->letterLesson('A', xp: 10);
        $material = $lesson->material;

        $this->actingAs($teacher)->delete(route('teacher.modules.lessons.destroy', $lesson))->assertSessionHas('error');
        $this->assertModelExists($lesson);

        $this->actingAs($teacher)->delete(route('teacher.modules.destroy', $material))->assertSessionHas('error');
        $this->assertModelExists($material);

        $this->actingAs($teacher)->put(route('teacher.modules.update', $material), [
            'category_id' => $material->category_id,
            'title' => $material->title,
            'language' => $material->language === 'sibi' ? 'bisindo' : 'sibi',
            'difficulty' => 1,
        ])->assertSessionHas('error');
        $this->assertSame($material->language, $material->fresh()->language);

        $this->actingAs($teacher)->put(route('teacher.modules.lessons.update', $lesson), [
            'title' => 'B',
            'xp_reward' => 10,
            'difficulty' => 1,
        ])->assertSessionHas('error');
        $this->assertSame('A', $lesson->fresh()->title);

        $this->actingAs($teacher)->put(route('teacher.modules.lessons.update', $lesson), [
            'title' => 'A',
            'xp_reward' => 25,
            'difficulty' => 2,
        ])->assertSessionHas('status');
        $this->assertSame(25, (int) $lesson->fresh()->xp_reward);
    }

    public function test_report_new_letters_ignores_letters_mastered_before_the_period(): void
    {
        $teacher = $this->teacherUser();
        $classroom = Classroom::factory()->for($teacher->teacher)->create();
        $student = $this->studentUser()->student;
        $classroom->students()->attach($student);
        $old = $this->letterLesson('A');
        $fresh = $this->letterLesson('B');
        PracticeSession::factory()->completed()->for($student)->for($old)->create(['score' => 90, 'completed_at' => now()->subDays(30)]);
        PracticeSession::factory()->completed()->for($student)->for($old)->create(['score' => 90, 'completed_at' => now()->subDay()]);
        PracticeSession::factory()->completed()->for($student)->for($fresh)->create(['score' => 90, 'completed_at' => now()->subDay()]);

        $report = app(TeacherReportService::class)->build(
            $teacher->teacher,
            $classroom,
            CarbonImmutable::today()->subDays(6),
            CarbonImmutable::today(),
        );

        $this->assertSame(1, $report['students']->first()['new_letters']);
    }

    public function test_learning_catalog_lists_full_alphabet_in_order(): void
    {
        $this->seed(ContentSeeder::class);

        $categories = collect($this->actingAs($this->studentUser())->getJson('/api/materials')->assertOk()->json('categories'));
        $materials = collect($categories->firstWhere('slug', 'alphabet')['materials'])->where('language', 'sibi');

        $this->assertSame(range('A', 'Z'), $materials->flatMap(fn (array $material) => collect($material['lessons'])->pluck('title'))->values()->all());
    }

    /*
    |--------------------------------------------------------------------------
    | Target belajar
    |--------------------------------------------------------------------------
    */

    public function test_recommendations_and_total_only_count_lessons_ai_can_verify(): void
    {
        $student = $this->studentUser()->student;
        $letter = $this->letterLesson();
        $number = $this->numberLesson();
        $wordCategory = Category::factory()->create(['slug' => 'word', 'name' => 'Word']);
        $wordMaterial = Material::factory()->for($wordCategory)->create();
        $word = Lesson::factory()->for($wordMaterial)->create(['title' => 'Aku', 'difficulty' => 1]);
        $invalidWord = Lesson::factory()->for($wordMaterial)->create(['title' => 'Halo!', 'difficulty' => 1]);
        $phraseCategory = Category::factory()->create(['slug' => 'phrase', 'name' => 'Phrase']);
        $phrase = Lesson::factory()
            ->for(Material::factory()->for($phraseCategory))
            ->create(['title' => 'Apa Kabar', 'difficulty' => 1]);
        $service = app(StudentStatsService::class);

        $recommended = $service->recommendedLessons($student, 10)->pluck('id')->all();

        $this->assertContains($letter->id, $recommended);
        $this->assertContains($word->id, $recommended);
        $this->assertContains($phrase->id, $recommended);
        $this->assertNotContains($number->id, $recommended);
        $this->assertNotContains($invalidWord->id, $recommended);

        PracticeSession::factory()->completed()->for($student)->for($word)->create(['score' => 90]);
        PracticeSession::factory()->completed()->for($student)->for($number)->create(['score' => 90]);
        PracticeSession::factory()->completed()->for($student)->for($invalidWord)->create(['score' => 90]);

        $summary = $service->dashboardSummary($student);
        $this->assertSame(3, $summary['lessons_total']);
        $this->assertSame(1, $summary['lessons_mastered']);
    }

    public function test_report_export_neutralizes_spreadsheet_formulas(): void
    {
        $teacher = $this->teacherUser();
        $classroom = Classroom::factory()->for($teacher->teacher)->create();
        $student = $this->studentUser();
        $student->update(['name' => '=HYPERLINK("http://contoh.test")']);
        app(ClassroomService::class)->addStudent($classroom, $student->student);

        $csv = $this->actingAs($teacher)->get(route('teacher.reports.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    /**
     * Windows tidak membedakan huruf besar-kecil pada path, sedangkan CI Linux membedakannya.
     * Nama folder dicocokkan dengan isi direktori agar salah kapital juga gagal di Windows.
     */
    public function test_inertia_page_paths_match_exact_folder_case(): void
    {
        foreach (config('inertia.pages.paths') as $pagePath) {
            $this->assertContains(basename($pagePath), scandir(dirname($pagePath)));
        }
    }
}
