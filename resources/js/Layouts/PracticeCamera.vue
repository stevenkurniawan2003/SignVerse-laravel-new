<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Camera, CameraOff, RefreshCw, RotateCcw, Timer, X } from '@lucide/vue';

const props = defineProps({
    lessonId: { type: Number, default: null },
    letter: { type: String, default: '' },
    system: { type: String, default: 'SIBI' },
    attempted: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['result']);

// Kamera — otomatis dinyalakan saat komponen tampil
const videoRef = ref(null);
const canvasRef = ref(null);
const cameraActive = ref(false);
const cameraStarting = ref(false);
const cameraError = ref('');
let mediaStream = null;

async function startCamera() {
    if (cameraActive.value || cameraStarting.value) return;
    cameraError.value = '';

    if (!navigator.mediaDevices?.getUserMedia) {
        cameraError.value = 'Browser ini tidak mendukung kamera. Coba buka lewat Google Chrome terbaru.';
        return;
    }

    cameraStarting.value = true;
    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
            audio: false,
        });
        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            await videoRef.value.play().catch(() => {});
            cameraActive.value = true;
        } else {
            mediaStream.getTracks().forEach((track) => track.stop());
            mediaStream = null;
        }
    } catch (err) {
        cameraError.value = err?.name === 'NotAllowedError'
            ? 'Izin kamera ditolak. Klik ikon kamera di address bar browser, pilih "Izinkan", lalu tekan tombol di bawah.'
            : 'Kamera tidak dapat dibuka. Pastikan kamera terpasang dan tidak sedang dipakai aplikasi lain.';
    } finally {
        cameraStarting.value = false;
    }
}

function stopCamera() {
    cancelCountdown();
    mediaStream?.getTracks().forEach((track) => track.stop());
    mediaStream = null;
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    cameraActive.value = false;
}

// Timer hitung mundur agar siswa sempat menyiapkan bentuk tangan
const timerOptions = [
    { value: 0, label: 'Langsung' },
    { value: 3, label: '3 detik' },
    { value: 5, label: '5 detik' },
];
const selectedTimer = ref(3);
const countdown = ref(0);
const scanning = ref(false);
const frameError = ref('');
let countdownInterval = null;

function cancelCountdown() {
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
    countdown.value = 0;
}

const busy = computed(() => scanning.value || countdown.value > 0);

const detectLabel = computed(() => {
    if (scanning.value) return 'Sedang memeriksa isyarat...';
    if (countdown.value > 0) return `Tahan posisi... ${countdown.value}`;
    if (!cameraActive.value) return 'Nyalakan kamera dulu';
    if (props.attempted) return 'Coba Lagi';
    return selectedTimer.value > 0 ? `Mulai Peragaan (${selectedTimer.value} detik)` : 'Periksa Sekarang';
});

// Huruf BISINDO D/F/P agak mirip pada webcam
const confusableNote = computed(() =>
    props.system === 'BISINDO' && ['D', 'F', 'P'].includes(props.letter)
        ? 'Bentuk huruf D, F, dan P BISINDO agak mirip di kamera. Tunjukkan kedua tangan dengan jelas dan pencahayaan yang cukup.'
        : ''
);

function startDetection() {
    frameError.value = '';

    if (!cameraActive.value) {
        startCamera();
        return;
    }
    if (busy.value || props.disabled) return;

    if (selectedTimer.value === 0) {
        performDetection();
        return;
    }

    countdown.value = selectedTimer.value;
    countdownInterval = setInterval(() => {
        countdown.value -= 1;
        if (countdown.value <= 0) {
            cancelCountdown();
            performDetection();
        }
    }, 1000);
}

function captureFrame() {
    const video = videoRef.value;
    const canvas = canvasRef.value;
    if (!cameraActive.value || !video || !canvas) return null;

    // Potong persegi dari tengah agar yang dinilai AI sama dengan bingkai kamera di layar
    const width = video.videoWidth || 640;
    const height = video.videoHeight || 480;
    const side = Math.min(width, height);
    canvas.width = side;
    canvas.height = side;
    canvas.getContext('2d').drawImage(video, (width - side) / 2, (height - side) / 2, side, side, 0, 0, side, side);
    return canvas.toDataURL('image/jpeg', 0.85);
}

function failedResult(lessonId, letter, system, message) {
    return {
        success: false,
        lessonId,
        letter,
        system,
        detectionStatus: 'service_unreachable',
        streak: 0,
        evaluated: false,
        recognized: null,
        confidence: 0,
        message,
        xp: 0,
        alreadyMastered: false,
        newBadges: [],
        leveledUp: false,
        levelName: '',
        challengeCompleted: false,
        assignmentsCompleted: [],
    };
}

async function performDetection() {
    const frame = captureFrame();
    if (!frame) {
        frameError.value = 'Gagal mengambil gambar dari kamera. Pastikan kamera menyala dengan baik.';
        return;
    }

    const { lessonId, letter, system } = props;
    scanning.value = true;

    try {
        const res = await fetch('/api/practice/attempt', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken || '',
            },
            body: JSON.stringify({ lesson_id: lessonId, image: frame, mirrored: true }),
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data) {
            throw new Error(data?.message || `Respons server status ${res.status}`);
        }

        const success = Boolean(data.correct);
        emit('result', {
            success,
            lessonId,
            letter,
            system,
            detectionStatus: data.detection_status || '',
            streak: Number(data.streak || 0),
            evaluated: Boolean(data.evaluated),
            recognized: data.recognized ? String(data.recognized).toUpperCase() : null,
            confidence: Number(data.confidence || 0),
            message: data.message || '',
            xp: Number(data.xp_earned || 0),
            alreadyMastered: Boolean(data.already_mastered),
            newBadges: Array.isArray(data.new_badges) ? data.new_badges : [],
            leveledUp: Boolean(data.leveled_up),
            levelName: data.level?.name || '',
            challengeCompleted: Boolean(data.challenge_completed),
            assignmentsCompleted: Array.isArray(data.assignments_completed) ? data.assignments_completed : [],
        });

        if (success) {
            router.reload({ only: ['auth'] });
        }
    } catch (e) {
        emit('result', failedResult(lessonId, letter, system, `Koneksi ke sistem deteksi terganggu: ${e.message}`));
    } finally {
        scanning.value = false;
    }
}

function reset() {
    cancelCountdown();
    frameError.value = '';
}

onMounted(startCamera);
onBeforeUnmount(stopCamera);

defineExpose({ busy, scanning, startDetection, reset });
</script>

<template>
    <div class="ps-camera-studio" :class="system === 'BISINDO' ? 'ps--bisindo' : 'ps--sibi'">
        <canvas ref="canvasRef" class="ps-hidden"></canvas>

                <div class="ps-camera" :class="{ 'is-busy': busy }">
                    <video v-show="cameraActive" ref="videoRef" class="ps-video" autoplay playsinline muted></video>

                    <div v-if="!cameraActive" class="ps-camera-off">
                        <template v-if="cameraStarting">
                            <RefreshCw :size="34" class="ps-spin" />
                            <b>Menyalakan kamera...</b>
                            <small>Jika browser meminta izin, pilih "Izinkan".</small>
                        </template>
                        <template v-else>
                            <CameraOff :size="36" />
                            <b>{{ cameraError ? 'Kamera belum bisa dibuka' : 'Kamera dimatikan' }}</b>
                            <small>{{ cameraError || 'Nyalakan kamera untuk mulai berlatih.' }}</small>
                            <button type="button" class="st-btn st-btn--primary" @click="startCamera">
                                <Camera :size="16" /> {{ cameraError ? 'Coba Nyalakan Kamera' : 'Nyalakan Kamera' }}
                            </button>
                        </template>
                    </div>

                    <template v-else>
                        <span class="ps-live"><i></i> Kamera aktif</span>
                        <span class="ps-overlay-target">{{ letter }}</span>
                        <div class="ps-guide-frame" aria-hidden="true"></div>
                        <button type="button" class="ps-camera-toggle" :disabled="busy" @click="stopCamera">
                            <CameraOff :size="14" /> Matikan
                        </button>
                    </template>

                    <div v-if="countdown > 0" class="ps-overlay">
                        <span class="ps-count">{{ countdown }}</span>
                        <b>Tahan bentuk tangan huruf {{ letter }}</b>
                        <button type="button" class="ps-cancel" @click="cancelCountdown"><X :size="13" /> Batal</button>
                    </div>
                    <div v-else-if="scanning" class="ps-overlay">
                        <RefreshCw :size="34" class="ps-spin" />
                        <b>Sedang memeriksa bentuk tanganmu...</b>
                    </div>
                </div>

                <div class="ps-controls">
                    <div class="ps-timer" role="radiogroup" aria-label="Hitung mundur sebelum foto diambil">
                        <span><Timer :size="15" /> Hitung mundur</span>
                        <div>
                            <button
                                v-for="option in timerOptions"
                                :key="option.value"
                                type="button"
                                role="radio"
                                :aria-checked="selectedTimer === option.value"
                                :class="{ active: selectedTimer === option.value }"
                                :disabled="busy"
                                @click="selectedTimer = option.value"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>

                    <button type="button" class="st-btn st-btn--primary ps-detect" :disabled="busy || disabled" @click="startDetection">
                        <RotateCcw v-if="attempted && !busy" :size="18" />
                        <Camera v-else :size="18" />
                        <span>{{ detectLabel }}</span>
                    </button>
                </div>

                <div v-if="frameError" class="ps-note ps-note--warning" role="alert">{{ frameError }}</div>
                <div v-if="confusableNote" class="ps-note">{{ confusableNote }}</div>

                <ol class="ps-steps">
                    <li><span>1</span><p>Posisikan tangan di dalam kotak panduan.</p></li>
                    <li><span>2</span><p>Tekan <b>Mulai Peragaan</b>, lalu tahan bentuk tangan.</p></li>
                    <li><span>3</span><p>Pemeriksaan otomatis. Pas = huruf dikuasai.</p></li>
                </ol>
    </div>
</template>

<style scoped>
.ps-camera-studio {
    display: grid;
    gap: 14px;
}

.ps-camera-studio {
    --sys: #34b182;
    --sys-dark: #1e7253;
    --sys-soft: #eefaf4;
    --sys-line: #bfe8d4;
}

.ps-camera-studio.ps--bisindo {
    --sys: #c29b40;
    --sys-dark: #8a6a1f;
    --sys-soft: #fdf6e6;
    --sys-line: #ecd9a8;
}

.ps-hidden {
    display: none;
}

/* Kamera */
.ps-camera {
    position: relative;
    display: grid;
    place-items: center;
    aspect-ratio: 1 / 1;
    width: min(100%, 460px, 52vh);
    justify-self: center;
    overflow: hidden;
    color: #fff;
    background: #14221d;
    border-radius: 18px;
}

.ps-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1);
}

.ps-camera-off {
    display: grid;
    justify-items: center;
    gap: 8px;
    max-width: 340px;
    padding: 20px;
    text-align: center;
}

.ps-camera-off b {
    font-size: 17px;
}

.ps-camera-off small {
    font-size: 14px;
    line-height: 1.5;
    color: #c7dcd2;
}

.ps-camera-off .st-btn {
    margin-top: 6px;
}

.ps-live {
    position: absolute;
    top: 12px;
    left: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 700;
    background: rgba(20, 34, 29, 0.6);
    border-radius: 999px;
}

.ps-live i {
    width: 8px;
    height: 8px;
    background: #ef4444;
    border-radius: 50%;
}

.ps-overlay-target {
    position: absolute;
    top: 12px;
    right: 12px;
    display: grid;
    place-items: center;
    width: 52px;
    height: 52px;
    font-size: 28px;
    font-weight: 800;
    background: var(--sys);
    border: 3px solid #fff;
    border-radius: 14px;
}

.ps-guide-frame {
    position: absolute;
    inset: 18%;
    border: 3px dashed rgba(255, 255, 255, 0.55);
    border-radius: 24px;
    pointer-events: none;
}

.ps-camera-toggle {
    position: absolute;
    bottom: 12px;
    right: 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    background: rgba(20, 34, 29, 0.6);
    border: 0;
    border-radius: 999px;
    cursor: pointer;
}

.ps-overlay {
    position: absolute;
    inset: 0;
    display: grid;
    place-content: center;
    justify-items: center;
    gap: 10px;
    padding: 20px;
    text-align: center;
    background: rgba(20, 34, 29, 0.55);
}

.ps-overlay b {
    font-size: 17px;
}

.ps-count {
    display: grid;
    place-items: center;
    width: 96px;
    height: 96px;
    font-size: 52px;
    font-weight: 800;
    background: var(--sys);
    border: 4px solid #fff;
    border-radius: 50%;
}

.ps-cancel {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    background: rgba(255, 255, 255, 0.18);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 999px;
    cursor: pointer;
}

.ps-spin {
    animation: ps-spin 1s linear infinite;
}

@keyframes ps-spin {
    to {
        transform: rotate(360deg);
    }
}

/* Kontrol */
.ps-controls {
    display: grid;
    gap: 12px;
}

.ps-timer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.ps-timer > span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 700;
    color: var(--st-text);
}

.ps-timer > div {
    display: inline-flex;
    gap: 4px;
    padding: 3px;
    background: var(--st-surface);
    border: 1px solid var(--st-line);
    border-radius: 12px;
}

.ps-timer button {
    min-height: 36px;
    padding: 0 12px;
    font-size: 13px;
    font-weight: 700;
    color: var(--st-muted);
    background: transparent;
    border: 0;
    border-radius: 9px;
    cursor: pointer;
}

.ps-timer button.active {
    color: #fff;
    background: var(--sys);
}

.ps-detect {
    width: 100%;
    min-height: 54px;
    font-size: 16px;
    background: var(--sys) !important;
    border-color: var(--sys) !important;
}

.ps-note {
    padding: 10px 12px;
    font-size: 14px;
    line-height: 1.5;
    color: var(--sys-dark);
    background: var(--sys-soft);
    border-radius: 12px;
}

.ps-note--warning {
    color: #9a6a2f;
    background: #fff5eb;
}

.ps-steps {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.ps-steps li {
    display: flex;
    gap: 8px;
    padding: 10px;
    font-size: 13px;
    line-height: 1.45;
    color: var(--st-text);
    background: var(--st-surface);
    border-radius: 12px;
}

.ps-steps p {
    margin: 0;
}

.ps-steps span {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 22px;
    height: 22px;
    font-size: 12px;
    font-weight: 800;
    color: #fff;
    background: var(--sys);
    border-radius: 50%;
}

@media (max-width: 600px) {
    .ps-steps {
        grid-template-columns: 1fr;
    }
}
</style>
