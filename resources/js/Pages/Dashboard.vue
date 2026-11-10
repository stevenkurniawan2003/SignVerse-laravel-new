<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowRight,
    ArrowUp,
    Award,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
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
import LevelLadderCard from '../Components/LevelLadderCard.vue';

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
    assignments: { type: Array, default: () => [] },
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
        value: masteredCount.value,
        icon: CheckCircle2,
        color: 'purple',
    },
    {
        id: 'in_progress',
        label: 'Modul Berjalan',
        value: props.progress.filter((p) => p.done > 0 && p.done < p.total).length,
        icon: RotateCw,
        color: 'amber',
    },
    {
        id: 'challenges',
        label: 'Target Hari Ini',
        value: props.challenges.filter((c) => !c.completed).length,
        icon: Target,
        color: 'rose',
    },
]);

function formatDueDate(iso) {
    if (!iso) return 'Tanpa batas waktu';
    return `Tenggat ${new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}`;
}

/**
 * Misi kata/frasa dikerjakan lewat Materi Belajar (ejaan per huruf), misi huruf lewat
 * Latihan Mandiri. Misi yang sudah tercapai diarahkan ke halaman Challenge untuk klaim XP.
 */
function challengeLink(challenge) {
    if (challenge.completed) return '/challenge';
    return challenge.category === 'kosakata' ? '/learn?bab=kosakata' : '/practice';
}

/**
 * Daftar Tugas & Misi. Status selesai selalu dari server sehingga tercentang
 * otomatis: tugas guru saat dikumpulkan, misi harian saat target tercapai.
 * Rekomendasi latihan hanya berisi pelajaran yang belum dikuasai.
 */
const tasksList = computed(() => {
    const assignmentTasks = (props.assignments || []).map((assignment) => ({
        id: `assignment_${assignment.id}`,
        title: `Tugas: ${assignment.title}`,
        subtitle: [assignment.classroom, assignment.lesson_title].filter(Boolean).join(' • ') || 'Tugas dari guru',
        date: assignment.completed ? 'Sudah dikumpulkan' : assignment.overdue ? 'Terlewat' : formatDueDate(assignment.due_at),
        reward_xp: assignment.xp_reward,
        completed: Boolean(assignment.completed),
        overdue: Boolean(assignment.overdue),
        link: assignment.link,
    }));

    const challengeTasks = (props.challenges || []).map((challenge) => ({
        id: `challenge_${challenge.id}`,
        title: challenge.title || 'Misi Harian Isyarat',
        subtitle: challenge.description || 'Latih gesture untuk bonus XP',
        date: challenge.completed ? 'Misi tercapai' : 'Hari ini',
        reward_xp: challenge.reward_xp || 20,
        completed: Boolean(challenge.completed),
        progress: Number(challenge.progress || 0),
        target: Number(challenge.target || 0),
        link: challengeLink(challenge),
    }));

    const recommendedTasks = (props.recommended || []).map((lesson) => ({
        id: `rec_${lesson.id}`,
        title: `Latihan: ${lesson.title}`,
        subtitle: lesson.material_title || 'Latihan mandiri',
        date: 'Rekomendasi',
        reward_xp: lesson.xp_reward || 10,
        completed: false,
        link: `/lesson/${lesson.id}`,
    }));

    return [...assignmentTasks, ...challengeTasks, ...recommendedTasks];
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

// "Semua Peringkat" membuka leaderboard lengkap di kartu yang sama (seperti dashboard lama)
const showAllRanks = ref(false);
const meOutsideTop = computed(() => Boolean(myEntry.value) && !boardEntries.value.some((entry) => entry.is_me));

// XP yang dibutuhkan untuk menyalip siswa satu peringkat di atas
const xpToNextRank = computed(() => {
    const me = myEntry.value;
    if (!me || me.rank === 1) return null;
    const above = [...boardEntries.value, me]
        .filter((entry) => entry.xp > me.xp)
        .sort((first, second) => first.xp - second.xp)[0];
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
                        Selamat datang kembali, {{ userName }} 👋
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
                            :percent="avgScore"
                            ring-label="Akurasi"
                            :trend-text="`${sessionsCount} sesi latihan tervalidasi`"
                            :tiles="studentTiles"
                        />
                    </div>

                    <!-- Tingkatan level siswa berdasarkan total XP -->
                    <LevelLadderCard :levels="props.levels" :summary="props.summary" />

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

                            <button
                                v-if="boardEntries.length"
                                type="button"
                                class="inline-flex items-center gap-1 text-xs font-bold text-[#1b735a] hover:text-[#34B182] transition-colors"
                                :aria-expanded="showAllRanks"
                                aria-controls="leaderboard-full"
                                @click="showAllRanks = !showAllRanks"
                            >
                                <span>{{ showAllRanks ? 'Tampilkan 3 Besar' : 'Semua Peringkat' }}</span>
                                <ChevronUp v-if="showAllRanks" :size="14" class="stroke-[2.5]" />
                                <ChevronDown v-else :size="14" class="stroke-[2.5]" />
                            </button>
                        </div>

                        <!-- Leaderboard lengkap (dibuka lewat tombol Semua Peringkat) -->
                        <div v-if="showAllRanks && boardEntries.length" id="leaderboard-full" class="space-y-3">
                            <!-- Ringkasan peringkatku -->
                            <div v-if="myEntry" class="flex items-center gap-3 p-3 rounded-2xl bg-[#eefaf4] border border-[#bfe8d4]">
                                <span class="font-display font-black text-xl text-[#1e7253] shrink-0">#{{ myEntry.rank }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[13px] font-bold text-[#14221d]">Peringkatmu dari {{ board.total }} siswa</div>
                                    <div class="text-[11px] text-[#52685d]">
                                        <template v-if="myEntry.rank === 1">Kamu memimpin! Pertahankan dengan terus berlatih.</template>
                                        <template v-else-if="xpToNextRank">Butuh <b>{{ xpToNextRank.xp }} XP</b> lagi untuk menyalip {{ xpToNextRank.name }}.</template>
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-1 text-xs font-extrabold text-[#14221d] shrink-0">
                                    <Star :size="13" class="fill-[#C29B40] text-[#C29B40]" /> {{ myEntry.xp }} XP
                                </span>
                            </div>

                            <!-- Semua baris peringkat -->
                            <ol class="space-y-1.5">
                                <li
                                    v-for="entry in boardEntries"
                                    :key="entry.user_id"
                                    class="flex items-center gap-3 px-3 py-2 rounded-xl border transition-colors duration-300"
                                    :class="[
                                        entry.is_me
                                            ? 'bg-[#eefaf4] border-[#bfe8d4]'
                                            : entry.rank === 1
                                            ? 'bg-[#fefbf3] border-[#f6e1ba]'
                                            : 'bg-[#fafcfb] border-[#eef3f0]',
                                    ]"
                                >
                                    <span
                                        class="w-7 h-7 rounded-lg font-display font-black text-xs flex items-center justify-center shrink-0"
                                        :class="[
                                            entry.rank === 1
                                                ? 'bg-[#C29B40] text-white'
                                                : entry.rank === 2
                                                ? 'bg-[#94a8a0] text-white'
                                                : entry.rank === 3
                                                ? 'bg-[#b8a07a] text-white'
                                                : 'bg-[#eef3f0] text-[#52685d]',
                                        ]"
                                    >
                                        <Crown v-if="entry.rank === 1" :size="14" />
                                        <template v-else>{{ entry.rank }}</template>
                                    </span>
                                    <span class="w-8 h-8 rounded-full bg-[#dff3ea] text-[#1e7861] font-bold text-[11px] flex items-center justify-center shrink-0">
                                        {{ entry.initials }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-display font-bold text-[13px] text-[#14221d] truncate">
                                            {{ entry.name }}
                                            <span v-if="entry.is_me" class="text-[10px] text-[#34B182] font-extrabold ml-1">(Kamu)</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[11px] text-[#738881]">
                                            <span>{{ entry.level }}</span>
                                            <span>·</span>
                                            <Flame :size="11" class="text-[#f19f38]" />
                                            <span>{{ entry.streak }} hari</span>
                                        </div>
                                    </div>
                                    <ArrowUp v-if="rankChanges[entry.user_id] === 'up'" :size="15" class="text-[#34B182] animate-bounce shrink-0" />
                                    <ArrowDown v-else-if="rankChanges[entry.user_id] === 'down'" :size="15" class="text-[#e25353] animate-bounce shrink-0" />
                                    <span class="text-xs font-extrabold text-[#14221d] shrink-0">{{ entry.xp }} <small class="text-[10px] text-[#738881]">XP</small></span>
                                </li>
                            </ol>

                            <!-- Posisiku bila berada di luar daftar teratas -->
                            <template v-if="meOutsideTop">
                                <div class="text-center text-[#a3b5ad] tracking-[0.3em] text-xs" aria-hidden="true">•••</div>
                                <div class="flex items-center gap-3 px-3 py-2 rounded-xl border bg-[#eefaf4] border-[#bfe8d4]">
                                    <span class="w-7 h-7 rounded-lg bg-[#eef3f0] text-[#52685d] font-display font-black text-xs flex items-center justify-center shrink-0">{{ myEntry.rank }}</span>
                                    <span class="w-8 h-8 rounded-full bg-[#dff3ea] text-[#1e7861] font-bold text-[11px] flex items-center justify-center shrink-0">{{ myEntry.initials }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-display font-bold text-[13px] text-[#14221d] truncate">
                                            {{ myEntry.name }} <span class="text-[10px] text-[#34B182] font-extrabold ml-1">(Kamu)</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[11px] text-[#738881]">
                                            <span>{{ myEntry.level }}</span>
                                            <span>·</span>
                                            <Flame :size="11" class="text-[#f19f38]" />
                                            <span>{{ myEntry.streak }} hari</span>
                                        </div>
                                    </div>
                                    <span class="text-xs font-extrabold text-[#14221d] shrink-0">{{ myEntry.xp }} <small class="text-[10px] text-[#738881]">XP</small></span>
                                </div>
                            </template>

                            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 text-[11px] text-[#738881]">
                                <span class="inline-flex items-center gap-1.5">
                                    <i class="w-2 h-2 rounded-full" :class="boardError ? 'bg-[#e25353]' : 'bg-[#34B182] animate-pulse'"></i>
                                    {{ boardError ? 'Koneksi terputus, mencoba lagi...' : `Diperbarui otomatis tiap 5 detik · terakhir ${updatedLabel}` }}
                                </span>
                                <Link href="/practice" class="inline-flex items-center gap-1 font-bold text-[#1b735a] hover:text-[#34B182]">
                                    Tambah XP di Latihan Mandiri <ArrowRight :size="12" class="stroke-[2.5]" />
                                </Link>
                            </div>
                        </div>

                        <!-- 3 Besar (tampilan ringkas) -->
                        <div v-else-if="boardEntries.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
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
