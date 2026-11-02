<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Hand, Lightbulb, RotateCcw, X } from '@lucide/vue';
import PracticeCamera from '../Layouts/PracticeCamera.vue';
import PracticeResultModal from '../Layouts/PracticeResultModal.vue';
import { shouldAnimatePageEntry } from '../pageTransition';

const isPageEntering = ref(shouldAnimatePageEntry(usePage().url));

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

// Peragaan kamera — lesson hanya selesai bila AI menyatakan peragaan benar
const cameraRef = ref(null);
const result = ref(null);
const resultOpen = ref(false);
const passed = ref(false);

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
const handTip = computed(() =>
    languageLabel.value === 'BISINDO'
        ? 'BISINDO umumnya memakai dua tangan. Pastikan kedua tangan masuk ke dalam bingkai kamera.'
        : 'SIBI memakai satu tangan. Pastikan hanya satu tangan yang terlihat di kamera.'
);

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

    // Acak agar jawaban benar tidak selalu berada di pilihan pertama
    const shuffled = list.slice(0, 4);
    for (let index = shuffled.length - 1; index > 0; index--) {
        const swapIndex = Math.floor(Math.random() * (index + 1));
        [shuffled[index], shuffled[swapIndex]] = [shuffled[swapIndex], shuffled[index]];
    }
    return shuffled;
});

function advance() {
    step.value += 1;
    choice.value = '';
    checked.value = false;
}

function chooseAnswer(option) {
    if (checked.value) return;
    choice.value = option;
}

const answerIsCorrect = computed(() => choice.value === targetGesture.value);

function verifyAnswer() {
    checked.value = true;
}

function retryAnswer() {
    choice.value = '';
    checked.value = false;
}

// Pelajaran berikutnya dalam modul yang sama
const nextLesson = computed(() => {
    const position = related.value.findIndex((item) => item.id === currentLesson.value?.id);
    return position >= 0 ? related.value[position + 1] ?? null : null;
});

const continueLabel = computed(() => (nextLesson.value ? `Lanjut ke Huruf ${nextLesson.value.title}` : 'Kembali ke Materi'));

function continueLesson() {
    router.visit(nextLesson.value ? `/lesson/${nextLesson.value.id}` : '/learn');
}

function onDetectionResult(detection) {
    result.value = detection;
    resultOpen.value = true;
    if (detection.success) {
        passed.value = true;
    }
}

function retryFromPopup() {
    resultOpen.value = false;
    cameraRef.value?.startDetection();
}
</script>

<template>
    <Head :title="`Pelajaran: ${lessonTitleText}`" />

    <div
        class="focus-layout font-sans text-ink bg-surface min-h-screen"
        :class="{ 'page-slide-in': isPageEntering }"
        @animationend.self="isPageEntering = false"
    >
        <header class="lesson-header">
            <Link href="/learn" class="exit-lesson" aria-label="Keluar latihan">
                <X :size="20" />
            </Link>
            <div class="lesson-progress" role="progressbar" :aria-valuenow="step + 1" :aria-valuemax="types.length">
                <i :style="{ width: `${passed ? 100 : (step / types.length) * 100}%` }"></i>
            </div>
            <div class="step-count">
                {{ step + 1 }} <small>/ {{ types.length }}</small>
            </div>
        </header>

        <section v-if="currentLesson" class="st-panel exercise-card">
            <div class="exercise-kicker">
                <span class="activity-badge"><Hand :size="14" /></span>
                <span>{{ languageLabel }} · {{ currentMaterial?.title || 'Kurikulum' }}</span>
                <span v-if="aiSupported" class="st-badge st-badge--gold exercise-xp">+{{ currentLesson.xp_reward || 10 }} XP</span>
            </div>

            <!-- Step 1: Info Gesture -->
            <div v-if="currentType === 'info'">
                <h1>Amati Bentuk Isyarat {{ currentLesson.title }}</h1>
                <div class="gesture-demo">
                    <span class="gesture-hand"><Hand :size="80" class="text-primary" /></span>
                    <span class="gesture-letter">{{ currentLesson.title }}</span>
                </div>
                <p class="exercise-detail">Peragakan bentuk jemari untuk isyarat <strong>{{ currentLesson.title }}</strong> sesuai kaidah {{ languageLabel }}.</p>
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
                <div class="practice-feedback" role="status">
                    <b>Isyarat ini belum bisa dinilai AI.</b>
                    <small>Saat ini AI SignTech baru mengenali isyarat huruf alfabet. Latih isyarat {{ targetGesture }} bersama guru dulu; pelajaran ini akan bisa diselesaikan dan memberi XP setelah AI mendukungnya.</small>
                </div>
                <Link href="/learn" class="st-btn st-btn--primary st-btn--block">
                    <span>Kembali ke Materi</span>
                    <ArrowRight :size="16" />
                </Link>
            </div>

            <!-- Step 3: Peragaan di depan kamera, dinilai AI -->
            <div v-else-if="currentType === 'practice'" class="practice-step">
                <h1>Peragakan Isyarat {{ targetGesture }}</h1>
                <div class="hand-tip">
                    <Lightbulb :size="16" />
                    <p>{{ handTip }}</p>
                </div>

                <PracticeCamera
                    ref="cameraRef"
                    :lesson-id="currentLesson.id"
                    :letter="targetGesture"
                    :system="languageLabel"
                    :attempted="Boolean(result)"
                    @result="onDetectionResult"
                />

                <button v-if="passed" type="button" class="st-btn st-btn--secondary st-btn--block" @click="continueLesson">
                    <span>{{ continueLabel }}</span>
                    <ArrowRight :size="16" />
                </button>
            </div>
        </section>

        <section v-else class="st-panel exercise-card">
            <div class="st-empty">
                <span>Materi tidak ditemukan atau sedang dimuat.</span>
                <Link href="/learn" class="st-btn st-btn--outline">Kembali ke Materi</Link>
            </div>
        </section>

        <PracticeResultModal
            :open="resultOpen"
            :result="result"
            :next-label="continueLabel"
            @close="resultOpen = false"
            @retry="retryFromPopup"
            @next="continueLesson"
        />
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
.practice-step {
    display: grid;
    gap: 14px;
}
.practice-step h1 {
    margin: 0;
}
.hand-tip {
    display: flex;
    gap: 8px;
    padding: 12px;
    color: #1e7253;
    background: #eefaf4;
    border-radius: 14px;
}
.hand-tip svg {
    flex-shrink: 0;
    margin-top: 2px;
}
.hand-tip p {
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
    color: var(--st-text);
}
.practice-feedback {
    display: grid;
    gap: 4px;
    margin-bottom: 14px;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 14px;
    background: #fff5eb;
    color: #9a6a2f;
}
.practice-feedback small {
    font-size: 13px;
    color: var(--st-text);
}
@media (max-width: 520px) {
    .answer-grid {
        grid-template-columns: 1fr;
    }
    .exercise-card {
        padding: 20px;
    }
}
</style>
