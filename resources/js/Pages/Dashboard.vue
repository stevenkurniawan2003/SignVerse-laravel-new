<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    ArrowDown,
    ArrowRight,
    ArrowUp,
    Award,
    BookOpen,
    Camera,
    CheckCircle,
    ChevronRight,
    Crown,
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
    leaderboard: { type: Object, default: () => ({ entries: [], me: null, total: 0 }) },
});

/*
 * Leaderboard XP realtime: polling /api/leaderboard tiap 5 detik selama tab
 * terlihat. Perubahan peringkat ditandai panah naik/turun sesaat.
 */
const LEADERBOARD_POLL_MS = 5000;
const board = ref(props.leaderboard || { entries: [], me: null, total: 0 });
const rankChanges = ref({});
const lastUpdated = ref(Date.now());
const now = ref(Date.now());
const boardError = ref(false);
let pollTimer = null;
let clockTimer = null;

const boardEntries = computed(() => board.value?.entries || []);
const myEntry = computed(() => board.value?.me || null);
const meOutsideTop = computed(() => myEntry.value && !boardEntries.value.some((entry) => entry.is_me));

// XP yang dibutuhkan untuk menyalip siswa satu peringkat di atas
const xpToNextRank = computed(() => {
    const me = myEntry.value;
    if (!me || me.rank === 1) return null;
    const all = [...boardEntries.value, me];
    const above = all.filter((entry) => entry.xp > me.xp).sort((a, b) => a.xp - b.xp)[0];
    return above ? { name: above.name, xp: above.xp - me.xp + 1 } : null;
});

const updatedLabel = computed(() => {
    const seconds = Math.max(0, Math.round((now.value - lastUpdated.value) / 1000));
    return seconds < 3 ? 'baru saja' : `${seconds} detik lalu`;
});

function applyBoard(next) {
    const previousRanks = Object.fromEntries(
        [...boardEntries.value, myEntry.value].filter(Boolean).map((entry) => [entry.user_id, entry.rank])
    );
    const changes = {};
    [...(next.entries || []), next.me].filter(Boolean).forEach((entry) => {
        const before = previousRanks[entry.user_id];
        if (before && before !== entry.rank) {
            changes[entry.user_id] = before > entry.rank ? 'up' : 'down';
        }
    });

    board.value = next;
    if (Object.keys(changes).length) {
        rankChanges.value = changes;
        setTimeout(() => (rankChanges.value = {}), 4000);
    }
}

async function refreshLeaderboard() {
    if (document.hidden) return;
    try {
        const res = await fetch('/api/leaderboard', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error(String(res.status));
        applyBoard(await res.json());
        lastUpdated.value = Date.now();
        boardError.value = false;
    } catch {
        boardError.value = true;
    }
}

function onVisibilityChange() {
    if (!document.hidden) refreshLeaderboard();
}

onMounted(() => {
    pollTimer = setInterval(refreshLeaderboard, LEADERBOARD_POLL_MS);
    clockTimer = setInterval(() => (now.value = Date.now()), 1000);
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
    clearInterval(clockTimer);
    document.removeEventListener('visibilitychange', onVisibilityChange);
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

            <!-- 3b. Leaderboard XP realtime -->
            <section class="st-panel leaderboard" aria-labelledby="leaderboard-title">
                <div class="st-panel-head">
                    <div>
                        <span class="st-eyebrow">Peringkat Siswa</span>
                        <h2 id="leaderboard-title" class="st-panel-title">Leaderboard XP</h2>
                    </div>
                    <span class="live-badge" :class="{ 'is-error': boardError }" :title="`Diperbarui ${updatedLabel}`">
                        <i></i>{{ boardError ? 'Terputus' : 'LIVE' }}
                    </span>
                </div>

                <div v-if="myEntry" class="my-rank">
                    <span class="my-rank-number">#{{ myEntry.rank }}</span>
                    <div>
                        <b>Peringkatmu dari {{ board.total }} siswa</b>
                        <small v-if="myEntry.rank === 1">Kamu memimpin! Pertahankan dengan terus berlatih.</small>
                        <small v-else-if="xpToNextRank">Butuh <b>{{ xpToNextRank.xp }} XP</b> lagi untuk menyalip {{ xpToNextRank.name }}.</small>
                    </div>
                    <span class="my-rank-xp"><Star :size="15" /> {{ myEntry.xp }} XP</span>
                </div>

                <ol v-if="boardEntries.length" class="rank-list">
                    <TransitionGroup name="rank">
                        <li
                            v-for="entry in boardEntries"
                            :key="entry.user_id"
                            class="rank-row"
                            :class="[`top-${entry.rank}`, { 'is-me': entry.is_me, 'moved-up': rankChanges[entry.user_id] === 'up', 'moved-down': rankChanges[entry.user_id] === 'down' }]"
                        >
                            <span class="rank-pos">
                                <Crown v-if="entry.rank === 1" :size="18" />
                                <template v-else>{{ entry.rank }}</template>
                            </span>
                            <span class="rank-avatar">{{ entry.initials }}</span>
                            <span class="rank-name">
                                <b>{{ entry.name }}</b>
                                <small>{{ entry.level }} · <Flame :size="11" /> {{ entry.streak }} hari</small>
                            </span>
                            <span v-if="entry.is_me" class="me-tag">Kamu</span>
                            <ArrowUp v-if="rankChanges[entry.user_id] === 'up'" :size="16" class="rank-trend up" />
                            <ArrowDown v-else-if="rankChanges[entry.user_id] === 'down'" :size="16" class="rank-trend down" />
                            <span class="rank-xp">{{ entry.xp }} <small>XP</small></span>
                        </li>
                    </TransitionGroup>
                </ol>
                <div v-else class="st-empty">Belum ada siswa di leaderboard.</div>

                <template v-if="meOutsideTop">
                    <div class="rank-gap" aria-hidden="true">•••</div>
                    <div class="rank-row is-me">
                        <span class="rank-pos">{{ myEntry.rank }}</span>
                        <span class="rank-avatar">{{ myEntry.initials }}</span>
                        <span class="rank-name">
                            <b>{{ myEntry.name }}</b>
                            <small>{{ myEntry.level }} · <Flame :size="11" /> {{ myEntry.streak }} hari</small>
                        </span>
                        <span class="me-tag">Kamu</span>
                        <span class="rank-xp">{{ myEntry.xp }} <small>XP</small></span>
                    </div>
                </template>

                <div class="st-panel-foot leaderboard-foot">
                    <small>Diperbarui otomatis tiap 5 detik · terakhir {{ updatedLabel }}</small>
                    <Link href="/practice" class="st-link">
                        Tambah XP di AI Practice <ArrowRight :size="14" />
                    </Link>
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
/* Leaderboard XP */
.leaderboard {
    display: grid;
    gap: 12px;
}

.live-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.8px;
    color: #b42318;
    background: #fef0ee;
    border-radius: 999px;
}

.live-badge i {
    width: 8px;
    height: 8px;
    background: #ef4444;
    border-radius: 50%;
    animation: live-pulse 1.4s ease-in-out infinite;
}

.live-badge.is-error {
    color: var(--st-muted);
    background: var(--st-surface);
}

.live-badge.is-error i {
    background: #94a3b8;
    animation: none;
}

@keyframes live-pulse {
    50% {
        opacity: 0.25;
    }
}

.my-rank {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: linear-gradient(120deg, #eefaf4, #fdf9ef);
    border: 1px solid #d5f3e5;
    border-radius: 14px;
}

.my-rank-number {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    min-width: 52px;
    height: 52px;
    padding: 0 6px;
    font-size: 20px;
    font-weight: 800;
    color: #fff;
    background: #34b182;
    border-radius: 14px;
}

.my-rank > div {
    display: grid;
    flex: 1;
    gap: 2px;
    min-width: 0;
}

.my-rank > div > b {
    font-size: 15px;
    color: var(--st-ink);
}

.my-rank small {
    font-size: 13px;
    color: var(--st-text);
}

.my-rank-xp {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 4px;
    font-size: 15px;
    font-weight: 800;
    color: #94711f;
}

.rank-list {
    position: relative;
    display: grid;
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.rank-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: #fff;
    border: 1px solid #eef3f0;
    border-radius: 12px;
    transition: background 0.3s, border-color 0.3s;
}

.rank-row.top-1 {
    background: #fdf9ef;
    border-color: #f1deaf;
}

.rank-row.top-2 {
    background: #f6f8fa;
    border-color: #e2e8f0;
}

.rank-row.top-3 {
    background: #fbf3ee;
    border-color: #f0dccd;
}

.rank-row.is-me {
    border: 2px solid #34b182;
    box-shadow: 0 0 0 3px #eefaf4;
}

.rank-row.moved-up {
    background: #eefaf4;
}

.rank-row.moved-down {
    background: #fef3f2;
}

.rank-pos {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 30px;
    height: 30px;
    font-size: 14px;
    font-weight: 800;
    color: var(--st-muted);
    background: var(--st-surface);
    border-radius: 50%;
}

.top-1 .rank-pos {
    color: #fff;
    background: #c29b40;
}

.top-2 .rank-pos {
    color: #fff;
    background: #94a3b8;
}

.top-3 .rank-pos {
    color: #fff;
    background: #c47a4a;
}

.rank-avatar {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 38px;
    height: 38px;
    font-size: 13px;
    font-weight: 800;
    color: #ad6544;
    background: #ffe4bd;
    border-radius: 50%;
}

.rank-name {
    display: grid;
    flex: 1;
    min-width: 0;
}

.rank-name b {
    overflow: hidden;
    font-size: 15px;
    color: var(--st-ink);
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rank-name small {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 12px;
    color: var(--st-muted);
}

.me-tag {
    flex-shrink: 0;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 800;
    color: #fff;
    background: #34b182;
    border-radius: 999px;
}

.rank-trend {
    flex-shrink: 0;
}

.rank-trend.up {
    color: #16a34a;
}

.rank-trend.down {
    color: #dc2626;
}

.rank-xp {
    flex-shrink: 0;
    min-width: 64px;
    font-size: 16px;
    font-weight: 800;
    color: var(--st-ink);
    text-align: right;
}

.rank-xp small {
    font-size: 11px;
    color: var(--st-muted);
}

.rank-gap {
    font-size: 14px;
    letter-spacing: 4px;
    color: var(--st-muted);
    text-align: center;
}

.leaderboard-foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.leaderboard-foot small {
    font-size: 12px;
    color: var(--st-muted);
}

.rank-move {
    transition: transform 0.5s ease;
}

@media (max-width: 480px) {
    .my-rank {
        flex-wrap: wrap;
    }

    .rank-row {
        gap: 8px;
        padding: 8px 10px;
    }

    .rank-avatar {
        display: none;
    }

    .rank-xp {
        min-width: 0;
        font-size: 14px;
    }
}
</style>
