<?php

namespace Tests\Feature\Teacher;

use App\Models\Category;
use App\Models\Classroom;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ClassroomService;
use App\Services\TeacherReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Laporan belajar guru (halaman, ekspor CSV) dan profil guru tanpa gamifikasi.
 */
class TeacherReportTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): Teacher
    {
        return User::factory()->teacher()->has(Teacher::factory(), 'teacher')->create(['name' => 'Bu Rina'])->teacher;
    }

    private function studentIn(Classroom $classroom, string $name): Student
    {
        $student = User::factory()->student()->has(Student::factory(), 'student')->create(['name' => $name])->student;
        app(ClassroomService::class)->addStudent($classroom, $student);

        return $student;
    }

    private function lesson(string $title): Lesson
    {
        $category = Category::query()->firstWhere('slug', 'alphabet') ?? Category::factory()->create(['slug' => 'alphabet']);

        return Lesson::factory()
            ->for(Material::factory()->for($category)->create(['language' => 'sibi']))
            ->create(['title' => $title, 'gesture_label' => strtolower($title)]);
    }

    private function practiceAt(Student $student, Lesson $lesson, int $score, string $at): void
    {
        PracticeSession::factory()->for($student)->for($lesson)->create([
            'status' => PracticeSession::STATUS_COMPLETED,
            'score' => $score,
            'language' => 'sibi',
            'completed_at' => $at,
        ]);
    }

    public function test_report_summarises_only_the_teachers_students_in_the_period(): void
    {
        $teacher = $this->teacher();
        $classroom = Classroom::factory()->for($teacher)->create();
        $ani = $this->studentIn($classroom, 'Ani');
        $budi = $this->studentIn($classroom, 'Budi');
        $outsider = User::factory()->student()->has(Student::factory(), 'student')->create()->student;
        $a = $this->lesson('A');
        $b = $this->lesson('B');

        $today = CarbonImmutable::today();
        $this->practiceAt($ani, $a, 90, $today->toDateTimeString());
        $this->practiceAt($ani, $b, 0, $today->toDateTimeString());
        $this->practiceAt($ani, $b, 70, $today->subDay()->toDateTimeString());
        $this->practiceAt($budi, $a, 40, $today->subDays(20)->toDateTimeString()); // di luar 7 hari
        $this->practiceAt($outsider, $a, 99, $today->toDateTimeString());           // bukan siswa guru

        $report = app(TeacherReportService::class)->build($teacher, null, $today->subDays(6), $today);

        $this->assertSame(2, $report['summary']['students']);
        $this->assertSame(1, $report['summary']['active_students']);
        $this->assertSame(3, $report['summary']['sessions']);
        $this->assertSame(2, $report['summary']['passed']);
        $this->assertSame(67, $report['summary']['pass_rate']);
        $this->assertSame(2, $report['summary']['letters_mastered']);
        $this->assertCount(7, $report['daily']);

        $rows = collect($report['students'])->keyBy('name');
        $this->assertSame('Aktif', $rows['Ani']['status']);
        $this->assertSame('Tidak berlatih', $rows['Budi']['status']);
        $this->assertSame(2, $rows['Ani']['new_letters']);
    }

    public function test_report_page_and_csv_export_are_scoped_to_teacher_classes(): void
    {
        $teacher = $this->teacher();
        $classroom = Classroom::factory()->for($teacher)->create(['name' => 'Kelas 7A']);
        $this->studentIn($classroom, 'Citra');
        $otherClass = Classroom::factory()->for($this->teacher())->create();

        $this->actingAs($teacher->user)
            ->get('/teacher/reports?range=30d&classroom_id='.$classroom->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Teacher/Reports')
                ->where('report.classroom.name', 'Kelas 7A')
                ->where('report.period.days', 30)
                ->has('report.students', 1));

        $csv = $this->actingAs($teacher->user)
            ->get('/teacher/reports/export?range=7d&classroom_id='.$classroom->id)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('Kelas;"Kelas 7A"', $csv);
        $this->assertStringContainsString('Citra', $csv);

        // Kelas milik guru lain ditolak.
        $this->actingAs($teacher->user)
            ->get('/teacher/reports?classroom_id='.$otherClass->id)
            ->assertSessionHasErrors('classroom_id');
    }

    public function test_students_cannot_open_reports(): void
    {
        $student = User::factory()->student()->has(Student::factory(), 'student')->create();

        $this->actingAs($student)->get('/teacher/reports')->assertRedirect();
    }

    public function test_teacher_profile_shows_teaching_summary_without_gamification(): void
    {
        $teacher = $this->teacher();
        $classroom = Classroom::factory()->for($teacher)->create();
        $this->studentIn($classroom, 'Dodi');

        $this->actingAs($teacher->user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Teacher/Profile')
                ->where('profile.name', 'Bu Rina')
                ->where('teaching.classes', 1)
                ->where('teaching.students', 1)
                ->missing('summary')
                ->missing('xp'));
    }

    public function test_student_profile_still_uses_student_page(): void
    {
        $student = User::factory()->student()->has(Student::factory(), 'student')->create();

        $this->actingAs($student)
            ->get('/profile')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Profile'));
    }
}
