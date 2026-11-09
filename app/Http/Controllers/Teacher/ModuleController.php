<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\Material;
use App\Services\AlphabetLessonService;
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
    private const ALPHABET_LOCKED_MESSAGE = 'Huruf A–Z selalu tersedia di Latihan Mandiri untuk SIBI dan BISINDO, jadi tidak bisa dihapus, diganti, atau dipindahkan. XP dan tingkat kesulitannya tetap bisa diubah.';

    private const SPELLING_CATEGORIES = ['word', 'phrase'];

    public function index(): Response
    {
        AlphabetLessonService::ensureComplete();
        $this->ensureVocabularyCategory();

        $categories = Category::query()
            ->orderBy('order')
            ->with(['materials' => fn ($query) => $query
                ->orderBy('order')
                ->orderBy('id')
                ->withCount('lessons')
                ->with(['lessons' => fn ($lessons) => $lessons
                    ->orderBy('order')
                    ->orderBy('id')
                    ->withCount(['practiceSessions', 'assignments'])])])
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
                        'used' => $lesson->practice_sessions_count > 0 || $lesson->assignments_count > 0,
                    ]),
                ]),
            ]),
        ]);
    }

    public function storeMaterial(Request $request): RedirectResponse
    {
        $validated = [
            ...$this->validateMaterial($request),
            ...$request->validate([
                'category_id' => [
                    'required',
                    'exists:categories,id',
                    function (string $attribute, mixed $value, \Closure $fail) {
                        if (Category::whereKey($value)->where('slug', AlphabetLessonService::CATEGORY_SLUG)->exists()) {
                            $fail('Huruf A–Z sudah lengkap. Pilih kategori lain untuk modul baru.');
                        }
                    },
                ],
            ]),
        ];

        Material::create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['title']),
            'order' => (int) Material::where('category_id', $validated['category_id'])->max('order') + 1,
        ]);

        return back()->with('status', "Modul {$validated['title']} ditambahkan.");
    }

    public function updateMaterial(Request $request, Material $material): RedirectResponse
    {
        $validated = $this->validateMaterial($request);

        if ($this->holdsAlphabetLessons($material) && $validated['language'] !== $material->language) {
            return back()->with('error', self::ALPHABET_LOCKED_MESSAGE);
        }

        $material->update($validated);

        return back()->with('status', 'Modul diperbarui.');
    }

    public function destroyMaterial(Material $material): RedirectResponse
    {
        if ($this->holdsAlphabetLessons($material)) {
            return back()->with('error', self::ALPHABET_LOCKED_MESSAGE);
        }

        if ($material->lessons()->where(fn ($query) => $query->whereHas('practiceSessions')->orWhereHas('assignments'))->exists()) {
            return back()->with('error', 'Modul ini sudah dipakai latihan atau tugas siswa, jadi tidak bisa dihapus agar riwayat belajar mereka tetap aman.');
        }

        $material->delete();

        return back()->with('status', 'Modul dihapus.');
    }

    public function storeLesson(Request $request, Material $material): RedirectResponse
    {
        $validated = $this->validateLesson($request, $material);

        $material->lessons()->create([
            ...$validated,
            'gesture_label' => $this->gestureLabel($validated['title']),
            'order' => (int) $material->lessons()->max('order') + 1,
        ]);

        return back()->with('status', "{$validated['title']} ditambahkan.");
    }

    public function updateLesson(Request $request, Lesson $lesson): RedirectResponse
    {
        $validated = $this->validateLesson($request, $lesson->material, $lesson);

        if ($this->holdsAlphabetLessons($lesson->material) && $validated['title'] !== strtoupper($lesson->title)) {
            return back()->with('error', self::ALPHABET_LOCKED_MESSAGE);
        }

        $lesson->update([...$validated, 'gesture_label' => $this->gestureLabel($validated['title'])]);

        return back()->with('status', "{$validated['title']} diperbarui.");
    }

    public function destroyLesson(Lesson $lesson): RedirectResponse
    {
        if ($this->holdsAlphabetLessons($lesson->material)) {
            return back()->with('error', self::ALPHABET_LOCKED_MESSAGE);
        }

        if ($lesson->practiceSessions()->exists() || $lesson->assignments()->exists()) {
            return back()->with('error', "{$lesson->title} sudah dipakai latihan atau tugas siswa, jadi tidak bisa dihapus agar riwayat belajar mereka tetap aman.");
        }

        $lesson->delete();

        return back()->with('status', "{$lesson->title} dihapus.");
    }

    /**
     * Kategori hanya dipilih saat modul dibuat, jadi tidak ikut divalidasi di sini.
     *
     * @return array{title: string, language: string, difficulty?: int, description?: ?string}
     */
    private function validateMaterial(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'language' => ['required', Rule::in(['sibi', 'bisindo'])],
            'difficulty' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * Pelajaran alfabet harus satu huruf A–Z (label yang dikenali AI). Judul
     * kata dan frasa hanya boleh berisi huruf ASCII dan spasi agar setiap huruf
     * bisa dieja. Judul tidak boleh dobel dalam kategori dan sistem isyarat
     * yang sama.
     *
     * @return array{title: string, xp_reward?: int, difficulty?: int}
     */
    private function validateLesson(Request $request, Material $material, ?Lesson $ignore = null): array
    {
        $isAlphabet = $material->category?->slug === AlphabetLessonService::CATEGORY_SLUG;
        $isSpelling = in_array($material->category?->slug, self::SPELLING_CATEGORIES, true);
        $title = trim((string) $request->input('title'));
        $request->merge(['title' => $isAlphabet ? strtoupper($title) : $title]);

        return $request->validate([
            'title' => [
                'required',
                'string',
                'max:100',
                ...($isAlphabet ? ['regex:/^[A-Z]$/'] : []),
                ...($isSpelling ? ['regex:/\A[A-Za-z\s]+\z/'] : []),
                function (string $attribute, string $value, \Closure $fail) use ($material, $ignore) {
                    $duplicate = Lesson::query()
                        ->whereRaw('LOWER(title) = ?', [mb_strtolower($value)])
                        ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
                        ->whereHas('material', fn ($query) => $query
                            ->where('language', $material->language)
                            ->where('category_id', $material->category_id))
                        ->exists();

                    if ($duplicate) {
                        $fail("{$value} sudah ada untuk ".strtoupper($material->language).'.');
                    }
                },
            ],
            'xp_reward' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'difficulty' => ['sometimes', 'integer', 'min:1', 'max:3'],
        ], [
            'title.regex' => $isAlphabet
                ? 'Pelajaran alfabet harus berupa satu huruf A–Z agar bisa diperiksa secara otomatis.'
                : 'Judul kata atau frasa hanya boleh berisi huruf A–Z dan spasi agar bisa dieja.',
        ]);
    }

    /**
     * Database yang tidak dibuat dari seeder lengkap bisa belum punya kategori
     * kosakata, padahal guru perlu memilihnya saat membuat modul.
     */
    private function ensureVocabularyCategory(): void
    {
        Category::firstOrCreate(
            ['slug' => 'word'],
            ['name' => 'Word', 'icon' => 'word', 'description' => 'Kosakata sehari-hari.', 'order' => 2],
        );
    }

    private function gestureLabel(string $title): string
    {
        return Str::of($title)->lower()->snake()->value();
    }

    private function holdsAlphabetLessons(Material $material): bool
    {
        return $material->category?->slug === AlphabetLessonService::CATEGORY_SLUG
            && $material->lessons()->exists();
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
