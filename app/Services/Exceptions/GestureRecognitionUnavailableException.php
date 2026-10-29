<?php

namespace App\Services\Exceptions;

use RuntimeException;

/**
 * Frame tidak dapat dinilai (service AI sibuk/timeout/galat, gambar
 * ditolak, atau tangan tidak terdeteksi). Attempt tidak dicatat agar skor siswa tidak
 * dirugikan oleh gangguan teknis.
 */
class GestureRecognitionUnavailableException extends RuntimeException {}
