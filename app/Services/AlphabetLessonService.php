<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Lesson;
use App\Models\Material;
use Illuminate\Support\Str;

/**
 * Katalog huruf A–Z untuk AI Practice: setiap sistem isyarat (SIBI & BISINDO)
 * selalu punya satu pelajaran untuk tiap huruf, tidak bergantung pada tugas
 * guru maupun perubahan di menu modul.
 */
class AlphabetLessonService
{
    public const CATEGORY_SLUG = 'alphabet';

    /**
     * Pembagian materi huruf.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const LETTER_GROUPS = [
        'A-M' => ['A', 'M'],
        'N-Z' => ['N', 'Z'],
    ];

    /**
     * Lengkapi huruf yang belum ada. Aman dipanggil berulang: huruf yang sudah
     * ada beserta riwayat latihannya tidak diubah.
     *
     * @return int jumlah pelajaran yang baru dibuat
     */
    public static function ensureComplete(): int
    {
        $existingLetters = Lesson::query()
            ->join('materials', 'materials.id', '=', 'lessons.material_id')
            ->join('categories', 'categories.id', '=', 'materials.category_id')
            ->where('categories.slug', self::CATEGORY_SLUG)
            ->get(['lessons.title', 'materials.language'])
            ->map(fn (Lesson $lesson) => $lesson->getAttribute('language').'|'.strtoupper(trim($lesson->title)))
            ->flip();

        $created = 0;
        $category = null;

        foreach ([Material::LANGUAGE_SIBI, Material::LANGUAGE_BISINDO] as $language) {
            foreach (self::LETTER_GROUPS as $groupName => [$firstLetter, $lastLetter]) {
                $letters = range($firstLetter, $lastLetter);
                $missingLetters = array_filter($letters, fn (string $letter) => ! $existingLetters->has("{$language}|{$letter}"));

                if ($missingLetters === []) {
                    continue;
                }

                $category ??= Category::firstOrCreate(
                    ['slug' => self::CATEGORY_SLUG],
                    ['name' => 'Alphabet', 'icon' => 'alphabet', 'description' => 'Isyarat huruf A-Z (SIBI & BISINDO).', 'order' => 0],
                );
                $material = self::materialFor($category, $language, $groupName);

                foreach ($missingLetters as $position => $letter) {
                    $material->lessons()->create([
                        'title' => $letter,
                        'gesture_label' => strtolower($letter),
                        'xp_reward' => 10,
                        'difficulty' => 1,
                        'order' => $position,
                    ]);
                    $created++;
                }
            }
        }

        return $created;
    }

    private static function materialFor(Category $category, string $language, string $groupName): Material
    {
        $title = "Huruf {$groupName} (".strtoupper($language).')';
        $languageOffset = $language === Material::LANGUAGE_SIBI ? 0 : count(self::LETTER_GROUPS);
        $groupOffset = (int) array_search($groupName, array_keys(self::LETTER_GROUPS), true);

        return Material::firstOrCreate(
            ['category_id' => $category->id, 'language' => $language, 'title' => $title],
            ['slug' => self::uniqueSlug($title), 'difficulty' => 1, 'order' => $languageOffset + $groupOffset],
        );
    }

    private static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (Material::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
