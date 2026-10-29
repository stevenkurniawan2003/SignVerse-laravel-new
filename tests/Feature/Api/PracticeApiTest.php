<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_practice_targets_returns_200(): void
    {
        $user = User::factory()->student()->has(Student::factory(), 'student')->create();
        $category = Category::factory()->create();
        $material = Material::factory()->for($category)->create();
        Lesson::factory()->for($material)->create(['title' => 'A']);

        $response = $this->actingAs($user)->getJson('/api/practice/targets');
        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'targets']);
    }

    public function test_api_practice_attempt_returns_200(): void
    {
        $user = User::factory()->student()->has(Student::factory(), 'student')->create();
        $category = Category::factory()->create();
        $material = Material::factory()->for($category)->create();
        Lesson::factory()->for($material)->create(['title' => 'A']);

        $im = imagecreatetruecolor(50, 50);
        ob_start();
        imagejpeg($im);
        $jpegBytes = ob_get_clean();
        imagedestroy($im);
        $base64 = 'data:image/jpeg;base64,' . base64_encode($jpegBytes);

        $response = $this->actingAs($user)->postJson('/api/practice/attempt', [
            'target_letter' => 'A',
            'system' => 'sibi',
            'image' => $base64,
            'mirrored' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'correct',
            'recognized',
            'confidence',
            'message',
        ]);
    }
}
