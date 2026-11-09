<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    Award,
    BookOpenCheck,
    Check,
    CheckCircle2,
    ClipboardList,
    Copy,
    GraduationCap,
    Plus,
    RotateCw,
    School,
    Search,
    Sparkles,
    Star,
    Target,
    UserPlus,
    Users,
} from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ActivityBarChart from '../../Components/ActivityBarChart.vue';
import ProgressOverviewCard from '../../Components/ProgressOverviewCard.vue';
import ProfileOverviewCard from '../../Components/ProfileOverviewCard.vue';
import MiniCalendar from '../../Components/MiniCalendar.vue';
import TodoTasksCard from '../../Components/TodoTasksCard.vue';

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    classes: { type: Array, default: () => [] },
    activity: { type: Array, default: () => [] },
    students: { type: Array, default: () => [] },
    errorAnalysis: { type: Array, default: () => [] },
    activeAssignments: { type: Array, default: () => [] },
    weekActivity: { type: Array, default: () => [] },
});

const page = usePage();
const teacherName = computed(() => page.props.auth?.user?.name || 'Guru Pembimbing');

// Search filter
const searchQuery = ref('');

/*
 * Realtime polling: /teacher/dashboard/live tiap 5 detik selama tab aktif
 */
const POLL_MS = 5000;
const overview = ref({ ...props.overview });
const classes = ref([...props.classes]);
const activity = ref([...props.activity]);
const students = ref([...props.students]);
const errorAnalysis = ref([...props.errorAnalysis]);
const activeAssignments = ref([...props.activeAssignments]);
const weekActivity = ref(props.weekActivity ? [...props.weekActivity] : []);
const lastUpdated = ref(Date.now());
const now = ref(Date.now());
const liveError = ref(false);
let pollTimer = null;
let clockTimer = null;

async function poll() {
    if (document.hidden) return;
    try {
        const res = await fetch('/teacher/dashboard/live', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) throw new Error(String(res.status));
        const data = await res.json();
        overview.value = data.overview ?? overview.value;
        classes.value = data.classes ?? classes.value;
        activity.value = data.activity ?? activity.value;
        students.value = data.students ?? students.value;
        errorAnalysis.value = data.errorAnalysis ?? errorAnalysis.value;
        activeAssignments.value = data.activeAssignments ?? activeAssignments.value;
        if (data.weekActivity) weekActivity.value = data.weekActivity;
        lastUpdated.value = Date.now();
        liveError.value = false;
    } catch {
        liveError.value = true;
    }
}

function onVisibilityChange() {
    if (!document.hidden) poll();
}

onMounted(() => {
    pollTimer = setInterval(poll, POLL_MS);
    clockTimer = setInterval(() => (now.value = Date.now()), 1000);
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
    clearInterval(clockTimer);
    document.removeEventListener('visibilitychange', onVisibilityChange);
});

// Copy class code to clipboard
const copiedCode = ref('');
async function copyCode(code) {
    try {
        await navigator.clipboard.writeText(code);
        copiedCode.value = code;
        setTimeout(() => (copiedCode.value = ''), 2000);
    } catch {
        copiedCode.value = '';
    }
}

// Teacher metric tiles for ProgressOverviewCard
const teacherTiles = computed(() => [
    {
        id: 'students',
        label: 'Siswa Terdaftar',
        value: overview.value.students ?? 0,
        icon: Users,
        color: 'purple',
    },
    {
        id: 'classes',
        label: 'Kelas Dikelola',
        value: overview.value.classes ?? 0,
        icon: School,
        color: 'amber',
    },
    {
        id: 'assignments',
        label: 'Tugas Aktif',
        value: overview.value.active_assignments ?? 0,
        icon: ClipboardList,
        color: 'rose',
    },
]);

// Build tasks for Teacher To-Do List (Assignments + Students needing attention)
const teacherTasks = computed(() => {
    const list = [];

    // Active assignments
    (activeAssignments.value || []).forEach((a) => {
        list.push({
            id: `assign_${a.id}`,
            title: `Tugas: ${a.title}`,
            subtitle: `${a.classroom || 'Kelas'} • ${a.lesson || 'Latihan'}`,
            date: a.due_at ? new Date(a.due_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) : 'Tanpa batas',
            badge: a.language || 'SIBI',
            completed: false,
            link: '/teacher/assignments',
        });
    });

    // Students needing attention
    const INACTIVE_DAYS = 3;
    students.value.forEach((student) => {
        const lastActive = student.last_activity ? new Date(student.last_activity).getTime() : null;
        const idleDays = lastActive ? Math.floor((now.value - lastActive) / 86400000) : null;
        if (!lastActive) {
            list.push({
                id: `attn_${student.id}`,
                title: `Bimbing ${student.name}`,
                subtitle: 'Belum pernah berlatih di sistem',
                date: 'Perlu Perhatian',
                badge: 'Perhatian',
                completed: false,
                link: `/teacher/students`,
            });
        } else if (idleDays >= INACTIVE_DAYS) {
            list.push({
                id: `attn_${student.id}`,
                title: `Ingatkan ${student.name}`,
                subtitle: `Tidak berlatih ${idleDays} hari`,
                date: 'Follow-up',
                badge: 'Inaktif',
                completed: false,
                link: `/teacher/students`,
            });
        }
    });

    if (list.length === 0) {
        list.push({
            id: 'teacher_task_default',
            title: 'Buat Tugas Latihan Isyarat',
            subtitle: 'Berikan tugas baru untuk kelas SLB',
            date: 'Hari ini',
            badge: 'Tugas Baru',
            completed: false,
            link: '/teacher/assignments',
        });
    }

    return list;
});

// Filtered classrooms based on search query
const filteredClasses = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return classes.value;
    return classes.value.filter(
        (cls) => cls.name?.toLowerCase().includes(q) || cls.code?.toLowerCase().includes(q)
    );
});
</script>

<template>
    <AppLayout>
        <Head title="Dashboard Guru — SignTech" />

        <div class="teacher-dashboard space-y-6 max-w-full">
            <!-- Top Greeting & Action Pills Bar -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h1 class="font-display font-extrabold text-2xl sm:text-3xl text-[#14221d] tracking-tight">
                        Welcome back, Bu/Pak {{ teacherName }} 👋
                    </h1>
                    <p class="text-xs sm:text-sm text-[#667e76] font-medium mt-1">
                        Pantau aktivitas siswa, kelola modul isyarat, dan evaluasi hasil belajar secara realtime.
                    </p>
                </div>

                <!-- Action Pills & Search Input -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Search input -->
                    <div class="relative w-full sm:w-64">
                        <Search :size="15" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#7f958e]" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari kelas atau siswa..."
                            class="w-full pl-9 pr-3.5 py-2 rounded-full bg-white border border-[#e2ece6] text-xs font-semibold text-[#14221d] placeholder-[#8ea39c] focus:outline-none focus:ring-2 focus:ring-[#34B182] shadow-xs transition-all"
                        />
                    </div>

                    <!-- Quick Action Buttons -->
                    <Link
                        href="/teacher/classes"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-[#143d34] hover:bg-[#1a4a40] text-white text-xs font-bold shadow-xs transition-colors"
                    >
                        <Plus :size="14" class="stroke-[2.5]" />
                        <span>Buat Kelas</span>
                    </Link>

                    <Link
                        href="/teacher/assignments"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full bg-[#e8f7f0] hover:bg-[#34B182] text-[#1b735a] hover:text-white text-xs font-bold shadow-xs transition-colors"
                    >
                        <ClipboardList :size="14" class="stroke-[2.5]" />
                        <span>Beri Tugas</span>
                    </Link>
                </div>
            </div>

            <!-- Unified 12-Column Grid (shadcn Fluid Dashboard Style) -->
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
                <!-- Main Area: 8 cols on desktop (col-span-12 xl:col-span-8) -->
                <div class="xl:col-span-8 flex flex-col gap-6 min-w-0">
                    <!-- Row 1: Student Activity Bar Chart & Class Performance Card -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 sm:gap-6">
                        <!-- Activity Bar Chart for Students in Teacher's Classes -->
                        <ActivityBarChart
                            :week-activity="weekActivity"
                            title="Aktivitas Latihan Siswa"
                            period-label="7 Hari Terakhir"
                            stat-label="latihan siswa"
                        />

                        <!-- Class Performance Overview Card -->
                        <ProgressOverviewCard
                            title="Kinerja Rata-rata Kelas"
                            period-label="Bulan ini"
                            :percent="overview.avg_score || 0"
                            ring-label="Akurasi Kelas"
                            trend-text="Rata-rata evaluasi siswa"
                            :tiles="teacherTiles"
                        />
                    </div>

                    <!-- Row 2: Active Classrooms (Playful Cards Grid) -->
                    <div class="classrooms-section">
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <div>
                                <h3 class="font-display font-extrabold text-xl text-[#14221d] tracking-tight">
                                    Ruang Kelas Dikelola
                                </h3>
                                <p class="text-xs text-[#738881]">
                                    Bagikan kode kelas ke siswa agar langsung terhubung dengan akun guru.
                                </p>
                            </div>

                            <Link
                                href="/teacher/classes"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-[#e8f7f0] text-[#1b735a] hover:bg-[#34B182] hover:text-white transition-colors"
                            >
                                <span>Kelola Semua Kelas</span>
                                <ArrowRight :size="13" class="stroke-[2.5]" />
                            </Link>
                        </div>

                        <!-- Classroom Cards Grid -->
                        <div v-if="filteredClasses.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                            <div
                                v-for="cls in filteredClasses"
                                :key="cls.id"
                                class="classroom-card bg-white rounded-[24px] p-5 sm:p-6 border border-[#e8efe9] shadow-sm flex flex-col justify-between transition-all duration-300 hover:shadow-lg hover:-translate-y-1 group"
                            >
                                <div>
                                    <!-- Header: Code pill & Copy button -->
                                    <div class="flex items-center justify-between gap-2 mb-3">
                                        <span class="px-2.5 py-1 rounded-xl text-[11px] font-extrabold tracking-wider bg-[#e8f7f0] text-[#18755c]">
                                            KELAS SLB
                                        </span>

                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[11px] font-bold transition-all"
                                            :class="[
                                                copiedCode === cls.code
                                                    ? 'bg-[#34B182] text-white shadow-sm'
                                                    : 'bg-[#f4f8f5] text-[#18755c] hover:bg-[#e6f4ed]',
                                            ]"
                                            :title="`Salin kode: ${cls.code}`"
                                            @click="copyCode(cls.code)"
                                        >
                                            <Check v-if="copiedCode === cls.code" :size="12" class="stroke-[3]" />
                                            <Copy v-else :size="12" />
                                            <span>{{ copiedCode === cls.code ? 'Tersalin!' : cls.code }}</span>
                                        </button>
                                    </div>

                                    <!-- Class Name -->
                                    <h4 class="font-display font-extrabold text-[17px] text-[#14221d] leading-snug group-hover:text-[#18755c] transition-colors mb-3">
                                        {{ cls.name }}
                                    </h4>

                                    <!-- Class stats row -->
                                    <div class="flex items-center gap-3 text-[11px] font-semibold text-[#667e76] mb-5">
                                        <div class="flex items-center gap-1">
                                            <Users :size="13" class="text-[#34B182]" />
                                            <span>{{ cls.students_count ?? 0 }} Siswa</span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <ClipboardList :size="13" class="text-[#C29B40]" />
                                            <span>{{ cls.assignments_count ?? 0 }} Tugas</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Footer: Action Link -->
                                <div class="pt-3 border-t border-[#f0f5f2] flex items-center justify-between">
                                    <span class="text-[11px] font-bold text-[#8ba098]">Status: Aktif</span>
                                    <Link
                                        :href="`/teacher/classes/${cls.id}`"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-[#f4f8f5] hover:bg-[#34B182] text-[#196b54] hover:text-white font-bold text-xs transition-colors"
                                    >
                                        <span>Buka Kelas</span>
                                        <ArrowRight :size="12" class="stroke-[2.5]" />
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-else class="text-center py-10 bg-white rounded-[24px] border border-dashed border-[#d9e5df]">
                            <School :size="32" class="mx-auto text-[#94a8a0] mb-2" />
                            <p class="text-sm font-bold text-[#14221d] mb-1">Belum ada kelas yang dibuat.</p>
                            <p class="text-xs text-[#738881] mb-4">Buat ruang kelas pertama Anda untuk mengundang siswa.</p>
                            <Link
                                href="/teacher/classes"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#34B182] text-white text-xs font-bold shadow-md shadow-[#34B182]/25"
                            >
                                <Plus :size="14" class="stroke-[2.5]" />
                                <span>Buat Kelas Sekarang</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Row 3: Gesture Error Analysis / Tantangan Isyarat Siswa -->
                    <div class="error-analysis-section bg-white rounded-[24px] p-5 sm:p-6 border border-[#e8efe9] shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-[#fef1f0] text-[#e25353] flex items-center justify-center">
                                    <AlertTriangle :size="18" class="stroke-[2.2]" />
                                </div>
                                <div>
                                    <h3 class="font-display font-extrabold text-[17px] text-[#14221d] tracking-tight">
                                        Analisis Gerakan yang Perlu Bimbingan
                                    </h3>
                                    <p class="text-[11px] text-[#738881]">
                                        Bentuk isyarat yang paling sering keliru saat latihan mandiri, perlu bimbingan tambahan.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href="/teacher/reports"
                                class="inline-flex items-center gap-1 text-xs font-bold text-[#1b735a] hover:text-[#34B182] transition-colors"
                            >
                                <span>Laporan Lengkap</span>
                                <ArrowRight :size="13" class="stroke-[2.5]" />
                            </Link>
                        </div>

                        <!-- Error cards grid -->
                        <div v-if="errorAnalysis.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div
                                v-for="err in errorAnalysis"
                                :key="err.label"
                                class="p-3.5 rounded-2xl bg-[#fdfaf8] border border-[#f5e6dc] flex items-start gap-3"
                            >
                                <div class="w-10 h-10 rounded-xl bg-[#e25353] text-white font-display font-extrabold text-base flex items-center justify-center shrink-0 shadow-sm">
                                    {{ err.label }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-display font-bold text-xs text-[#14221d] mb-0.5">
                                        Huruf / Isyarat "{{ err.label }}"
                                    </div>
                                    <div class="text-[11px] text-[#7a8c85]">
                                        Tingkat Kesalahan: <b class="text-[#e25353]">{{ err.wrong ?? 0 }}x</b> dari {{ err.total ?? 0 }} percobaan
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-6 text-xs text-[#738881]">
                            Belum ada rekaman kesalahan signifikan dari siswa. Semua latihan berjalan lancar!
                        </div>
                    </div>
                </div>

                <!-- Right Insights Area: 4 cols on desktop (col-span-12 xl:col-span-4) -->
                <div class="xl:col-span-4 flex flex-col gap-5 min-w-0">
                    <!-- 1. Teacher Profile Overview Card -->
                    <ProfileOverviewCard
                        :user="page.props.auth?.user"
                        :summary="overview"
                        :is-teacher="true"
                    />

                    <!-- 2. Mini Calendar -->
                    <MiniCalendar />

                    <!-- 3. Teacher To-Do List (Assignments & Follow-ups) -->
                    <TodoTasksCard
                        title="Tugas & Perhatian Siswa"
                        :tasks="teacherTasks"
                        :is-teacher="true"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.teacher-dashboard {
    font-family: 'Giphurs', 'Poppins', system-ui, sans-serif;
}
.classroom-card {
    min-height: 180px;
}
</style>
