<?php

namespace App\Services;

use App\Services\Contracts\GestureRecognitionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service deteksi gesture AI — terintegrasi langsung dengan Live Production
 * FastAPI SIBI / BISINDO Service (https://ai.signtech.tech).
 *
 * Menerima frame citra (base64 data-URL / raw bytes), mengirimkan ke:
 * POST https://ai.signtech.tech/v1/predict/{language}
 * dengan payload binary image/jpeg murni.
 *
 * Tidak menggunakan data dummy / simulasi acak.
 */
class AIRecognitionService implements GestureRecognitionService
{
    private string $aiServiceUrl;
    private int $timeout;

    public function __construct(?string $aiServiceUrl = null, ?int $timeout = null)
    {
        $this->aiServiceUrl = rtrim($aiServiceUrl ?? config('services.ai.url', 'https://ai.signtech.tech'), '/');
        $this->timeout = $timeout ?? (int) config('services.ai.timeout', 15);
    }

    /**
     * Evaluasi satu frame latihan terhadap gesture yang diharapkan melalui Live AI Service.
     *
     * @param  string  $expectedGesture  Target gesture (misal "A", "B", "C", atau label gesture).
     * @param  string|null  $imageData   Data gambar base64 atau data URL (data:image/jpeg;base64,...).
     * @param  string  $language         Sistem bahasa isyarat ('sibi' atau 'bisindo').
     * @param  bool  $mirrored           Apakah kamera user termirror secara horizontal.
     * @return array{recognized: ?string, confidence: int, correct: bool, status: string, detection_status: string, source: string, message: string, hand_count?: int}
     */
    public function recognize(string $expectedGesture, ?string $imageData = null, string $language = 'sibi', bool $mirrored = false): array
    {
        $cleanExpected = strtoupper(trim($expectedGesture));
        $cleanLang = strtolower(trim($language));
        if (!in_array($cleanLang, ['sibi', 'bisindo'], true)) {
            $cleanLang = 'sibi';
        }

        // 1. Validasi keberadaan frame citra
        if (empty($imageData)) {
            return [
                'recognized' => null,
                'confidence' => 0,
                'correct' => false,
                'status' => 'no_image',
                'detection_status' => 'no_image',
                'source' => 'signtech_ai_service',
                'message' => 'Tidak ada citra kamera yang dikirim. Nyalakan kamera dan posisikan tangan.',
            ];
        }

        // 2. Ekstraksi bytes citra dari base64 data-URI
        $frameBytes = $this->extractImageBytes($imageData);
        if ($frameBytes === null || strlen($frameBytes) < 32) {
            return [
                'recognized' => null,
                'confidence' => 0,
                'correct' => false,
                'status' => 'invalid_image',
                'detection_status' => 'invalid_image',
                'source' => 'signtech_ai_service',
                'message' => 'Format citra kamera tidak valid atau gagal didekode.',
            ];
        }

        // 3. Panggil Live AI Service Endpoint
        try {
            $endpoint = "{$this->aiServiceUrl}/v1/predict/{$cleanLang}?include_landmarks=false&mirrored=" . ($mirrored ? 'true' : 'false');

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'image/jpeg',
                    'Accept' => 'application/json',
                ])
                ->withBody($frameBytes, 'image/jpeg')
                ->post($endpoint);

            if (!$response->successful()) {
                Log::warning('SignTech AI Service HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'endpoint' => $endpoint,
                ]);

                return [
                    'recognized' => null,
                    'confidence' => 0,
                    'correct' => false,
                    'status' => 'http_error',
                    'detection_status' => 'http_' . $response->status(),
                    'source' => 'signtech_ai_service',
                    'message' => 'Layanan AI merespons dengan kode ' . $response->status() . '.',
                ];
            }

            $json = $response->json();
            $detectionStatus = $json['detection_status'] ?? $json['status'] ?? 'unknown';
            $label = $json['label'] ?? null;
            $score = $json['score'] ?? null;
            $handCount = (int) ($json['hand_count'] ?? 0);

            // Deteksi tangan nihil
            if ($label === null || $detectionStatus === 'no_hand' || $handCount === 0) {
                return [
                    'recognized' => null,
                    'confidence' => 0,
                    'correct' => false,
                    'status' => $json['status'] ?? 'no_hand',
                    'detection_status' => $detectionStatus,
                    'hand_count' => $handCount,
                    'source' => 'signtech_ai_service',
                    'message' => 'Tangan tidak terdeteksi di kamera. Posisikan tangan di dalam frame dengan pencahayaan memadai.',
                ];
            }

            // Tangan dan gesture terdeteksi
            $detected = strtoupper(trim((string) $label));
            $isCorrect = ($detected === $cleanExpected);
            $confidence = (int) round(((float) $score) * 100);

            $message = $isCorrect
                ? "Isyarat '{$detected}' berhasil divalidasi dengan akurasi {$confidence}%."
                : "Terdeteksi isyarat '{$detected}' (akurasi {$confidence}%), target adalah '{$cleanExpected}'.";

            return [
                'recognized' => $detected,
                'confidence' => $confidence,
                'correct' => $isCorrect,
                'status' => $json['status'] ?? 'success',
                'detection_status' => $detectionStatus,
                'hand_count' => $handCount,
                'source' => 'signtech_ai_service',
                'message' => $message,
            ];
        } catch (Throwable $e) {
            Log::error('SignTech AI Service connection exception', [
                'message' => $e->getMessage(),
                'url' => $this->aiServiceUrl,
            ]);

            return [
                'recognized' => null,
                'confidence' => 0,
                'correct' => false,
                'status' => 'service_unreachable',
                'detection_status' => 'service_unreachable',
                'source' => 'signtech_ai_service',
                'message' => 'Tidak dapat terhubung ke AI Service (' . $this->aiServiceUrl . '). Periksa koneksi jaringan.',
            ];
        }
    }

    /**
     * Ekstrak raw bytes dari base64 data-URI, string base64, atau raw binary.
     */
    private function extractImageBytes(string $data): ?string
    {
        // 1. Jika sudah berupa binary bytes JPEG/PNG murni
        if (str_starts_with($data, "\xFF\xD8\xFF") || str_starts_with($data, "\x89PNG")) {
            return $data;
        }

        // 2. Data URI (contoh: data:image/jpeg;base64,....)
        if (str_contains($data, ';base64,')) {
            $parts = explode(';base64,', $data);
            $base64 = end($parts);
        } else {
            $base64 = $data;
        }

        $decoded = base64_decode(trim($base64), true);
        return $decoded !== false ? $decoded : null;
    }
}
