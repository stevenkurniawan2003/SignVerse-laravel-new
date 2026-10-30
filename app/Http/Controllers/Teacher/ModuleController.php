<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\Material;
use App\Services\PracticeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manajemen modul belajar: materi (kumpulan pelajaran per kategori & sistem
 * isyarat) dan pelajaran di dalamnya. Modul yang sudah dipakai latihan siswa
 * tidak bisa dihapus agar riwayat belajar tidak ikut terhapus.
 */
class ModuleController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->orderBy('order')
            ->with(['materials' => fn ($query) => $query
                ->orderBy('order')
                ->orderBy('id')
                ->withCount('lessons')
                ->with(['lessons' => fn ($lessons) => $lessons
                    ->orderBy('order')
                    ->withCount('practiceSessions')])])
            ->get();

        return Inertia::render('Teacher/Modules', [
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'ai_supported' => in_array($category->slug, PracticeService::AI_SUPPORTED_CATEGORIES, true),
                'materials' => $category->materials->map(fn (Material $material) => [
                    'id' => $material->id,
                    'title' => $material->title,
                    'language' => $material->language,
                    'difficulty' => (int) $material->difficulty,
                    'description' => $material->description,
                    'lessons' => $material->lessons->map(fn (Lesson $lesson) => [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'xp_reward' => (int) $lesson->xp_reward,
                        'difficulty' => (int) $lesson->difficulty,
                        'used' => $lesson->practice_sessions_count > 0,
                    ]),
                ]),
            ]),
        ]);
    }

    public function storeMaterial(Request $request): RedirectResponse
    {
        $validated = $this->validateMaterial($request);

        Material::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['title']),
            'order' => (int) Material::where('category_id', $validated['category_id'])->max('order') + 1,
        ]);

        return back()->with('status', "Materi {$validated['title']} ditambahkan.");
    }

    public function updateMaterial(Request $request, Material $material): RedirectResponse
    {
        $material->update($this->validateMaterial($request));

        return back()->with('status', 'Materi diperbarui.');
    }

    public function destroyMaterial(Material $material): RedirectResponse
    {
        if ($material->lessons()->whereHas('practiceSessions')->exists()) {
            return back()->with('error', 'Materi ini sudah dipakai latihan siswa, jadi tidak bisa dihapus agar riwayat belajar mereka tetap aman.');
        }

        $material->delete();

        return back()->with('status', 'Materi dihapus.');
    }

    public function storeLesson(Request $request, Material $material): RedirectResponse
    {
        $validated = $this->validateLesson($request, $material);

        $material->lessons()->create([
            ...$validated,
            'gesture_label' => strtolower($validated['title']),
            'order' => (int) $material->lessons()->max('order') + 1,
        ]);

        return back()->with('status', "Pelajaran {$validated['title']} ditambahkan.");
    }

    public function updateLesson(Request $request, Lesson $lesson): RedirectResponse
    {
        $validated = $this->validateLesson($request, $lesson->material, $lesson);

        $lesson->update([...$validated, 'gesture_label' => strtolower($validated['title'])]);

        return back()->with('status', 'Pelajaran diperbarui.');
    }

    public function destroyLesson(Lesson $lesson): RedirectResponse
    {
        if ($lesson->practiceSessions()->exists()) {
            return back()->with('error', 'Pelajaran ini sudah dipakai latihan siswa, jadi tidak bisa dihapus agar riwayat belajar mereka tetap aman.');
        }

        $lesson->delete();

        return back()->with('status', 'Pelajaran dihapus.');
    }

    /**
     * @return array{category_id: int, title: string, language: string, difficulty: int, description: ?string}
     */
    private function validateMaterial(Request $request): array
    {
        return $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:200'],
            'language' => ['required', Rule::in(['sibi', 'bisindo'])],
            'difficulty' => ['required', 'integer', 'min:1', 'max:5'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * Pelajaran alfabet harus satu huruf A–Z (label yang dikenali AI) dan
     * tidak boleh dobel dalam sistem isyarat yang sama.
     *
     * @return array{title: string, xp_reward: int, difficulty: int}
     */
    private function validateLesson(Request $request, Material $material, ?Lesson $ignore = null): array
    {
        $request->merge(['title' => strtoupper(trim((string) $request->input('title')))]);
        $isAlphabet = $material->category?->slug === 'alphabet';

        return $request->validate([
            'title' => [
                'required',
                'string',
                'max:50',
                ...($isAlphabet ? ['regex:/^[A-Z]$/'] : []),
                function (string $attribute, string $value, \Closure $fail) use ($material, $ignore) {
                    $duplicate = Lesson::query()
                        ->where('title', $value)
                        ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
                        ->whereHas('material', fn ($query) => $query
                            ->where('language', $material->language)
                            ->where('category_id', $material->category_id))
                        ->exists();

                    if ($duplicate) {
                        $fail("Pelajaran {$value} sudah ada untuk ".strtoupper($material->language).'.');
                    }
                },
            ],
            'xp_reward' => ['required', 'integer', 'min:1', 'max:100'],
            'difficulty' => ['required', 'integer', 'min:1', 'max:3'],
        ], [
            'title.regex' => 'Pelajaran alfabet harus berupa satu huruf A–Z agar bisa dinilai AI.',
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'materi';
        $slug = $base;
        $suffix = 2;
        while (Material::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
