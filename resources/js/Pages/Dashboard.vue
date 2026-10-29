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
    RotateCcw,
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
    phrase: Award,
};

function getCategoryIcon(slug) {
    return categoryIcons[slug] || Hand;
}

// Recent activity table filter
const activityFilter = ref('all');
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

        <div class="dashboard-wrap">
            <!-- 1. Hero Welcome Banner -->
            <section class="dash-hero">
                <div class="dash-hero-main">
                    <div class="dash-hero-badge">
                        <span>Kurikulum Bahasa Isyarat SIBI & BISINDO</span>
                    </div>

                    <h1 class="dash-hero-title">
                        {{ greeting }}, <span class="text-primary-gradient">{{ userName }}</span>
                    </h1>

                    <p class="dash-hero-desc">
                        <template v-if="currentStreak > 0">
                            Kamu telah berlatih <strong>{{ currentStreak }} hari berturut-turut</strong>. Pertahankan konsistensi latihan isyaratmu hari ini!
                        </template>
                        <template v-else>
                            Mulai latihan isyarat mandiri dengan panduan visual dan verifikasi kamera secara langsung.
                        </template>
                    </p>

                    <div class="dash-hero-actions">
                        <Link href="/practice" class="btn-primary-action">
                            <Camera :size="17" />
                            <span>Buka AI Practice Studio</span>
                            <ArrowRight :size="15" />
                        </Link>

                        <Link href="/learn" class="btn-secondary-action">
                            <BookOpen :size="17" />
                            <span>Katalog Materi Belajar</span>
                        </Link>
                    </div>
                </div>

                <!-- Level & XP Widget -->
                <div class="dash-hero-level">
                    <div class="dash-level-head">
                        <span class="level-label">Peringkat Pelajar</span>
                        <span class="level-xp">{{ totalXp }} XP</span>
                    </div>

                    <div class="dash-level-title-row">
                        <Award :size="22" class="level-icon" />
                        <span class="level-title">{{ currentLevel.name }}</span>
                    </div>

                    <div class="level-track">
                        <div class="level-bar" :style="{ width: `${levelPercent}%` }"></div>
                    </div>

                    <div class="level-foot">
                        <span v-if="currentLevel.next_xp">{{ currentLevel.next_xp - totalXp }} XP lagi menuju level berikutnya</span>
                        <span v-else>Peringkat tertinggi tercapai!</span>
                        <span class="level-pct">{{ levelPercent }}%</span>
                    </div>
                </div>
            </section>

            <!-- 2. 4 Key Stat Metrics -->
            <section class="dash-stats-grid" aria-label="Statistik Utama">
                <!-- Streak Card -->
                <article class="stat-card">
                    <div class="stat-card-head">
                        <span class="stat-title">Daily Streak</span>
                        <div class="stat-icon-tile gold">
                            <Flame :size="20" />
                        </div>
                    </div>
                    <div class="stat-value">
                        <b>{{ currentStreak }}</b> <small>hari</small>
                    </div>
                    <div class="stat-foot">Rekor: {{ longestStreak }} hari berturut-turut</div>
                </article>

                <!-- XP Card -->
                <article class="stat-card">
                    <div class="stat-card-head">
                        <span class="stat-title">Total XP</span>
                        <div class="stat-icon-tile emerald">
                            <Star :size="20" />
                        </div>
                    </div>
                    <div class="stat-value">
                        <b>{{ totalXp }}</b> <small>XP</small>
                    </div>
                    <div class="stat-foot">Peringkat {{ currentLevel.name }}</div>
                </article>

                <!-- Mastery Card -->
                <article class="stat-card">
                    <div class="stat-card-head">
                        <span class="stat-title">Modul Dikuasai</span>
                        <div class="stat-icon-tile green">
                            <CheckCircle :size="20" />
                        </div>
                    </div>
                    <div class="stat-value">
                        <b>{{ masteredCount }}</b> <small>/ {{ totalGoals }} modul</small>
                    </div>
                    <div class="stat-foot">{{ masteryPercent }}% dari kurikulum selesai</div>
                </article>

                <!-- Accuracy Card -->
                <article class="stat-card">
                    <div class="stat-card-head">
                        <span class="stat-title">Rata-rata Akurasi</span>
                        <div class="stat-icon-tile mint">
                            <Activity :size="20" />
                        </div>
                    </div>
                    <div class="stat-value">
                        <b>{{ avgScore }}</b><small>%</small>
                    </div>
                    <div class="stat-foot">{{ sessionsCount }} sesi latihan tervalidasi</div>
                </article>
            </section>

            <!-- 3. Split Grid: Ritme Belajar & Daily Challenge -->
            <section class="dash-split-grid">
                <!-- 7-Day Habit Tracker Card -->
                <div class="dash-panel habit-panel">
                    <div class="panel-header">
                        <div>
                            <span class="panel-eyebrow">RITME BELAJAR</span>
                            <h2 class="panel-title">Aktivitas 7 Hari Terakhir</h2>
                        </div>
                        <span class="habit-badge">
                            <Flame :size="14" />
                            {{ currentStreak }} Hari Aktif
                        </span>
                    </div>

                    <p class="panel-desc">Pantau ritme dan konsistensi latihan isyaratmu setiap hari.</p>

                    <div class="week-strip">
                        <div
                            v-for="(day, index) in weekDays"
                            :key="index"
                            class="day-col"
                            :class="{ 'is-active': day.count > 0, 'is-today': day.isToday }"
                        >
                            <div class="day-indicator">
                                <CheckCircle v-if="day.count > 0" :size="16" />
                                <span v-else class="day-dot"></span>
                            </div>
                            <span class="day-label">{{ day.label }}</span>
                            <span v-if="day.isToday" class="today-tag">Hari ini</span>
                        </div>
                    </div>

                    <div class="panel-footer">
                        <div class="legend-row">
                            <span class="legend-dot active"></span>
                            <span>Sudah Latihan</span>
                            <span class="legend-dot inactive"></span>
                            <span>Belum Ada Sesi</span>
                        </div>
                        <Link href="/progress" class="panel-link">
                            Analisis Detail <ArrowRight :size="13" />
                        </Link>
                    </div>
                </div>

                <!-- Daily Challenge Quest Card -->
                <div class="dash-panel quest-panel">
                    <div class="panel-header">
                        <div>
                            <span class="panel-eyebrow">TARGET HARIAN</span>
                            <h2 class="panel-title">Tantangan Hari Ini</h2>
                        </div>
                        <span v-if="activeQuest" class="reward-pill">
                            <Star :size="13" />
                            +{{ activeQuest.reward_xp }} XP
                        </span>
                    </div>

                    <div v-if="activeQuest" class="quest-content">
                        <div class="quest-info-row">
                            <div class="quest-icon-tile">
                                <Target :size="22" />
                            </div>
                            <div class="quest-text">
                                <h3 class="quest-name">{{ activeQuest.title }}</h3>
                                <p class="quest-desc">{{ activeQuest.description }}</p>
                            </div>
                        </div>

                        <div class="quest-progress-box">
                            <div class="quest-progress-head">
                                <span>Progres Latihan</span>
                                <strong>{{ activeQuest.progress }} / {{ activeQuest.target }} selesai</strong>
                            </div>
                            <div class="quest-track">
                                <div
                                    class="quest-bar"
                                    :style="{ width: `${Math.min(100, Math.round(((activeQuest.progress || 0) / (activeQuest.target || 1)) * 100))}%` }"
                                ></div>
                            </div>
                        </div>

                        <div class="quest-action-row">
                            <button
                                v-if="activeQuest.progress >= activeQuest.target && !activeQuest.completed"
                                class="btn-claim-bonus"
                                @click="claimQuest(activeQuest)"
                            >
                                <Trophy :size="15" />
                                <span>Klaim Bonus +{{ activeQuest.reward_xp }} XP</span>
                            </button>
                            <div v-else-if="activeQuest.completed" class="banner-claimed">
                                <CheckCircle :size="15" />
                                <span>Bonus tantangan hari ini telah berhasil diklaim!</span>
                            </div>
                            <Link v-else href="/practice" class="btn-start-quest">
                                <Camera :size="15" />
                                <span>Kerjakan Lewat AI Practice</span>
                            </Link>
                        </div>
                    </div>
                    <div v-else class="empty-quest-message">
                        Semua tantangan latihan hari ini telah selesai dikerjakan!
                    </div>
                </div>
            </section>

            <!-- 4. Category Pathways Grid -->
            <section v-if="props.progress && props.progress.length > 0" class="dash-section">
                <div class="section-topline">
                    <div>
                        <span class="panel-eyebrow">JALUR PEMBELAJARAN</span>
                        <h2 class="section-heading">Kategori Materi Terstruktur</h2>
                    </div>
                    <Link href="/learn" class="panel-link">
                        Lihat Semua Modul <ArrowRight :size="14" />
                    </Link>
                </div>

                <div class="categories-grid">
                    <article
                        v-for="cat in props.progress"
                        :key="cat.id"
                        class="category-card"
                    >
                        <div class="cat-card-head">
                            <div class="cat-icon-tile">
                                <component :is="getCategoryIcon(cat.slug)" :size="20" />
                            </div>
                            <span class="cat-module-count">{{ cat.done }} / {{ cat.total }} modul</span>
                        </div>

                        <h3 class="cat-title">{{ cat.name }}</h3>

                        <div class="cat-progress-wrap">
                            <div class="cat-track">
                                <div class="cat-bar" :style="{ width: `${cat.percent}%` }"></div>
                            </div>
                            <span class="cat-pct">{{ cat.percent }}%</span>
                        </div>

                        <Link href="/learn" class="cat-action-link">
                            <span>Mulai Belajar</span>
                            <ChevronRight :size="15" />
                        </Link>
                    </article>
                </div>
            </section>

            <!-- 5. Rekomendasi Materi Lanjutan (3 Kolom Bersih) -->
            <section v-if="props.recommended && props.recommended.length > 0" class="dash-section">
                <div class="section-topline">
                    <div>
                        <span class="panel-eyebrow">REKOMENDASI</span>
                        <h2 class="section-heading">Latihan Isyarat Berikutnya</h2>
                    </div>
                </div>

                <div class="recommended-grid">
                    <Link
                        v-for="item in props.recommended.slice(0, 3)"
                        :key="item.id"
                        :href="`/lesson/${item.id}`"
                        class="rec-card"
                    >
                        <div class="rec-symbol">{{ item.title.slice(0, 2) }}</div>
                        <div class="rec-body">
                            <b class="rec-title">{{ item.title }}</b>
                            <small class="rec-sub">{{ item.material_title }}</small>
                        </div>
                        <span class="rec-xp-tag">+{{ item.xp_reward }} XP</span>
                    </Link>
                </div>
            </section>

            <!-- 6. Riwayat Sesi Latihan Terakhir (Full Width, Rapi & Bersih) -->
            <section class="dash-section">
                <div class="dash-panel history-panel">
                    <div class="panel-header">
                        <div>
                            <span class="panel-eyebrow">RIWAYAT AKTIVITAS</span>
                            <h2 class="panel-title">Sesi Latihan Terakhir</h2>
                        </div>
                        <div class="filter-pills" role="radiogroup" aria-label="Filter status sesi">
                            <button
                                type="button"
                                class="filter-pill"
                                :class="{ active: activityFilter === 'all' }"
                                @click="activityFilter = 'all'"
                            >
                                Semua
                            </button>
                            <button
                                type="button"
                                class="filter-pill"
                                :class="{ active: activityFilter === 'passed' }"
                                @click="activityFilter = 'passed'"
                            >
                                Lulus
                            </button>
                            <button
                                type="button"
                                class="filter-pill"
                                :class="{ active: activityFilter === 'needs-practice' }"
                                @click="activityFilter = 'needs-practice'"
                            >
                                Perlu Ulang
                            </button>
                        </div>
                    </div>

                    <div class="table-container">
                        <table v-if="filteredSessions.length > 0" class="history-table">
                            <thead>
                                <tr>
                                    <th>Materi Isyarat</th>
                                    <th>Akurasi Kamera</th>
                                    <th>Poin XP</th>
                                    <th>Status</th>
                                    <th>Waktu Selesai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="session in filteredSessions" :key="session.id">
                                    <td class="font-semibold text-charcoal">
                                        {{ session.lesson_title }}
                                    </td>
                                    <td>
                                        <span class="score-pill">{{ session.score }}%</span>
                                    </td>
                                    <td>
                                        <span class="xp-pill">+{{ session.xp_earned }} XP</span>
                                    </td>
                                    <td>
                                        <span v-if="session.passed" class="status-badge passed">Tuntas</span>
                                        <span v-else class="status-badge retry">Coba Lagi</span>
                                    </td>
                                    <td class="text-xs text-muted">
                                        {{ session.completed_at }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else class="empty-history-box">
                            Belum ada riwayat sesi latihan. Buka AI Practice Studio untuk memulai latihan isyarat mandiri.
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.dashboard-wrap {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding-bottom: 40px;
    font-family: 'Poppins', sans-serif;
}

/* 1. Hero Welcome Banner */
.dash-hero {
    background: #ffffff;
    border: 1px solid #e5ede8;
    border-radius: 18px;
    padding: 26px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    box-shadow: 0 2px 10px rgba(20, 34, 29, 0.03);
}

.dash-hero-main {
    max-width: 600px;
}

.dash-hero-badge {
    display: inline-flex;
    align-items: center;
    margin-bottom: 10px;
}

.dash-hero-badge span {
    font-size: 11px;
    font-weight: 700;
    color: #1e7253;
    background: #eefaf4;
    border: 1px solid #d5f3e5;
    padding: 4px 12px;
    border-radius: 20px;
}

.dash-hero-title {
    font-size: 26px;
    font-weight: 800;
    color: #14221d;
    line-height: 1.25;
    margin: 0 0 10px;
}

.text-primary-gradient {
    color: #34B182;
}

.dash-hero-desc {
    font-size: 13px;
    color: #52685d;
    line-height: 1.6;
    margin: 0 0 20px;
}

.dash-hero-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.btn-primary-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #34B182;
    color: #ffffff;
    font-weight: 700;
    font-size: 13px;
    padding: 10px 18px;
    border-radius: 10px;
    text-decoration: none;
    transition: 0.18s ease;
}

.btn-primary-action:hover {
    background: #279069;
}

.btn-secondary-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #f8faf9;
    color: #14221d;
    border: 1px solid #dbe6e0;
    font-weight: 700;
    font-size: 13px;
    padding: 10px 18px;
    border-radius: 10px;
    text-decoration: none;
    transition: 0.18s ease;
}

.btn-secondary-action:hover {
    background: #eefaf4;
    border-color: #34B182;
    color: #1e7253;
}

.dash-hero-level {
    background: #fbfdfc;
    border: 1px solid #e5ede8;
    border-radius: 14px;
    padding: 18px 22px;
    min-width: 260px;
    flex-shrink: 0;
}

.dash-level-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.level-label {
    font-size: 10px;
    font-weight: 700;
    color: #859c90;
    text-transform: uppercase;
    letter-spacing: 0.6px;
}

.level-xp {
    font-size: 13px;
    font-weight: 800;
    color: #C29B40;
}

.dash-level-title-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.level-icon {
    color: #34B182;
}

.level-title {
    font-size: 17px;
    font-weight: 800;
    color: #14221d;
}

.level-track {
    height: 7px;
    background: #edf3f0;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 8px;
}

.level-bar {
    height: 100%;
    background: #34B182;
    border-radius: 8px;
    transition: width 0.3s ease;
}

.level-foot {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    font-weight: 600;
    color: #859c90;
}

.level-pct {
    font-weight: 800;
    color: #1e7253;
}

/* 2. 4 Key Stat Metrics */
.dash-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.stat-card {
    background: #ffffff;
    border: 1px solid #e5ede8;
    border-radius: 14px;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: 0.15s ease;
}

.stat-card:hover {
    border-color: #ade6cc;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(20, 34, 29, 0.04);
}

.stat-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.stat-title {
    font-size: 12px;
    font-weight: 600;
    color: #71887c;
}

.stat-icon-tile {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: grid;
    place-items: center;
}

.stat-icon-tile.gold { background: #fdf9ef; color: #C29B40; }
.stat-icon-tile.emerald { background: #eefaf4; color: #34B182; }
.stat-icon-tile.green { background: #f1f9f3; color: #5FAB6F; }
.stat-icon-tile.mint { background: #f0f7f4; color: #279069; }

.stat-value {
    font-size: 26px;
    font-weight: 800;
    color: #14221d;
    line-height: 1.1;
    margin-bottom: 8px;
}

.stat-value small {
    font-size: 12px;
    font-weight: 600;
    color: #859c90;
}

.stat-foot {
    font-size: 11px;
    font-weight: 500;
    color: #859c90;
    padding-top: 8px;
    border-top: 1px solid #f1f5f3;
}

/* 3. Split Grid: Ritme & Challenge */
.dash-split-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.dash-panel {
    background: #ffffff;
    border: 1px solid #e5ede8;
    border-radius: 16px;
    padding: 22px;
    display: flex;
    flex-direction: column;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.panel-eyebrow {
    font-size: 10px;
    font-weight: 800;
    color: #859c90;
    letter-spacing: 0.7px;
}

.panel-title {
    font-size: 16px;
    font-weight: 800;
    color: #14221d;
    margin: 2px 0 0;
}

.panel-desc {
    font-size: 12px;
    color: #657b70;
    margin: 0 0 16px;
    line-height: 1.5;
}

.habit-badge, .reward-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
}

.habit-badge {
    background: #fdf9ef;
    color: #a58230;
    border: 1px solid #f1deaf;
}

.reward-pill {
    background: #eefaf4;
    color: #1e7253;
    border: 1px solid #ade6cc;
}

/* Week Strip */
.week-strip {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    margin-bottom: 16px;
}

.day-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    background: #f8faf9;
    border: 1px solid #edf3f0;
    border-radius: 10px;
    padding: 10px 4px;
}

.day-col.is-active {
    background: #eefaf4;
    border-color: #ade6cc;
}

.day-indicator {
    width: 22px;
    height: 22px;
    display: grid;
    place-items: center;
    color: #34B182;
}

.day-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #cfdcd5;
}

.day-label {
    font-size: 11px;
    font-weight: 700;
    color: #52685d;
}

.today-tag {
    font-size: 9px;
    font-weight: 800;
    color: #34B182;
}

.panel-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
    padding-top: 14px;
    border-top: 1px solid #f1f5f3;
}

.legend-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 10px;
    font-weight: 600;
    color: #859c90;
}

.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.legend-dot.active { background: #34B182; }
.legend-dot.inactive { background: #cfdcd5; }

.panel-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    color: #34B182;
    text-decoration: none;
}

.panel-link:hover {
    color: #279069;
}

/* Quest Card Body */
.quest-content {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.quest-info-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 14px;
}

.quest-icon-tile {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #eefaf4;
    color: #34B182;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.quest-name {
    font-size: 14px;
    font-weight: 800;
    color: #14221d;
    margin: 0 0 2px;
}

.quest-desc {
    font-size: 11px;
    color: #71887c;
    margin: 0;
    line-height: 1.4;
}

.quest-progress-box {
    margin-bottom: 14px;
}

.quest-progress-head {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #52685d;
    margin-bottom: 6px;
}

.quest-track {
    height: 7px;
    background: #edf3f0;
    border-radius: 8px;
    overflow: hidden;
}

.quest-bar {
    height: 100%;
    background: #34B182;
    border-radius: 8px;
    transition: width 0.3s ease;
}

.quest-action-row {
    margin-top: auto;
}

.btn-claim-bonus {
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #C29B40;
    color: #ffffff;
    font-weight: 700;
    font-size: 12px;
    padding: 9px 14px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
}

.btn-start-quest {
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #ffffff;
    border: 1px solid #34B182;
    color: #1e7253;
    font-weight: 700;
    font-size: 12px;
    padding: 8px 14px;
    border-radius: 8px;
    text-decoration: none;
}

.banner-claimed {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #eefaf4;
    color: #1e7253;
    font-size: 12px;
    font-weight: 700;
    padding: 8px 14px;
    border-radius: 8px;
}

.empty-quest-message {
    padding: 30px 0;
    text-align: center;
    font-size: 12px;
    color: #859c90;
}

/* 4. Category Pathways */
.dash-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.section-topline {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.section-heading {
    font-size: 17px;
    font-weight: 800;
    color: #14221d;
    margin: 2px 0 0;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.category-card {
    background: #ffffff;
    border: 1px solid #e5ede8;
    border-radius: 14px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    transition: 0.15s ease;
}

.category-card:hover {
    border-color: #ade6cc;
    transform: translateY(-1px);
}

.cat-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.cat-icon-tile {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #eefaf4;
    color: #34B182;
    display: grid;
    place-items: center;
}

.cat-module-count {
    font-size: 10px;
    font-weight: 700;
    color: #859c90;
}

.cat-title {
    font-size: 14px;
    font-weight: 800;
    color: #14221d;
    margin: 0 0 12px;
}

.cat-progress-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.cat-track {
    flex: 1;
    height: 6px;
    background: #edf3f0;
    border-radius: 6px;
    overflow: hidden;
}

.cat-bar {
    height: 100%;
    background: #34B182;
    border-radius: 6px;
}

.cat-pct {
    font-size: 10px;
    font-weight: 800;
    color: #1e7253;
}

.cat-action-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    font-weight: 700;
    color: #34B182;
    text-decoration: none;
    padding-top: 10px;
    border-top: 1px solid #f1f5f3;
}

/* 5. Recommended Grid */
.recommended-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}

.rec-card {
    background: #ffffff;
    border: 1px solid #e5ede8;
    border-radius: 12px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    transition: 0.15s ease;
}

.rec-card:hover {
    border-color: #34B182;
    transform: translateY(-1px);
}

.rec-symbol {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #eefaf4;
    color: #1e7253;
    font-weight: 800;
    font-size: 14px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.rec-body {
    flex: 1;
    min-width: 0;
}

.rec-title {
    display: block;
    font-size: 13px;
    color: #14221d;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.rec-sub {
    font-size: 10px;
    color: #859c90;
}

.rec-xp-tag {
    font-size: 10px;
    font-weight: 800;
    color: #C29B40;
    background: #fdf9ef;
    padding: 3px 7px;
    border-radius: 6px;
    flex-shrink: 0;
}

/* 6. History Table */
.history-panel {
    padding: 22px;
}

.filter-pills {
    display: flex;
    gap: 6px;
}

.filter-pill {
    background: #f8faf9;
    border: 1px solid #e2ede6;
    color: #52685d;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: 0.12s;
}

.filter-pill.active {
    background: #34B182;
    border-color: #34B182;
    color: #ffffff;
}

.table-container {
    overflow-x: auto;
    margin-top: 10px;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    text-align: left;
}

.history-table th {
    padding: 10px 12px;
    border-bottom: 1px solid #edf3f0;
    color: #859c90;
    font-size: 11px;
    font-weight: 700;
}

.history-table td {
    padding: 12px;
    border-bottom: 1px solid #f6f9f7;
    vertical-align: middle;
}

.score-pill {
    font-weight: 800;
    color: #14221d;
}

.xp-pill {
    font-size: 11px;
    font-weight: 700;
    color: #C29B40;
    background: #fdf9ef;
    padding: 3px 8px;
    border-radius: 6px;
}

.status-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
}

.status-badge.passed {
    background: #eefaf4;
    color: #1e7253;
}

.status-badge.retry {
    background: #fdf2f2;
    color: #d9534f;
}

.empty-history-box {
    text-align: center;
    padding: 36px 12px;
    font-size: 12px;
    color: #859c90;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .dash-hero {
        flex-direction: column;
        align-items: stretch;
    }
    .dash-hero-level {
        min-width: 100%;
    }
    .dash-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .categories-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .dash-split-grid {
        grid-template-columns: 1fr;
    }
    .recommended-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .dash-stats-grid {
        grid-template-columns: 1fr;
    }
    .categories-grid {
        grid-template-columns: 1fr;
    }
}
</style>
