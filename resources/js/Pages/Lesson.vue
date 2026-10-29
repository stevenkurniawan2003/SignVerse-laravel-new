<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    Camera,
    Check,
    CheckCircle,
    Flame,
    Hand,
    RotateCcw,
    Star,
    Target,
    Trophy,
    X,
} from '@lucide/vue';

const props = defineProps({
    id: { type: String, default: '1' },
    lesson: { type: Object, default: () => null },
    material: { type: Object, default: () => null },
    category: { type: Object, default: () => null },
    relatedLessons: { type: Array, default: () => [] },
});

const page = usePage();
const currentStreak = computed(() => page.props.auth?.student?.current_streak || 1);

const currentLesson = ref(props.lesson);
const currentMaterial = ref(props.material);
const related = ref(props.relatedLessons || []);

const step = ref(0);
const choice = ref('');
const checked = ref(false);
const scanning = ref(false);
const result = ref(null);
const wrong = ref(0);
const done = ref(false);
const earnedXp = ref(0);

onMounted(async () => {
    if (!currentLesson.value) {
        try {
            const res = await fetch(`/api/lessons/${props.id}`);
            if (res.ok) {
                const data = await res.json();
                currentLesson.value = data.lesson;
                currentMaterial.value = data.material;
                related.value = data.relatedLessons || [];
            }
        } catch {}
    }
});

const lessonTitleText = computed(() => {
    if (!currentLesson.value) return 'Pelajaran Isyarat';
    return `${currentLesson.value.title} (${String(currentMaterial.value?.language || 'SIBI').toUpperCase()})`;
});

const targetGesture = computed(() => currentLesson.value?.title || 'A');

const types = ['info', 'choice', 'select', 'practice'];
const currentType = computed(() => types[step.value] || 'info');

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

async function advance() {
    if (step.value === types.length - 1) {
        const amount = currentLesson.value?.xp_reward || 10;
        earnedXp.value = amount;
        done.value = true;
        try {
            await fetch(`/api/lessons/${currentLesson.value?.id || props.id}/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            });
            router.reload({ only: ['auth'] });
        } catch {}
        return;
    }
    step.value += 1;
    choice.value = '';
    checked.value = false;
    result.value = null;
}

function chooseAnswer(option) {
    if (checked.value) return;
    choice.value = option;
}

function verifyAnswer() {
    checked.value = true;
    if (choice.value !== targetGesture.value) {
        wrong.value += 1;
    }
}

async function verifyPractice() {
    scanning.value = true;
    result.value = null;

    try {
        const res = await fetch('/api/practice/attempt', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                target_letter: targetGesture.value,
                system: String(currentMaterial.value?.language || 'sibi').toLowerCase(),
            }),
        });
        const data = res.ok ? await res.json() : null;
        result.value = Boolean(data?.correct);
    } catch (e) {
        result.value = false;
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

        <section v-if="done" class="result-screen card">
            <div class="result-medal">
                <Trophy :size="56" class="text-secondary" />
            </div>
            <h1>Pelajaran Selesai!</h1>
            <p>Hebat! Kamu telah menuntaskan seluruh tahapan latihan untuk isyarat {{ lessonTitleText }}.</p>

            <div class="reward-grid">
                <div>
                    <span class="reward-icon"><Star class="text-secondary" :size="22" /></span>
                    <b>+{{ earnedXp }} XP</b>
                    <small>Hadiah Didapat</small>
                </div>
                <div>
                    <span class="reward-icon"><CheckCircle class="text-primary" :size="22" /></span>
                    <b>{{ Math.max(0, 100 - wrong * 20) }}%</b>
                    <small>Akurasi Sesi</small>
                </div>
                <div>
                    <span class="reward-icon"><Flame class="text-secondary" :size="22" /></span>
                    <b>{{ currentStreak }} Hari</b>
                    <small>Streak Aktif</small>
                </div>
            </div>

            <Link href="/learn" class="primary-button">
                <span>Kembali ke Kurikulum</span>
                <ArrowRight :size="16" />
            </Link>
        </section>

        <section v-else-if="currentLesson" class="exercise-card card">
            <div class="exercise-kicker">
                <span class="activity-badge"><Hand :size="14" /></span>
                <span>{{ String(currentMaterial?.language || 'SIBI').toUpperCase() }} · {{ currentMaterial?.title || 'Kurikulum' }}</span>
                <span class="exercise-xp">+{{ currentLesson.xp_reward || 10 }} XP</span>
            </div>

            <!-- Step 1: Info Gesture -->
            <div v-if="currentType === 'info'">
                <h1>Amati Bentuk Isyarat {{ currentLesson.title }}</h1>
                <div class="gesture-demo">
                    <span class="gesture-hand"><Hand :size="80" class="text-primary" /></span>
                    <span class="gesture-letter">{{ currentLesson.title }}</span>
                </div>
                <p class="exercise-detail">Peragakan bentuk jemari untuk isyarat <strong>{{ currentLesson.title }}</strong> sesuai kaidah {{ String(currentMaterial?.language || 'SIBI').toUpperCase() }}.</p>
                <button type="button" class="primary-button exercise-submit" @click="advance">
                    <span>Saya Siap Berlatih</span>
                    <ArrowRight :size="16" />
                </button>
            </div>

            <!-- Step 2: Choice Verification -->
            <div v-else-if="currentType === 'choice' || currentType === 'select'">
                <h1>Pilih Isyarat yang Sesuai</h1>
                <div class="gesture-demo">
                    <span class="gesture-hand"><Hand :size="80" class="text-primary" /></span>
                    <span class="gesture-letter">{{ targetGesture }}</span>
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

                <div v-if="!checked" class="mt-4">
                    <button type="button" class="primary-button exercise-submit" :disabled="!choice" @click="verifyAnswer">
                        Periksa Jawaban
                    </button>
                </div>
                <div v-else class="mt-4">
                    <div class="answer-feedback" :class="choice === targetGesture ? 'good' : 'bad'">
                        {{ choice === targetGesture ? 'Jawaban Benar! Formasi tangan sangat tepat.' : `Kurang tepat. Bentuk ini adalah ${targetGesture}.` }}
                    </div>
                    <button type="button" class="primary-button exercise-submit mt-3" @click="advance">
                        <span>Lanjut ke Tahap Berikutnya</span>
                        <ArrowRight :size="16" />
                    </button>
                </div>
            </div>

            <!-- Step 3: Practice Verification -->
            <div v-else-if="currentType === 'practice'">
                <h1>Peragakan Isyarat Huruf {{ targetGesture }}</h1>
                <p class="exercise-detail">Arahkan tangan ke kamera atau buka AI Practice Studio untuk evaluasi real-time langsung melalui Live AI Service.</p>
                <div class="practice-target">
                    <span>TARGET</span>
                    <b>Isyarat {{ targetGesture }}</b>
                </div>

                <div class="camera-preview" :class="{ scanning }">
                    <div class="camera-frame">
                        <Hand :size="48" class="text-primary" />
                        <span class="camera-label">{{ scanning ? 'MEMERIKSA ISYARAT...' : 'POSISIKAN TANGAN DI BIDANG KAMERA' }}</span>
                    </div>
                </div>

                <div v-if="result !== null" class="practice-feedback" :class="result ? 'success' : 'retry'">
                    <b>{{ result ? 'Bagus sekali! Gesture terdeteksi sesuai kaidah.' : 'Tangan belum terdeteksi. Posisikan tangan di depan kamera.' }}</b>
                </div>

                <div class="practice-actions">
                    <Link href="/practice" class="secondary-button text-center">
                        <Camera :size="14" /> Buka Studio Kamera
                    </Link>
                    <button type="button" class="primary-button" :disabled="scanning" @click="advance">
                        <span>Selesaikan Modul</span>
                        <ArrowRight :size="16" />
                    </button>
                </div>
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
    width: 36px;
    height: 36px;
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
    font-size: 12px;
    font-weight: 700;
    color: #52685d;
}
.exercise-card {
    padding: 28px;
    border-radius: 20px;
}
.exercise-kicker {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 700;
    color: #859c90;
    margin-bottom: 12px;
}
.exercise-xp {
    margin-left: auto;
    background: #fdf9ef;
    color: #C29B40;
    padding: 3px 8px;
    border-radius: 6px;
}
.exercise-card h1 {
    font-size: 20px;
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
    font-size: 13px;
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
    font-size: 13px;
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
    font-size: 12px;
    font-weight: 700;
    color: #1e7253;
}
.camera-preview {
    height: 180px;
    background: #14221d;
    border-radius: 14px;
    display: grid;
    place-items: center;
    color: #ffffff;
    margin-bottom: 14px;
}
.camera-frame {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.camera-label {
    font-size: 10px;
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
</style>
