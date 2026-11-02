<?php

namespace Tests\Feature\Teacher;

use App\Models\Assignment;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AlphabetLessonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function teacherUser(): User
    {
        return User::factory()->teacher()->has(Teacher::factory(), 'teacher')->create();
    }

    private function wordCategory(): Category
    {
        return Category::factory()->create(['name' => 'Word', 'slug' => 'word']);
    }

    private function wordMaterial(string $language = 'sibi'): Material
    {
        return Material::factory()->for($this->wordCategory())->create(['language' => $language]);
    }

    public function test_teacher_adds_module_without_difficulty_field(): void
    {
        $category = $this->wordCategory();

        $this->actingAs($this->teacherUser())->post(route('teacher.modules.store'), [
            'category_id' => $category->id,
            'title' => 'Kosakata Keluarga',
            'language' => 'sibi',
            'description' => '',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertDatabaseHas('materials', [
            'category_id' => $category->id,
            'title' => 'Kosakata Keluarga',
            'difficulty' => 1,
        ]);
    }

    public function test_new_module_cannot_be_added_to_complete_alphabet(): void
    {
        $alphabet = Category::factory()->create(['name' => 'Alphabet', 'slug' => AlphabetLessonService::CATEGORY_SLUG]);

        $this->actingAs($this->teacherUser())->post(route('teacher.modules.store'), [
            'category_id' => $alphabet->id,
            'title' => 'Huruf Tambahan',
            'language' => 'sibi',
        ])->assertSessionHasErrors('category_id');

        $this->assertDatabaseMissing('materials', ['title' => 'Huruf Tambahan']);
    }

    public function test_teacher_edits_module_using_only_fields_from_the_form(): void
    {
        $material = $this->wordMaterial();
        $otherCategory = Category::factory()->create();

        $this->actingAs($this->teacherUser())->put(route('teacher.modules.update', $material), [
            'category_id' => $otherCategory->id,
            'title' => 'Judul Baru',
            'language' => 'bisindo',
            'description' => 'Deskripsi baru',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $material->refresh();
        $this->assertSame('Judul Baru', $material->title);
        $this->assertSame('bisindo', $material->language);
        $this->assertSame('Deskripsi baru', $material->description);
        $this->assertNotSame($otherCategory->id, $material->category_id);
    }

    public function test_new_vocabulary_keeps_its_spelling_and_reaches_student_catalog(): void
    {
        $material = $this->wordMaterial();

        $this->actingAs($this->teacherUser())->post(route('teacher.modules.lessons.store', $material), [
            'title' => '  Terima Kasih Banyak ',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertDatabaseHas('lessons', [
            'material_id' => $material->id,
            'title' => 'Terima Kasih Banyak',
            'gesture_label' => 'terima_kasih_banyak',
            'xp_reward' => 10,
        ]);

        $student = User::factory()->student()->has(Student::factory(), 'student')->create();
        $this->actingAs($student)->get('/learn')->assertOk()->assertSee('Terima Kasih Banyak');
    }

    public function test_duplicate_vocabulary_is_rejected_regardless_of_case(): void
    {
        $material = $this->wordMaterial();
        Lesson::factory()->for($material)->create(['title' => 'Maaf']);

        $this->actingAs($this->teacherUser())->post(route('teacher.modules.lessons.store', $material), [
            'title' => 'MAAF',
        ])->assertSessionHasErrors('title');

        $this->assertSame(1, $material->lessons()->count());
    }

    public function test_same_vocabulary_is_allowed_in_the_other_sign_system(): void
    {
        $sibi = $this->wordMaterial('sibi');
        Lesson::factory()->for($sibi)->create(['title' => 'Maaf']);
        $bisindo = Material::factory()->for($sibi->category)->create(['language' => 'bisindo']);

        $this->actingAs($this->teacherUser())->post(route('teacher.modules.lessons.store', $bisindo), [
            'title' => 'Maaf',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $bisindo->lessons()->count());
    }

    public function test_teacher_edits_vocabulary_name_with_only_the_name_field(): void
    {
        $lesson = Lesson::factory()->for($this->wordMaterial())->create([
            'title' => 'Permisi',
            'xp_reward' => 15,
            'difficulty' => 2,
        ]);

        $this->actingAs($this->teacherUser())->put(route('teacher.modules.lessons.update', $lesson), [
            'title' => 'Permisi Dulu',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $lesson->refresh();
        $this->assertSame('Permisi Dulu', $lesson->title);
        $this->assertSame('permisi_dulu', $lesson->gesture_label);
        $this->assertSame(15, (int) $lesson->xp_reward);
        $this->assertSame(2, (int) $lesson->difficulty);
    }

    public function test_vocabulary_used_by_an_assignment_cannot_be_deleted(): void
    {
        $lesson = Lesson::factory()->for($this->wordMaterial())->create();
        Assignment::factory()->for(Teacher::factory())->for($lesson)->create();
        $teacher = $this->teacherUser();

        $this->actingAs($teacher)->delete(route('teacher.modules.lessons.destroy', $lesson))->assertSessionHas('error');
        $this->actingAs($teacher)->delete(route('teacher.modules.destroy', $lesson->material))->assertSessionHas('error');

        $this->assertModelExists($lesson);
        $this->assertModelExists($lesson->material);
    }

    public function test_unused_vocabulary_and_module_can_be_deleted(): void
    {
        $lesson = Lesson::factory()->for($this->wordMaterial())->create();
        $material = $lesson->material;
        $teacher = $this->teacherUser();

        $this->actingAs($teacher)->delete(route('teacher.modules.lessons.destroy', $lesson))->assertSessionHas('status');
        $this->assertModelMissing($lesson);

        $this->actingAs($teacher)->delete(route('teacher.modules.destroy', $material))->assertSessionHas('status');
        $this->assertModelMissing($material);
    }

    public function test_module_page_marks_lessons_used_by_assignments(): void
    {
        $lesson = Lesson::factory()->for($this->wordMaterial())->create();
        Assignment::factory()->for(Teacher::factory())->for($lesson)->create();

        $this->actingAs($this->teacherUser())
            ->get(route('teacher.modules.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Teacher/Modules')
                ->where('categories', fn ($categories) => collect($categories)
                    ->firstWhere('slug', 'word')['materials'][0]['lessons'][0]['used'] === true));
    }

    public function test_module_page_provides_vocabulary_category_when_database_lacks_it(): void
    {
        $teacher = $this->teacherUser();
        $this->assertDatabaseMissing('categories', ['slug' => 'word']);

        $this->actingAs($teacher)
            ->get(route('teacher.modules.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('categories', fn ($categories) => collect($categories)->contains('slug', 'word')));

        $this->actingAs($teacher)->get(route('teacher.modules.index'))->assertOk();
        $this->assertSame(1, Category::where('slug', 'word')->count());
    }

    public function test_students_cannot_manage_modules(): void
    {
        $material = $this->wordMaterial();
        $student = User::factory()->student()->has(Student::factory(), 'student')->create();

        $this->actingAs($student)
            ->post(route('teacher.modules.lessons.store', $material), ['title' => 'Coba'])
            ->assertRedirect();

        $this->assertSame(0, $material->lessons()->count());
    }
}
