<?php

namespace App\Services;

use App\Services\Contracts\GestureRecognitionService;
use App\Services\Exceptions\GestureRecognitionUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Recognizer nyata: memanggil SignTech AI API (POST /v1/predict/{language}).
 *
 * Frame dari browser (data-URI JPEG/PNG) di-decode lalu dikirim sebagai
 * byte mentah — API tidak menerima JSON/base64/multipart.
 *
 * Model hanya mengenali pose alfabet statis A-Z; gesture lain (angka,
 * kata, frasa) diteruskan ke recognizer fallback.
 */
class AiGestureRecognitionService implements GestureRecognitionService
{
    public const MAX_FRAME_BYTES = 2_000_000;

    /**
     * Pesan umpan balik untuk status ekstraksi yang tidak valid.
     *
     * @var array<string, string>
     */
    private const STATUS_MESSAGES = [
        'no_hand' => 'Tangan tidak terdeteksi — pastikan tanganmu terlihat jelas di kamera.',
        'unexpected_extra_hand' => 'Terdeteksi tangan berlebih — pastikan hanya kamu yang ada di kamera.',
        'invalid_geometry' => 'Pose tangan tidak terbaca — coba posisikan ulang tanganmu.',
    ];

    public function __construct(
        private string $baseUrl,
        private int $timeout,
        private GestureRecognitionService $fallback,
    ) {}

    public function recognize(string $expectedGesture, ?string $imageData = null, string $language = 'bisindo'): array
    {
        if (! self::supportsGesture($expectedGesture)) {
            return $this->fallback->recognize($expectedGesture, $imageData, $language);
        }

        [$bytes, $mimeType] = $this->decodeImage($imageData);

        $prediction = $this->predict($language, $bytes, $mimeType);

        // Tangan tidak terbaca bukan isyarat salah — tolak frame tanpa mencatat attempt.
        if ($prediction['status'] !== 'predicted' || $prediction['label'] === null) {
            throw new GestureRecognitionUnavailableException(
                self::STATUS_MESSAGES[$prediction['status']] ?? 'Isyarat tidak terbaca — coba lagi.',
            );
        }

        $recognized = strtolower($prediction['label']);

        return [
            'recognized' => $recognized,
            'confidence' => (int) round(($prediction['score'] ?? 0) * 100),
            'correct' => $recognized === strtolower($expectedGesture),
        ];
    }

    /**
     * Model AI saat ini hanya punya label huruf tunggal A-Z.
     */
    public static function supportsGesture(string $expectedGesture): bool
    {
        return (bool) preg_match('/^[a-z]$/i', $expectedGesture);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function decodeImage(?string $imageData): array
    {
        if ($imageData === null || ! preg_match('#^data:(image/(?:jpeg|png));base64,(.+)$#s', $imageData, $matches)) {
            throw new GestureRecognitionUnavailableException('Frame kamera tidak tersedia — aktifkan kamera terlebih dahulu.');
        }

        $bytes = base64_decode($matches[2], true);

        if ($bytes === false || $bytes === '') {
            throw new GestureRecognitionUnavailableException('Frame kamera tidak valid — coba lagi.');
        }

        if (strlen($bytes) > self::MAX_FRAME_BYTES) {
            throw new GestureRecognitionUnavailableException('Ukuran frame kamera terlalu besar.');
        }

        return [$bytes, $matches[1]];
    }

    /**
     * @return array{status: string, label: ?string, score: ?float}
     */
    private function predict(string $language, string $bytes, string $mimeType): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->acceptJson()
                ->withBody($bytes, $mimeType)
                ->post('/v1/predict/'.$language);
        } catch (ConnectionException $exception) {
            Log::warning('SignTech AI tidak dapat dihubungi.', ['error' => $exception->getMessage()]);

            throw new GestureRecognitionUnavailableException('Layanan AI tidak dapat dihubungi — coba beberapa saat lagi.', previous: $exception);
        }

        if ($response->failed() || ! is_array($response->json())) {
            $this->logFailure($response);

            throw new GestureRecognitionUnavailableException(match ($response->status()) {
                503 => 'Layanan AI sedang sibuk — coba lagi sebentar.',
                413, 422 => 'Gambar kamera tidak dapat diproses — coba lagi.',
                default => 'Layanan AI sedang bermasalah — coba beberapa saat lagi.',
            });
        }

        return [
            'status' => (string) $response->json('status'),
            'label' => $response->json('label'),
            'score' => $response->json('score'),
        ];
    }

    private function logFailure(Response $response): void
    {
        Log::warning('SignTech AI mengembalikan galat.', [
            'status' => $response->status(),
            'request_id' => $response->header('X-Request-ID'),
            'body' => mb_substr($response->body(), 0, 500),
        ]);
    }
}
