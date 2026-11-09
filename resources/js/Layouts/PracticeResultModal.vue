<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    ArrowRight,
    Award,
    Check,
    CheckCircle,
    ClipboardCheck,
    Flame,
    Lightbulb,
    RotateCcw,
    Star,
    Trophy,
    X,
} from '@lucide/vue';
import SpeakButton from './SpeakButton.vue';
import { percentText, speak, stopSpeech } from '../speech';

const props = defineProps({
    open: { type: Boolean, default: false },
    result: { type: Object, default: null },
    nextLabel: { type: String, default: '' },
    repeatAction: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'retry', 'repeat', 'next']);

const retryButtonRef = ref(null);
const nextButtonRef = ref(null);
const modalRef = ref(null);
let previousFocus = null;
const isSpellingAttempt = computed(() => Boolean(props.result?.spelling?.word));
const isSpellingComplete = computed(() => isSpellingAttempt.value && props.result.spelling.word_completed === true);
// Satu huruf benar di tengah ejaan (bukan huruf terakhir)
const isLetterStep = computed(() => Boolean(props.result?.success && props.result?.letterStep && isSpellingAttempt.value && !isSpellingComplete.value));
const spellingKindTitle = computed(() => (props.result?.spellingKind === 'frasa' ? 'Frasa' : 'Kata'));
const passScore = computed(() => Number(props.result?.passScore) || 60);
const wordSpeech = computed(() => String(props.result?.spelling?.word || '').toLowerCase());

/**
 * Penjelasan kegagalan untuk pop-up: apa yang salah + tips perbaikan,
 * disesuaikan dengan status deteksi dari AI Service.
 */
const failure = computed(() => {
    const r = props.result;
    if (!r || r.success) return null;

    const detectionStatus = String(r.detectionStatus || '');
    const unreachable = ['service_unreachable', 'http_error'].includes(detectionStatus) || Number(r.httpStatus) >= 500;
    if (unreachable) {
        return {
            title: 'AI sedang tidak bisa dihubungi',
            reason: 'Isyaratmu belum sempat dinilai karena layanan AI tidak merespons. Ini bukan kesalahanmu.',
            tips: ['Periksa koneksi internet.', 'Tunggu sebentar, lalu coba lagi.'],
        };
    }

    if (r.requestFailed) {
        return {
            title: 'Latihan belum dapat diproses',
            reason: r.message || 'Server menolak percobaan ini, jadi huruf belum dinilai.',
            tips: ['Muat ulang materi bila sesi ejaan sudah lama terbuka.', 'Pastikan kamu berlatih dari akun siswa yang benar, lalu coba lagi.'],
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
        reason: `Bentuk tanganmu mirip huruf ${r.letter}, tetapi akurasinya baru ${r.confidence}% (minimal ${passScore.value}%).`,
        tips: ['Tahan tangan lebih stabil tanpa bergerak.', 'Hadapkan telapak tangan dengan jelas ke kamera.'],
    };
});

/*
 * Isyarat → suara: huruf yang dibaca AI dari kamera diucapkan, lalu
 * ringkasan hasilnya. Dibacakan otomatis saat pop-up terbuka bila
 * suara otomatis aktif, dan bisa diputar ulang lewat tombol.
 */
const recognizedSpeech = computed(() => (props.result?.recognized ? `Huruf ${props.result.recognized}` : ''));

const resultSpeech = computed(() => {
    const r = props.result;
    if (!r) return '';

    if (isLetterStep.value) {
        return `Huruf ${r.letter} benar! Akurasi ${percentText(r.confidence)}. `
            + `${r.spelling.letters_done} dari ${r.spelling.letters.length} huruf selesai. Berikutnya huruf ${r.spelling.current_letter}.`;
    }

    if (r.success) {
        const parts = isSpellingComplete.value
            ? [
                `${spellingKindTitle.value} ${r.spelling.word} berhasil dieja.`,
                `Semua ${r.spelling.letters.length} huruf sudah benar.`,
                `Rata-rata akurasi ${percentText(r.confidence)}.`,
                `AI menilai setiap huruf dalam ejaan jari, bukan satu isyarat ${r.spellingKind || 'kata'} utuh.`,
            ]
            : [
                `Isyaratmu terbaca huruf ${r.letter}. Benar!`,
                `Akurasi ${percentText(r.confidence)}.`,
            ];
        if (r.xp > 0) parts.push(`Kamu mendapat ${r.xp} XP.`);
        else if (r.alreadyMastered) parts.push(isSpellingAttempt.value ? `${spellingKindTitle.value} ini sudah kamu kuasai sebelumnya.` : 'Huruf ini sudah kamu kuasai sebelumnya.');
        if (r.leveledUp && r.levelName) parts.push(`Selamat, kamu naik level menjadi ${r.levelName}.`);
        (r.assignmentsCompleted || []).forEach((task) => parts.push(`Tugas ${task.title} selesai.`));
        if (r.challengeCompleted) parts.push('Tantangan harian selesai.');
        return parts.join(' ');
    }

    const info = failure.value;
    if (!info) return '';
    const firstTip = info.tips[0] || '';

    if (r.recognized && r.recognized !== r.letter) {
        return `Isyaratmu terbaca huruf ${r.recognized}, padahal targetnya huruf ${r.letter}. ${firstTip}`;
    }
    if (r.recognized) {
        return `Isyaratmu terbaca huruf ${r.recognized}, tetapi akurasinya baru ${percentText(r.confidence)}. Minimal ${passScore.value} persen. ${firstTip}`;
    }
    return `${info.title}. ${info.reason} ${firstTip}`;
});

function onNext() {
    emit(props.nextLabel ? 'next' : 'close');
}

function onRepeat() {
    emit(props.repeatAction ? 'repeat' : 'close');
}

function onKeydown(event) {
    if (!props.open) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
        return;
    }

    if (event.key !== 'Tab' || !modalRef.value) return;

    const buttons = modalRef.value.querySelectorAll('button:not(:disabled), a[href], [tabindex="0"]');
    const first = buttons[0];
    const last = buttons[buttons.length - 1];
    if (!first) return;

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && (document.activeElement === last || !modalRef.value.contains(document.activeElement))) {
        event.preventDefault();
        first.focus();
    }
}

watch(() => props.open, async (open) => {
    if (!open) {
        stopSpeech();
        await nextTick();
        if (!props.open) {
            if (previousFocus?.isConnected) previousFocus.focus();
            previousFocus = null;
        }
        return;
    }
    previousFocus = document.activeElement;
    await nextTick();
    if (!props.open) return;
    (props.result?.success ? nextButtonRef.value : retryButtonRef.value)?.focus();
    speak(resultSpeech.value, { auto: true });
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    stopSpeech();
});
</script>

<template>
    <!-- Di-teleport ke body agar menutupi seluruh layar -->
        <Teleport to="body">
        <Transition name="ps-pop">
            <div v-if="open && result" class="ps-modal-backdrop" @click.self="emit('close')">
                <div
                    ref="modalRef"
                    class="ps-modal"
                    :class="[result.success ? 'is-success' : 'is-retry', result.system === 'BISINDO' ? 'ps--bisindo' : 'ps--sibi']"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="ps-modal-title"
                >
                    <button type="button" class="ps-modal-close" aria-label="Tutup" @click="emit('close')">
                        <X :size="18" />
                    </button>

                    <!-- Satu huruf benar di tengah ejaan: jeda sebelum huruf berikutnya -->
                    <template v-if="isLetterStep">
                        <span class="ps-modal-icon"><Check :size="40" /></span>
                        <h2 id="ps-modal-title">Huruf {{ result.letter }} benar!</h2>
                        <p class="ps-modal-sub">
                            Ejaan {{ result.spellingKind || 'kata' }} {{ result.spelling.word }} ·
                            {{ result.spelling.letters_done }} dari {{ result.spelling.letters.length }} huruf selesai
                        </p>

                        <div class="ps-spelling-summary">
                            <div class="ps-spelling-letters" :aria-label="`Progres ejaan ${result.spelling.word}`">
                                <span
                                    v-for="(letter, index) in result.spelling.letters"
                                    :key="index"
                                    :class="{
                                        'is-pending': index > result.spelling.letter_index,
                                        'is-next': index === result.spelling.letter_index,
                                    }"
                                >
                                    {{ letter }} <Check v-if="index < result.spelling.letters_done" :size="11" aria-hidden="true" />
                                </span>
                            </div>
                            <b>Berikutnya: huruf {{ result.spelling.current_letter }}</b>
                        </div>

                        <div class="ps-modal-voice">
                            <SpeakButton :text="resultSpeech" label="Bacakan hasil" />
                        </div>

                        <div class="ps-modal-stats ps-modal-stats--single">
                            <div>
                                <CheckCircle :size="20" />
                                <b>{{ result.confidence }}%</b>
                                <small>Akurasi huruf {{ result.letter }}</small>
                            </div>
                        </div>

                        <div class="ps-modal-actions">
                            <button type="button" class="st-btn st-btn--secondary" @click="emit('close')">
                                Kembali
                            </button>
                            <button ref="nextButtonRef" type="button" class="st-btn st-btn--primary" @click="onNext">
                                {{ nextLabel }} <ArrowRight :size="16" />
                            </button>
                        </div>
                    </template>

                    <!-- Benar -->
                    <template v-else-if="result.success">
                        <span class="ps-modal-icon"><Check :size="40" /></span>
                        <h2 id="ps-modal-title">
                            {{ isSpellingComplete ? `${spellingKindTitle} ${result.spelling.word} berhasil dieja!` : `Hebat! Isyarat ${result.letter} benar` }}
                        </h2>
                        <p class="ps-modal-sub">
                            {{ isSpellingComplete
                                ? `AI menilai setiap huruf ejaan jari ${result.system} kamu, bukan satu isyarat ${result.spellingKind || 'kata'} utuh.`
                                : `AI mengenali isyarat huruf ${result.letter} (${result.system}) kamu.`
                            }}
                        </p>

                        <div v-if="isSpellingComplete" class="ps-spelling-summary">
                            <b>{{ result.spelling.letters_done }} / {{ result.spelling.letters.length }} huruf selesai</b>
                            <div class="ps-spelling-letters" :aria-label="`Ejaan ${result.spelling.word} selesai`">
                                <span v-for="(letter, index) in result.spelling.letters" :key="index">
                                    {{ letter }} <Check :size="11" aria-hidden="true" />
                                </span>
                            </div>
                            <SpeakButton :text="wordSpeech" :label="`Ucapkan ${result.spellingKind || 'kata'}`" />
                        </div>

                        <div class="ps-modal-voice">
                            <div v-if="result.recognized && !isSpellingAttempt" class="ps-voice-read">
                                <span class="ps-voice-letter">{{ result.recognized }}</span>
                                <div>
                                    <small>Isyaratmu terbaca</small>
                                    <b>Huruf {{ result.recognized }}</b>
                                </div>
                                <SpeakButton :text="recognizedSpeech" label="Ucapkan huruf" icon-only />
                            </div>
                            <SpeakButton :text="resultSpeech" label="Bacakan hasil" />
                        </div>

                        <div class="ps-modal-stats">
                            <div>
                                <CheckCircle :size="20" />
                                <b>{{ result.confidence }}%</b>
                                <small>{{ isSpellingComplete ? 'Rata-rata akurasi' : 'Akurasi' }}</small>
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

                        <ul v-if="result.alreadyMastered || result.challengeCompleted || result.newBadges?.length || result.leveledUp || result.assignmentsCompleted?.length" class="ps-modal-list">
                            <li v-if="result.leveledUp" class="highlight"><Trophy :size="16" /> Naik level! Sekarang kamu <b>{{ result.levelName }}</b>.</li>
                            <li v-for="badge in result.newBadges" :key="badge" class="highlight"><Award :size="16" /> Lencana baru terbuka: <b>{{ badge }}</b></li>
                            <li v-for="task in result.assignmentsCompleted" :key="task.title" class="highlight"><ClipboardCheck :size="16" /> Tugas guru selesai: <b>{{ task.title }}</b> (+{{ task.xp_earned }} XP)</li>
                            <li v-if="result.challengeCompleted"><Trophy :size="16" /> Tantangan harian selesai! Bonus XP sudah ditambahkan.</li>
                            <li v-if="result.alreadyMastered && result.xp === 0"><Star :size="16" /> {{ isSpellingAttempt ? `${spellingKindTitle} ini` : 'Huruf ini' }} sudah kamu kuasai sebelumnya, jadi XP tidak bertambah lagi.</li>
                        </ul>

                        <div class="ps-modal-actions">
                            <button type="button" class="st-btn st-btn--secondary" @click="onRepeat">
                                <RotateCcw :size="16" /> Latih Lagi
                            </button>
                            <button ref="nextButtonRef" type="button" class="st-btn st-btn--primary" @click="onNext">
                                {{ nextLabel || 'Selesai' }} <ArrowRight v-if="nextLabel" :size="16" />
                            </button>
                        </div>
                    </template>

                    <!-- Salah -->
                    <template v-else-if="failure">
                        <span class="ps-modal-icon"><X :size="40" /></span>
                        <h2 id="ps-modal-title">{{ failure.title }}</h2>
                        <p class="ps-modal-sub">
                            <template v-if="isSpellingAttempt">Target ejaan {{ result.spellingKind || 'kata' }} {{ result.spelling.word }}: </template>
                            Huruf <b>{{ result.letter }}</b> ({{ result.system }})
                        </p>
                        <p v-if="isSpellingAttempt" class="ps-modal-sub">Posisi {{ result.spelling.letter_index + 1 }} dari {{ result.spelling.letters.length }}. Huruf yang sudah benar tetap tersimpan.</p>

                        <div class="ps-modal-voice">
                            <div v-if="result.recognized" class="ps-voice-read is-miss">
                                <span class="ps-voice-letter">{{ result.recognized }}</span>
                                <div>
                                    <small>Isyaratmu terbaca</small>
                                    <b>Huruf {{ result.recognized }}</b>
                                </div>
                                <SpeakButton :text="recognizedSpeech" label="Ucapkan huruf" icon-only />
                            </div>
                            <SpeakButton :text="resultSpeech" label="Bacakan hasil" />
                        </div>

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
                            <button type="button" class="st-btn st-btn--secondary" @click="emit('close')">Tutup</button>
                            <button ref="retryButtonRef" type="button" class="st-btn st-btn--primary" @click="emit('retry')">
                                <RotateCcw :size="16" /> Coba Lagi
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>
        </Teleport>
</template>

<style scoped>
.ps-modal {
    --sys: #34b182;
    --sys-dark: #1e7253;
    --sys-soft: #eefaf4;
    --sys-line: #bfe8d4;
}

.ps-modal.ps--bisindo {
    --sys: #c29b40;
    --sys-dark: #8a6a1f;
    --sys-soft: #fdf6e6;
    --sys-line: #ecd9a8;
}

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
    overflow-wrap: anywhere;
}

.ps-modal-sub {
    margin: -6px 0 0;
    font-size: 14px;
    color: var(--st-muted);
}

.ps-spelling-summary {
    display: grid;
    justify-items: center;
    gap: 10px;
    width: 100%;
    padding: 12px;
    color: var(--sys-dark);
    background: var(--sys-soft);
    border: 1px solid var(--sys-line);
    border-radius: 14px;
}

.ps-spelling-summary > b {
    font-size: 14px;
}

.ps-spelling-letters {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 5px;
}

.ps-spelling-letters span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    min-width: 34px;
    min-height: 36px;
    padding: 4px;
    font-size: 17px;
    font-weight: 800;
    color: #fff;
    background: var(--sys-dark);
    border-radius: 8px;
}

/* Isyarat → suara: huruf yang terbaca + tombol bacakan */
.ps-modal-voice {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
}

.ps-voice-read {
    display: flex;
    flex: 1 1 220px;
    align-items: center;
    gap: 10px;
    padding: 8px 8px 8px 10px;
    text-align: left;
    background: var(--sys-soft);
    border: 1px solid var(--sys-line);
    border-radius: 14px;
}

.ps-voice-read > div {
    display: grid;
    flex: 1;
    min-width: 0;
}

.ps-voice-read small {
    font-size: 12px;
    color: var(--st-muted);
}

.ps-voice-read b {
    font-size: 15px;
    font-weight: 800;
    color: var(--st-ink);
}

.ps-voice-letter {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 40px;
    height: 40px;
    font-size: 22px;
    font-weight: 800;
    color: #fff;
    background: var(--sys);
    border-radius: 11px;
}

.ps-voice-read.is-miss {
    background: #fdeee4;
    border-color: #f6d3bd;
}

.ps-voice-read.is-miss .ps-voice-letter {
    background: #e07a3f;
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

.ps-modal-stats--single {
    grid-template-columns: minmax(0, 1fr);
}

.ps-spelling-letters span.is-next {
    color: #795a16;
    background: #fff8e8;
    box-shadow: inset 0 0 0 2px #c29b40;
}

.ps-spelling-letters span.is-pending {
    color: #9aaba2;
    background: #f1f5f3;
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
</style>
