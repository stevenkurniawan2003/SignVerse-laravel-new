<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Check,
    Clock3,
    Hand,
    MessageSquare,
    Target,
    X,
} from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    completedLessonIds: { type: Array, default: () => [] },
});

const categoryList = ref(Array.isArray(props.categories) && props.categories.length > 0 ? props.categories : []);
const completedIds = ref(new Set((props.completedLessonIds || []).map((id) => Number(id))));
const selectedLesson = ref(null);

onMounted(async () => {
    if (categoryList.value.length === 0) {
        try {
            const res = await fetch('/api/materials', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                if (Array.isArray(data.categories)) {
                    categoryList.value = data.categories;
                }
                if (Array.isArray(data.completedLessonIds)) {
                    data.completedLessonIds.forEach((id) => completedIds.value.add(Number(id)));
                }
            }
        } catch {}
    }
});

const categoryIcons = {
    alphabet: BookOpen,
    number: Target,
    word: Hand,
    phrase: MessageSquare,
};

const categoryTones = {
    alphabet: 'st-icon--primary',
    number: 'st-icon--gold',
    word: 'st-icon--green',
    phrase: 'st-icon--mint',
};

function getCategoryIcon(slug) {
    return categoryIcons[slug] || Hand;
}

function getCategoryTone(slug) {
    return categoryTones[slug] || 'st-icon--primary';
}

function isLessonCompleted(lesson) {
    return completedIds.value.has(Number(lesson.id));
}

function lessonCountOf(category) {
    return (category.materials || []).reduce((acc, m) => acc + (m.lessons ? m.lessons.length : 0), 0);
}

function completedCountOf(category) {
    return (category.materials || []).reduce(
        (acc, m) => acc + (m.lessons || []).filter((lesson) => isLessonCompleted(lesson)).length,
        0
    );
}

function openLesson(lesson, material) {
    selectedLesson.value = { ...lesson, materialTitle: material.title, language: material.language };
}

const totalLessonsCount = computed(() => categoryList.value.reduce((acc, cat) => acc + lessonCountOf(cat), 0));
const totalCompletedCount = computed(() => categoryList.value.reduce((acc, cat) => acc + completedCountOf(cat), 0));
</script>

<template>
    <AppLayout>
        <Head title="Katalog Materi Belajar" />

        <div class="st-page">
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <span class="st-badge st-badge--primary">Kurikulum Terstruktur</span>
                    <h1>Materi Belajar</h1>
                    <p>Pilih materi, pelajari bentuk isyaratnya, lalu praktikkan langsung dengan kamera.</p>
                </div>
                <div class="st-page-header-aside st-summary-box">
                    <span class="st-icon st-icon--primary"><BookOpen :size="22" /></span>
                    <div>
                        <b>{{ totalCompletedCount }} / {{ totalLessonsCount }}</b>
                        <small>modul selesai · {{ categoryList.length }} kategori</small>
                    </div>
                </div>
            </header>

            <div v-if="categoryList.length === 0" class="st-empty">
                Materi belum tersedia. Silakan muat ulang halaman beberapa saat lagi.
            </div>

            <section
                v-for="(cat, cIndex) in categoryList"
                :key="cat.id"
                class="st-panel"
            >
                <div class="st-panel-head chapter-head">
                    <div class="chapter-title">
                        <span class="st-icon st-icon--lg" :class="getCategoryTone(cat.slug)">
                            <component :is="getCategoryIcon(cat.slug)" :size="22" />
                        </span>
                        <div>
                            <span class="st-eyebrow">Bab {{ cIndex + 1 }}</span>
                            <h2 class="st-panel-title">{{ cat.name }}</h2>
                            <p v-if="cat.description" class="st-panel-desc">{{ cat.description }}</p>
                        </div>
                    </div>
                    <span class="st-badge st-badge--neutral">{{ completedCountOf(cat) }} / {{ lessonCountOf(cat) }} selesai</span>
                </div>

                <div
                    v-for="mat in cat.materials || []"
                    :key="mat.id"
                    class="material-group"
                >
                    <div class="material-meta">
                        <b>{{ mat.title }}</b>
                        <span class="st-badge st-badge--gold">{{ String(mat.language).toUpperCase() }}</span>
                    </div>

                    <div class="lesson-grid">
                        <button
                            v-for="lesson in mat.lessons || []"
                            :key="lesson.id"
                            type="button"
                            class="lesson-tile"
                            :class="{ 'is-done': isLessonCompleted(lesson) }"
                            :aria-label="`Buka materi ${lesson.title}`"
                            @click="openLesson(lesson, mat)"
                        >
                            <span class="lesson-tile-icon">
                                <Check v-if="isLessonCompleted(lesson)" :size="18" />
                                <Hand v-else :size="18" />
                            </span>
                            <span class="lesson-tile-copy">
                                <b>{{ lesson.title }}</b>
                                <small>{{ isLessonCompleted(lesson) ? 'Selesai' : `+${lesson.xp_reward} XP` }}</small>
                            </span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Modal detail materi -->
            <div v-if="selectedLesson" class="modal-backdrop" @click.self="selectedLesson = null">
                <div class="lesson-modal" role="dialog" aria-modal="true" aria-labelledby="lesson-modal-title">
                    <button type="button" class="modal-close" aria-label="Tutup" @click="selectedLesson = null">
                        <X :size="18" />
                    </button>
                    <div class="modal-badge-row">
                        <span class="st-badge st-badge--primary">{{ String(selectedLesson.language || 'SIBI').toUpperCase() }}</span>
                        <span class="st-badge st-badge--gold">+{{ selectedLesson.xp_reward || 10 }} XP</span>
                    </div>
                    <h2 id="lesson-modal-title">Isyarat {{ selectedLesson.title }}</h2>
                    <p>{{ selectedLesson.materialTitle }} — pelajari dan peragakan bentuk isyarat ini dengan bantuan deteksi AI.</p>

                    <div class="modal-meta">
                        <span><Clock3 :size="16" /> ± 5 menit</span>
                        <span><Target :size="16" /> Deteksi AI</span>
                    </div>

                    <Link
                        :href="`/lesson/${selectedLesson.id}`"
                        class="st-btn st-btn--primary st-btn--block"
                        @click="selectedLesson = null"
                    >
                        <span>Mulai Pelajaran</span>
                        <ArrowRight :size="16" />
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.chapter-head {
    padding-bottom: 16px;
    border-bottom: 1px solid var(--st-line-soft);
}

.chapter-title {
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

.material-group + .material-group {
    margin-top: 18px;
}

.material-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
    font-size: 14px;
    color: var(--st-ink);
}

.lesson-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.lesson-tile {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 56px;
    padding: 10px 12px;
    text-align: left;
    cursor: pointer;
    background: #fff;
    border: 1px solid var(--st-line);
    border-radius: 12px;
    transition: border-color 0.15s ease, transform 0.15s ease;
}

.lesson-tile:hover {
    border-color: var(--st-primary);
    transform: translateY(-1px);
}

.lesson-tile-icon {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 34px;
    height: 34px;
    color: var(--st-primary);
    background: var(--st-primary-soft);
    border-radius: 50%;
}

.lesson-tile-copy {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.lesson-tile-copy b {
    overflow: hidden;
    font-size: 14px;
    color: var(--st-ink);
    text-overflow: ellipsis;
    white-space: nowrap;
}

.lesson-tile-copy small {
    font-size: 12px;
    color: var(--st-muted);
}

.lesson-tile.is-done {
    background: var(--st-primary-soft);
    border-color: var(--st-primary-line);
}

.lesson-tile.is-done .lesson-tile-icon {
    color: #fff;
    background: var(--st-primary);
}

.lesson-tile.is-done .lesson-tile-copy small {
    font-weight: 700;
    color: var(--st-primary-dark);
}

.modal-badge-row {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 8px;
}

.modal-meta svg {
    color: var(--st-primary);
}

@media (max-width: 760px) {
    .chapter-head {
        flex-direction: column;
    }
}
</style>
