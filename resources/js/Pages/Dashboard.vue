<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Award,
    BookOpen,
    Camera,
    CheckCircle,
    ChevronRight,
    Flame,
    Hand,
    MessageSquare,
    Star,
    Target,
    Trophy,
} from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    progress: { type: Array, default: () => [] },
    recentSessions: { type: Array, default: () => [] },
    recommended: { type: Array, default: () => [] },
    challenges: { type: Array, default: () => [] },
    weekActivity: { type: Array, default: () => [] },
    user: { type: Object, default: () => null },
});

// Time-aware greeting
const hour = new Date().getHours();
const greeting = computed(() => {
    if (hour < 11) return 'Selamat pagi';
    if (hour < 15) return 'Selamat siang';
    if (hour < 18) return 'Selamat sore';
    return 'Selamat malam';
});

const userName = computed(() => props.user?.name || 'Pelajar');
const totalXp = computed(() => Number(props.summary?.xp ?? 0));
const currentStreak = computed(() => Number(props.summary?.streak ?? 0));
const longestStreak = computed(() => Number(props.summary?.longest_streak ?? 0));
const avgScore = computed(() => Number(props.summary?.avg_score ?? 0));
const sessionsCount = computed(() => Number(props.summary?.sessions ?? 0));
const masteredCount = computed(() => Number(props.summary?.lessons_mastered ?? 0));
const totalGoals = computed(() => Number(props.summary?.lessons_total ?? 20));
const masteryPercent = computed(() => Math.min(100, Math.round((masteredCount.value / (totalGoals.value || 1)) * 100)));

const currentLevel = computed(() => props.summary?.level || {
    name: 'Beginner',
    color: '#94a3b8',
    xp: totalXp.value,
    threshold: 0,
    next_xp: 300,
    pct: 0,
});

const levelPercent = computed(() => Math.min(100, Math.round(Number(currentLevel.value.pct || 0))));

// Active Challenge from Database
const challengeList = ref(Array.isArray(props.challenges) ? [...props.challenges] : []);
const activeQuest = computed(() => challengeList.value[0] || null);
const questPercent = computed(() => {
    if (!activeQuest.value) return 0;
    return Math.min(100, Math.round(((activeQuest.value.progress || 0) / (activeQuest.value.target || 1)) * 100));
});

async function claimQuest(ch) {
    if (!ch || ch.completed) return;
    try {
        const res = await fetch('/api/challenges/claim', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken || '',
            },
            body: JSON.stringify({ challenge_id: ch.id }),
        });
        if (res.ok) {
            ch.completed = true;
            ch.progress = ch.target;
            router.reload({ only: ['summary', 'challenges'] });
        }
    } catch {}
}

// 7-day habit tracker from DB
const weekDays = computed(() => {
    if (Array.isArray(props.weekActivity) && props.weekActivity.length > 0) {
        return props.weekActivity;
    }
    const names = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    return Array.from({ length: 7 }, (_, index) => {
        const d = new Date();
        d.setDate(d.getDate() - (6 - index));
        return {
            label: names[d.getDay()],
            date: d.toISOString().slice(0, 10),
            count: 0,
            isToday: index === 6,
        };
    });
});

// Category pathways
const categoryIcons = {
    alphabet: BookOpen,
    number: Target,
    word: Hand,
    phrase: MessageSquare,
};

function getCategoryIcon(slug) {
    return categoryIcons[slug] || Hand;
}

// Recent activity table filter
const activityFilter = ref('all');
const activityFilters = [
    { value: 'all', label: 'Semua' },
    { value: 'passed', label: 'Lulus' },
    { value: 'needs-practice', label: 'Perlu Ulang' },
];
const sessionsList = computed(() => Array.isArray(props.recentSessions) ? props.recentSessions : []);

const filteredSessions = computed(() => {
    if (activityFilter.value === 'passed') {
        return sessionsList.value.filter((s) => s.passed);
    }
    if (activityFilter.value === 'needs-practice') {
        return sessionsList.value.filter((s) => !s.passed);
    }
    return sessionsList.value;
});
</script>

<template>
    <AppLayout>
        <Head title="Dashboard Belajar" />

        <div class="st-page">
            <!-- 1. Sambutan & level -->
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <span class="st-badge st-badge--primary">Kurikulum SIBI & BISINDO</span>
                    <h1>{{ greeting }}, <span class="st-highlight">{{ userName }}</span></h1>
                    <p>
                        <template v-if="currentStreak > 0">
                            Kamu sudah berlatih <strong>{{ currentStreak }} hari berturut-turut</strong>. Pertahankan konsistensimu hari ini!
                        </template>
                        <template v-else>
                            Mulai latihan isyarat mandiri dengan panduan visual dan verifikasi kamera secara langsung.
                        </template>
                    </p>

                    <div class="st-page-header-actions">
                        <Link href="/practice" class="st-btn st-btn--primary">
                            <Camera :size="17" />
                            <span>Mulai AI Practice</span>
                        </Link>
                        <Link href="/learn" class="st-btn st-btn--secondary">
                            <BookOpen :size="17" />
                            <span>Lihat Materi</span>
                        </Link>
                    </div>
                </div>

                <div class="st-page-header-aside level-box">
                    <div class="level-box-head">
                        <span class="st-eyebrow">Peringkat</span>
                        <span class="st-badge st-badge--gold">{{ totalXp }} XP</span>
                    </div>
                    <div class="level-box-title">
                        <Award :size="22" />
                        <span>{{ currentLevel.name }}</span>
                    </div>
                    <div class="st-track">
                        <i :style="{ width: `${levelPercent}%` }"></i>
                    </div>
                    <div class="level-box-foot">
                        <span v-if="currentLevel.next_xp">{{ Math.max(0, currentLevel.next_xp - totalXp) }} XP lagi ke level berikutnya</span>
                        <span v-else>Peringkat tertinggi tercapai!</span>
                        <b>{{ levelPercent }}%</b>
                    </div>
                </div>
            </header>

            <!-- 2. Statistik utama -->
            <section class="st-stat-grid" aria-label="Statistik utama">
                <article class="st-stat">
                    <div class="st-stat-head">
                        <span class="st-stat-label">Streak Harian</span>
                        <span class="st-icon st-icon--gold"><Flame :size="20" /></span>
                    </div>
                    <div class="st-stat-value">{{ currentStreak }}<small>hari</small></div>
                    <div class="st-stat-foot">Rekor: {{ longestStreak }} hari berturut-turut</div>
                </article>

                <article class="st-stat">
                    <div class="st-stat-head">
                        <span class="st-stat-label">Total XP</span>
                        <span class="st-icon st-icon--primary"><Star :size="20" /></span>
                    </div>
                    <div class="st-stat-value">{{ totalXp }}<small>XP</small></div>
                    <div class="st-stat-foot">Peringkat {{ currentLevel.name }}</div>
                </article>

                <article class="st-stat">
                    <div class="st-stat-head">
                        <span class="st-stat-label">Modul Dikuasai</span>
                        <span class="st-icon st-icon--green"><CheckCircle :size="20" /></span>
                    </div>
                    <div class="st-stat-value">{{ masteredCount }}<small>/ {{ totalGoals }}</small></div>
                    <div class="st-stat-foot">{{ masteryPercent }}% kurikulum selesai</div>
                </article>

                <article class="st-stat">
                    <div class="st-stat-head">
                        <span class="st-stat-label">Rata-rata Akurasi</span>
                        <span class="st-icon st-icon--mint"><Activity :size="20" /></span>
                    </div>
                    <div class="st-stat-value">{{ avgScore }}<small>%</small></div>
                    <div class="st-stat-foot">{{ sessionsCount }} sesi latihan tervalidasi</div>
                </article>
            </section>

            <!-- 3. Ritme belajar & tantangan harian -->
            <section class="st-grid-2">
                <div class="st-panel">
                    <div class="st-panel-head">
                        <div>
                            <span class="st-eyebrow">Ritme Belajar</span>
                            <h2 class="st-panel-title">Aktivitas 7 Hari Terakhir</h2>
                        </div>
                        <span class="st-badge st-badge--gold">
                            <Flame :size="13" />
                            {{ currentStreak }} hari aktif
                        </span>
                    </div>

                    <div class="week-strip">
                        <div
                            v-for="(day, index) in weekDays"
                            :key="index"
                            class="day-col"
                            :class="{ 'is-active': day.count > 0, 'is-today': day.isToday }"
                        >
                            <span class="day-indicator">
                                <CheckCircle v-if="day.count > 0" :size="16" />
                                <span v-else class="day-dot"></span>
                            </span>
                            <span class="day-label">{{ day.label }}</span>
                        </div>
                    </div>

                    <div class="st-panel-foot">
                        <div class="legend-row">
                            <span><i class="legend-dot is-active"></i>Sudah latihan</span>
                            <span><i class="legend-dot"></i>Belum ada sesi</span>
                        </div>
                        <Link href="/progress" class="st-link">
                            Lihat detail <ArrowRight :size="14" />
                        </Link>
                    </div>
                </div>

                <div class="st-panel">
                    <div class="st-panel-head">
                        <div>
                            <span class="st-eyebrow">Target Harian</span>
                            <h2 class="st-panel-title">Tantangan Hari Ini</h2>
                        </div>
                        <span v-if="activeQuest" class="st-badge st-badge--primary">
                            <Star :size="13" />
                            +{{ activeQuest.reward_xp }} XP
                        </span>
                    </div>

                    <template v-if="activeQuest">
                        <div class="quest-info">
                            <span class="st-icon st-icon--primary"><Target :size="20" /></span>
                            <div>
                                <h3>{{ activeQuest.title }}</h3>
                                <p>{{ activeQuest.description }}</p>
                            </div>
                        </div>

                        <div class="st-progress-meta">
                            <span>Progres</span>
                            <b>{{ activeQuest.progress }} / {{ activeQuest.target }} selesai</b>
                        </div>
                        <div class="st-track">
                            <i :style="{ width: `${questPercent}%` }"></i>
                        </div>

                        <div class="st-panel-foot">
                            <button
                                v-if="activeQuest.progress >= activeQuest.target && !activeQuest.completed"
                                type="button"
                                class="st-btn st-btn--gold st-btn--block"
                                @click="claimQuest(activeQuest)"
                            >
                                <Trophy :size="16" />
                                <span>Klaim Bonus +{{ activeQuest.reward_xp }} XP</span>
                            </button>
                            <div v-else-if="activeQuest.completed" class="st-alert st-alert--success quest-claimed">
                                <CheckCircle :size="16" />
                                <span>Bonus tantangan hari ini sudah diklaim.</span>
                            </div>
                            <Link v-else href="/practice" class="st-btn st-btn--outline st-btn--block">
                                <Camera :size="16" />
                                <span>Kerjakan di AI Practice</span>
                            </Link>
                        </div>
                    </template>
                    <div v-else class="st-empty">
                        Semua tantangan hari ini sudah selesai. Kerja bagus!
                    </div>
                </div>
            </section>

            <!-- 4. Jalur kategori materi -->
            <section v-if="props.progress && props.progress.length > 0" class="st-section">
                <div class="st-panel-head section-head">
                    <div>
                        <span class="st-eyebrow">Jalur Pembelajaran</span>
                        <h2 class="st-panel-title">Kategori Materi</h2>
                    </div>
                    <Link href="/learn" class="st-link">
                        Lihat semua <ArrowRight :size="14" />
                    </Link>
                </div>

                <div class="category-grid">
                    <Link
                        v-for="cat in props.progress"
                        :key="cat.id"
                        href="/learn"
                        class="st-panel st-hoverable category-card"
                    >
                        <div class="category-card-head">
                            <span class="st-icon st-icon--primary">
                                <component :is="getCategoryIcon(cat.slug)" :size="20" />
                            </span>
                            <span class="category-count">{{ cat.done }} / {{ cat.total }} modul</span>
                        </div>
                        <h3>{{ cat.name }}</h3>
                        <div class="category-progress">
                            <div class="st-track">
                                <i :style="{ width: `${cat.percent}%` }"></i>
                            </div>
                            <b>{{ cat.percent }}%</b>
                        </div>
                        <span class="st-link category-cta">
                            Mulai belajar <ChevronRight :size="15" />
                        </span>
                    </Link>
                </div>
            </section>

            <!-- 5. Rekomendasi -->
            <section v-if="props.recommended && props.recommended.length > 0" class="st-section">
                <div class="st-panel-head section-head">
                    <div>
                        <span class="st-eyebrow">Rekomendasi</span>
                        <h2 class="st-panel-title">Latihan Berikutnya</h2>
                    </div>
                </div>

                <div class="st-grid-3">
                    <Link
                        v-for="item in props.recommended.slice(0, 3)"
                        :key="item.id"
                        :href="`/lesson/${item.id}`"
                        class="st-panel st-hoverable rec-card"
                    >
                        <span class="st-icon st-icon--primary rec-symbol">{{ item.title.slice(0, 2) }}</span>
                        <div class="rec-body">
                            <b>{{ item.title }}</b>
                            <small>{{ item.material_title }}</small>
                        </div>
                        <span class="st-badge st-badge--gold">+{{ item.xp_reward }} XP</span>
                    </Link>
                </div>
            </section>

            <!-- 6. Riwayat sesi -->
            <section class="st-panel">
                <div class="st-panel-head">
                    <div>
                        <span class="st-eyebrow">Riwayat Aktivitas</span>
                        <h2 class="st-panel-title">Sesi Latihan Terakhir</h2>
                    </div>
                    <div class="st-seg" role="group" aria-label="Filter status sesi">
                        <button
                            v-for="option in activityFilters"
                            :key="option.value"
                            type="button"
                            :class="{ 'is-active': activityFilter === option.value }"
                            :aria-pressed="activityFilter === option.value"
                            @click="activityFilter = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <div v-if="filteredSessions.length > 0" class="st-table-wrap">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Materi</th>
                                <th>Akurasi</th>
                                <th>XP</th>
                                <th>Status</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="session in filteredSessions" :key="session.id">
                                <td class="session-title">{{ session.lesson_title }}</td>
                                <td><b>{{ session.score }}%</b></td>
                                <td><span class="st-badge st-badge--gold">+{{ session.xp_earned }} XP</span></td>
                                <td>
                                    <span v-if="session.passed" class="st-badge st-badge--primary">Tuntas</span>
                                    <span v-else class="st-badge st-badge--danger">Coba Lagi</span>
                                </td>
                                <td class="session-time">{{ session.completed_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="st-empty">
                    <span>Belum ada riwayat sesi pada filter ini.</span>
                    <Link href="/practice" class="st-btn st-btn--outline">
                        <Camera :size="16" />
                        <span>Mulai Latihan</span>
                    </Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Kotak level di header */
.level-box {
    width: 280px;
    padding: 18px 20px;
    background: #fff;
    border: 1px solid var(--st-line);
    border-radius: var(--st-radius);
}

.level-box-head,
.level-box-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.level-box-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 10px 0 12px;
    font-size: 18px;
    font-weight: 800;
    color: var(--st-ink);
}

.level-box-title svg {
    color: var(--st-primary);
}

.level-box-foot {
    margin-top: 8px;
    font-size: 12px;
    color: var(--st-muted);
}

.level-box-foot b {
    color: var(--st-primary-dark);
}

/* Strip mingguan */
.week-strip {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 8px;
}

.day-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 12px 4px;
    background: var(--st-surface);
    border: 1px solid var(--st-line-soft);
    border-radius: 12px;
}

.day-col.is-active {
    background: var(--st-primary-soft);
    border-color: var(--st-primary-line);
}

.day-col.is-today {
    box-shadow: inset 0 0 0 2px var(--st-primary);
}

.day-indicator {
    display: grid;
    place-items: center;
    width: 22px;
    height: 22px;
    color: var(--st-primary);
}

.day-dot {
    width: 7px;
    height: 7px;
    background: #cfdcd5;
    border-radius: 50%;
}

.day-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--st-text);
}

.legend-row {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    font-size: 12px;
    color: var(--st-muted);
}

.legend-row span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.legend-dot {
    width: 9px;
    height: 9px;
    background: #cfdcd5;
    border-radius: 50%;
}

.legend-dot.is-active {
    background: var(--st-primary);
}

/* Tantangan */
.quest-info {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
}

.quest-info h3 {
    margin: 0 0 3px;
    font-size: 15px;
    font-weight: 800;
    color: var(--st-ink);
}

.quest-info p {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: var(--st-muted);
}

.quest-claimed {
    width: 100%;
    justify-content: center;
}

/* Kategori */
.section-head {
    margin-bottom: 0;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.category-card {
    padding: 18px;
    text-decoration: none;
    color: inherit;
}

.category-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.category-count {
    font-size: 12px;
    font-weight: 600;
    color: var(--st-muted);
}

.category-card h3 {
    margin: 0 0 12px;
    font-size: 15px;
    font-weight: 800;
    color: var(--st-ink);
}

.category-progress {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.category-progress b {
    font-size: 12px;
    color: var(--st-primary-dark);
}

.category-cta {
    justify-content: space-between;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid var(--st-line-soft);
}

/* Rekomendasi */
.rec-card {
    flex-direction: row;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    text-decoration: none;
    color: inherit;
}

.rec-symbol {
    font-size: 15px;
    font-weight: 800;
}

.rec-body {
    flex: 1;
    min-width: 0;
}

.rec-body b,
.rec-body small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rec-body b {
    font-size: 14px;
    color: var(--st-ink);
}

.rec-body small {
    font-size: 12px;
    color: var(--st-muted);
}

/* Tabel */
.session-title {
    font-weight: 600;
    color: var(--st-ink);
}

.session-time {
    font-size: 13px;
    color: var(--st-muted);
    white-space: nowrap;
}

@media (max-width: 1100px) {
    .category-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .level-box {
        width: auto;
    }
}

@media (max-width: 480px) {
    .category-grid {
        grid-template-columns: 1fr;
    }

    .week-strip {
        gap: 4px;
    }

    .day-col {
        padding: 10px 2px;
    }
}
</style>
