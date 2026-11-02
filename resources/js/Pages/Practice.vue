<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Hand,
    Lightbulb,
} from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';
import PracticeCamera from '../Layouts/PracticeCamera.vue';
import PracticeResultModal from '../Layouts/PracticeResultModal.vue';

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

// Kamera, hitung mundur, dan penilaian AI ada di komponen PracticeCamera
const cameraRef = ref(null);
const busy = computed(() => cameraRef.value?.busy ?? false);
const scanning = computed(() => cameraRef.value?.scanning ?? false);

// Hasil deteksi
const result = ref(null);
const resultOpen = ref(false);
const sessionStats = ref({ attempts: 0, passed: 0, xp: 0 });

const nextLabel = computed(() =>
    nextTarget.value && nextTarget.value.id !== target.value?.id ? `Lanjut ke Huruf ${nextTarget.value.letter}` : ''
);

function resetFeedback() {
    cameraRef.value?.reset();
    result.value = null;
    resultOpen.value = false;
}

function selectFirstOpenLetter() {
    selectedId.value = (letters.value.find((item) => !isMastered(item)) || letters.value[0])?.id ?? null;
}

function choose(item) {
    if (!item || scanning.value) return;
    selectedId.value = item.id;
    resetFeedback();
}

function shiftTarget(direction) {
    const list = letters.value;
    if (!list.length) return;
    choose(list[(targetIndex.value + direction + list.length) % list.length]);
}

function applySystem(system) {
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

function onDetectionResult(detection) {
    result.value = detection;
    resultOpen.value = true;

    if (detection.evaluated) {
        sessionStats.value.attempts += 1;
    }
    if (detection.success) {
        sessionStats.value.passed += 1;
        sessionStats.value.xp += detection.xp;
        masteredIds.value = new Set([...masteredIds.value, detection.lessonId]);
    }
}

function closeResult() {
    resultOpen.value = false;
}

function retryFromPopup() {
    closeResult();
    cameraRef.value?.startDetection();
}

function goToNextTarget() {
    closeResult();
    choose(nextTarget.value);
}

onMounted(async () => {
    window.addEventListener('signtech:system-changed', onSystemChanged);

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
});

onBeforeUnmount(() => {
    window.removeEventListener('signtech:system-changed', onSystemChanged);
});
</script>

<template>
    <AppLayout>
        <Head title="AI Practice Studio" />

        <div class="st-page ps" :class="activeSystem === 'BISINDO' ? 'ps--bisindo' : 'ps--sibi'">
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

                    <PracticeCamera
                        ref="cameraRef"
                        :lesson-id="target?.id ?? null"
                        :letter="target?.letter ?? ''"
                        :system="activeSystem"
                        :attempted="Boolean(result)"
                        :disabled="!target"
                        @result="onDetectionResult"
                    />
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

            <PracticeResultModal
                :open="resultOpen"
                :result="result"
                :next-label="nextLabel"
                @close="closeResult"
                @retry="retryFromPopup"
                @next="goToNextTarget"
            />
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
}
</style>
