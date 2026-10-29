<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    BookOpen,
    Check,
    Clock3,
    Hand,
    LockKeyhole,
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

const totalLessonsCount = computed(() => {
    return categoryList.value.reduce((acc, cat) => {
        const matCount = cat.materials ? cat.materials.reduce((mAcc, m) => mAcc + (m.lessons ? m.lessons.length : 0), 0) : 0;
        return acc + matCount;
    }, 0);
});

const categoryIcons = {
    alphabet: BookOpen,
    number: Target,
    word: Hand,
    phrase: MessageSquare,
};

const categoryAccents = {
    alphabet: '#34B182',
    number: '#C29B40',
    word: '#5FAB6F',
    phrase: '#34B182',
};

function getCategoryIcon(slug) {
    return categoryIcons[slug] || Hand;
}

function getCategoryAccent(slug) {
    return categoryAccents[slug] || '#34B182';
}

function isLessonCompleted(lesson) {
    return completedIds.value.has(Number(lesson.id));
}
</script>

<template>
    <AppLayout>
        <Head title="Katalog Materi Belajar" />

        <div class="learn-page">
            <header class="learn-header">
                <div class="heading-badge">Kurikulum Terstruktur · SignTech</div>
                <h1>Katalog Materi Pembelajaran</h1>
                <p>Materi pembelajaran interaktif kurikulum bahasa isyarat Indonesia.</p>
            </header>

            <div class="path-section">
                <div class="path-intro">
                    <div>
                        <div class="section-label">Alur Pembelajaran</div>
                        <h2>Jalur Kurikulum Berjenjang</h2>
                    </div>
                    <span class="path-count">{{ categoryList.length }} Kategori · {{ totalLessonsCount }} Modul</span>
                </div>

                <div
                    v-for="(cat, cIndex) in categoryList"
                    :key="cat.id"
                    class="path-chapter"
                >
                    <div class="chapter-header">
                        <span
                            class="chapter-icon"
                            :style="{ backgroundColor: `${getCategoryAccent(cat.slug)}18`, color: getCategoryAccent(cat.slug) }"
                        >
                            <component :is="getCategoryIcon(cat.slug)" :size="20" />
                        </span>
                        <div>
                            <span class="eyebrow">BAB {{ cIndex + 1 }} · {{ cat.slug.toUpperCase() }}</span>
                            <h3>{{ cat.name }}</h3>
                            <p>{{ cat.description }}</p>
                        </div>
                    </div>

                    <div
                        v-for="mat in cat.materials || []"
                        :key="mat.id"
                        class="material-group"
                    >
                        <div class="material-meta">
                            <b>{{ mat.title }}</b>
                            <span class="mat-lang-tag">{{ String(mat.language).toUpperCase() }}</span>
                        </div>

                        <div class="node-track">
                            <div
                                v-for="(lesson, index) in mat.lessons || []"
                                :key="lesson.id"
                                class="path-node-row"
                                :class="index % 2 === 0 ? 'left' : 'right'"
                            >
                                <button
                                    type="button"
                                    class="lesson-node"
                                    :class="{ completed: isLessonCompleted(lesson) }"
                                    :aria-label="`Buka materi ${lesson.title}`"
                                    @click="selectedLesson = { ...lesson, materialTitle: mat.title, language: mat.language }"
                                >
                                    <Check v-if="isLessonCompleted(lesson)" :size="22" />
                                    <Hand v-else :size="20" />
                                </button>

                                <button
                                    type="button"
                                    class="node-copy"
                                    :class="{ completed: isLessonCompleted(lesson) }"
                                    @click="selectedLesson = { ...lesson, materialTitle: mat.title, language: mat.language }"
                                >
                                    <span class="node-title">{{ lesson.title }}</span>
                                    <small>+{{ lesson.xp_reward }} XP · {{ isLessonCompleted(lesson) ? 'Selesai' : 'Siap Dipelajari' }}</small>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Lesson Detail -->
            <div v-if="selectedLesson" class="modal-backdrop" @click.self="selectedLesson = null">
                <div class="lesson-modal" role="dialog" aria-modal="true">
                    <button class="modal-close" aria-label="Tutup modal" @click="selectedLesson = null">
                        <X :size="18" />
                    </button>
                    <div class="modal-badge-row">
                        <span class="badge badge-primary">{{ String(selectedLesson.language || 'SIBI').toUpperCase() }}</span>
                        <span class="badge badge-gold">+{{ selectedLesson.xp_reward || 10 }} XP</span>
                    </div>
                    <h2>Isyarat {{ selectedLesson.title }}</h2>
                    <p>{{ selectedLesson.materialTitle }} — Latih dan peragakan bentuk isyarat ini di studio deteksi AI.</p>

                    <div class="modal-meta">
                        <span><Clock3 :size="14" /> 5 Menit Sesi</span>
                        <span><Target :size="14" /> Deteksi AI</span>
                    </div>

                    <Link
                        :href="`/lesson/${selectedLesson.id}`"
                        class="primary-button modal-start"
                        @click="selectedLesson = null"
                    >
                        <span>Mulai Pelajaran Sekarang</span>
                        <ArrowRight :size="16" />
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.learn-page {
    padding-bottom: 36px;
}

.heading-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    color: #1e7253;
    background: #eefaf4;
    padding: 4px 10px;
    border-radius: 8px;
    margin-bottom: 8px;
}

.material-group {
    background: #fbfdfc;
    border: 1px solid #eef3f0;
    border-radius: 14px;
    padding: 16px;
    margin-top: 14px;
}

.material-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    font-weight: 700;
    color: #14221d;
    margin-bottom: 12px;
}

.mat-lang-tag {
    font-size: 10px;
    font-weight: 800;
    color: #C29B40;
    background: #fdf9ef;
    border: 1px solid #f1deaf;
    padding: 2px 8px;
    border-radius: 6px;
}

.modal-badge-row {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 12px;
}
</style>
