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
    Clock,
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
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
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

        <div class="dashboard-revamp">
            <!-- Hero Welcome Banner -->
            <section class="dash-hero">
                <div class="dash-hero-content">
                    <div class="dash-eyebrow">
                        <span class="dash-pill-brand">SignTech Learning</span>
                        <span class="dash-system-badge">Basis Data Aktif</span>
                    </div>

                    <h1 class="dash-title">
                        {{ greeting }}, <span class="dash-name">{{ userName }}</span>
                    </h1>

                    <p class="dash-subtitle">
                        <template v-if="currentStreak > 0">
                            Kamu telah berlatih <strong>{{ currentStreak }} hari berturut-turut</strong>. Pertahankan momentum belajarmu hari ini!
                        </template>
                        <template v-else>
                            Mulai sesi latihan pertamamu hari ini dan kumpulkan poin pengalaman di database SignTech.
                        </template>
                    </p>

                    <div class="dash-hero-actions">
                        <Link href="/practice" class="dash-btn-primary">
                            <Camera :size="18" />
                            <span>Buka AI Practice Studio</span>
                            <ArrowRight :size="16" />
                        </Link>

                        <Link href="/learn" class="dash-btn-glass">
                            <BookOpen :size="18" />
                            <span>Katalog Materi Belajar</span>
                        </Link>
                    </div>
                </div>

                <!-- Level & XP Widget on Hero -->
                <div class="dash-hero-level-box">
                    <div class="dash-level-header">
                        <span class="dash-level-label">Peringkat Pelajar</span>
                        <span class="dash-level-xp-value">{{ totalXp }} XP</span>
                    </div>
                    <div class="dash-level-name-row">
                        <Award :size="24" class="dash-award-icon" />
                        <span class="dash-level-title">{{ currentLevel.name }}</span>
                    </div>

                    <div class="dash-xp-track">
                        <div class="dash-xp-bar" :style="{ width: `${levelPercent}%` }"></div>
                    </div>

                    <div class="dash-level-footer">
                        <span v-if="currentLevel.next_xp">{{ currentLevel.next_xp - totalXp }} XP lagi menuju level berikutnya</span>
                        <span v-else>Peringkat tertinggi tercapai!</span>
                        <span class="dash-pct-text">{{ levelPercent }}%</span>
                    </div>
                </div>
            </section>

            <!-- 4 Key Stat Metrics -->
            <section class="dash-stats-grid" aria-label="Statistik Utama">
                <!-- Streak Card -->
                <article class="dash-stat-card streak-theme">
                    <div class="dash-stat-icon-tile gold">
                        <Flame :size="22" />
                    </div>
                    <div class="dash-stat-data">
                        <span class="dash-stat-label">Daily Streak</span>
                        <div class="dash-stat-number">
                            <b>{{ currentStreak }}</b> <small>hari</small>
                        </div>
                    </div>
                    <div class="dash-stat-subtext">Rekor: {{ longestStreak }} hari</div>
                </article>

                <!-- XP Card -->
                <article class="dash-stat-card xp-theme">
                    <div class="dash-stat-icon-tile emerald">
                        <Star :size="22" />
                    </div>
                    <div class="dash-stat-data">
                        <span class="dash-stat-label">Total Pengalaman</span>
                        <div class="dash-stat-number">
                            <b>{{ totalXp }}</b> <small>XP</small>
                        </div>
                    </div>
                    <div class="dash-stat-subtext">Tingkat {{ currentLevel.name }}</div>
                </article>

                <!-- Mastery Card -->
                <article class="dash-stat-card mastery-theme">
                    <div class="dash-stat-icon-tile green">
                        <CheckCircle :size="22" />
                    </div>
                    <div class="dash-stat-data">
                        <span class="dash-stat-label">Modul Dikuasai</span>
                        <div class="dash-stat-number">
                            <b>{{ masteredCount }}</b> <small>/ {{ totalGoals }}</small>
                        </div>
                    </div>
                    <div class="dash-stat-subtext">{{ masteryPercent }}% tuntas</div>
                </article>

                <!-- Accuracy Card -->
                <article class="dash-stat-card session-theme">
                    <div class="dash-stat-icon-tile mint">
                        <Activity :size="22" />
                    </div>
                    <div class="dash-stat-data">
                        <span class="dash-stat-label">Rata-rata Akurasi</span>
                        <div class="dash-stat-number">
                            <b>{{ avgScore }}</b><small>%</small>
                        </div>
                    </div>
                    <div class="dash-stat-subtext">{{ sessionsCount }} sesi tervalidasi</div>
                </article>
            </section>

            <!-- 2 Columns: 7-Day Habit Tracker & Daily Challenge Quest -->
            <section class="dash-split-grid">
                <!-- 7-Day Habit Tracker Card -->
                <div class="dash-card habit-box-card">
                    <div class="dash-card-header">
                        <div>
                            <span class="dash-card-eyebrow">RITME BELAJAR</span>
                            <h2 class="dash-card-title">Aktivitas 7 Hari Terakhir</h2>
                        </div>
                        <span class="dash-habit-badge">
                            <Flame :size="14" />
                            {{ currentStreak }} Hari Aktif
                        </span>
                    </div>

                    <p class="dash-card-desc">Rekapan sesi latihan harianmu yang tercatat langsung di database.</p>

                    <div class="dash-week-strip">
                        <div
                            v-for="(day, index) in weekDays"
                            :key="index"
                            class="dash-day-col"
                            :class="{ 'is-active': day.count > 0, 'is-today': day.isToday }"
                        >
                            <div class="dash-day-indicator">
                                <CheckCircle v-if="day.count > 0" :size="16" />
                                <span v-else class="dash-dot"></span>
                            </div>
                            <span class="dash-day-label">{{ day.label }}</span>
                            <span v-if="day.isToday" class="dash-today-tag">Hari ini</span>
                        </div>
                    </div>

                    <div class="dash-habit-footer">
                        <div class="dash-habit-legend">
                            <span class="dash-legend-dot active"></span>
                            <span>Sudah Latihan</span>
                            <span class="dash-legend-dot inactive"></span>
                            <span>Belum Ada Sesi</span>
                        </div>
                        <Link href="/progress" class="dash-link-action">
                            Lihat Analisis Detail <ArrowRight :size="14" />
                        </Link>
                    </div>
                </div>

                <!-- Daily Challenge Quest Checklist -->
                <div class="dash-card challenge-box-card">
                    <div class="dash-card-header">
                        <div>
                            <span class="dash-card-eyebrow">TARGET HARIAN</span>
                            <h2 class="dash-card-title">Challenge Hari Ini</h2>
                        </div>
                        <span v-if="activeQuest" class="dash-reward-pill">
                            <Star :size="14" />
                            +{{ activeQuest.reward_xp }} XP Bonus
                        </span>
                    </div>

                    <div v-if="activeQuest" class="dash-quest-body">
                        <div class="dash-quest-info">
                            <div class="dash-quest-icon-box">
                                <Target :size="24" />
                            </div>
                            <div>
                                <h3 class="dash-quest-name">{{ activeQuest.title }}</h3>
                                <p class="dash-quest-desc">{{ activeQuest.description }}</p>
                            </div>
                        </div>

                        <div class="dash-quest-progress-block">
                            <div class="dash-quest-progress-header">
                                <span>Progres</span>
                                <strong>{{ activeQuest.progress }} dari {{ activeQuest.target }} selesai</strong>
                            </div>
                            <div class="dash-xp-track">
                                <div
                                    class="dash-xp-bar"
                                    :style="{ width: `${Math.min(100, Math.round(((activeQuest.progress || 0) / (activeQuest.target || 1)) * 100))}%` }"
                                ></div>
                            </div>
                        </div>

                        <div class="dash-quest-actions">
                            <button
                                v-if="activeQuest.progress >= activeQuest.target && !activeQuest.completed"
                                class="dash-btn-gold w-full"
                                @click="claimQuest(activeQuest)"
                            >
                                <Trophy :size="16" />
                                Klaim Bonus +{{ activeQuest.reward_xp }} XP Sekarang!
                            </button>
                            <div v-else-if="activeQuest.completed" class="dash-claimed-banner">
                                <CheckCircle :size="16" />
                                Bonus tantangan hari ini telah berhasil diklaim!
                            </div>
                            <Link v-else href="/practice" class="dash-btn-outline w-full">
                                <Camera :size="16" />
                                Kerjakan Tantangan Lewat AI Practice
                            </Link>
                        </div>
                    </div>
                    <div v-else class="text-xs text-muted py-6 text-center">
                        Semua tantangan hari ini telah tuntas!
                    </div>
                </div>
            </section>

            <!-- Category Pathways Grid from Database -->
            <section v-if="props.progress && props.progress.length > 0" class="dash-section">
                <div class="dash-section-header">
                    <div>
                        <span class="dash-card-eyebrow">JALUR PEMBELAJARAN</span>
                        <h2 class="dash-section-title">Kategori Materi Terstruktur</h2>
                    </div>
                    <Link href="/learn" class="dash-link-action">
                        Buka Semua Materi <ArrowRight :size="15" />
                    </Link>
                </div>

                <div class="dash-category-grid">
                    <article
                        v-for="cat in props.progress"
                        :key="cat.id"
                        class="dash-cat-card card"
                    >
                        <div class="dash-cat-top">
                            <span class="dash-cat-icon-tile emerald">
                                <component :is="getCategoryIcon(cat.slug)" :size="20" />
                            </span>
                            <span class="dash-cat-count">{{ cat.done }} / {{ cat.total }} modul</span>
                        </div>

                        <h3 class="dash-cat-title">{{ cat.name }}</h3>
                        <p class="dash-cat-desc">Kategori materi isyarat {{ cat.name }} terdaftar di kurikulum.</p>

                        <div class="dash-cat-progress">
                            <div class="dash-xp-track">
                                <div
                                    class="dash-xp-bar"
                                    :style="{ width: `${cat.percent}%` }"
                                ></div>
                            </div>
                            <span class="dash-cat-pct">{{ cat.percent }}%</span>
                        </div>

                        <Link href="/learn" class="dash-cat-btn">
                            <span>Mulai Modul</span>
                            <ChevronRight :size="16" />
                        </Link>
                    </article>
                </div>
            </section>

            <!-- Recommendations & Activity Feed -->
            <div class="dash-split-grid bottom-split">
                <!-- Recommended Lessons from Database -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <div>
                            <span class="dash-card-eyebrow">REKOMENDASI</span>
                            <h2 class="dash-card-title">Materi Rekomendasi Berikutnya</h2>
                        </div>
                    </div>

                    <div v-if="props.recommended && props.recommended.length > 0" class="dash-rec-list">
                        <Link
                            v-for="item in props.recommended"
                            :key="item.id"
                            :href="`/lesson/${item.id}`"
                            class="dash-rec-item"
                        >
                            <div class="dash-rec-symbol">
                                {{ item.title.slice(0, 2) }}
                            </div>
                            <div class="dash-rec-info">
                                <b>{{ item.title }}</b>
                                <small>{{ item.material_title }}</small>
                            </div>
                            <div class="dash-rec-tag">
                                +{{ item.xp_reward }} XP
                            </div>
                        </Link>
                    </div>
                    <div v-else class="text-xs text-muted py-6 text-center">
                        Belum ada rekomendasi latihan tambahan.
                    </div>
                </div>

                <!-- Recent Activity Feed from Database -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <div>
                            <span class="dash-card-eyebrow">RIWAYAT</span>
                            <h2 class="dash-card-title">Sesi Latihan Terakhir</h2>
                        </div>
                        <div class="dash-filter-pills">
                            <button
                                :class="{ active: activityFilter === 'all' }"
                                @click="activityFilter = 'all'"
                            >
                                Semua
                            </button>
                            <button
                                :class="{ active: activityFilter === 'passed' }"
                                @click="activityFilter = 'passed'"
                            >
                                Lulus
                            </button>
                            <button
                                :class="{ active: activityFilter === 'needs-practice' }"
                                @click="activityFilter = 'needs-practice'"
                            >
                                Perlu Ulang
                            </button>
                        </div>
                    </div>

                    <div class="dash-table-wrap">
                        <table v-if="filteredSessions.length > 0" class="dash-activity-table">
                            <thead>
                                <tr>
                                    <th>Materi Sesi</th>
                                    <th>Akurasi</th>
                                    <th>XP</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="session in filteredSessions" :key="session.id">
                                    <td>
                                        <b>{{ session.lesson_title }}</b>
                                        <small>{{ session.completed_at }}</small>
                                    </td>
                                    <td>
                                        <span class="dash-score-pill">{{ session.score }}%</span>
                                    </td>
                                    <td>
                                        <span class="dash-xp-badge">+{{ session.xp_earned }}</span>
                                    </td>
                                    <td>
                                        <span v-if="session.passed" class="dash-status-passed">Tuntas</span>
                                        <span v-else class="dash-status-retry">Coba Lagi</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else class="text-xs text-muted py-8 text-center">
                            Belum ada riwayat sesi latihan yang tercatat. Buka AI Practice Studio untuk memulai latihan.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.dashboard-revamp {
    display: flex;
    flex-direction: column;
    gap: 28px;
    padding-bottom: 36px;
}

.dash-hero {
    background: linear-gradient(135deg, #eefaf4 0%, #f6fbf8 60%, #fdf9ef 100%);
    border: 1px solid #d5f3e5;
    border-radius: 20px;
    padding: 28px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    box-shadow: 0 4px 18px rgba(52, 177, 130, 0.06);
}

.dash-hero-content {
    max-width: 580px;
}

.dash-eyebrow {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.dash-pill-brand {
    font-size: 11px;
    font-weight: 700;
    color: #1e7253;
    background: #d5f3e5;
    padding: 3px 10px;
    border-radius: 6px;
}

.dash-system-badge {
    font-size: 11px;
    font-weight: 700;
    color: #a58230;
    background: #fdf9ef;
    border: 1px solid #f1deaf;
    padding: 3px 10px;
    border-radius: 6px;
}

.dash-title {
    font-size: 28px;
    font-weight: 800;
    color: #14221d;
    line-height: 1.2;
    margin: 0 0 10px;
    letter-spacing: -0.5px;
}

.dash-name {
    color: #34B182;
}

.dash-subtitle {
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

.dash-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #34B182;
    color: #ffffff;
    font-weight: 700;
    font-size: 13px;
    padding: 10px 18px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(52, 177, 130, 0.28);
    transition: 0.18s;
}

.dash-btn-primary:hover {
    background: #279069;
    transform: translateY(-1px);
}

.dash-btn-glass {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    color: #14221d;
    border: 1px solid #d5f3e5;
    font-weight: 700;
    font-size: 13px;
    padding: 10px 16px;
    border-radius: 12px;
    transition: 0.18s;
}

.dash-btn-glass:hover {
    background: #eefaf4;
    border-color: #ade6cc;
}

.dash-hero-level-box {
    background: #ffffff;
    border: 1px solid #e2ede6;
    border-radius: 16px;
    padding: 18px 22px;
    min-width: 250px;
    box-shadow: 0 4px 12px rgba(20, 34, 29, 0.04);
}

.dash-level-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}

.dash-level-label {
    font-size: 10px;
    font-weight: 700;
    color: #859c90;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.dash-level-xp-value {
    font-size: 13px;
    font-weight: 800;
    color: #C29B40;
}

.dash-level-name-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.dash-award-icon {
    color: #34B182;
}

.dash-level-title {
    font-size: 18px;
    font-weight: 800;
    color: #14221d;
}

.dash-xp-track {
    height: 8px;
    background: #f1f5f3;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 8px;
}

.dash-xp-bar {
    height: 100%;
    background: linear-gradient(90deg, #34B182, #5FAB6F);
    border-radius: 8px;
    transition: width 0.4s ease;
}

.dash-level-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 10px;
    font-weight: 600;
    color: #859c90;
}

.dash-pct-text {
    color: #1e7253;
    font-weight: 800;
}

/* 4 Metrics Grid */
.dash-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.dash-stat-card {
    background: #ffffff;
    border: 1px solid #e2ede6;
    border-radius: 16px;
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: 0.18s;
}

.dash-stat-card:hover {
    border-color: #ade6cc;
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(20, 34, 29, 0.05);
}

.dash-stat-icon-tile {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    margin-bottom: 12px;
}

.dash-stat-icon-tile.gold { background: #fdf9ef; color: #C29B40; }
.dash-stat-icon-tile.emerald { background: #eefaf4; color: #34B182; }
.dash-stat-icon-tile.green { background: #f1f9f3; color: #5FAB6F; }
.dash-stat-icon-tile.mint { background: #f0f7f4; color: #279069; }

.dash-stat-label {
    font-size: 11px;
    font-weight: 600;
    color: #859c90;
    display: block;
    margin-bottom: 4px;
}

.dash-stat-number {
    font-size: 26px;
    font-weight: 800;
    color: #14221d;
    line-height: 1.1;
}

.dash-stat-number small {
    font-size: 12px;
    font-weight: 600;
    color: #859c90;
}

.dash-stat-subtext {
    font-size: 11px;
    font-weight: 600;
    color: #52685d;
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid #f1f5f3;
}

/* Split Grids */
.dash-split-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.dash-card {
    background: #ffffff;
    border: 1px solid #e2ede6;
    border-radius: 16px;
    padding: 20px;
}

.dash-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}

.dash-card-eyebrow {
    font-size: 10px;
    font-weight: 800;
    color: #859c90;
    letter-spacing: 0.8px;
}

.dash-card-title {
    font-size: 16px;
    font-weight: 800;
    color: #14221d;
    margin: 2px 0 0;
}

.dash-habit-badge, .dash-reward-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 8px;
}

.dash-habit-badge {
    background: #fdf9ef;
    color: #a58230;
    border: 1px solid #f1deaf;
}

.dash-reward-pill {
    background: #eefaf4;
    color: #1e7253;
    border: 1px solid #ade6cc;
}

.dash-card-desc {
    font-size: 12px;
    color: #52685d;
    margin: 0 0 16px;
    line-height: 1.5;
}

.dash-week-strip {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    margin-bottom: 16px;
}

.dash-day-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    background: #f8faf9;
    border: 1px solid #eef3f0;
    border-radius: 10px;
    padding: 10px 4px;
    position: relative;
}

.dash-day-col.is-active {
    background: #eefaf4;
    border-color: #ade6cc;
}

.dash-day-indicator {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #34B182;
}

.dash-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #d0ded6;
}

.dash-day-label {
    font-size: 11px;
    font-weight: 700;
    color: #52685d;
}

.dash-today-tag {
    font-size: 9px;
    font-weight: 800;
    color: #34B182;
}

.dash-habit-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 12px;
    border-top: 1px solid #f1f5f3;
}

.dash-habit-legend {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 10px;
    font-weight: 600;
    color: #859c90;
}

.dash-legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.dash-legend-dot.active { background: #34B182; }
.dash-legend-dot.inactive { background: #d0ded6; }

.dash-link-action {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    color: #34B182;
    text-decoration: none;
}

.dash-quest-info {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 14px;
}

.dash-quest-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #eefaf4;
    color: #34B182;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.dash-quest-name {
    font-size: 14px;
    font-weight: 800;
    color: #14221d;
    margin: 0 0 4px;
}

.dash-quest-desc {
    font-size: 11px;
    color: #859c90;
    margin: 0;
    line-height: 1.4;
}

.dash-quest-progress-block {
    margin-bottom: 14px;
}

.dash-quest-progress-header {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    margin-bottom: 6px;
    color: #52685d;
}

.dash-btn-gold {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #C29B40;
    color: #ffffff;
    font-weight: 700;
    font-size: 12px;
    padding: 8px 14px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
}

.dash-claimed-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #eefaf4;
    color: #1e7253;
    font-size: 12px;
    font-weight: 700;
    padding: 8px 14px;
    border-radius: 10px;
}

.dash-btn-outline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: 1px solid #34B182;
    background: #ffffff;
    color: #1e7253;
    font-weight: 700;
    font-size: 12px;
    padding: 8px 14px;
    border-radius: 10px;
    text-decoration: none;
}

.dash-section {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.dash-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dash-section-title {
    font-size: 18px;
    font-weight: 800;
    color: #14221d;
    margin: 2px 0 0;
}

.dash-category-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.dash-cat-card {
    padding: 18px;
    display: flex;
    flex-direction: column;
}

.dash-cat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.dash-cat-icon-tile {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    background: #eefaf4;
    color: #34B182;
}

.dash-cat-count {
    font-size: 10px;
    font-weight: 700;
    color: #859c90;
}

.dash-cat-title {
    font-size: 14px;
    font-weight: 800;
    color: #14221d;
    margin: 0 0 6px;
}

.dash-cat-desc {
    font-size: 11px;
    color: #859c90;
    line-height: 1.4;
    margin: 0 0 14px;
    flex: 1;
}

.dash-cat-progress {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.dash-cat-pct {
    font-size: 10px;
    font-weight: 800;
    color: #1e7253;
}

.dash-cat-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 11px;
    font-weight: 700;
    color: #34B182;
    padding-top: 10px;
    border-top: 1px solid #f1f5f3;
    text-decoration: none;
}

.dash-rec-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.dash-rec-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 12px;
    background: #fbfdfc;
    border: 1px solid #eef3f0;
    text-decoration: none;
}

.dash-rec-symbol {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #eefaf4;
    color: #1e7253;
    font-weight: 800;
    font-size: 13px;
    display: grid;
    place-items: center;
}

.dash-rec-info {
    flex: 1;
}

.dash-rec-info b {
    display: block;
    font-size: 12px;
    color: #14221d;
}

.dash-rec-info small {
    font-size: 10px;
    color: #859c90;
}

.dash-rec-tag {
    font-size: 10px;
    font-weight: 800;
    color: #C29B40;
    background: #fdf9ef;
    padding: 2px 6px;
    border-radius: 4px;
}

.dash-filter-pills {
    display: flex;
    gap: 4px;
}

.dash-filter-pills button {
    border: 1px solid #e2ede6;
    background: #ffffff;
    font-size: 10px;
    font-weight: 700;
    color: #52685d;
    padding: 3px 8px;
    border-radius: 6px;
    cursor: pointer;
}

.dash-filter-pills button.active {
    background: #34B182;
    border-color: #34B182;
    color: #ffffff;
}

.dash-table-wrap {
    overflow-x: auto;
}

.dash-activity-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

.dash-activity-table th {
    text-align: left;
    padding: 8px;
    border-bottom: 1px solid #eef3f0;
    color: #859c90;
    font-size: 10px;
    font-weight: 700;
}

.dash-activity-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #f6f9f7;
}

.dash-activity-table td b {
    display: block;
    color: #14221d;
}

.dash-activity-table td small {
    font-size: 10px;
    color: #859c90;
}

.dash-score-pill {
    font-weight: 800;
    color: #14221d;
}

.dash-xp-badge {
    font-weight: 700;
    color: #C29B40;
    background: #fdf9ef;
    padding: 2px 6px;
    border-radius: 4px;
}

.dash-status-passed {
    font-weight: 700;
    color: #1e7253;
}

.dash-status-retry {
    font-weight: 700;
    color: #d9534f;
}

@media (max-width: 900px) {
    .dash-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .dash-split-grid { grid-template-columns: 1fr; }
    .dash-category-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
