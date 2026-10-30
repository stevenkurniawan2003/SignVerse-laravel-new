<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    Camera,
    CameraOff,
    Check,
    CheckCircle,
    ChevronLeft,
    ClipboardCheck,
    ChevronRight,
    Flame,
    Hand,
    Lightbulb,
    RefreshCw,
    RotateCcw,
    Star,
    Timer,
    Trophy,
    X,
} from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    targets: { type: Array, default: () => [] },
    completedLessonIds: { type: Array, default: () => [] },
});

const SYSTEMS = {
    SIBI: {
        label: 'SIBI',
        fullName: 'Sistem Isyarat Bahasa Indonesia',
        hands: 'Gunakan satu tangan',
        tip: 'SIBI memakai satu tangan. Pastikan hanya satu tangan yang terlihat di kamera.',
    },
    BISINDO: {
        label: 'BISINDO',
        fullName: 'Bahasa Isyarat Indonesia',
        hands: 'Umumnya dua tangan',
        tip: 'BISINDO umumnya memakai dua tangan. Pastikan kedua tangan masuk ke dalam bingkai kamera.',
    },
};

const allTargets = ref(Array.isArray(props.targets) ? props.targets : []);
const masteredIds = ref(new Set(allTargets.value.filter((item) => item.practiced).map((item) => item.id)));

// Sistem isyarat disinkronkan dengan pilihan SIBI/BISINDO di topbar
function readSystem() {
    try {
        return localStorage.getItem('signtech_system') === 'BISINDO' ? 'BISINDO' : 'SIBI';
    } catch {
        return 'SIBI';
    }
}

const activeSystem = ref(readSystem());
const selectedId = ref(null);

function lettersFor(system) {
    return allTargets.value.filter((item) => item.language === system.toLowerCase() && item.category_slug === 'alphabet');
}

const letters = computed(() => lettersFor(activeSystem.value));
const systemInfo = computed(() => SYSTEMS[activeSystem.value]);

function isMastered(item) {
    return masteredIds.value.has(item.id);
}

function masteredCount(system) {
    return lettersFor(system).filter(isMastered).length;
}

const masteredInSystem = computed(() => letters.value.filter(isMastered).length);
const progressPercent = computed(() => Math.round((masteredInSystem.value / (letters.value.length || 1)) * 100));

const target = computed(() => letters.value.find((item) => item.id === selectedId.value) || letters.value[0] || null);
const targetIndex = computed(() => letters.value.findIndex((item) => item.id === target.value?.id));

// Huruf berikutnya yang belum dikuasai (melingkar), atau huruf setelahnya
const nextTarget = computed(() => {
    const list = letters.value;
    for (let step = 1; step <= list.length; step++) {
        const candidate = list[(targetIndex.value + step) % list.length];
        if (!isMastered(candidate)) {
            return candidate;
        }
    }
    return list[(targetIndex.value + 1) % list.length] || null;
});

// Keterbatasan model AI: huruf BISINDO D/F/P sering tertukar pada webcam
const confusableNote = computed(() =>
    activeSystem.value === 'BISINDO' && ['D', 'F', 'P'].includes(target.value?.letter)
        ? 'AI kadang tertukar antara huruf D, F, dan P BISINDO. Tunjukkan kedua tangan dengan jelas dan pencahayaan yang cukup.'
        : ''
);

// Hasil deteksi
const scanning = ref(false);
const result = ref(null);
const promptError = ref('');
const sessionStats = ref({ attempts: 0, passed: 0, xp: 0 });

function resetFeedback() {
    result.value = null;
    resultOpen.value = false;
    promptError.value = '';
}

function selectFirstOpenLetter() {
    selectedId.value = (letters.value.find((item) => !isMastered(item)) || letters.value[0])?.id ?? null;
}

function choose(item) {
    if (!item || scanning.value) return;
    cancelCountdown();
    selectedId.value = item.id;
    resetFeedback();
}

function shiftTarget(direction) {
    const list = letters.value;
    if (!list.length) return;
    choose(list[(targetIndex.value + direction + list.length) % list.length]);
}

function applySystem(system) {
    cancelCountdown();
    activeSystem.value = system === 'BISINDO' ? 'BISINDO' : 'SIBI';
    resetFeedback();
    selectFirstOpenLetter();
}

function switchSystem(system) {
    if (system === activeSystem.value || scanning.value) return;
    try {
        localStorage.setItem('signtech_system', system);
    } catch {
        // penyimpanan lokal tidak tersedia — pilihan tetap berlaku di halaman ini
    }
    window.dispatchEvent(new CustomEvent('signtech:system-changed', { detail: system }));
}

function onSystemChanged(event) {
    applySystem(event.detail);
}

// Kamera — otomatis dinyalakan saat halaman dibuka
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

function toggleCamera() {
    if (cameraActive.value) {
        stopCamera();
    } else {
        startCamera();
    }
}

// Timer hitung mundur agar siswa sempat menyiapkan bentuk tangan
const timerOptions = [
    { value: 0, label: 'Langsung' },
    { value: 3, label: '3 detik' },
    { value: 5, label: '5 detik' },
];
const selectedTimer = ref(3);
const countdown = ref(0);
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
    if (scanning.value) return 'AI sedang memeriksa...';
    if (countdown.value > 0) return `Tahan posisi... ${countdown.value}`;
    if (!cameraActive.value) return 'Nyalakan kamera dulu';
    if (result.value) return 'Coba Lagi';
    return selectedTimer.value > 0 ? `Mulai Peragaan (${selectedTimer.value} detik)` : 'Periksa Sekarang';
});

function startDetection() {
    promptError.value = '';

    if (!target.value) {
        promptError.value = 'Pilih huruf yang ingin diperagakan terlebih dahulu.';
        return;
    }
    if (!cameraActive.value) {
        startCamera();
        return;
    }
    if (busy.value) return;

    result.value = null;
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

async function performDetection() {
    const frame = captureFrame();
    if (!frame) {
        promptError.value = 'Gagal mengambil gambar dari kamera. Pastikan kamera menyala dengan baik.';
        return;
    }

    const practicedTarget = target.value;
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
            body: JSON.stringify({ lesson_id: practicedTarget.id, image: frame, mirrored: true }),
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data) {
            throw new Error(data?.message || `Respons server status ${res.status}`);
        }

        const success = Boolean(data.correct);
        result.value = {
            success,
            letter: practicedTarget.letter,
            system: activeSystem.value,
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
        };

        if (data.evaluated) {
            sessionStats.value.attempts += 1;
        }
        if (success) {
            sessionStats.value.passed += 1;
            sessionStats.value.xp += result.value.xp;
            masteredIds.value = new Set([...masteredIds.value, practicedTarget.id]);
            router.reload({ only: ['auth'] });
        }
    } catch (e) {
        result.value = {
            success: false,
            letter: practicedTarget.letter,
            system: activeSystem.value,
            detectionStatus: 'service_unreachable',
            streak: 0,
            evaluated: false,
            recognized: null,
            confidence: 0,
            message: `Koneksi ke sistem deteksi terganggu: ${e.message}`,
            xp: 0,
            alreadyMastered: false,
            newBadges: [],
            leveledUp: false,
            levelName: '',
            challengeCompleted: false,
            assignmentsCompleted: [],
        };
    } finally {
        scanning.value = false;
        resultOpen.value = Boolean(result.value);
    }
}

// Pop-up hasil penilaian
const resultOpen = ref(false);
const retryButtonRef = ref(null);
const nextButtonRef = ref(null);

/**
 * Penjelasan kegagalan untuk pop-up: apa yang salah + tips perbaikan,
 * disesuaikan dengan status deteksi dari AI Service.
 */
const failure = computed(() => {
    const r = result.value;
    if (!r || r.success) return null;

    const unreachable = ['service_unreachable', 'http_error'].includes(r.detectionStatus) || r.detectionStatus.startsWith('http_');
    if (unreachable) {
        return {
            title: 'AI sedang tidak bisa dihubungi',
            reason: 'Isyaratmu belum sempat dinilai karena layanan AI tidak merespons. Ini bukan kesalahanmu.',
            tips: ['Periksa koneksi internet.', 'Tunggu sebentar, lalu coba lagi.'],
        };
    }

    if (r.detectionStatus === 'unexpected_extra_hand') {
        return {
            title: 'Terlihat lebih dari satu tangan',
            reason: `SIBI hanya memakai satu tangan, tetapi kamera menangkap dua tangan saat memeriksa huruf ${r.letter}.`,
            tips: ['Turunkan tangan yang tidak dipakai.', 'Pastikan tidak ada tangan orang lain di belakangmu.'],
        };
    }

    if (!r.evaluated) {
        return {
            title: 'Tangan belum terlihat',
            reason: 'AI tidak menemukan tangan di dalam bingkai kamera, jadi isyaratmu belum bisa dinilai.',
            tips: [
                r.system === 'BISINDO' ? 'Masukkan kedua tangan ke dalam kotak panduan.' : 'Masukkan satu tanganmu ke dalam kotak panduan.',
                'Gunakan ruangan yang terang dan hindari cahaya dari belakang.',
                'Jangan terlalu dekat dengan kamera.',
            ],
        };
    }

    if (r.recognized && r.recognized !== r.letter) {
        return {
            title: `Terbaca sebagai huruf ${r.recognized}`,
            reason: `AI membaca isyaratmu sebagai huruf ${r.recognized} (akurasi ${r.confidence}%), padahal targetnya huruf ${r.letter}.`,
            tips: [
                `Perhatikan lagi posisi jari untuk huruf ${r.letter} ${r.system}.`,
                'Tahan bentuk tangan tetap diam sampai hitung mundur selesai.',
            ],
        };
    }

    return {
        title: 'Hampir benar!',
        reason: `Bentuk tanganmu mirip huruf ${r.letter}, tetapi akurasinya baru ${r.confidence}% (minimal 60%).`,
        tips: ['Tahan tangan lebih stabil tanpa bergerak.', 'Hadapkan telapak tangan dengan jelas ke kamera.'],
    };
});

function closeResult() {
    resultOpen.value = false;
}

function retryFromPopup() {
    closeResult();
    startDetection();
}

function goToNextTarget() {
    closeResult();
    choose(nextTarget.value);
}

function onKeydown(event) {
    if (event.key === 'Escape' && resultOpen.value) {
        closeResult();
    }
}

watch(resultOpen, async (open) => {
    if (!open) return;
    await nextTick();
    (result.value?.success ? nextButtonRef.value : retryButtonRef.value)?.focus();
});

onMounted(async () => {
    window.addEventListener('signtech:system-changed', onSystemChanged);
    window.addEventListener('keydown', onKeydown);

    if (allTargets.value.length === 0) {
        try {
            const res = await fetch('/api/practice/targets', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                allTargets.value = Array.isArray(data.targets) ? data.targets : [];
                masteredIds.value = new Set(allTargets.value.filter((item) => item.practiced).map((item) => item.id));
            }
        } catch {
            // ditangani oleh tampilan kosong
        }
    }

    selectFirstOpenLetter();
    startCamera();
});

onBeforeUnmount(() => {
    window.removeEventListener('signtech:system-changed', onSystemChanged);
    window.removeEventListener('keydown', onKeydown);
    stopCamera();
});
</script>

<template>
    <AppLayout>
        <Head title="AI Practice Studio" />

        <div class="st-page ps" :class="activeSystem === 'BISINDO' ? 'ps--bisindo' : 'ps--sibi'">
            <canvas ref="canvasRef" class="ps-hidden"></canvas>

            <!-- Judul & pilihan sistem isyarat -->
            <header class="ps-header">
                <div class="ps-intro">
                    <span class="ps-kicker"><Hand :size="14" /> AI Practice</span>
                    <h1>Latihan Isyarat dengan Kamera</h1>
                    <p>Pilih huruf, peragakan di depan kamera, lalu AI akan menilai isyaratmu secara otomatis.</p>
                </div>

                <div class="ps-systems" role="tablist" aria-label="Pilih sistem bahasa isyarat">
                    <button
                        v-for="(info, key) in SYSTEMS"
                        :key="key"
                        type="button"
                        role="tab"
                        class="ps-system"
                        :class="[`ps-system--${key.toLowerCase()}`, { active: activeSystem === key }]"
                        :aria-selected="activeSystem === key"
                        @click="switchSystem(key)"
                    >
                        <span class="ps-system-name">{{ info.label }}</span>
                        <span class="ps-system-full">{{ info.fullName }}</span>
                        <span class="ps-system-meta">
                            <Check :size="13" /> {{ masteredCount(key) }}/{{ lettersFor(key).length }} huruf dikuasai
                        </span>
                    </button>
                </div>
            </header>

            <div class="ps-grid">
                <!-- Studio kamera -->
                <section class="st-panel ps-studio" aria-label="Studio kamera">
                    <div class="ps-target">
                        <button type="button" class="ps-nav" aria-label="Huruf sebelumnya" :disabled="busy || !letters.length" @click="shiftTarget(-1)">
                            <ChevronLeft :size="22" />
                        </button>
                        <div class="ps-target-main">
                            <span class="ps-target-letter">{{ target?.letter || '-' }}</span>
                            <div>
                                <small>Peragakan huruf ({{ activeSystem }})</small>
                                <b>{{ target ? `Huruf ${target.letter}` : 'Belum ada huruf' }}</b>
                                <span v-if="target && isMastered(target)" class="ps-chip ps-chip--done"><Check :size="12" /> Sudah dikuasai</span>
                                <span v-else-if="target" class="ps-chip">+{{ target.xp_reward || 10 }} XP<span class="ps-chip-extra">&nbsp;saat pertama benar</span></span>
                            </div>
                        </div>
                        <button type="button" class="ps-nav" aria-label="Huruf berikutnya" :disabled="busy || !letters.length" @click="shiftTarget(1)">
                            <ChevronRight :size="22" />
                        </button>
                    </div>

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
                            <span class="ps-overlay-target">{{ target?.letter }}</span>
                            <div class="ps-guide-frame" aria-hidden="true"></div>
                            <button type="button" class="ps-camera-toggle" :disabled="busy" @click="toggleCamera">
                                <CameraOff :size="14" /> Matikan
                            </button>
                        </template>

                        <div v-if="countdown > 0" class="ps-overlay">
                            <span class="ps-count">{{ countdown }}</span>
                            <b>Tahan bentuk tangan huruf {{ target?.letter }}</b>
                            <button type="button" class="ps-cancel" @click="cancelCountdown"><X :size="13" /> Batal</button>
                        </div>
                        <div v-else-if="scanning" class="ps-overlay">
                            <RefreshCw :size="34" class="ps-spin" />
                            <b>AI sedang memeriksa isyaratmu...</b>
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

                        <button type="button" class="st-btn st-btn--primary ps-detect" :disabled="busy || !target" @click="startDetection">
                            <RotateCcw v-if="result && !busy" :size="18" />
                            <Camera v-else :size="18" />
                            <span>{{ detectLabel }}</span>
                        </button>
                    </div>

                    <div v-if="promptError" class="ps-note ps-note--warning" role="alert">{{ promptError }}</div>
                    <div v-if="confusableNote" class="ps-note">{{ confusableNote }}</div>

                    <ol class="ps-steps">
                        <li><span>1</span><p>Posisikan tangan di dalam kotak panduan.</p></li>
                        <li><span>2</span><p>Tekan <b>Mulai Peragaan</b>, lalu tahan bentuk tangan.</p></li>
                        <li><span>3</span><p>AI menilai otomatis. Benar = huruf dikuasai.</p></li>
                    </ol>
                </section>

                <div class="ps-side">
                <!-- Daftar huruf A–Z -->
                <aside class="st-panel ps-letters" :aria-label="`Huruf ${activeSystem} A sampai Z`">
                    <div class="ps-letters-head">
                        <div>
                            <span class="ps-system-tag">{{ activeSystem }}</span>
                            <h2>Huruf A–Z</h2>
                        </div>
                        <b>{{ masteredInSystem }}<small>/{{ letters.length }}</small></b>
                    </div>
                    <div class="ps-progress" role="progressbar" :aria-valuenow="progressPercent" aria-valuemin="0" aria-valuemax="100">
                        <i :style="{ width: `${progressPercent}%` }"></i>
                    </div>

                    <div v-if="letters.length" class="ps-letter-grid">
                        <button
                            v-for="item in letters"
                            :key="item.id"
                            type="button"
                            class="ps-letter"
                            :class="{ selected: target?.id === item.id, done: isMastered(item) }"
                            :aria-pressed="target?.id === item.id"
                            :aria-label="`Huruf ${item.letter}${isMastered(item) ? ', sudah dikuasai' : ''}`"
                            :disabled="busy"
                            @click="choose(item)"
                        >
                            {{ item.letter }}
                            <Check v-if="isMastered(item)" :size="11" class="ps-letter-check" />
                        </button>
                    </div>
                    <p v-else class="ps-empty">Belum ada materi huruf untuk {{ activeSystem }}.</p>

                    <div class="ps-legend">
                        <span><i class="selected"></i> Dipilih</span>
                        <span><i class="done"></i> Dikuasai</span>
                        <span><i></i> Belum</span>
                    </div>
                </aside>

                <!-- Tips, statistik sesi, dan aturan penilaian -->
                <aside class="st-panel ps-info" aria-label="Tips dan statistik latihan">
                    <div class="ps-tip">
                        <Lightbulb :size="16" />
                        <p><b>{{ systemInfo.hands }}.</b> {{ systemInfo.tip }}</p>
                    </div>

                    <dl class="ps-stats">
                        <div><dt>Percobaan</dt><dd>{{ sessionStats.attempts }}</dd></div>
                        <div><dt>Berhasil</dt><dd>{{ sessionStats.passed }}</dd></div>
                        <div><dt>XP sesi ini</dt><dd>+{{ sessionStats.xp }}</dd></div>
                    </dl>

                    <details class="ps-rules">
                        <summary>Bagaimana AI menilai?</summary>
                        <ul>
                            <li>Huruf yang dibaca AI harus sama dengan target.</li>
                            <li>Akurasi minimal 60% agar dinyatakan benar.</li>
                            <li>XP diberikan saat pertama kali menguasai sebuah huruf.</li>
                        </ul>
                    </details>
                </aside>
                </div>
            </div>

            <!-- Pop-up hasil penilaian AI (di-teleport ke body agar menutupi seluruh layar) -->
            <Teleport to="body">
            <Transition name="ps-pop">
                <div v-if="resultOpen && result" class="ps-modal-backdrop" @click.self="closeResult">
                    <div
                        class="ps-modal"
                        :class="[result.success ? 'is-success' : 'is-retry', result.system === 'BISINDO' ? 'ps--bisindo' : 'ps--sibi']"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="ps-modal-title"
                    >
                        <button type="button" class="ps-modal-close" aria-label="Tutup" @click="closeResult">
                            <X :size="18" />
                        </button>

                        <!-- Benar -->
                        <template v-if="result.success">
                            <span class="ps-modal-icon"><Check :size="40" /></span>
                            <h2 id="ps-modal-title">Hebat! Isyarat {{ result.letter }} benar</h2>
                            <p class="ps-modal-sub">AI mengenali isyarat huruf {{ result.letter }} ({{ result.system }}) kamu.</p>

                            <div class="ps-modal-stats">
                                <div>
                                    <CheckCircle :size="20" />
                                    <b>{{ result.confidence }}%</b>
                                    <small>Akurasi</small>
                                </div>
                                <div>
                                    <Star :size="20" />
                                    <b>+{{ result.xp }} XP</b>
                                    <small>{{ result.alreadyMastered ? 'Sudah dikuasai' : 'XP didapat' }}</small>
                                </div>
                                <div>
                                    <Flame :size="20" />
                                    <b>{{ result.streak }} hari</b>
                                    <small>Streak</small>
                                </div>
                                <div>
                                    <Award :size="20" />
                                    <b>{{ result.levelName || '-' }}</b>
                                    <small>Level</small>
                                </div>
                            </div>

                            <ul v-if="result.alreadyMastered || result.challengeCompleted || result.newBadges.length || result.leveledUp || result.assignmentsCompleted.length" class="ps-modal-list">
                                <li v-if="result.leveledUp" class="highlight"><Trophy :size="16" /> Naik level! Sekarang kamu <b>{{ result.levelName }}</b>.</li>
                                <li v-for="badge in result.newBadges" :key="badge" class="highlight"><Award :size="16" /> Lencana baru terbuka: <b>{{ badge }}</b></li>
                                <li v-for="task in result.assignmentsCompleted" :key="task.title" class="highlight"><ClipboardCheck :size="16" /> Tugas guru selesai: <b>{{ task.title }}</b> (+{{ task.xp_earned }} XP)</li>
                                <li v-if="result.challengeCompleted"><Trophy :size="16" /> Tantangan harian selesai! Bonus XP sudah ditambahkan.</li>
                                <li v-if="result.alreadyMastered && result.xp === 0"><Star :size="16" /> Huruf ini sudah kamu kuasai sebelumnya, jadi XP tidak bertambah lagi.</li>
                            </ul>

                            <div class="ps-modal-actions">
                                <button type="button" class="st-btn st-btn--secondary" @click="closeResult">
                                    <RotateCcw :size="16" /> Latih Lagi
                                </button>
                                <button
                                    v-if="nextTarget && nextTarget.id !== target?.id"
                                    ref="nextButtonRef"
                                    type="button"
                                    class="st-btn st-btn--primary"
                                    @click="goToNextTarget"
                                >
                                    Lanjut ke Huruf {{ nextTarget.letter }} <ArrowRight :size="16" />
                                </button>
                                <button v-else ref="nextButtonRef" type="button" class="st-btn st-btn--primary" @click="closeResult">
                                    Selesai
                                </button>
                            </div>
                        </template>

                        <!-- Salah -->
                        <template v-else-if="failure">
                            <span class="ps-modal-icon"><X :size="40" /></span>
                            <h2 id="ps-modal-title">{{ failure.title }}</h2>
                            <p class="ps-modal-sub">Target: huruf <b>{{ result.letter }}</b> ({{ result.system }})</p>

                            <div class="ps-modal-reason">
                                <b>Apa yang salah?</b>
                                <p>{{ failure.reason }}</p>
                            </div>

                            <div class="ps-modal-tips">
                                <b><Lightbulb :size="16" /> Coba perbaiki:</b>
                                <ul>
                                    <li v-for="tip in failure.tips" :key="tip">{{ tip }}</li>
                                </ul>
                            </div>

                            <div class="ps-modal-actions">
                                <button type="button" class="st-btn st-btn--secondary" @click="closeResult">Tutup</button>
                                <button ref="retryButtonRef" type="button" class="st-btn st-btn--primary" @click="retryFromPopup">
                                    <RotateCcw :size="16" /> Coba Lagi
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </Transition>
            </Teleport>
        </div>
    </AppLayout>
</template>

<style scoped>
.ps {
    --sys: #34b182;
    --sys-dark: #1e7253;
    --sys-soft: #eefaf4;
    --sys-line: #bfe8d4;
    display: grid;
    gap: 18px;
    padding-bottom: 32px;
    container-type: inline-size;
}

.ps--bisindo {
    --sys: #c29b40;
    --sys-dark: #8a6a1f;
    --sys-soft: #fdf6e6;
    --sys-line: #ecd9a8;
}

.ps-hidden {
    display: none;
}

/* Header */
.ps-header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 18px;
    padding: 22px 24px;
    background: linear-gradient(120deg, #ffffff, var(--sys-soft));
    border: 1px solid var(--st-line);
    border-radius: 20px;
}

.ps-kicker {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 700;
    color: var(--sys-dark);
    background: #fff;
    border: 1px solid var(--sys-line);
    border-radius: 999px;
}

.ps-intro h1 {
    margin: 10px 0 6px;
    font-size: 26px;
    font-weight: 800;
    line-height: 1.2;
    color: var(--st-ink);
}

.ps-intro p {
    margin: 0;
    max-width: 460px;
    font-size: 15px;
    line-height: 1.55;
    color: var(--st-text);
}

.ps-systems {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.ps-system {
    display: grid;
    gap: 2px;
    min-width: 190px;
    padding: 12px 14px;
    text-align: left;
    background: #fff;
    border: 2px solid var(--st-line);
    border-radius: 14px;
    cursor: pointer;
    transition: 0.15s;
}

.ps-system:hover {
    transform: translateY(-1px);
}

.ps-system-name {
    font-size: 18px;
    font-weight: 800;
    color: var(--st-ink);
}

.ps-system-full {
    font-size: 12px;
    color: var(--st-muted);
}

.ps-system-meta {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 6px;
    font-size: 12px;
    font-weight: 700;
    color: var(--st-text);
}

.ps-system--sibi.active {
    border-color: #34b182;
    background: #eefaf4;
    box-shadow: 0 0 0 3px #d5f3e5;
}

.ps-system--sibi.active .ps-system-name,
.ps-system--sibi.active .ps-system-meta {
    color: #1e7253;
}

.ps-system--bisindo.active {
    border-color: #c29b40;
    background: #fdf6e6;
    box-shadow: 0 0 0 3px #f6e8c5;
}

.ps-system--bisindo.active .ps-system-name,
.ps-system--bisindo.active .ps-system-meta {
    color: #8a6a1f;
}

/* Grid utama */
.ps-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 18px;
    align-items: start;
}

.ps-studio,
.ps-letters,
.ps-info {
    display: grid;
    gap: 14px;
    padding: 18px;
    border-radius: 20px;
}

.ps-side {
    position: sticky;
    top: 16px;
    display: grid;
    gap: 18px;
}

/* Target huruf */
.ps-target {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ps-nav {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 44px;
    height: 44px;
    color: var(--st-text);
    background: #fff;
    border: 1px solid var(--st-line);
    border-radius: 12px;
    cursor: pointer;
}

.ps-nav:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.ps-target-main {
    display: flex;
    flex: 1;
    align-items: center;
    gap: 14px;
    min-width: 0;
    padding: 10px 14px;
    background: var(--sys-soft);
    border: 1px solid var(--sys-line);
    border-radius: 16px;
}

.ps-target-letter {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 64px;
    height: 64px;
    font-size: 36px;
    font-weight: 800;
    color: #fff;
    background: var(--sys);
    border-radius: 16px;
}

.ps-target-main small {
    display: block;
    font-size: 12px;
    color: var(--st-muted);
}

.ps-target-main b {
    display: block;
    font-size: 20px;
    color: var(--st-ink);
}

.ps-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: 700;
    color: var(--sys-dark);
    background: #fff;
    border-radius: 999px;
}

.ps-chip--done {
    color: #fff;
    background: var(--sys);
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

/* Pop-up hasil */
.ps-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: grid;
    place-items: center;
    padding: 16px;
    background: rgba(20, 34, 29, 0.55);
}

.ps-modal {
    --sys: #34b182;
    --sys-dark: #1e7253;
    --sys-soft: #eefaf4;
    --sys-line: #bfe8d4;
    position: relative;
    display: grid;
    justify-items: center;
    gap: 14px;
    width: min(100%, 440px);
    max-height: calc(100dvh - 32px);
    overflow-y: auto;
    padding: 28px 22px 22px;
    text-align: center;
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 24px 60px rgba(20, 34, 29, 0.25);
}

.ps-modal.ps--bisindo {
    --sys: #c29b40;
    --sys-dark: #8a6a1f;
    --sys-soft: #fdf6e6;
    --sys-line: #ecd9a8;
}

.ps-modal-close {
    position: absolute;
    top: 12px;
    right: 12px;
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    color: var(--st-muted);
    background: var(--st-surface);
    border: 0;
    border-radius: 50%;
    cursor: pointer;
}

.ps-modal-icon {
    display: grid;
    place-items: center;
    width: 80px;
    height: 80px;
    color: #fff;
    background: #34b182;
    border-radius: 50%;
    box-shadow: 0 0 0 8px #eefaf4;
}

.ps-modal.is-retry .ps-modal-icon {
    background: #e07a3f;
    box-shadow: 0 0 0 8px #fdeee4;
}

.ps-modal h2 {
    margin: 4px 0 0;
    font-size: 22px;
    font-weight: 800;
    line-height: 1.25;
    color: var(--st-ink);
}

.ps-modal-sub {
    margin: -6px 0 0;
    font-size: 14px;
    color: var(--st-muted);
}

.ps-modal-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    width: 100%;
}

.ps-modal-stats div {
    display: grid;
    justify-items: center;
    gap: 2px;
    padding: 12px 6px;
    background: var(--sys-soft);
    border: 1px solid var(--sys-line);
    border-radius: 14px;
}

.ps-modal-stats svg {
    color: var(--sys);
}

.ps-modal-stats b {
    font-size: 18px;
    font-weight: 800;
    color: var(--st-ink);
}

.ps-modal-stats small {
    font-size: 12px;
    color: var(--st-muted);
}

.ps-modal-list {
    display: grid;
    gap: 6px;
    width: 100%;
    margin: 0;
    padding: 0;
    list-style: none;
    text-align: left;
}

.ps-modal-list li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 10px 12px;
    font-size: 14px;
    line-height: 1.45;
    color: var(--st-text);
    background: var(--st-surface);
    border-radius: 12px;
}

.ps-modal-list li.highlight {
    background: #fdf6e6;
}

.ps-modal-list svg {
    flex-shrink: 0;
    margin-top: 2px;
    color: #c29b40;
}

.ps-modal-reason,
.ps-modal-tips {
    display: grid;
    gap: 6px;
    width: 100%;
    padding: 12px 14px;
    text-align: left;
    border-radius: 14px;
}

.ps-modal-reason {
    background: #fdeee4;
    border: 1px solid #f6d3bd;
}

.ps-modal-reason b {
    font-size: 14px;
    color: #b4562a;
}

.ps-modal-reason p {
    margin: 0;
    font-size: 15px;
    line-height: 1.5;
    color: var(--st-ink);
}

.ps-modal-tips {
    background: var(--sys-soft);
}

.ps-modal-tips b {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: var(--sys-dark);
}

.ps-modal-tips ul {
    margin: 0;
    padding-left: 20px;
    font-size: 14px;
    line-height: 1.55;
    color: var(--st-text);
    list-style: disc;
}

.ps-modal-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 8px;
    width: 100%;
    margin-top: 4px;
}

.ps-modal-actions .st-btn {
    min-height: 48px;
    font-size: 15px;
}

.ps-modal-actions .st-btn--primary {
    background: var(--sys);
    border-color: var(--sys);
}

.ps-pop-enter-active,
.ps-pop-leave-active {
    transition: opacity 0.2s;
}

.ps-pop-enter-active .ps-modal,
.ps-pop-leave-active .ps-modal {
    transition: transform 0.2s;
}

.ps-pop-enter-from,
.ps-pop-leave-to {
    opacity: 0;
}

.ps-pop-enter-from .ps-modal,
.ps-pop-leave-to .ps-modal {
    transform: scale(0.92);
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

/* Panel huruf */
.ps-letters-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
}

.ps-system-tag {
    display: inline-block;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: #fff;
    background: var(--sys);
    border-radius: 6px;
}

.ps-letters-head h2 {
    margin: 4px 0 0;
    font-size: 20px;
    font-weight: 800;
    color: var(--st-ink);
}

.ps-letters-head > b {
    font-size: 26px;
    font-weight: 800;
    color: var(--sys-dark);
}

.ps-letters-head small {
    font-size: 14px;
    color: var(--st-muted);
}

.ps-progress {
    height: 8px;
    overflow: hidden;
    background: var(--st-surface);
    border-radius: 999px;
}

.ps-progress i {
    display: block;
    height: 100%;
    background: var(--sys);
    border-radius: 999px;
    transition: width 0.3s;
}

.ps-letter-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 6px;
}

.ps-letter {
    position: relative;
    display: grid;
    place-items: center;
    aspect-ratio: 1;
    font-size: 18px;
    font-weight: 800;
    color: var(--st-text);
    background: #fff;
    border: 1px solid var(--st-line);
    border-radius: 12px;
    cursor: pointer;
    transition: 0.15s;
}

.ps-letter:hover:not(:disabled) {
    border-color: var(--sys);
}

.ps-letter:disabled {
    cursor: not-allowed;
}

.ps-letter.done {
    color: var(--sys-dark);
    background: var(--sys-soft);
    border-color: var(--sys-line);
}

.ps-letter.selected {
    color: #fff;
    background: var(--sys);
    border-color: var(--sys);
    box-shadow: 0 0 0 3px var(--sys-line);
}

.ps-letter-check {
    position: absolute;
    top: 4px;
    right: 4px;
}

.ps-empty {
    margin: 0;
    font-size: 14px;
    color: var(--st-muted);
}

.ps-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 14px;
    font-size: 12px;
    color: var(--st-muted);
}

.ps-legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.ps-legend i {
    width: 12px;
    height: 12px;
    background: #fff;
    border: 1px solid var(--st-line);
    border-radius: 4px;
}

.ps-legend i.selected {
    background: var(--sys);
    border-color: var(--sys);
}

.ps-legend i.done {
    background: var(--sys-soft);
    border-color: var(--sys-line);
}

.ps-tip {
    display: flex;
    gap: 8px;
    padding: 12px;
    color: var(--sys-dark);
    background: var(--sys-soft);
    border-radius: 14px;
}

.ps-tip svg {
    flex-shrink: 0;
    margin-top: 2px;
}

.ps-tip p {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: var(--st-text);
}

.ps-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
    margin: 0;
}

.ps-stats div {
    padding: 10px 4px;
    text-align: center;
    background: var(--st-surface);
    border-radius: 12px;
}

.ps-stats dt {
    font-size: 11px;
    color: var(--st-muted);
}

.ps-stats dd {
    margin: 2px 0 0;
    font-size: 18px;
    font-weight: 800;
    color: var(--st-ink);
}

.ps-rules {
    font-size: 13px;
    color: var(--st-text);
}

.ps-rules summary {
    font-weight: 700;
    color: var(--sys-dark);
    cursor: pointer;
}

.ps-rules ul {
    margin: 8px 0 0;
    padding-left: 18px;
    line-height: 1.55;
    list-style: disc;
}

/* Responsif — mengikuti lebar area konten, bukan lebar layar, karena
   sidebar & panel aktivitas ikut memakan ruang di layar laptop */
@container (max-width: 980px) {
    .ps-header {
        grid-template-columns: 1fr;
    }

    .ps-grid {
        grid-template-columns: 1fr;
    }

    /* Ditumpuk: huruf A–Z → kamera → tips & statistik */
    .ps-side {
        display: contents;
    }

    .ps-letters {
        order: -1;
    }

    .ps-letter-grid {
        grid-template-columns: repeat(13, minmax(0, 1fr));
    }

    .ps-letter {
        font-size: 16px;
    }

}

@container (max-width: 560px) {
    .ps-letter-grid {
        grid-template-columns: repeat(7, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .ps-header {
        padding: 18px 16px;
    }

    .ps-intro h1 {
        font-size: 22px;
    }

    .ps-system {
        min-width: 0;
    }

    .ps-system-full {
        display: none;
    }

    .ps-studio,
    .ps-letters {
        padding: 14px;
    }

    .ps-target-letter {
        width: 52px;
        height: 52px;
        font-size: 28px;
    }

    .ps-target {
        gap: 6px;
    }

    .ps-nav {
        width: 38px;
        height: 38px;
    }

    .ps-target-main {
        gap: 10px;
        padding: 8px 10px;
    }

    .ps-target-main b {
        font-size: 17px;
    }

    .ps-chip-extra {
        display: none;
    }

    .ps-system-meta {
        font-size: 11px;
    }

    .ps-steps {
        grid-template-columns: 1fr;
    }
}
</style>
