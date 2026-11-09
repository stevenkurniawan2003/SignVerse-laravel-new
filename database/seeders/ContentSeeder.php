<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Material;
use App\Services\AlphabetLessonService;
use Illuminate\Database\Seeder;

/**
 * Konten pembelajaran — setara data demo project lama (db.py):
 * kategori Alphabet/Number/Word/Phrase → materi → lesson per gesture.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Alphabet',
                'icon' => 'alphabet',
                'description' => 'Isyarat huruf A-Z (SIBI & BISINDO).',
                'materials' => [],
            ],
            [
                'name' => 'Number',
                'icon' => 'number',
                'description' => 'Angka 1-10 dalam bahasa isyarat.',
                'materials' => [
                    [
                        'title' => 'Angka 1-10 (BISINDO)',
                        'language' => Material::LANGUAGE_BISINDO,
                        'difficulty' => 1,
                        'lessons' => ['1', '2', '3', '4', '5'],
                    ],
                ],
            ],
            [
                'name' => 'Word',
                'icon' => 'word',
                'description' => 'Kosakata sehari-hari.',
                'materials' => [
                    [
                        'title' => 'Sapaan Dasar (BISINDO)',
                        'language' => Material::LANGUAGE_BISINDO,
                        'difficulty' => 2,
                        'lessons' => ['Terima kasih', 'Maaf', 'Permisi'],
                    ],
                ],
            ],
            [
                'name' => 'Phrase',
                'icon' => 'phrase',
                'description' => 'Frasa percakapan sederhana.',
                'materials' => [
                    [
                        'title' => 'Percakapan Sederhana (BISINDO)',
                        'language' => Material::LANGUAGE_BISINDO,
                        'difficulty' => 3,
                        'lessons' => ['Apa kabar', 'Sampai jumpa'],
                    ],
                ],
            ],
        ];

        foreach ($categories as $index => $data) {
            $slug = str($data['name'])->slug()->value();
            $category = Category::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'icon' => $data['icon'],
                    'description' => $data['description'],
                    'order' => $index,
                ]
            );

            foreach ($data['materials'] as $mIndex => $materialData) {
                $matSlug = str($materialData['title'])->slug()->value();
                $material = $category->materials()->firstOrCreate(
                    ['slug' => $matSlug],
                    [
                        'title' => $materialData['title'],
                        'language' => $materialData['language'],
                        'difficulty' => $materialData['difficulty'],
                        'order' => $mIndex,
                    ]
                );

                foreach ($materialData['lessons'] as $lIndex => $lessonTitle) {
                    $material->lessons()->firstOrCreate(
                        ['title' => $lessonTitle],
                        [
                            'gesture_label' => str($lessonTitle)->lower()->snake()->value(),
                            'xp_reward' => 10,
                            'difficulty' => $materialData['difficulty'],
                            'order' => $lIndex,
                        ]
                    );
                }
            }
        }

        AlphabetLessonService::ensureComplete();
    }
}
