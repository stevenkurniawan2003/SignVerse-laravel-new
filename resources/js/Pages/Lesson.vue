<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    Camera,
    CameraOff,
    CheckCircle,
    Flame,
    Hand,
    RotateCcw,
    Star,
    Trophy,
    X,
} from '@lucide/vue';

const props = defineProps({
    id: { type: String, default: '1' },
    lesson: { type: Object, default: () => null },
    material: { type: Object, default: () => null },
    category: { type: Object, default: () => null },
    relatedLessons: { type: Array, default: () => [] },
    ai_supported: { type: Boolean, default: true },
});

// AI baru mengenali huruf alfabet; isyarat lain belum bisa diverifikasi kamera
const aiSupported = ref(props.ai_supported);

const currentLesson = ref(props.lesson);
const currentMaterial = ref(props.material);
const related = ref(props.relatedLessons || []);

const step = ref(0);
const choice = ref('');
const checked = ref(false);
const wrong = ref(0);
const done = ref(false);

// Peragaan kamera — lesson hanya selesai bila AI menyatakan peragaan benar
const videoRef = ref(null);
const canvasRef = ref(null);
const cameraActive = ref(false);
const cameraError = ref('');
const scanning = ref(false);
const countdown = ref(0);
const attempt = ref(null);
const attemptCount = ref(0);
let mediaStream = null;
let countdownInterval = null;

const demonstrationPassed = computed(() => Boolean(attempt.value?.correct));

onMounted(async () => {
    if (!currentLesson.value) {
        try {
            const res = await fetch(`/api/lessons/${props.id}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                currentLesson.value = data.lesson;
                currentMaterial.value = data.material;
                related.value = data.relatedLessons || [];
                aiSupported.value = data.ai_supported !== false;
            }
        } catch {}
    }
});

const lessonTitleText = computed(() => {
    if (!currentLesson.value) return 'Pelajaran Isyarat';
    return `${currentLesson.value.title} (${String(currentMaterial.value?.language || 'SIBI').toUpperCase()})`;
});

const targetGesture = computed(() => currentLesson.value?.title || 'A');

const types = ['info', 'choice', 'practice'];
const currentType = computed(() => types[step.value] || 'info');
const languageLabel = computed(() => String(currentMaterial.value?.language || 'SIBI').toUpperCase());

const options = computed(() => {
    const list = [targetGesture.value];
    if (related.value && related.value.length > 0) {
        for (const item of related.value) {
            if (item.title !== targetGesture.value && !list.includes(item.title)) {
                list.push(item.title);
            }
            if (list.length >= 4) break;
        }
    }
    const fallbacks = ['A', 'B', 'C', 'D', 'E'];
    for (const f of fallbacks) {
        if (!list.includes(f)) list.push(f);
        if (list.length >= 4) break;
    }
    return list.slice(0, 4);
});

function advance() {
    step.value += 1;
    choice.value = '';
    checked.value = false;
}

// Tahap akhir hanya bisa diselesaikan setelah peragaan lulus penilaian AI
function finishLesson() {
    if (!demonstrationPassed.value) return;
    stopCamera();
    done.value = true;
    router.reload({ only: ['auth'] });
}

function chooseAnswer(option) {
    if (checked.value) return;
    choice.value = option;
}

const answerIsCorrect = computed(() => choice.value === targetGesture.value);

function verifyAnswer() {
    checked.value = true;
    if (!answerIsCorrect.value) {
        wrong.value += 1;
    }
}

function retryAnswer() {
    choice.value = '';
    checked.value = false;
}

async function startCamera() {
    cameraError.value = '';
    try {
        if (!navigator.mediaDevices?.getUserMedia) {
            cameraError.value = 'Browser ini tidak mendukung akses kamera.';
            return;
        }
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
            audio: false,
        });
        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            await videoRef.value.play();
            cameraActive.value = true;
        }
    } catch {
        cameraError.value = 'Izin kamera tidak diberikan atau kamera sedang dipakai aplikasi lain.';
        cameraActive.value = false;
    }
}

function cancelCountdown() {
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
    countdown.value = 0;
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

onBeforeUnmount(stopCamera);

function captureFrame() {
    const video = videoRef.value;
    const canvas = canvasRef.value;
    if (!cameraActive.value || !video || !canvas) return null;
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.85);
}

// Hitung mundur 3 detik agar siswa sempat menyiapkan bentuk tangan
function startDemonstration() {
    if (!cameraActive.value || scanning.value || countdown.value > 0) return;
    attempt.value = null;
    countdown.value = 3;
    countdownInterval = setInterval(() => {
        countdown.value -= 1;
        if (countdown.value <= 0) {
            cancelCountdown();
            verifyDemonstration();
        }
    }, 1000);
}

async function verifyDemonstration() {
    const frame = captureFrame();
    if (!frame) {
        attempt.value = { correct: false, message: 'Gagal mengambil gambar dari kamera. Pastikan kamera menyala.' };
        return;
    }

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
            body: JSON.stringify({
                lesson_id: currentLesson.value?.id,
                image: frame,
                mirrored: true,
            }),
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data) {
            throw new Error(data?.message || `Respons server status ${res.status}`);
        }
        attempt.value = data;
        if (data.evaluated) {
            attemptCount.value += 1;
        }
    } catch (e) {
        attempt.value = { correct: false, message: `Koneksi ke sistem deteksi terganggu: ${e.message}` };
    } finally {
        scanning.value = false;
    }
}
</script>

<template>
    <Head :title="`Pelajaran: ${lessonTitleText}`" />

    <div class="focus-layout font-sans text-ink bg-surface min-h-screen">
        <header class="lesson-header">
            <Link href="/learn" class="exit-lesson" aria-label="Keluar latihan">
                <X :size="20" />
            </Link>
            <div class="lesson-progress" role="progressbar" :aria-valuenow="step + 1" :aria-valuemax="types.length">
                <i :style="{ width: `${done ? 100 : ((step + 1) / types.length) * 100}%` }"></i>
            </div>
            <div class="step-count">
                {{ done ? types.length : step + 1 }} <small>/ {{ types.length }}</small>
            </div>
        </header>

        <section v-if="done" class="st-panel result-screen">
            <div class="result-medal">
                <Trophy :size="56" class="text-secondary" />
            </div>
            <h1>Pelajaran Selesai!</h1>
            <p>Hebat! Peragaan isyarat {{ lessonTitleText }} kamu sudah dinilai benar oleh AI.</p>

            <div class="reward-grid">
                <div>
                    <span class="reward-icon"><Star class="text-secondary" :size="22" /></span>
                    <b>+{{ attempt?.xp_earned || 0 }} XP</b>
                    <small>{{ attempt?.already_mastered ? 'Sudah dikuasai sebelumnya' : 'Hadiah Didapat' }}</small>
                </div>
                <div>
                    <span class="reward-icon"><CheckCircle class="text-primary" :size="22" /></span>
                    <b>{{ attempt?.confidence || 0 }}%</b>
                    <small>Akurasi Peragaan</small>
                </div>
                <div>
                    <span class="reward-icon"><Flame class="text-secondary" :size="22" /></span>
                    <b>{{ attempt?.streak || 0 }} Hari</b>
                    <small>Streak Aktif</small>
                </div>
            </div>

            <ul class="lesson-rewards">
                <li>
                    <CheckCircle :size="15" />
                    <span>Kuis dijawab benar {{ wrong === 0 ? 'pada percobaan pertama' : `setelah ${wrong} kali salah` }}, peragaan berhasil pada percobaan ke-{{ attemptCount }}.</span>
                </li>
                <li v-if="attempt?.challenge_completed">
                    <Trophy :size="15" />
                    <span>Tantangan harian selesai! Bonus XP tantangan sudah ditambahkan.</span>
                </li>
                <li v-for="badge in attempt?.new_badges || []" :key="badge">
                    <Award :size="15" />
                    <span>Lencana baru terbuka: <b>{{ badge }}</b></span>
                </li>
                <li v-if="attempt?.leveled_up">
                    <Trophy :size="15" />
                    <span>Naik level! Sekarang kamu <b>{{ attempt.level?.name }}</b>.</span>
                </li>
            </ul>

            <Link href="/learn" class="st-btn st-btn--primary st-btn--block">
                <span>Kembali ke Kurikulum</span>
                <ArrowRight :size="16" />
            </Link>
        </section>

        <section v-else-if="currentLesson" class="st-panel exercise-card">
            <div class="exercise-kicker">
                <span class="activity-badge"><Hand :size="14" /></span>
                <span>{{ String(currentMaterial?.language || 'SIBI').toUpperCase() }} · {{ currentMaterial?.title || 'Kurikulum' }}</span>
                <span class="st-badge st-badge--gold exercise-xp">+{{ currentLesson.xp_reward || 10 }} XP</span>
            </div>

            <!-- Step 1: Info Gesture -->
            <div v-if="currentType === 'info'">
                <h1>Amati Bentuk Isyarat {{ currentLesson.title }}</h1>
                <div class="gesture-demo">
                    <span class="gesture-hand"><Hand :size="80" class="text-primary" /></span>
                    <span class="gesture-letter">{{ currentLesson.title }}</span>
                </div>
                <p class="exercise-detail">Peragakan bentuk jemari untuk isyarat <strong>{{ currentLesson.title }}</strong> sesuai kaidah {{ String(currentMaterial?.language || 'SIBI').toUpperCase() }}.</p>
                <button type="button" class="st-btn st-btn--primary st-btn--block exercise-submit" @click="advance">
                    <span>Saya Siap Berlatih</span>
                    <ArrowRight :size="16" />
                </button>
            </div>

            <!-- Step 2: Kuis pemahaman -->
            <div v-else-if="currentType === 'choice'">
                <h1>Isyarat yang baru kamu amati melambangkan apa?</h1>
                <div class="gesture-demo">
                    <span class="gesture-hand"><Hand :size="80" class="text-primary" /></span>
                </div>
                <div class="answer-grid">
                    <button
                        v-for="opt in options"
                        :key="opt"
                        type="button"
                        class="answer-option"
                        :class="{
                            selected: choice === opt,
                            correct: checked && opt === targetGesture,
                            incorrect: checked && choice === opt && opt !== targetGesture,
                        }"
                        @click="chooseAnswer(opt)"
                    >
                        <span class="choice-letter">{{ opt }}</span>
                        <span>Isyarat {{ opt }}</span>
                    </button>
                </div>

                <div v-if="!checked" class="step-actions">
                    <button type="button" class="st-btn st-btn--primary st-btn--block exercise-submit" :disabled="!choice" @click="verifyAnswer">
                        Periksa Jawaban
                    </button>
                </div>
                <div v-else class="step-actions">
                    <div class="answer-feedback" :class="answerIsCorrect ? 'good' : 'bad'">
                        {{ answerIsCorrect ? 'Jawaban benar! Sekarang saatnya memperagakan isyarat ini.' : 'Kurang tepat. Coba ingat lagi bentuk isyarat yang tadi kamu amati.' }}
                    </div>
                    <button v-if="answerIsCorrect" type="button" class="st-btn st-btn--primary st-btn--block exercise-submit" @click="advance">
                        <span>Lanjut ke Peragaan</span>
                        <ArrowRight :size="16" />
                    </button>
                    <button v-else type="button" class="st-btn st-btn--secondary st-btn--block exercise-submit" @click="retryAnswer">
                        <RotateCcw :size="16" />
                        <span>Jawab Lagi</span>
                    </button>
                </div>
            </div>

            <!-- Step 3 (isyarat belum didukung AI): tidak bisa diverifikasi kamera -->
            <div v-else-if="currentType === 'practice' && !aiSupported">
                <h1>Peragakan Isyarat {{ targetGesture }}</h1>
                <div class="practice-feedback retry" role="status">
                    <b>Isyarat ini belum bisa dinilai AI.</b>
                    <small>Saat ini AI SignTech baru mengenali isyarat huruf alfabet. Latih isyarat {{ targetGesture }} bersama guru dulu; pelajaran ini akan bisa diselesaikan dan memberi XP setelah AI mendukungnya.</small>
                </div>
                <Link href="/learn" class="st-btn st-btn--primary st-btn--block">
                    <span>Kembali ke Materi</span>
                    <ArrowRight :size="16" />
                </Link>
            </div>

            <!-- Step 3: Peragaan di depan kamera, dinilai AI -->
            <div v-else-if="currentType === 'practice'">
                <h1>Peragakan Isyarat {{ targetGesture }}</h1>
                <p class="exercise-detail">Nyalakan kamera, tekan tombol peragaan, lalu tahan bentuk tangan isyarat {{ targetGesture }} ({{ languageLabel }}) sampai foto diambil. Pelajaran selesai setelah AI menyatakan peragaanmu benar.</p>
                <div class="practice-target">
                    <span>TARGET</span>
                    <b>Isyarat {{ targetGesture }}</b>
                </div>

                <canvas ref="canvasRef" class="hidden-canvas"></canvas>
                <div class="camera-preview" :class="{ scanning }">
                    <video v-show="cameraActive" ref="videoRef" class="camera-video" autoplay playsinline muted></video>
                    <div v-if="!cameraActive" class="camera-frame">
                        <CameraOff :size="40" />
                        <span class="camera-label">KAMERA BELUM AKTIF</span>
                    </div>
                    <div v-if="countdown > 0" class="camera-overlay">
                        <b>{{ countdown }}</b>
                        <span>Tahan posisi tangan...</span>
                    </div>
                    <div v-else-if="scanning" class="camera-overlay">
                        <span>MEMERIKSA ISYARAT...</span>
                    </div>
                </div>

                <div v-if="cameraError" class="practice-feedback retry" role="alert">
                    <b>{{ cameraError }}</b>
                </div>

                <div v-if="attempt" class="practice-feedback" :class="demonstrationPassed ? 'success' : 'retry'" role="status">
                    <b>{{ demonstrationPassed ? `Benar! Isyarat ${targetGesture} dikenali dengan akurasi ${attempt.confidence}%.` : 'Peragaan belum berhasil.' }}</b>
                    <small v-if="!demonstrationPassed && attempt.message">{{ attempt.message }}</small>
                </div>

                <div class="practice-actions">
                    <button v-if="!cameraActive" type="button" class="st-btn st-btn--secondary" @click="startCamera">
                        <Camera :size="16" />
                        <span>Nyalakan Kamera</span>
                    </button>
                    <button
                        v-else
                        type="button"
                        class="st-btn st-btn--secondary"
                        :disabled="scanning || countdown > 0"
                        @click="startDemonstration"
                    >
                        <RotateCcw v-if="attempt" :size="16" />
                        <Camera v-else :size="16" />
                        <span>{{ countdown > 0 ? `Bersiap (${countdown})...` : scanning ? 'Memeriksa...' : attempt ? 'Peragakan Lagi' : 'Mulai Peragaan' }}</span>
                    </button>
                    <button
                        type="button"
                        class="st-btn st-btn--primary"
                        :disabled="!demonstrationPassed"
                        :title="demonstrationPassed ? '' : 'Peragakan isyarat dengan benar terlebih dahulu'"
                        @click="finishLesson"
                    >
                        <span>Selesaikan Pelajaran</span>
                        <ArrowRight :size="16" />
                    </button>
                </div>
            </div>
        </section>

        <section v-else class="st-panel exercise-card">
            <div class="st-empty">
                <span>Materi tidak ditemukan atau sedang dimuat.</span>
                <Link href="/learn" class="st-btn st-btn--outline">Kembali ke Materi</Link>
            </div>
        </section>
    </div>
</template>

<style scoped>
.focus-layout {
    max-width: 680px;
    margin: 0 auto;
    padding: 24px 16px;
}
.lesson-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}
.exit-lesson {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #e2ede6;
    display: grid;
    place-items: center;
    color: #52685d;
}
.lesson-progress {
    flex: 1;
    height: 8px;
    background: #eef3f0;
    border-radius: 8px;
    overflow: hidden;
}
.lesson-progress i {
    display: block;
    height: 100%;
    background: #34B182;
    transition: width 0.3s ease;
}
.step-count {
    font-size: 14px;
    font-weight: 700;
    color: #52685d;
}
.exercise-card {
    padding: 28px;
    border-radius: 20px;
}
.step-actions {
    margin-top: 18px;
}
.step-actions .answer-feedback {
    margin: 0 0 12px;
}
.exercise-kicker {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    color: var(--st-muted);
    margin-bottom: 14px;
}
.exercise-xp {
    margin-left: auto;
}
.exercise-card h1 {
    font-size: 22px;
    font-weight: 800;
    color: #14221d;
    margin: 0 0 16px;
}
.gesture-demo {
    height: 180px;
    background: #f6fbf8;
    border: 1px solid #d5f3e5;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    margin-bottom: 16px;
}
.gesture-letter {
    position: absolute;
    top: 14px;
    right: 18px;
    font-size: 24px;
    font-weight: 800;
    color: #34B182;
}
.exercise-detail {
    font-size: 15px;
    color: #52685d;
    line-height: 1.6;
    margin-bottom: 20px;
}
.answer-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 16px;
}
.answer-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: #ffffff;
    border: 1.5px solid #e2ede6;
    border-radius: 12px;
    min-height: 52px;
    font-size: 15px;
    font-weight: 700;
    color: #14221d;
    cursor: pointer;
    transition: 0.16s;
}
.answer-option.selected {
    border-color: #34B182;
    background: #eefaf4;
}
.answer-option.correct {
    border-color: #34B182;
    background: #d5f3e5;
    color: #1e7253;
}
.answer-option.incorrect {
    border-color: #d9534f;
    background: #fdf2f2;
    color: #d9534f;
}
.choice-letter {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #f1f5f3;
    display: grid;
    place-items: center;
    font-size: 12px;
}
.practice-target {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #eefaf4;
    padding: 10px 14px;
    border-radius: 10px;
    margin-bottom: 14px;
    font-size: 14px;
    font-weight: 700;
    color: #1e7253;
}
.camera-preview {
    position: relative;
    height: 260px;
    overflow: hidden;
    background: #14221d;
    border-radius: 14px;
    display: grid;
    place-items: center;
    color: #ffffff;
    margin-bottom: 14px;
}
.camera-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1);
}
.camera-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: rgba(20, 34, 29, 0.55);
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.camera-overlay b {
    font-size: 56px;
    line-height: 1;
}
.hidden-canvas {
    display: none;
}
.practice-feedback {
    display: grid;
    gap: 4px;
    margin-bottom: 14px;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 14px;
}
.practice-feedback.success {
    background: #eefaf4;
    color: #1e7253;
}
.practice-feedback.retry {
    background: #fff5eb;
    color: #9a6a2f;
}
.practice-feedback small {
    font-size: 13px;
    color: var(--st-text);
}
.lesson-rewards {
    display: grid;
    gap: 8px;
    margin: 0 0 20px;
    padding: 0;
    list-style: none;
    text-align: left;
}
.lesson-rewards li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 10px 12px;
    font-size: 14px;
    color: var(--st-text);
    background: #f8faf9;
    border-radius: 10px;
}
.lesson-rewards svg {
    flex-shrink: 0;
    margin-top: 2px;
    color: #C29B40;
}
.camera-frame {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.camera-label {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: #ade6cc;
}
.practice-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.result-screen {
    text-align: center;
    padding: 36px 24px;
    border-radius: 20px;
}
.result-medal {
    width: 88px;
    height: 88px;
    border-radius: 50%;
    background: #fdf9ef;
    display: grid;
    place-items: center;
    margin: 0 auto 16px;
}
.reward-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin: 24px 0;
}
.result-screen h1 {
    margin: 0 0 8px;
    font-size: 24px;
    font-weight: 800;
}
.result-screen > p {
    margin: 0;
    font-size: 15px;
    line-height: 1.6;
    color: var(--st-text);
}
.reward-grid b {
    font-size: 18px;
}
.reward-grid small {
    font-size: 12px;
    color: var(--st-muted);
}
.reward-grid > div {
    background: #f8faf9;
    border: 1px solid #eef3f0;
    border-radius: 12px;
    padding: 14px 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
@media (max-width: 520px) {
    .answer-grid,
    .practice-actions {
        grid-template-columns: 1fr;
    }
    .exercise-card {
        padding: 20px;
    }
}
</style>
