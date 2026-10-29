<?php

namespace Tests\Feature\Student;

use App\Models\Category;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\PracticeSession;
use App\Models\Student;
use App\Models\User;
use App\Services\AiGestureRecognitionService;
use App\Services\Contracts\GestureRecognitionService;
use App\Services\Exceptions\GestureRecognitionUnavailableException;
use App\Services\MockGestureRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integrasi SignTech AI API: frame data-URI dikirim sebagai byte JPEG,
 * hasil prediksi dipetakan ke kontrak recognizer.
 */
class AiGestureRecognitionTest extends TestCase
{
    use RefreshDatabase;

    private const FRAME = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQ==';

    private function recognizer(): AiGestureRecognitionService
    {
        return new AiGestureRecognitionService(
            baseUrl: 'https://ai.test',
            timeout: 5,
            fallback: new MockGestureRecognitionService,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function prediction(array $overrides = []): array
    {
        return array_merge([
            'language' => 'sibi',
            'status' => 'predicted',
            'detection_status' => 'ok',
            'label' => 'A',
            'score' => 0.94,
            'hand_count' => 1,
        ], $overrides);
    }

    public function test_sends_raw_image_bytes_to_language_endpoint(): void
    {
        Http::fake(['ai.test/v1/predict/sibi' => Http::response($this->prediction())]);

        $result = $this->recognizer()->recognize('a', self::FRAME, 'sibi');

        $this->assertSame(['recognized' => 'a', 'confidence' => 94, 'correct' => true], $result);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->hasHeader('Content-Type', 'image/jpeg')
            && $request->body() === base64_decode('/9j/4AAQSkZJRgABAQ=='));
    }

    public function test_wrong_label_is_marked_incorrect(): void
    {
        Http::fake(['ai.test/*' => Http::response($this->prediction(['label' => 'B', 'score' => 0.6]))]);

        $result = $this->recognizer()->recognize('a', self::FRAME, 'bisindo');

        $this->assertSame(['recognized' => 'b', 'confidence' => 60, 'correct' => false], $result);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v1/predict/bisindo'));
    }

    public function test_no_hand_is_rejected_without_result(): void
    {
        Http::fake(['ai.test/*' => Http::response($this->prediction(['status' => 'no_hand', 'label' => null, 'score' => null]))]);

        $this->expectException(GestureRecognitionUnavailableException::class);
        $this->expectExceptionMessage('Tangan tidak terdeteksi');

        $this->recognizer()->recognize('a', self::FRAME, 'sibi');
    }

    public function test_busy_service_is_rejected(): void
    {
        Http::fake(['ai.test/*' => Http::response(['detail' => 'busy'], 503)]);

        $this->expectException(GestureRecognitionUnavailableException::class);
        $this->expectExceptionMessage('sibuk');

        $this->recognizer()->recognize('a', self::FRAME, 'sibi');
    }

    public function test_missing_frame_is_rejected_without_calling_api(): void
    {
        Http::fake();

        try {
            $this->recognizer()->recognize('a', null, 'sibi');
            $this->fail('Exception expected.');
        } catch (GestureRecognitionUnavailableException) {
            Http::assertNothingSent();
        }
    }

    public function test_non_alphabet_gesture_uses_fallback(): void
    {
        Http::fake();

        $result = $this->recognizer()->recognize('halo', null, 'bisindo');

        $this->assertTrue($result['correct']);
        Http::assertNothingSent();
    }

    public function test_attempt_endpoint_returns_422_and_skips_attempt_when_unreadable(): void
    {
        config(['services.signtech_ai.driver' => 'ai', 'services.signtech_ai.url' => 'https://ai.test']);
        $this->app->forgetInstance(GestureRecognitionService::class);
        Http::fake(['ai.test/*' => Http::response($this->prediction(['status' => 'no_hand', 'label' => null, 'score' => null]))]);

        $user = User::factory()->student()->has(Student::factory(), 'student')->create();
        $lesson = Lesson::factory()
            ->for(Material::factory()->for(Category::factory()))
            ->create(['gesture_label' => 'a']);
        $session = PracticeSession::factory()->inProgress()->create([
            'student_id' => $user->student->id,
            'lesson_id' => $lesson->id,
            'language' => 'sibi',
            'attempts' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('student.practice.attempt', $session), ['image' => self::FRAME])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Tangan tidak terdeteksi'));

        $this->assertSame(0, $session->refresh()->attempts);
    }
}
