<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Camera, CameraOff, Check, Hand, Info, RefreshCw, RotateCcw } from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    targets: { type: Array, default: () => [] },
    completedLessonIds: { type: Array, default: () => [] },
});

const targetList = ref(Array.isArray(props.targets) && props.targets.length > 0 ? props.targets : []);
const selected = ref(0);
const activeSystem = ref('SIBI');
const scanning = ref(false);
const result = ref(null);
const earnedXp = ref(0);
const detectionConfidence = ref(0);
const detectionRecognized = ref('');
const feedbackMessage = ref('');
const promptError = ref('');

// Initialize completed letters from database props
const completedLetters = ref(
    new Set(
        targetList.value
            .filter((item) => Boolean(item.practiced))
            .map((item) => item.letter)
    )
);

// Webcam management
const videoRef = ref(null);
const canvasRef = ref(null);
const cameraActive = ref(false);
const cameraError = ref('');
let mediaStream = null;

const target = computed(() => targetList.value[selected.value] || {
    letter: 'A',
    name: 'Huruf A',
    hint: 'Posisikan tangan di depan kamera untuk verifikasi bentuk isyarat.',
});

function isPracticed(letter) {
    return completedLetters.value.has(letter);
}

const practicedCount = computed(
    () => targetList.value.filter((item) => isPracticed(item.letter)).length
);

onMounted(async () => {
    if (targetList.value.length === 0) {
        try {
            const res = await fetch('/api/practice/targets');
            if (res.ok) {
                const data = await res.json();
                if (Array.isArray(data.targets) && data.targets.length > 0) {
                    targetList.value = data.targets;
                    data.targets.forEach((item) => {
                        if (item.practiced) {
                            completedLetters.value.add(item.letter);
                        }
                    });
                }
            }
        } catch {
            // handled
        }
    }
});

async function toggleCamera() {
    if (cameraActive.value) {
        stopCamera();
    } else {
        await startCamera();
    }
}

async function startCamera() {
    cameraError.value = '';
    promptError.value = '';
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            cameraError.value = 'Perangkat browser tidak mendukung akses kamera langsung.';
            return;
        }

        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
            audio: false,
        });

        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            videoRef.value.play();
            cameraActive.value = true;
        }
    } catch (err) {
        cameraError.value = 'Izin kamera tidak diberikan atau sedang digunakan aplikasi lain.';
        cameraActive.value = false;
    }
}

function stopCamera() {
    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    cameraActive.value = false;
}

onBeforeUnmount(() => {
    stopCamera();
});

function choose(index) {
    selected.value = index;
    result.value = null;
    earnedXp.value = 0;
    detectionConfidence.value = 0;
    detectionRecognized.value = '';
    feedbackMessage.value = '';
    promptError.value = '';
}

function captureFrame() {
    if (!cameraActive.value || !videoRef.value || !canvasRef.value) {
        return null;
    }

    const video = videoRef.value;
    const canvas = canvasRef.value;
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.85);
}

async function detect() {
    promptError.value = '';

    if (!cameraActive.value) {
        promptError.value = 'Silakan aktifkan kamera terlebih dahulu untuk mendeteksi gerakan tangan Anda melalui Live AI.';
        return;
    }

    if (scanning.value) return;
    scanning.value = true;
    result.value = null;

    const frameData = captureFrame();
    if (!frameData) {
        promptError.value = 'Gagal mengambil frame dari kamera. Pastikan kamera menyala.';
        scanning.value = false;
        return;
    }

    try {
        const res = await fetch('/api/practice/attempt', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                target_letter: target.value.letter,
                system: activeSystem.value.toLowerCase(),
                image: frameData,
                mirrored: true,
            }),
        });

        if (!res.ok) {
            throw new Error(`Server merespons dengan status ${res.status}`);
        }

        const data = await res.json();
        const success = Boolean(data.correct);
        const recognized = data.recognized ? String(data.recognized).toUpperCase() : null;
        const confidence = Number(data.confidence || 0);

        detectionConfidence.value = confidence;
        detectionRecognized.value = recognized || '-';
        feedbackMessage.value = data.message || (success
            ? `Bentuk isyarat ${target.value.letter} sesuai kaidah.`
            : `Bentuk tangan belum sesuai target ${target.value.letter}.`);

        earnedXp.value = Number(data.xp_earned || 0);
        result.value = success ? 'success' : 'retry';

        if (success) {
            completedLetters.value.add(target.value.letter);
            router.reload({ only: ['auth'] });
        }
    } catch (e) {
        result.value = 'retry';
        detectionConfidence.value = 0;
        detectionRecognized.value = '-';
        feedbackMessage.value = 'Gagal menghubungi Live AI Service: ' + e.message;
    } finally {
        scanning.value = false;
    }
}
</script>

<template>
    <AppLayout>
        <Head title="AI Practice Studio" />

        <main class="practice-page practice-focused">
            <canvas ref="canvasRef" style="display: none"></canvas>

            <header class="practice-page-heading">
                <div class="heading-badge">Studio Deteksi Gesture · {{ activeSystem }}</div>
                <h1>Latihan Mandiri AI Practice</h1>
                <p>Terhubung langsung ke Live AI Service. Posisikan tangan di depan kamera untuk verifikasi real-time.</p>
            </header>

            <div v-if="promptError" class="prompt-alert-box" role="alert">
                <Info :size="16" />
                <span>{{ promptError }}</span>
            </div>

            <div class="practice-dashboard-grid">
                <section class="practice-console card">
                    <div class="practice-console-heading">
                        <div>
                            <div class="section-label">Sesi Isyarat</div>
                            <h2>Latihan Bentuk Tangan</h2>
                        </div>
                        <span class="session-system">{{ activeSystem }}</span>
                    </div>

                    <div class="practice-target-select">
                        <div>
                            <b>Pilih Target</b>
                            <small>Alfabet Bahasa Isyarat</small>
                        </div>
                        <div class="target-pills" role="group" aria-label="Pilih target huruf">
                            <button
                                v-for="(item, index) in targetList"
                                :key="item.letter"
                                type="button"
                                class="target-pill"
                                :class="{
                                    selected: selected === index,
                                    practiced: isPracticed(item.letter),
                                }"
                                :aria-pressed="selected === index"
                                @click="choose(index)"
                            >
                                <span class="letter-badge">{{ item.letter }}</span>
                                <Check v-if="isPracticed(item.letter)" :size="12" />
                            </button>
                        </div>
                    </div>

                    <div class="practice-studio" :class="{ 'is-scanning': scanning }">
                        <div class="studio-topline">
                            <span class="studio-status-indicator">
                                <i class="studio-dot" :class="{ active: cameraActive }"></i>
                                {{ cameraActive ? 'Kamera Aktif · Siap Deteksi AI' : 'Kamera Nonaktif' }}
                            </span>
                            <button class="camera-toggle-btn" type="button" @click="toggleCamera">
                                <component :is="cameraActive ? CameraOff : Camera" :size="13" />
                                <span>{{ cameraActive ? 'Matikan Kamera' : 'Buka Kamera' }}</span>
                            </button>
                        </div>

                        <div class="studio-viewport">
                            <video
                                v-show="cameraActive"
                                ref="videoRef"
                                class="webcam-video"
                                autoplay
                                playsinline
                                muted
                            ></video>

                            <div v-show="!cameraActive" class="hand-guide-box">
                                <div class="guide-circle">
                                    <Hand :size="54" class="guide-hand-icon" />
                                    <span class="guide-letter">{{ target.letter }}</span>
                                </div>
                                <p class="guide-text">
                                    Buka kamera untuk evaluasi langsung dengan AI Service (https://ai.signtech.tech).
                                </p>
                            </div>

                            <div class="target-overlay-badge">
                                <small>Target</small>
                                <b>{{ target.letter }}</b>
                            </div>

                            <div v-if="scanning" class="scanning-indicator-bar">
                                <RefreshCw :size="14" class="spin-icon" />
                                <span>Live AI sedang memeriksa formasi tangan...</span>
                            </div>
                        </div>

                        <p class="studio-hint">{{ target.hint }}</p>
                    </div>

                    <div v-if="result" class="studio-result" :class="{ retry: result === 'retry' }" role="status">
                        <div class="result-title">
                            <span class="result-icon-badge">
                                <Check v-if="result === 'success'" :size="16" />
                                <RotateCcw v-else :size="16" />
                            </span>
                            <div>
                                <b>{{ result === 'success' ? `Gesture '${detectionRecognized}' Berhasil Divalidasi` : 'Perlu Penyesuaian Gerakan' }}</b>
                                <small>{{ feedbackMessage }}</small>
                            </div>
                            <strong v-if="result === 'success' && earnedXp > 0">+{{ earnedXp }} XP</strong>
                        </div>
                    </div>

                    <div class="practice-control-row">
                        <button v-if="result === 'retry'" class="secondary-button" @click="detect">
                            <RotateCcw :size="14" /> Coba Ulangi
                        </button>
                        <button class="primary-button" :disabled="scanning" @click="detect">
                            <Camera :size="15" />
                            <span>{{ scanning ? 'Menganalisis...' : 'Periksa Isyarat Saya' }}</span>
                        </button>
                    </div>
                </section>

                <aside class="practice-progress-card card">
                    <div class="section-label">Kemajuan Sesi</div>
                    <div class="practice-progress-total">
                        <b>{{ practicedCount }}</b>
                        <span> dari {{ targetList.length }} tuntas</span>
                    </div>

                    <div class="progress-track">
                        <i :style="{ width: `${(practicedCount / (targetList.length || 1)) * 100}%` }"></i>
                    </div>

                    <div class="practice-letter-list">
                        <div
                            v-for="item in targetList"
                            :key="item.letter"
                            :class="{ complete: isPracticed(item.letter) }"
                        >
                            <span>{{ item.name }}</span>
                            <small>
                                {{ isPracticed(item.letter) ? 'Tuntas' : 'Belum selesai' }}
                            </small>
                            <Check v-if="isPracticed(item.letter)" :size="14" />
                        </div>
                    </div>
                </aside>
            </div>
        </main>
    </AppLayout>
</template>

<style scoped>
.practice-page-heading {
    margin-bottom: 20px;
}

.heading-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    color: #1e7253;
    background: #eefaf4;
    padding: 4px 10px;
    border-radius: 8px;
    margin-bottom: 8px;
}

.prompt-alert-box {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff8e6;
    border: 1px solid #C29B40;
    color: #8c6a1e;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 16px;
}

.section-label {
    font-size: 11px;
    font-weight: 700;
    color: #52685d;
    margin-bottom: 4px;
}

.letter-badge {
    font-weight: 800;
    font-size: 13px;
}

.practice-studio {
    border-radius: 16px;
    background: #14221d;
    color: #ffffff;
    padding: 16px;
    margin-top: 16px;
}

.studio-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 11px;
    font-weight: 600;
    color: #ade6cc;
    margin-bottom: 12px;
}

.studio-status-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
}

.studio-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #52685d;
}

.studio-dot.active {
    background: #34B182;
    box-shadow: 0 0 8px #34B182;
}

.camera-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
    padding: 5px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 700;
    transition: 0.18s;
}

.camera-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

.studio-viewport {
    height: 260px;
    border-radius: 12px;
    background: #0d1714;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.webcam-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1);
}

.hand-guide-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 20px;
}

.guide-circle {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    background: rgba(52, 177, 130, 0.12);
    border: 2px solid rgba(52, 177, 130, 0.4);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}

.guide-hand-icon {
    color: #34B182;
}

.guide-letter {
    font-size: 18px;
    font-weight: 800;
    color: #ffffff;
    margin-top: 2px;
}

.guide-text {
    font-size: 11px;
    color: #97d2a3;
    max-width: 280px;
    line-height: 1.5;
    margin: 0;
}

.target-overlay-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(20, 34, 29, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    padding: 6px 12px;
    text-align: right;
    backdrop-filter: blur(4px);
}

.target-overlay-badge small {
    display: block;
    font-size: 9px;
    font-weight: 700;
    color: #ade6cc;
}

.target-overlay-badge b {
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
}

.scanning-indicator-bar {
    position: absolute;
    bottom: 12px;
    left: 12px;
    right: 12px;
    background: rgba(20, 34, 29, 0.9);
    border: 1px solid rgba(52, 177, 130, 0.5);
    border-radius: 8px;
    padding: 8px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 600;
    color: #7cd3ae;
    backdrop-filter: blur(4px);
}

.spin-icon {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.studio-hint {
    margin: 12px 0 0;
    font-size: 11px;
    color: #ade6cc;
    line-height: 1.5;
}

.target-pill.selected {
    border-color: #34B182 !important;
    background: #eefaf4 !important;
    color: #1e7253 !important;
}

.result-icon-badge {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #d5f3e5;
    color: #1e7253;
    display: grid;
    place-items: center;
}

.studio-result.retry .result-icon-badge {
    background: #fdf9ef;
    color: #a58230;
}
</style>
