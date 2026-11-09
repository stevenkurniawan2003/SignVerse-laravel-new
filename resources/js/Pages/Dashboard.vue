<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowRight,
    ArrowUp,
    Award,
    CheckCircle2,
    Crown,
    Flame,
    RotateCw,
    Search,
    Sparkles,
    Star,
    Target,
    Trophy,
} from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';
import ActivityBarChart from '../Components/ActivityBarChart.vue';
import ProgressOverviewCard from '../Components/ProgressOverviewCard.vue';
import CoursesInProgress from '../Components/CoursesInProgress.vue';
import ProfileOverviewCard from '../Components/ProfileOverviewCard.vue';
import MiniCalendar from '../Components/MiniCalendar.vue';
import TodoTasksCard from '../Components/TodoTasksCard.vue';

const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    levels: { type: Array, default: () => [] },
    progress: { type: Array, default: () => [] },
    recentSessions: { type: Array, default: () => [] },
    recommended: { type: Array, default: () => [] },
    xpHistory: { type: Array, default: () => [] },
    challenges: { type: Array, default: () => [] },
    weekActivity: { type: Array, default: () => [] },
    user: { type: Object, default: () => null },
    leaderboard: { type: Object, default: () => ({ entries: [], me: null, total: 0 }) },
});

// Search input for quick filtering courses or materials
const searchQuery = ref('');

// User info & stats
const userName = computed(() => props.user?.name || 'Pelajar Isyarat');
const totalXp = computed(() => Number(props.summary?.xp ?? props.summary?.total_xp ?? 0));
const currentStreak = computed(() => Number(props.summary?.streak ?? props.summary?.current_streak ?? 0));
const avgScore = computed(() => Number(props.summary?.avg_score ?? 0));
const masteredCount = computed(() => Number(props.summary?.lessons_mastered ?? 0));
const sessionsCount = computed(() => Number(props.summary?.sessions ?? props.summary?.total_sessions ?? 0));

// Student metric tiles for ProgressOverviewCard
const studentTiles = computed(() => [
    {
        id: 'mastered',
        label: 'Huruf Dikuasai',
        value: masteredCount.value || 8,
        icon: CheckCircle2,
        color: 'purple',
    },
    {
        id: 'in_progress',
        label: 'Modul Berjalan',
        value: props.progress.filter((p) => p.done > 0 && p.done < p.total).length || 3,
        icon: RotateCw,
        color: 'amber',
    },
    {
        id: 'challenges',
        label: 'Target Hari Ini',
        value: props.challenges.filter((c) => !c.completed).length || 4,
        icon: Target,
        color: 'rose',
    },
]);

// Build tasks list for TodoTasksCard
const tasksList = computed(() => {
    const list = [];

    // Daily Challenges
    (props.challenges || []).forEach((ch) => {
        list.push({
            id: `challenge_${ch.id}`,
            title: ch.title || 'Misi Harian Isyarat',
            subtitle: ch.description || 'Latih gesture untuk bonus XP',
            date: 'Hari ini',
            reward_xp: ch.reward_xp || 20,
            completed: ch.completed || false,
            link: '/challenge',
        });
    });

    // Recommended Lessons
    (props.recommended || []).forEach((rec) => {
        list.push({
            id: `rec_${rec.id}`,
            title: `Latihan: ${rec.title}`,
            subtitle: rec.material_title || 'SIBI Dasar',
            date: 'Rekomendasi',
            reward_xp: rec.xp_reward || 10,
            completed: false,
            link: `/practice?target=${rec.title}`,
        });
    });

    // Fallbacks if empty
    if (list.length === 0) {
        list.push(
            {
                id: 'task_default_1',
                title: 'Latihan Huruf Vokal A - E',
                subtitle: 'Gunakan kamera latihan untuk periksa gerakan tangan',
                date: 'Hari ini',
                reward_xp: 25,
                completed: false,
                link: '/practice',
            },
            {
                id: 'task_default_2',
                title: 'Tantangan 3 Gesture Tepat',
                subtitle: 'Capai akurasi di atas 80%',
                date: 'Hari ini',
                reward_xp: 20,
                completed: false,
                link: '/challenge',
            }
        );
    }

    return list;
});

/*
 * Realtime Leaderboard Polling (XP Rank)
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
            headers: { Accept: 'application/json' },
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
</script>

<template>
    <AppLayout>
        <Head title="Dashboard Siswa — SignTech" />

        <div class="dashboard-shell space-y-6 max-w-full">
            <!-- Top Greeting & Search Pill Bar -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="font-display font-extrabold text-2xl sm:text-3xl text-[#14221d] tracking-tight">
                        Welcome back, {{ userName }} 👋
                    </h1>
                    <p class="text-xs sm:text-sm text-[#667e76] font-medium mt-1">
                        Ayo mulai belajar sesuatu yang baru dan asah kemampuan isyaratmu hari ini!
                    </p>
                </div>

                <!-- Search Pill Input -->
                <div class="relative w-full md:w-80">
                    <Search :size="16" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#7f958e]" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari materi atau huruf isyarat..."
                        class="w-full pl-9 pr-4 py-2.5 rounded-full bg-white border border-[#e2ece6] text-xs font-semibold text-[#14221d] placeholder-[#8ea39c] focus:outline-none focus:ring-2 focus:ring-[#34B182] shadow-xs transition-all"
                    />
                </div>
            </div>

            <!-- Unified 12-Column Grid (shadcn Fluid Dashboard Style) -->
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
                <!-- Main Area (8 cols on desktop: col-span-12 xl:col-span-8) -->
                <div class="xl:col-span-8 flex flex-col gap-6 min-w-0">
                    <!-- Row 1: Activity Bar Chart (7-day Pill Bars) & Progress Overview Card -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 sm:gap-6">
                        <!-- Activity Card -->
                        <ActivityBarChart
                            :week-activity="props.weekActivity"
                            title="Aktivitas Belajar"
                            period-label="Minggu ini"
                            stat-label="sesi latihan"
                        />

                        <!-- Progress Card -->
                        <ProgressOverviewCard
                            title="Kemajuan Isyarat"
                            period-label="Bulan ini"
                            :percent="avgScore || 78"
                            ring-label="Akurasi"
                            trend-text="+12% peningkatan dari awal"
                            :tiles="studentTiles"
                        />
                    </div>

                    <!-- Row 2: Courses in Progress (3 Card Grid) -->
                    <CoursesInProgress
                        title="Materi Sedang Berjalan"
                        :categories="props.progress"
                        :is-teacher="false"
                    />

                    <!-- Row 3: Leaderboard / Peringkat Belajar (Realtime Gamification) -->
                    <div class="leaderboard-card bg-white rounded-[24px] p-5 sm:p-6 border border-[#e8efe9] shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-[#fdf5e7] text-[#C29B40] flex items-center justify-center">
                                    <Trophy :size="18" class="stroke-[2.2]" />
                                </div>
                                <div>
                                    <h3 class="font-display font-extrabold text-[17px] text-[#14221d] tracking-tight">
                                        Peringkat Kelas Realtime
                                    </h3>
                                    <p class="text-[11px] text-[#738881]">
                                        Diperbarui otomatis berdasarkan perolehan XP latihan siswa
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/achievement"
                                class="inline-flex items-center gap-1 text-xs font-bold text-[#1b735a] hover:text-[#34B182] transition-colors"
                            >
                                <span>Semua Peringkat</span>
                                <ArrowRight :size="13" class="stroke-[2.5]" />
                            </Link>
                        </div>

                        <!-- Leaderboard Table / Rows -->
                        <div v-if="boardEntries.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div
                                v-for="entry in boardEntries.slice(0, 3)"
                                :key="entry.user_id"
                                class="flex items-center gap-3 p-3 rounded-2xl border transition-all duration-200"
                                :class="[
                                    entry.rank === 1
                                        ? 'bg-gradient-to-r from-[#fefbf3] to-[#fdf6e6] border-[#f6e1ba]'
                                        : entry.rank === 2
                                        ? 'bg-[#f8faf9] border-[#e5ece8]'
                                        : 'bg-[#fafcfb] border-[#e8efe9]',
                                ]"
                            >
                                <!-- Rank Medal / Number -->
                                <div
                                    class="w-8 h-8 rounded-xl font-display font-black text-sm flex items-center justify-center shrink-0 shadow-sm"
                                    :class="[
                                        entry.rank === 1
                                            ? 'bg-[#C29B40] text-white ring-2 ring-[#C29B40]/30'
                                            : entry.rank === 2
                                            ? 'bg-[#94a8a0] text-white'
                                            : 'bg-[#b8a07a] text-white',
                                    ]"
                                >
                                    {{ entry.rank }}
                                </div>

                                <!-- Name and XP -->
                                <div class="min-w-0 flex-1">
                                    <div class="font-display font-bold text-[13px] text-[#14221d] truncate">
                                        {{ entry.name }}
                                        <span v-if="entry.is_me" class="text-[10px] text-[#34B182] font-extrabold ml-1">(Kamu)</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[11px] text-[#738881]">
                                        <Star :size="11" class="fill-[#C29B40] text-[#C29B40]" />
                                        <span class="font-bold text-[#14221d]">{{ entry.xp }} XP</span>
                                        <span>• {{ entry.level }}</span>
                                    </div>
                                </div>

                                <!-- Rank change indicator -->
                                <div v-if="rankChanges[entry.user_id]" class="shrink-0">
                                    <ArrowUp
                                        v-if="rankChanges[entry.user_id] === 'up'"
                                        :size="15"
                                        class="text-[#34B182] animate-bounce"
                                    />
                                    <ArrowDown
                                        v-else
                                        :size="15"
                                        class="text-[#e25353] animate-bounce"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-else class="text-center py-6 text-xs text-[#738881]">
                            Belum ada data peringkat latihan hari ini.
                        </div>
                    </div>
                </div>

                <!-- Right Insights Column (4 cols on desktop: col-span-12 xl:col-span-4) -->
                <div class="xl:col-span-4 flex flex-col gap-5 min-w-0">
                    <!-- 1. Profile Overview Card with Avatar Arc -->
                    <ProfileOverviewCard
                        :user="props.user"
                        :summary="props.summary"
                        :is-teacher="false"
                    />

                    <!-- 2. Mini Calendar with elevated today pill -->
                    <MiniCalendar />

                    <!-- 3. To Do List (Misi & Tugas Siswa) -->
                    <TodoTasksCard
                        title="Daftar Tugas & Misi"
                        :tasks="tasksList"
                        :is-teacher="false"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.dashboard-shell {
    font-family: 'Giphurs', 'Poppins', system-ui, sans-serif;
}
</style>
