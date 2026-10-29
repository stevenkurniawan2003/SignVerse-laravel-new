<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Camera,
    CameraOff,
    Check,
    Hand,
    Info,
    RefreshCw,
    RotateCcw,
    Timer,
    X,
} from '@lucide/vue';
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

// Timer & Countdown system for SLB learners
const selectedTimer = ref(3); // Options: 0s, 3s, 5s
const countdown = ref(0);
let countdownInterval = null;

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
            const res = await fetch('/api/practice/targets', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
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
    cancelCountdown();
    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    cameraActive.value = false;
}

function cancelCountdown() {
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
    countdown.value = 0;
}

onBeforeUnmount(() => {
    stopCamera();
});

function choose(index) {
    cancelCountdown();
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

function startDetection() {
    promptError.value = '';

    if (!cameraActive.value) {
        promptError.value = 'Buka kamera terlebih dahulu agar Anda dapat mempraktikkan gerakan isyarat.';
        return;
    }

    if (scanning.value || countdown.value > 0) return;

    if (selectedTimer.value === 0) {
        performDetection();
        return;
    }

    countdown.value = selectedTimer.value;
    countdownInterval = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            clearInterval(countdownInterval);
            countdownInterval = null;
            performDetection();
        }
    }, 1000);
}

async function performDetection() {
    promptError.value = '';

    if (!cameraActive.value) {
        promptError.value = 'Kamera tidak aktif. Nyalakan kamera untuk mendeteksi isyarat tangan Anda.';
        return;
    }

    if (scanning.value) return;
    scanning.value = true;
    result.value = null;

    const frameData = captureFrame();
    if (!frameData) {
        promptError.value = 'Gagal mengambil gambar dari kamera. Pastikan kamera menyala dengan baik.';
        scanning.value = false;
        return;
    }

    try {
        const res = await fetch('/api/practice/attempt', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken,
            },
            body: JSON.stringify({
                target_letter: target.value.letter,
                system: activeSystem.value.toLowerCase(),
                image: frameData,
                mirrored: true,
            }),
        });

        if (!res.ok) {
            const errData = await res.json().catch(() => null);
            throw new Error(errData?.message || `Respons server status ${res.status}`);
        }

        const data = await res.json();
        const success = Boolean(data.correct);
        const recognized = data.recognized ? String(data.recognized).toUpperCase() : null;
        const confidence = Number(data.confidence || 0);

        detectionConfidence.value = confidence;
        detectionRecognized.value = recognized || '-';
        feedbackMessage.value = data.message || (success
            ? `Bentuk isyarat ${target.value.letter} sesuai kaidah bahasa isyarat.`
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
        feedbackMessage.value = 'Koneksi ke sistem deteksi terganggu: ' + e.message;
    } finally {
        scanning.value = false;
    }
}
</script>

<template>
    <AppLayout>
        <Head title="AI Practice Studio" />

        <div class="st-page practice-page practice-focused">
            <canvas ref="canvasRef" style="display: none"></canvas>

            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <span class="st-badge st-badge--primary">AI Practice Studio · {{ activeSystem }}</span>
                    <h1>Latihan Mandiri Bahasa Isyarat</h1>
                    <p>Posisikan tangan di depan kamera dengan nyaman, lalu gunakan timer hitung mundur untuk menyiapkan bentuk isyarat.</p>
                </div>
                <div class="st-page-header-aside st-summary-box">
                    <span class="st-icon st-icon--primary"><Hand :size="22" /></span>
                    <div>
                        <b>{{ practicedCount }} / {{ targetList.length }}</b>
                        <small>target tuntas</small>
                    </div>
                </div>
            </header>

            <div v-if="promptError" class="st-alert st-alert--warning" role="alert">
                <Info :size="18" />
                <span>{{ promptError }}</span>
            </div>
            <div v-if="cameraError" class="st-alert st-alert--danger" role="alert">
                <CameraOff :size="18" />
                <span>{{ cameraError }}</span>
            </div>

            <div class="practice-dashboard-grid">
                <section class="st-panel practice-console">
                    <div class="st-panel-head">
                        <div>
                            <span class="st-eyebrow">Sesi Isyarat</span>
                            <h2 class="st-panel-title">Latihan Bentuk Tangan</h2>
                        </div>
                        <span class="st-badge st-badge--primary">{{ activeSystem }}</span>
                    </div>

                    <!-- Target Selection Pills -->
                    <div class="practice-target-select">
                        <div>
                            <b>Pilih Target</b>
                            <small>Alfabet bahasa isyarat</small>
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

                    <!-- Main Camera Studio with Vector Animation and Timer -->
                    <div class="practice-studio" :class="{ 'is-scanning': scanning, 'is-counting': countdown > 0 }">
                        <div class="studio-topline">
                            <span class="studio-status-indicator">
                                <i class="studio-dot" :class="{ active: cameraActive, countdown: countdown > 0 }"></i>
                                <span v-if="countdown > 0">Bersiap... Mengambil dalam {{ countdown }} detik</span>
                                <span v-else-if="scanning">Menganalisis Posisi Tangan...</span>
                                <span v-else>{{ cameraActive ? 'Kamera Aktif · Posisikan Tangan' : 'Kamera Nonaktif' }}</span>
                            </span>
                            <button class="camera-toggle-btn" type="button" @click="toggleCamera">
                                <component :is="cameraActive ? CameraOff : Camera" :size="16" />
                                <span>{{ cameraActive ? 'Matikan Kamera' : 'Buka Kamera' }}</span>
                            </button>
                        </div>

                        <div class="studio-viewport">
                            <!-- Real Webcam Stream -->
                            <video
                                v-show="cameraActive"
                                ref="videoRef"
                                class="webcam-video"
                                autoplay
                                playsinline
                                muted
                            ></video>

                            <!-- Placeholder when camera is inactive -->
                            <div v-show="!cameraActive" class="hand-guide-box">
                                <div class="guide-circle">
                                    <Hand :size="54" class="guide-hand-icon" />
                                    <span class="guide-letter">{{ target.letter }}</span>
                                </div>
                                <p class="guide-text">
                                    Nyalakan kamera untuk melihat posisi tangan Anda dan memulai verifikasi isyarat.
                                </p>
                            </div>

                            <!-- Interactive Countdown Timer Overlay -->
                            <div v-if="countdown > 0" class="countdown-overlay">
                                <div class="countdown-circle">
                                    <svg class="countdown-svg" viewBox="0 0 100 100">
                                        <circle class="countdown-bg" cx="50" cy="50" r="42" />
                                        <circle
                                            class="countdown-progress"
                                            cx="50"
                                            cy="50"
                                            r="42"
                                            :style="{
                                                strokeDashoffset: 264 - (264 * countdown) / selectedTimer,
                                            }"
                                        />
                                    </svg>
                                    <span class="countdown-number">{{ countdown }}</span>
                                </div>
                                <div class="countdown-banner">
                                    <b>Tahan Posisi Tangan...</b>
                                    <small>Foto akan diambil secara otomatis</small>
                                </div>
                                <button type="button" class="countdown-cancel-btn" @click="cancelCountdown">
                                    <X :size="13" />
                                    <span>Batal</span>
                                </button>
                            </div>

                            <!-- Target Letter Badge in Viewport -->
                            <div class="target-overlay-badge">
                                <small>Target</small>
                                <b>{{ target.letter }}</b>
                            </div>

                            <!-- Scanning Laser Animation Line -->
                            <div v-if="scanning" class="scanning-laser-line"></div>

                            <!-- Scanning Indicator Bar -->
                            <div v-if="scanning" class="scanning-indicator-bar">
                                <RefreshCw :size="15" class="spin-icon" />
                                <span>Memverifikasi kesesuaian isyarat tangan Anda...</span>
                            </div>
                        </div>

                        <!-- Timer Duration Selector Bar -->
                        <div class="timer-selector-bar">
                            <div class="timer-selector-label">
                                <Timer :size="15" class="text-secondary" />
                                <span>Hitung Mundur Persiapan (Timer):</span>
                            </div>
                            <div class="timer-pill-group" role="radiogroup" aria-label="Pilih timer persiapan">
                                <button
                                    type="button"
                                    class="timer-pill"
                                    :class="{ active: selectedTimer === 0 }"
                                    @click="selectedTimer = 0"
                                >
                                    Langsung
                                </button>
                                <button
                                    type="button"
                                    class="timer-pill"
                                    :class="{ active: selectedTimer === 3 }"
                                    @click="selectedTimer = 3"
                                >
                                    3 Detik
                                </button>
                                <button
                                    type="button"
                                    class="timer-pill"
                                    :class="{ active: selectedTimer === 5 }"
                                    @click="selectedTimer = 5"
                                >
                                    5 Detik
                                </button>
                            </div>
                        </div>

                        <p class="studio-hint">{{ target.hint }}</p>
                    </div>

                    <!-- Detection Result Banner -->
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

                    <!-- Control Buttons -->
                    <div class="practice-control-row">
                        <button v-if="result === 'retry'" type="button" class="st-btn st-btn--secondary" @click="startDetection">
                            <RotateCcw :size="16" />
                            <span>Coba Ulangi</span>
                        </button>
                        <button
                            type="button"
                            class="st-btn st-btn--primary"
                            :disabled="scanning || countdown > 0"
                            @click="startDetection"
                        >
                            <Timer v-if="selectedTimer > 0" :size="16" />
                            <Camera v-else :size="16" />
                            <span>
                                {{ scanning ? 'Sedang Menganalisis...' : countdown > 0 ? `Bersiap (${countdown}s)...` : selectedTimer > 0 ? `Mulai Deteksi (${selectedTimer} Detik)` : 'Deteksi Langsung' }}
                            </span>
                        </button>
                    </div>
                </section>

                <!-- Sidebar Progress -->
                <aside class="st-panel practice-progress-card">
                    <span class="st-eyebrow">Kemajuan Sesi</span>
                    <div class="progress-total">
                        <b>{{ practicedCount }}</b>
                        <span>dari {{ targetList.length }} tuntas</span>
                    </div>

                    <div class="st-track">
                        <i :style="{ width: `${(practicedCount / (targetList.length || 1)) * 100}%` }"></i>
                    </div>

                    <ul class="letter-list">
                        <li
                            v-for="item in targetList"
                            :key="item.letter"
                            :class="{ 'is-done': isPracticed(item.letter) }"
                        >
                            <span class="letter-chip">{{ item.letter }}</span>
                            <span class="letter-name">{{ item.name }}</span>
                            <Check v-if="isPracticed(item.letter)" :size="16" />
                            <small v-else>Belum</small>
                        </li>
                    </ul>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.practice-focused .practice-dashboard-grid {
    grid-template-columns: minmax(0, 1fr) 260px;
    gap: 20px;
    margin-top: 0;
}

.practice-console .st-panel-head {
    margin-bottom: 0;
}

.practice-target-select {
    align-items: center;
    gap: 14px;
    margin: 16px 0 0;
    padding: 14px;
    border-color: var(--st-line);
}

.practice-target-select b {
    font-size: 14px;
    color: var(--st-ink);
}

.practice-target-select small {
    font-size: 12px;
    color: var(--st-muted);
}

.target-pills {
    flex-wrap: wrap;
    justify-content: flex-end;
}

.target-pill {
    min-width: 44px;
    height: 44px;
    color: var(--st-text);
    cursor: pointer;
}

.target-pill.practiced {
    color: var(--st-primary-dark);
    border-color: var(--st-primary-line);
}

.practice-control-row {
    display: flex;
    gap: 12px;
    margin-top: 16px;
}

.practice-control-row .st-btn {
    flex: 1;
    min-height: 48px;
    font-size: 15px;
}

.studio-result {
    margin-top: 14px;
    padding: 14px 16px;
    border-radius: 12px;
}

.studio-result .result-title b {
    font-size: 14px;
}

.studio-result .result-title small {
    font-size: 13px;
    color: var(--st-text);
}

.studio-result .result-title strong {
    font-size: 14px;
    color: var(--st-gold-dark);
}

/* Kartu kemajuan */
.practice-progress-card {
    position: sticky;
    top: 16px;
    gap: 10px;
}

.progress-total {
    display: flex;
    align-items: baseline;
    gap: 6px;
    font-size: 13px;
    color: var(--st-muted);
}

.progress-total b {
    font-size: 28px;
    font-weight: 800;
    color: var(--st-ink);
}

.letter-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-height: 420px;
    margin: 6px 0 0;
    padding: 0;
    overflow-y: auto;
    list-style: none;
}

.letter-list li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    font-size: 13px;
    color: var(--st-text);
    background: var(--st-surface);
    border-radius: 10px;
}

.letter-list li.is-done {
    color: var(--st-primary-dark);
    background: var(--st-primary-soft);
}

.letter-list li svg {
    color: var(--st-primary);
}

.letter-list small {
    font-size: 12px;
    color: var(--st-muted);
}

.letter-chip {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 28px;
    height: 28px;
    font-weight: 800;
    background: #fff;
    border: 1px solid var(--st-line);
    border-radius: 8px;
}

.letter-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (max-width: 1100px) {
    .practice-focused .practice-dashboard-grid {
        grid-template-columns: 1fr;
    }

    .practice-progress-card {
        position: static;
    }

    .letter-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        max-height: none;
    }
}

@media (max-width: 760px) {
    .practice-target-select {
        display: grid;
    }

    .target-pills {
        justify-content: flex-start;
    }

    .practice-control-row {
        flex-direction: column;
    }
}

.letter-badge {
    font-weight: 800;
    font-size: 13px;
}

.practice-studio {
    border-radius: 16px;
    background: #14221d;
    color: #ffffff;
    padding: 18px;
    margin-top: 16px;
}

.studio-topline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    font-size: 13px;
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
    transition: all 0.2s ease;
}

.studio-dot.active {
    background: #34B182;
    box-shadow: 0 0 8px #34B182;
}

.studio-dot.countdown {
    background: #C29B40;
    box-shadow: 0 0 10px #C29B40;
    animation: pulse-dot 0.8s infinite;
}

@keyframes pulse-dot {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: 0.6; }
}

.camera-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    transition: 0.18s;
}

.camera-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}

.studio-viewport {
    height: 340px;
    border-radius: 14px;
    background: #091210;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, 0.08);
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
    padding: 24px;
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
    font-size: 14px;
    color: #97d2a3;
    max-width: 290px;
    line-height: 1.5;
    margin: 0;
}

/* Countdown Overlay */
.countdown-overlay {
    position: absolute;
    inset: 0;
    background: rgba(14, 25, 21, 0.75);
    backdrop-filter: blur(5px);
    z-index: 30;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    animation: fade-in 0.2s ease-out;
}

@keyframes fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
}

.countdown-circle {
    position: relative;
    width: 90px;
    height: 90px;
    display: grid;
    place-items: center;
}

.countdown-svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.countdown-bg {
    fill: none;
    stroke: rgba(255, 255, 255, 0.15);
    stroke-width: 6;
}

.countdown-progress {
    fill: none;
    stroke: #C29B40;
    stroke-width: 6;
    stroke-linecap: round;
    stroke-dasharray: 264;
    transition: stroke-dashoffset 0.9s linear;
}

.countdown-number {
    position: absolute;
    font-size: 38px;
    font-weight: 900;
    color: #ffffff;
    animation: scale-bounce 0.95s infinite ease-out;
}

@keyframes scale-bounce {
    0% { transform: scale(0.7); opacity: 0.6; }
    30% { transform: scale(1.15); opacity: 1; }
    100% { transform: scale(1); opacity: 0.9; }
}

.countdown-banner {
    margin-top: 14px;
}

.countdown-banner b {
    display: block;
    font-size: 15px;
    color: #ffffff;
}

.countdown-banner small {
    display: block;
    font-size: 13px;
    color: #d5f3e5;
    margin-top: 2px;
}

.countdown-cancel-btn {
    margin-top: 16px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    padding: 8px 14px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: 0.18s;
}

.countdown-cancel-btn:hover {
    background: rgba(217, 83, 79, 0.3);
    border-color: #d9534f;
}

/* Scanning Laser Effect */
.scanning-laser-line {
    position: absolute;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, #34B182 30%, #5FAB6F 70%, transparent);
    box-shadow: 0 0 16px #34B182, 0 0 30px #5FAB6F;
    z-index: 20;
    animation: laser-sweep 1.4s ease-in-out infinite alternate;
}

@keyframes laser-sweep {
    0% { top: 8%; opacity: 0.3; }
    50% { opacity: 1; }
    100% { top: 92%; opacity: 0.3; }
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
    z-index: 15;
}

.target-overlay-badge small {
    display: block;
    font-size: 11px;
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
    font-size: 13px;
    font-weight: 600;
    color: #7cd3ae;
    backdrop-filter: blur(4px);
    z-index: 25;
}

.spin-icon {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Timer Duration Selector */
.timer-selector-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 8px 12px;
    margin-top: 12px;
}

.timer-selector-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #ade6cc;
}

.timer-pill-group {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.timer-pill {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #cde6d9;
    font-size: 13px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.16s;
}

.timer-pill:hover {
    background: rgba(255, 255, 255, 0.16);
}

.timer-pill.active {
    background: #C29B40;
    border-color: #C29B40;
    color: #ffffff;
    box-shadow: 0 0 8px rgba(194, 155, 64, 0.4);
}

.studio-hint {
    margin: 12px 0 0;
    font-size: 13px;
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
