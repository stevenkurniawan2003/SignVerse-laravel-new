<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Lesson;
use App\Models\Material;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konten seeder: alfabet A-Z lengkap untuk SIBI dan BISINDO, plus angka;
 * tanpa kategori kata/frasa.
 */
class ContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_alphabet_and_number_categories_only(): void
    {
        $this->seed(ContentSeeder::class);

        $this->assertSame(['alphabet', 'number'], Category::orderBy('order')->pluck('slug')->all());
    }

    public function test_each_language_covers_full_alphabet(): void
    {
        $this->seed(ContentSeeder::class);

        $expectedLabels = array_map('strtolower', range('A', 'Z'));

        foreach ([Material::LANGUAGE_SIBI, Material::LANGUAGE_BISINDO] as $language) {
            $labels = Lesson::query()
                ->whereHas('material', fn ($query) => $query
                    ->where('language', $language)
                    ->whereHas('category', fn ($q) => $q->where('slug', 'alphabet')))
                ->orderBy('gesture_label')
                ->pluck('gesture_label')
                ->all();

            $this->assertSame($expectedLabels, $labels, "Lesson {$language} harus A-Z.");
        }
    }

    public function test_number_material_covers_one_to_ten(): void
    {
        $this->seed(ContentSeeder::class);

        $labels = Lesson::query()
            ->whereHas('material.category', fn ($query) => $query->where('slug', 'number'))
            ->orderBy('order')
            ->pluck('gesture_label')
            ->all();

        $this->assertSame(array_map('strval', range(1, 10)), $labels);
    }
}
