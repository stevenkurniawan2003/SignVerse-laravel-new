<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    BookOpenCheck,
    ClipboardList,
    Copy,
    Plus,
    School,
    UserPlus,
    UserRound,
    Users,
} from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    classes: { type: Array, default: () => [] },
    activity: { type: Array, default: () => [] },
    students: { type: Array, default: () => [] },
    errorAnalysis: { type: Array, default: () => [] },
    activeAssignments: { type: Array, default: () => [] },
});

const page = usePage();
const teacherName = computed(() => page.props.auth?.user?.name || 'Guru');

/*
 * Data realtime: polling /teacher/dashboard/live tiap 5 detik selama tab
 * terlihat, sama seperti leaderboard siswa.
 */
const POLL_MS = 5000;
const overview = ref({ ...props.overview });
const classes = ref([...props.classes]);
const activity = ref([...props.activity]);
const students = ref([...props.students]);
const errorAnalysis = ref([...props.errorAnalysis]);
const activeAssignments = ref([...props.activeAssignments]);
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

const updatedLabel = computed(() => {
    const seconds = Math.max(0, Math.round((now.value - lastUpdated.value) / 1000));
    return seconds < 3 ? 'baru saja' : `${seconds} detik lalu`;
});

function timeAgo(iso) {
    if (!iso) return 'Belum pernah';
    const minutes = Math.floor((now.value - new Date(iso).getTime()) / 60000);
    if (minutes < 1) return 'Baru saja';
    if (minutes < 60) return `${minutes} menit lalu`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} jam lalu`;
    return `${Math.floor(hours / 24)} hari lalu`;
}

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) : 'Tanpa batas';
}

function initials(name) {
    return (name || '?')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('');
}

const recentActivity = computed(() => activity.value.slice(0, 8));

// Siswa yang perlu perhatian guru: belum/lama tidak berlatih, atau skornya rendah
const INACTIVE_DAYS = 3;
const needsAttention = computed(() =>
    students.value
        .map((student) => {
            const lastActive = student.last_activity ? new Date(student.last_activity).getTime() : null;
            const idleDays = lastActive ? Math.floor((now.value - lastActive) / 86400000) : null;
            if (!lastActive) return { ...student, reason: 'Belum pernah berlatih', tone: 'warn' };
            if (idleDays >= INACTIVE_DAYS) return { ...student, reason: `Tidak berlatih ${idleDays} hari`, tone: 'warn' };
            if (student.sessions >= 3 && student.avg_score < 60) return { ...student, reason: `Skor rata-rata ${student.avg_score}%`, tone: 'danger' };
            return null;
        })
        .filter(Boolean)
        .slice(0, 5)
);

const errorRate = (row) => (row.total ? Math.round((row.wrong / row.total) * 100) : 0);

// Tabel siswa: aktivitas terbaru di atas
const studentRows = computed(() =>
    [...students.value].sort((a, b) => new Date(b.last_activity || 0) - new Date(a.last_activity || 0))
);

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
</script>

<template>
    <AppLayout>
        <Head title="Dashboard Guru" />

        <div class="st-page td">
            <!-- Sambutan & aksi cepat -->
            <header class="td-hero">
                <div>
                    <span class="td-kicker"><School :size="14" /> Ruang Guru</span>
                    <h1>Selamat datang, {{ teacherName }}</h1>
                    <p>Pantau latihan siswa secara realtime, kelola kelas, dan berikan tugas dari sini.</p>
                </div>
                <div class="td-actions">
                    <Link href="/teacher/classes" class="st-btn st-btn--primary"><Plus :size="16" /> Buat Kelas</Link>
                    <Link href="/teacher/assignments" class="st-btn st-btn--secondary"><ClipboardList :size="16" /> Beri Tugas</Link>
                    <Link href="/teacher/students" class="st-btn st-btn--secondary"><UserPlus :size="16" /> Tambah Siswa</Link>
                </div>
            </header>

            <!-- Statistik -->
            <section class="td-stats" aria-label="Ringkasan kelas">
                <article class="td-stat">
                    <span class="td-stat-icon is-primary"><Users :size="20" /></span>
                    <div>
                        <small>Total Siswa</small>
                        <b>{{ overview.students ?? 0 }}</b>
                        <span>di {{ overview.classes ?? 0 }} kelas</span>
                    </div>
                </article>
                <article class="td-stat">
                    <span class="td-stat-icon is-green"><UserRound :size="20" /></span>
                    <div>
                        <small>Aktif Hari Ini</small>
                        <b>{{ overview.active_today ?? 0 }}</b>
                        <span>siswa berlatih hari ini</span>
                    </div>
                </article>
                <article class="td-stat">
                    <span class="td-stat-icon is-mint"><Activity :size="20" /></span>
                    <div>
                        <small>Latihan Minggu Ini</small>
                        <b>{{ overview.weekly_sessions ?? 0 }}</b>
                        <span>sesi sejak Senin</span>
                    </div>
                </article>
                <article class="td-stat">
                    <span class="td-stat-icon is-gold"><BookOpenCheck :size="20" /></span>
                    <div>
                        <small>Akurasi Rata-rata</small>
                        <b>{{ overview.avg_score ?? 0 }}<em>%</em></b>
                        <span>dari semua peragaan</span>
                    </div>
                </article>
            </section>

            <div class="td-grid">
                <!-- Feed aktivitas realtime -->
                <section class="st-panel td-panel">
                    <div class="td-panel-head">
                        <h2><Activity :size="18" /> Aktivitas Latihan Terbaru</h2>
                        <span class="td-live" :class="{ 'is-error': liveError }" :title="`Diperbarui ${updatedLabel}`">
                            <i></i>{{ liveError ? 'Terputus' : 'LIVE' }}
                        </span>
                    </div>

                    <ul v-if="recentActivity.length" class="td-feed">
                        <TransitionGroup name="td-feed">
                            <li v-for="row in recentActivity" :key="row.id">
                                <span class="td-avatar">{{ initials(row.student) }}</span>
                                <div class="td-feed-main">
                                    <b>{{ row.student }}</b>
                                    <small>Memperagakan huruf <strong>{{ row.lesson }}</strong> · {{ row.language }} · {{ timeAgo(row.at) }}</small>
                                </div>
                                <span class="td-result" :class="row.passed ? 'is-pass' : 'is-fail'">
                                    {{ row.passed ? 'Benar' : 'Belum tepat' }} · {{ row.score }}%
                                </span>
                                <span v-if="row.xp > 0" class="td-xp">+{{ row.xp }} XP</span>
                            </li>
                        </TransitionGroup>
                    </ul>
                    <p v-else class="st-empty">Belum ada latihan. Aktivitas siswa akan muncul di sini secara otomatis.</p>

                    <small class="td-updated">Diperbarui otomatis tiap 5 detik · terakhir {{ updatedLabel }}</small>
                </section>

                <!-- Kelas & tugas -->
                <div class="td-side">
                    <section class="st-panel td-panel">
                        <div class="td-panel-head">
                            <h2><School :size="18" /> Kelas Saya</h2>
                            <Link href="/teacher/classes" class="st-link">Kelola <ArrowRight :size="14" /></Link>
                        </div>
                        <ul v-if="classes.length" class="td-classes">
                            <li v-for="room in classes" :key="room.id">
                                <Link :href="`/teacher/classes/${room.id}`" class="td-class-name">
                                    <b>{{ room.name }}</b>
                                    <small>{{ room.students_count }} siswa · {{ room.assignments_count }} tugas</small>
                                </Link>
                                <button type="button" class="td-code" :title="`Salin kode ${room.code}`" @click="copyCode(room.code)">
                                    {{ copiedCode === room.code ? 'Tersalin!' : room.code }} <Copy :size="12" />
                                </button>
                            </li>
                        </ul>
                        <div v-else class="st-empty">
                            Belum ada kelas. <Link href="/teacher/classes" class="st-link">Buat kelas pertama</Link>
                        </div>
                    </section>

                    <section class="st-panel td-panel">
                        <div class="td-panel-head">
                            <h2><ClipboardList :size="18" /> Tugas Aktif</h2>
                            <Link href="/teacher/assignments" class="st-link">Semua <ArrowRight :size="14" /></Link>
                        </div>
                        <ul v-if="activeAssignments.length" class="td-tasks">
                            <li v-for="task in activeAssignments.slice(0, 4)" :key="task.id">
                                <Link :href="`/teacher/assignments/${task.id}`">
                                    <span class="td-task-letter">{{ task.lesson }}</span>
                                    <span class="td-task-main">
                                        <b>{{ task.title }}</b>
                                        <small>{{ task.classroom }} · {{ task.language }}</small>
                                    </span>
                                    <span class="td-due">{{ formatDate(task.due_at) }}</span>
                                </Link>
                            </li>
                        </ul>
                        <div v-else class="st-empty">
                            Tidak ada tugas aktif. <Link href="/teacher/assignments" class="st-link">Beri tugas</Link>
                        </div>
                    </section>
                </div>
            </div>

            <div class="td-grid td-grid--even">
                <!-- Siswa perlu perhatian -->
                <section class="st-panel td-panel">
                    <div class="td-panel-head">
                        <h2><AlertTriangle :size="18" /> Siswa Perlu Perhatian</h2>
                    </div>
                    <ul v-if="needsAttention.length" class="td-attention">
                        <li v-for="student in needsAttention" :key="student.id">
                            <span class="td-avatar">{{ initials(student.name) }}</span>
                            <Link :href="`/teacher/students/${student.id}`" class="td-attention-main">
                                <b>{{ student.name }}</b>
                                <small>{{ student.classes.join(', ') || 'Tanpa kelas' }}</small>
                            </Link>
                            <span class="td-flag" :class="`is-${student.tone}`">{{ student.reason }}</span>
                        </li>
                    </ul>
                    <div v-else class="st-empty">Semua siswa aktif berlatih. Kerja bagus!</div>
                </section>

                <!-- Isyarat sering salah -->
                <section class="st-panel td-panel">
                    <div class="td-panel-head">
                        <h2><AlertTriangle :size="18" /> Isyarat Paling Sering Salah</h2>
                    </div>
                    <ul v-if="errorAnalysis.length" class="td-errors">
                        <li v-for="row in errorAnalysis" :key="`${row.lesson_id}-${row.gesture}`">
                            <span class="td-error-letter">{{ row.lesson }}</span>
                            <div class="td-error-main">
                                <div class="td-error-top">
                                    <small>{{ row.wrong }} dari {{ row.total }} peragaan salah</small>
                                    <b>{{ errorRate(row) }}% salah</b>
                                </div>
                                <div class="td-error-track"><i :style="{ width: `${errorRate(row)}%` }"></i></div>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="st-empty">Belum ada kesalahan isyarat yang tercatat.</div>
                </section>
            </div>

            <!-- Tabel siswa -->
            <section class="st-panel td-panel">
                <div class="td-panel-head">
                    <h2><Users :size="18" /> Perkembangan Siswa</h2>
                    <Link href="/teacher/students" class="st-link">Manajemen siswa <ArrowRight :size="14" /></Link>
                </div>
                <div v-if="studentRows.length" class="td-table-wrap">
                    <table class="td-table">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Kelas</th>
                                <th class="num">Latihan</th>
                                <th class="num">Huruf dikuasai</th>
                                <th class="num">Akurasi</th>
                                <th class="num">XP</th>
                                <th>Terakhir aktif</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in studentRows" :key="row.id">
                                <td>
                                    <Link :href="`/teacher/students/${row.id}`" class="td-user">
                                        <span class="td-avatar">{{ initials(row.name) }}</span>
                                        <span>
                                            <b>{{ row.name }}</b>
                                            <small>{{ row.email }}</small>
                                        </span>
                                    </Link>
                                </td>
                                <td>
                                    <span v-for="name in row.classes" :key="name" class="td-chip">{{ name }}</span>
                                </td>
                                <td class="num">{{ row.sessions }}</td>
                                <td class="num">{{ row.mastered }}</td>
                                <td class="num">
                                    <span :class="row.avg_score >= 60 ? 'td-good' : 'td-low'">{{ row.avg_score }}%</span>
                                </td>
                                <td class="num">{{ row.xp }}</td>
                                <td>{{ timeAgo(row.last_activity) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="st-empty">
                    Belum ada siswa. Buat kelas lalu bagikan kodenya, atau <Link href="/teacher/students" class="st-link">buatkan akun siswa</Link>.
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.td {
    display: grid;
    gap: 18px;
    padding-bottom: 32px;
}

/* Header */
.td-hero {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 22px 24px;
    background: linear-gradient(120deg, #ffffff, #eefaf4);
    border: 1px solid #e2ede6;
    border-radius: 20px;
}

.td-kicker {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 700;
    color: #1e7253;
    background: #fff;
    border: 1px solid #d5f3e5;
    border-radius: 999px;
}

.td-hero h1 {
    margin: 10px 0 6px;
    font-size: 26px;
    font-weight: 800;
    color: #14221d;
}

.td-hero p {
    margin: 0;
    font-size: 15px;
    color: #52685d;
}

.td-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

/* Statistik */
.td-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.td-stat {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    background: #fff;
    border: 1px solid #e2ede6;
    border-radius: 18px;
}

.td-stat-icon {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 46px;
    height: 46px;
    border-radius: 14px;
}

.td-stat-icon.is-primary { color: #34b182; background: #eefaf4; }
.td-stat-icon.is-green { color: #5fab6f; background: #f1f9f3; }
.td-stat-icon.is-mint { color: #279069; background: #f0f7f4; }
.td-stat-icon.is-gold { color: #c29b40; background: #fdf9ef; }

.td-stat small {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #6b8176;
}

.td-stat b {
    display: block;
    font-size: 28px;
    font-weight: 800;
    line-height: 1.15;
    color: #14221d;
}

.td-stat b em {
    font-size: 16px;
    font-style: normal;
    color: #6b8176;
}

.td-stat span {
    font-size: 12px;
    color: #859c90;
}

/* Grid konten */
.td-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
    gap: 18px;
    align-items: start;
}

.td-grid--even {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.td-side {
    display: grid;
    gap: 18px;
}

.td-panel {
    display: grid;
    gap: 12px;
    padding: 18px 20px;
    border-radius: 20px;
}

.td-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.td-panel-head h2 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 17px;
    font-weight: 800;
    color: #14221d;
}

.td-panel-head .st-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Badge live */
.td-live {
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

.td-live i {
    width: 8px;
    height: 8px;
    background: #ef4444;
    border-radius: 50%;
    animation: td-pulse 1.4s ease-in-out infinite;
}

.td-live.is-error {
    color: #6b8176;
    background: #f1f5f3;
}

.td-live.is-error i {
    background: #94a3b8;
    animation: none;
}

@keyframes td-pulse {
    50% { opacity: 0.25; }
}

.td-updated {
    font-size: 12px;
    color: #859c90;
}

/* Feed */
.td-feed,
.td-classes,
.td-tasks,
.td-attention,
.td-errors {
    display: grid;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.td-feed li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: #fbfdfc;
    border: 1px solid #eef3f0;
    border-radius: 12px;
}

.td-feed-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}

.td-feed-enter-active {
    transition: 0.35s ease;
}

.td-avatar {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 36px;
    height: 36px;
    font-size: 12px;
    font-weight: 800;
    color: #ad6544;
    background: #ffe4bd;
    border-radius: 50%;
}

.td-feed-main {
    display: grid;
    flex: 1;
    min-width: 0;
}

.td-feed-main b {
    overflow: hidden;
    font-size: 14px;
    color: #14221d;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.td-feed-main small {
    overflow: hidden;
    font-size: 12px;
    color: #6b8176;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.td-result {
    flex-shrink: 0;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 800;
    border-radius: 999px;
}

.td-result.is-pass { color: #1e7253; background: #eefaf4; }
.td-result.is-fail { color: #b4562a; background: #fdeee4; }

.td-xp {
    flex-shrink: 0;
    font-size: 12px;
    font-weight: 800;
    color: #94711f;
}

/* Kelas */
.td-classes li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: #fbfdfc;
    border: 1px solid #eef3f0;
    border-radius: 12px;
}

.td-class-name {
    display: grid;
    flex: 1;
    min-width: 0;
    color: inherit;
}

.td-class-name b {
    overflow: hidden;
    font-size: 14px;
    color: #14221d;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.td-class-name small {
    font-size: 12px;
    color: #6b8176;
}

.td-code {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    font-family: ui-monospace, monospace;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 1.5px;
    color: #1e7253;
    background: #eefaf4;
    border: 1px dashed #9fdcbf;
    border-radius: 10px;
    cursor: pointer;
}

/* Tugas */
.td-tasks a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    color: inherit;
    background: #fbfdfc;
    border: 1px solid #eef3f0;
    border-radius: 12px;
}

.td-task-letter,
.td-error-letter {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 38px;
    height: 38px;
    font-size: 18px;
    font-weight: 800;
    color: #fff;
    background: #34b182;
    border-radius: 11px;
}

.td-task-main {
    display: grid;
    flex: 1;
    min-width: 0;
}

.td-task-main b {
    overflow: hidden;
    font-size: 14px;
    color: #14221d;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.td-task-main small {
    font-size: 12px;
    color: #6b8176;
}

.td-due {
    flex-shrink: 0;
    font-size: 12px;
    font-weight: 700;
    color: #52685d;
}

/* Perlu perhatian */
.td-attention li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: #fbfdfc;
    border: 1px solid #eef3f0;
    border-radius: 12px;
}

.td-attention-main {
    display: grid;
    flex: 1;
    min-width: 0;
    color: inherit;
}

.td-attention-main b {
    font-size: 14px;
    color: #14221d;
}

.td-attention-main small {
    font-size: 12px;
    color: #6b8176;
}

.td-flag {
    flex-shrink: 0;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 999px;
}

.td-flag.is-warn { color: #8a6a1f; background: #fdf6e6; }
.td-flag.is-danger { color: #b42318; background: #fef0ee; }

/* Isyarat sering salah */
.td-errors li {
    display: flex;
    align-items: center;
    gap: 12px;
}

.td-error-letter {
    color: #b4562a;
    background: #fdeee4;
}

.td-error-main {
    display: grid;
    flex: 1;
    gap: 6px;
}

.td-error-top {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
}

.td-error-top small {
    color: #6b8176;
}

.td-error-top b {
    color: #b4562a;
}

.td-error-track {
    height: 8px;
    overflow: hidden;
    background: #f1f5f3;
    border-radius: 999px;
}

.td-error-track i {
    display: block;
    height: 100%;
    background: #e07a3f;
    border-radius: 999px;
}

/* Tabel siswa */
.td-table-wrap {
    overflow-x: auto;
}

.td-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.td-table th {
    padding: 10px 12px;
    font-size: 12px;
    font-weight: 700;
    color: #6b8176;
    text-align: left;
    white-space: nowrap;
    border-bottom: 1px solid #e2ede6;
}

.td-table td {
    padding: 12px;
    color: #14221d;
    white-space: nowrap;
    border-bottom: 1px solid #f1f5f3;
}

.td-table .num {
    text-align: right;
}

.td-user {
    display: flex;
    align-items: center;
    gap: 10px;
    color: inherit;
}

.td-user b {
    display: block;
    font-size: 14px;
}

.td-user small {
    display: block;
    font-size: 12px;
    color: #6b8176;
}

.td-chip {
    display: inline-block;
    margin: 2px 4px 2px 0;
    padding: 3px 10px;
    font-size: 12px;
    font-weight: 700;
    color: #1e7253;
    background: #eefaf4;
    border: 1px solid #d5f3e5;
    border-radius: 999px;
}

.td-good { font-weight: 800; color: #1e7253; }
.td-low { font-weight: 800; color: #b4562a; }

/* Responsif */
@media (max-width: 1180px) {
    .td-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .td-grid,
    .td-grid--even {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 560px) {
    .td-stats {
        grid-template-columns: 1fr;
    }

    .td-hero {
        padding: 18px 16px;
    }

    .td-hero h1 {
        font-size: 22px;
    }

    .td-actions .st-btn {
        flex: 1;
    }

    .td-feed li {
        flex-wrap: wrap;
    }

    .td-feed-main {
        flex-basis: calc(100% - 46px);
    }
}
</style>
