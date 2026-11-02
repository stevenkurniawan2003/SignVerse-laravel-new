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

const props = defineProps({
    open: { type: Boolean, default: false },
    result: { type: Object, default: null },
    nextLabel: { type: String, default: '' },
});

const emit = defineEmits(['close', 'retry', 'next']);

const retryButtonRef = ref(null);
const nextButtonRef = ref(null);

/**
 * Penjelasan kegagalan untuk pop-up: apa yang salah + tips perbaikan,
 * disesuaikan dengan status deteksi dari AI Service.
 */
const failure = computed(() => {
    const r = props.result;
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

function onNext() {
    emit(props.nextLabel ? 'next' : 'close');
}

function onKeydown(event) {
    if (event.key === 'Escape' && props.open) {
        emit('close');
    }
}

watch(() => props.open, async (open) => {
    if (!open) return;
    await nextTick();
    (props.result?.success ? nextButtonRef.value : retryButtonRef.value)?.focus();
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <!-- Di-teleport ke body agar menutupi seluruh layar -->
        <Teleport to="body">
        <Transition name="ps-pop">
            <div v-if="open && result" class="ps-modal-backdrop" @click.self="emit('close')">
                <div
                    class="ps-modal"
                    :class="[result.success ? 'is-success' : 'is-retry', result.system === 'BISINDO' ? 'ps--bisindo' : 'ps--sibi']"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="ps-modal-title"
                >
                    <button type="button" class="ps-modal-close" aria-label="Tutup" @click="emit('close')">
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
                            <button type="button" class="st-btn st-btn--secondary" @click="emit('close')">
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
</style>
