<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    BookOpenCheck,
    CalendarRange,
    CheckCircle,
    ClipboardList,
    Download,
    FileBarChart,
    Printer,
    Target,
    Users,
} from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    report: { type: Object, required: true },
    filters: { type: Object, required: true },
    classes: { type: Array, default: () => [] },
    teacherName: { type: String, default: '' },
    generatedAt: { type: String, default: '' },
});

/* ---------- Filter ---------- */
const form = ref({
    classroom_id: props.filters.classroom_id ?? '',
    range: props.filters.range,
    from: props.filters.from,
    to: props.filters.to,
});

const ranges = [
    { value: '7d', label: '7 hari' },
    { value: '30d', label: '30 hari' },
    { value: 'month', label: 'Bulan ini' },
    { value: 'custom', label: 'Pilih tanggal' },
];

const loading = ref(false);

function queryParams() {
    const params = { range: form.value.range };
    if (form.value.classroom_id) params.classroom_id = form.value.classroom_id;
    if (form.value.range === 'custom') {
        params.from = form.value.from;
        params.to = form.value.to;
    }
    return params;
}

function apply() {
    router.get('/teacher/reports', queryParams(), {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

function selectRange(value) {
    form.value.range = value;
    if (value !== 'custom') apply();
}

const exportUrl = computed(() => `/teacher/reports/export?${new URLSearchParams(queryParams()).toString()}`);

function printReport() {
    window.print();
}

/* ---------- Format ---------- */
function formatDate(iso, options = { day: 'numeric', month: 'short', year: 'numeric' }) {
    return iso ? new Date(iso).toLocaleDateString('id-ID', options) : '-';
}

const periodLabel = computed(() => `${formatDate(props.report.period.from)} – ${formatDate(props.report.period.to)}`);
const classLabel = computed(() => props.report.classroom?.name || 'Semua kelas');
const summary = computed(() => props.report.summary);

/* ---------- Grafik latihan harian (satu seri) ---------- */
const daily = computed(() => props.report.daily || []);
const maxDaily = computed(() => Math.max(1, ...daily.value.map((day) => day.sessions)));
const niceMax = computed(() => {
    const max = maxDaily.value;
    if (max <= 4) return 4;
    const step = Math.pow(10, Math.floor(Math.log10(max)));
    return Math.ceil(max / step) * step;
});
const gridLines = computed(() => [1, 0.5].map((ratio) => Math.round(niceMax.value * ratio)));
const labelEvery = computed(() => Math.max(1, Math.ceil(daily.value.length / 10)));
const hovered = ref(null);
const totalSessions = computed(() => daily.value.reduce((sum, day) => sum + day.sessions, 0));

function dayLabel(date, short = true) {
    return formatDate(date, short ? { day: 'numeric', month: 'short' } : { weekday: 'long', day: 'numeric', month: 'long' });
}

/* ---------- Tabel siswa ---------- */
const sortKey = ref('status');
const statusOrder = { 'Tidak berlatih': 0, 'Kurang aktif': 1, Aktif: 2 };
const students = computed(() => {
    const rows = [...(props.report.students || [])];
    const sorters = {
        status: (a, b) => statusOrder[a.status] - statusOrder[b.status] || a.name.localeCompare(b.name),
        name: (a, b) => a.name.localeCompare(b.name),
        sessions: (a, b) => b.sessions - a.sessions,
        accuracy: (a, b) => b.avg_score - a.avg_score,
    };
    return rows.sort(sorters[sortKey.value]);
});

const statusCounts = computed(() =>
    (props.report.students || []).reduce((counts, row) => ({ ...counts, [row.status]: (counts[row.status] || 0) + 1 }), {})
);

function statusClass(status) {
    return { Aktif: 'is-good', 'Kurang aktif': 'is-warn', 'Tidak berlatih': 'is-bad' }[status];
}
</script>

<template>
    <AppLayout>
        <Head title="Laporan Belajar" />

        <div class="st-page rp">
            <!-- Kepala laporan (juga tampil saat dicetak) -->
            <header class="rp-hero">
                <div>
                    <span class="rp-kicker"><FileBarChart :size="14" /> Laporan Belajar</span>
                    <h1>Laporan Perkembangan Siswa</h1>
                    <p>{{ classLabel }} · {{ periodLabel }} ({{ report.period.days }} hari)</p>
                    <small class="rp-print-only">Guru: {{ teacherName }} · Dicetak {{ formatDate(generatedAt, { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</small>
                </div>
                <div class="rp-actions rp-no-print">
                    <a :href="exportUrl" class="st-btn st-btn--secondary"><Download :size="16" /> Unduh Excel (CSV)</a>
                    <button type="button" class="st-btn st-btn--primary" @click="printReport"><Printer :size="16" /> Cetak / PDF</button>
                </div>
            </header>

            <!-- Filter -->
            <section class="st-panel rp-filters rp-no-print" aria-label="Filter laporan">
                <label class="rp-select">
                    <span>Kelas</span>
                    <select v-model="form.classroom_id" @change="apply">
                        <option value="">Semua kelas</option>
                        <option v-for="room in classes" :key="room.id" :value="room.id">{{ room.name }}</option>
                    </select>
                </label>
                <div class="rp-range">
                    <span>Periode</span>
                    <div class="rp-chips" role="radiogroup" aria-label="Periode laporan">
                        <button
                            v-for="option in ranges"
                            :key="option.value"
                            type="button"
                            role="radio"
                            :aria-checked="form.range === option.value"
                            :class="{ active: form.range === option.value }"
                            @click="selectRange(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
                <form v-if="form.range === 'custom'" class="rp-dates" @submit.prevent="apply">
                    <label><span>Dari</span><input v-model="form.from" type="date" required :max="form.to" /></label>
                    <label><span>Sampai</span><input v-model="form.to" type="date" required :min="form.from" /></label>
                    <button type="submit" class="st-btn st-btn--primary"><CalendarRange :size="16" /> Terapkan</button>
                </form>
                <span v-if="loading" class="rp-loading">Memuat laporan...</span>
            </section>

            <!-- Ringkasan -->
            <section class="rp-stats" aria-label="Ringkasan laporan">
                <article>
                    <span class="rp-icon"><Users :size="18" /></span>
                    <small>Siswa aktif berlatih</small>
                    <b>{{ summary.active_students }}<em>/{{ summary.students }}</em></b>
                </article>
                <article>
                    <span class="rp-icon"><Activity :size="18" /></span>
                    <small>Total latihan</small>
                    <b>{{ summary.sessions }}</b>
                </article>
                <article>
                    <span class="rp-icon"><CheckCircle :size="18" /></span>
                    <small>Tingkat keberhasilan</small>
                    <b>{{ summary.pass_rate }}<em>%</em></b>
                </article>
                <article>
                    <span class="rp-icon"><Target :size="18" /></span>
                    <small>Akurasi rata-rata</small>
                    <b>{{ summary.avg_score }}<em>%</em></b>
                </article>
                <article>
                    <span class="rp-icon"><BookOpenCheck :size="18" /></span>
                    <small>Huruf dikuasai</small>
                    <b>{{ summary.letters_mastered }}</b>
                </article>
                <article>
                    <span class="rp-icon"><ClipboardList :size="18" /></span>
                    <small>Penyelesaian tugas</small>
                    <b>{{ summary.assignment_completion }}<em>%</em></b>
                </article>
            </section>

            <!-- Grafik latihan harian -->
            <section class="st-panel rp-panel">
                <div class="rp-panel-head">
                    <h2><Activity :size="18" /> Latihan per Hari</h2>
                    <small>{{ totalSessions }} latihan dalam periode ini</small>
                </div>

                <div v-if="totalSessions" class="rp-chart" role="img" :aria-label="`Grafik latihan per hari, total ${totalSessions} latihan`">
                    <div class="rp-chart-grid" aria-hidden="true">
                        <span v-for="value in gridLines" :key="value" :style="{ bottom: `${(value / niceMax) * 100}%` }">
                            <em>{{ value }}</em>
                        </span>
                    </div>
                    <div class="rp-bars">
                        <div
                            v-for="(day, index) in daily"
                            :key="day.date"
                            class="rp-bar-col"
                            @mouseenter="hovered = index"
                            @mouseleave="hovered = null"
                            @focus="hovered = index"
                            @blur="hovered = null"
                            tabindex="0"
                        >
                            <i
                                class="rp-bar"
                                :class="{ 'is-dim': hovered !== null && hovered !== index }"
                                :style="{ height: `${(day.sessions / niceMax) * 100}%` }"
                            ></i>
                            <div v-if="hovered === index" class="rp-tooltip" role="tooltip">
                                <b>{{ dayLabel(day.date, false) }}</b>
                                <span>{{ day.sessions }} latihan</span>
                                <span>{{ day.passed }} berhasil</span>
                            </div>
                        </div>
                    </div>
                    <div class="rp-xaxis" aria-hidden="true">
                        <span v-for="(day, index) in daily" :key="day.date">
                            {{ index % labelEvery === 0 ? dayLabel(day.date) : '' }}
                        </span>
                    </div>
                </div>
                <div v-else class="st-empty">Belum ada latihan siswa pada periode ini.</div>

                <details v-if="totalSessions" class="rp-data rp-no-print">
                    <summary>Lihat data harian</summary>
                    <table>
                        <thead><tr><th>Tanggal</th><th class="num">Latihan</th><th class="num">Berhasil</th></tr></thead>
                        <tbody>
                            <tr v-for="day in daily" :key="day.date">
                                <td>{{ dayLabel(day.date, false) }}</td>
                                <td class="num">{{ day.sessions }}</td>
                                <td class="num">{{ day.passed }}</td>
                            </tr>
                        </tbody>
                    </table>
                </details>
            </section>

            <!-- Per siswa -->
            <section class="st-panel rp-panel">
                <div class="rp-panel-head">
                    <h2><Users :size="18" /> Rekap per Siswa</h2>
                    <div class="rp-status-summary">
                        <span class="rp-status is-good">{{ statusCounts['Aktif'] || 0 }} aktif</span>
                        <span class="rp-status is-warn">{{ statusCounts['Kurang aktif'] || 0 }} kurang aktif</span>
                        <span class="rp-status is-bad">{{ statusCounts['Tidak berlatih'] || 0 }} tidak berlatih</span>
                    </div>
                </div>
                <label class="rp-sort rp-no-print">
                    Urutkan:
                    <select v-model="sortKey">
                        <option value="status">Perlu perhatian dulu</option>
                        <option value="name">Nama</option>
                        <option value="sessions">Latihan terbanyak</option>
                        <option value="accuracy">Akurasi tertinggi</option>
                    </select>
                </label>

                <div v-if="students.length" class="rp-table-wrap">
                    <table class="rp-table">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Status</th>
                                <th class="num">Latihan</th>
                                <th class="num">Berhasil</th>
                                <th class="num">Akurasi</th>
                                <th class="num">Huruf baru</th>
                                <th class="num">Total huruf</th>
                                <th class="num">Tugas</th>
                                <th>Terakhir aktif</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in students" :key="row.id">
                                <td>
                                    <b>{{ row.name }}</b>
                                    <small>{{ row.classes || row.email }}</small>
                                </td>
                                <td><span class="rp-status" :class="statusClass(row.status)">{{ row.status }}</span></td>
                                <td class="num">{{ row.sessions }}</td>
                                <td class="num">{{ row.passed }} <small>({{ row.pass_rate }}%)</small></td>
                                <td class="num">{{ row.sessions ? `${row.avg_score}%` : '-' }}</td>
                                <td class="num">{{ row.new_letters }}</td>
                                <td class="num">{{ row.mastered_total }}</td>
                                <td class="num">{{ row.assignments_done }}/{{ row.assignments_total }}</td>
                                <td>{{ row.last_active ? formatDate(row.last_active) : 'Belum pernah' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="st-empty">Belum ada siswa di kelas ini.</div>
            </section>

            <div class="rp-grid">
                <!-- Huruf tersulit -->
                <section class="st-panel rp-panel">
                    <div class="rp-panel-head">
                        <h2><AlertTriangle :size="18" /> Huruf Tersulit</h2>
                        <small>tingkat berhasil terendah</small>
                    </div>
                    <ul v-if="report.letters.length" class="rp-letters">
                        <li v-for="row in report.letters" :key="`${row.language}-${row.letter}`">
                            <span class="rp-letter">{{ row.letter }}</span>
                            <div>
                                <div class="rp-letter-top">
                                    <b>{{ row.language }}</b>
                                    <span>{{ row.passed }}/{{ row.attempts }} berhasil · {{ row.pass_rate }}%</span>
                                </div>
                                <div class="rp-track"><i :style="{ width: `${row.pass_rate}%` }"></i></div>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="st-empty">Belum cukup data (minimal 2 percobaan per huruf).</div>
                </section>

                <!-- Tugas -->
                <section class="st-panel rp-panel">
                    <div class="rp-panel-head">
                        <h2><ClipboardList :size="18" /> Rekap Tugas</h2>
                    </div>
                    <ul v-if="report.assignments.length" class="rp-tasks">
                        <li v-for="task in report.assignments" :key="task.id">
                            <span class="rp-letter is-task">{{ task.lesson }}</span>
                            <div>
                                <div class="rp-letter-top">
                                    <b>{{ task.title }}</b>
                                    <span>{{ task.completed }}/{{ task.total }} selesai</span>
                                </div>
                                <small>{{ task.classroom }} · {{ task.language }} · rata-rata {{ task.avg_score ?? '-' }}{{ task.avg_score !== null ? '%' : '' }}</small>
                                <div class="rp-track"><i :style="{ width: `${task.total ? (task.completed / task.total) * 100 : 0}%` }"></i></div>
                            </div>
                        </li>
                    </ul>
                    <div v-else class="st-empty">Belum ada tugas untuk kelas ini.</div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.rp {
    --rp-bar: #279069;
    display: grid;
    gap: 18px;
    padding-bottom: 32px;
}

.rp-hero {
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

.rp-kicker {
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

.rp-hero h1 {
    margin: 10px 0 6px;
    font-size: 26px;
    font-weight: 800;
    color: #14221d;
}

.rp-hero p {
    margin: 0;
    font-size: 15px;
    color: #52685d;
}

.rp-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.rp-print-only {
    display: none;
}

/* Filter */
.rp-filters {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: flex-start;
    gap: 14px 20px;
    padding: 16px 20px;
    border-radius: 18px;
}

.rp-select,
.rp-range,
.rp-dates label {
    display: grid;
    gap: 6px;
}

.rp-select span,
.rp-range > span,
.rp-dates span {
    font-size: 12px;
    font-weight: 700;
    color: #6b8176;
}

.rp-select select,
.rp-dates input,
.rp-sort select {
    min-height: 40px;
    padding: 0 12px;
    font-size: 14px;
    background: #fff;
    border: 1px solid #d5e2dc;
    border-radius: 10px;
}

.rp-chips {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 3px;
    background: #f5f8f6;
    border: 1px solid #e2ede6;
    border-radius: 12px;
}

.rp-chips button {
    min-height: 34px;
    padding: 0 12px;
    font-size: 13px;
    font-weight: 700;
    color: #6b8176;
    background: transparent;
    border: 0;
    border-radius: 9px;
    cursor: pointer;
}

.rp-chips button.active {
    color: #fff;
    background: #34b182;
}

.rp-dates {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 10px;
}

.rp-loading {
    font-size: 13px;
    font-weight: 600;
    color: #1e7253;
}

/* Ringkasan */
.rp-stats {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
}

.rp-stats article {
    display: grid;
    gap: 4px;
    padding: 14px 16px;
    background: #fff;
    border: 1px solid #e2ede6;
    border-radius: 16px;
}

.rp-icon {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    color: #279069;
    background: #eefaf4;
    border-radius: 10px;
}

.rp-stats small {
    font-size: 12px;
    font-weight: 600;
    color: #6b8176;
}

.rp-stats b {
    font-size: 26px;
    font-weight: 800;
    line-height: 1.1;
    color: #14221d;
}

.rp-stats em {
    font-size: 14px;
    font-style: normal;
    color: #6b8176;
}

/* Panel */
.rp-panel {
    display: grid;
    gap: 12px;
    padding: 18px 20px;
    border-radius: 20px;
}

.rp-panel-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.rp-panel-head h2 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 17px;
    font-weight: 800;
    color: #14221d;
}

.rp-panel-head > small {
    font-size: 13px;
    color: #6b8176;
}

/* Grafik */
.rp-chart {
    position: relative;
    display: grid;
    gap: 6px;
    padding-left: 28px;
}

.rp-chart-grid {
    position: absolute;
    inset: 0 0 22px 28px;
    pointer-events: none;
}

.rp-chart-grid span {
    position: absolute;
    right: 0;
    left: 0;
    border-top: 1px dashed #e2ede6;
}

.rp-chart-grid em {
    position: absolute;
    top: -8px;
    left: -28px;
    width: 22px;
    font-size: 11px;
    font-style: normal;
    color: #859c90;
    text-align: right;
}

.rp-bars {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    height: 200px;
    border-bottom: 1px solid #c9d8d0;
}

.rp-bar-col {
    position: relative;
    display: flex;
    flex: 1;
    align-items: flex-end;
    justify-content: center;
    height: 100%;
    outline: none;
    cursor: default;
}

.rp-bar {
    display: block;
    width: 100%;
    max-width: 28px;
    min-height: 0;
    background: var(--rp-bar);
    border-radius: 4px 4px 0 0;
    transition: opacity 0.15s;
}

.rp-bar.is-dim {
    opacity: 0.35;
}

.rp-tooltip {
    position: absolute;
    bottom: calc(100% + 4px);
    left: 50%;
    z-index: 2;
    display: grid;
    gap: 2px;
    min-width: 150px;
    padding: 8px 10px;
    font-size: 12px;
    color: #14221d;
    background: #fff;
    border: 1px solid #e2ede6;
    border-radius: 10px;
    box-shadow: 0 8px 20px rgba(20, 34, 29, 0.12);
    transform: translateX(-50%);
    pointer-events: none;
}

.rp-bar-col:first-child .rp-tooltip {
    left: 0;
    transform: none;
}

.rp-bar-col:last-child .rp-tooltip {
    right: 0;
    left: auto;
    transform: none;
}

.rp-xaxis {
    display: flex;
    gap: 2px;
}

.rp-xaxis span {
    flex: 1;
    overflow: visible;
    font-size: 11px;
    color: #859c90;
    text-align: center;
    white-space: nowrap;
}

.rp-data summary {
    font-size: 13px;
    font-weight: 700;
    color: #1e7253;
    cursor: pointer;
}

.rp-data table {
    width: 100%;
    margin-top: 8px;
    font-size: 13px;
    border-collapse: collapse;
}

.rp-data th,
.rp-data td {
    padding: 6px 8px;
    text-align: left;
    border-bottom: 1px solid #f1f5f3;
}

/* Tabel siswa */
.rp-sort {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #6b8176;
}

.rp-table-wrap {
    overflow-x: auto;
}

.rp-table {
    width: 100%;
    font-size: 14px;
    border-collapse: collapse;
}

.rp-table th {
    padding: 10px 12px;
    font-size: 12px;
    font-weight: 700;
    color: #6b8176;
    text-align: left;
    white-space: nowrap;
    border-bottom: 1px solid #e2ede6;
}

.rp-table td {
    padding: 11px 12px;
    color: #14221d;
    white-space: nowrap;
    border-bottom: 1px solid #f1f5f3;
}

.rp-table td b {
    display: block;
}

.rp-table td small {
    font-size: 12px;
    color: #6b8176;
}

.num,
.rp-data .num {
    text-align: right;
}

.rp-status-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.rp-status {
    display: inline-block;
    padding: 3px 10px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 999px;
}

.rp-status.is-good { color: #1e7253; background: #eefaf4; }
.rp-status.is-warn { color: #8a6a1f; background: #fdf6e6; }
.rp-status.is-bad { color: #b42318; background: #fef0ee; }

/* Huruf & tugas */
.rp-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    align-items: start;
}

.rp-letters,
.rp-tasks {
    display: grid;
    gap: 10px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.rp-letters li,
.rp-tasks li {
    display: flex;
    align-items: center;
    gap: 12px;
}

.rp-letters li > div,
.rp-tasks li > div {
    display: grid;
    flex: 1;
    gap: 4px;
    min-width: 0;
}

.rp-letter {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 38px;
    height: 38px;
    font-size: 18px;
    font-weight: 800;
    color: #b4562a;
    background: #fdeee4;
    border-radius: 11px;
}

.rp-letter.is-task {
    color: #fff;
    background: #34b182;
}

.rp-letter-top {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 13px;
}

.rp-letter-top b {
    overflow: hidden;
    color: #14221d;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rp-letter-top span {
    flex-shrink: 0;
    color: #6b8176;
}

.rp-tasks small {
    font-size: 12px;
    color: #6b8176;
}

.rp-track {
    height: 8px;
    overflow: hidden;
    background: #f1f5f3;
    border-radius: 999px;
}

.rp-track i {
    display: block;
    height: 100%;
    background: #279069;
    border-radius: 999px;
}

@media (max-width: 1180px) {
    .rp-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .rp-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 560px) {
    .rp-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .rp-hero {
        padding: 18px 16px;
    }

    .rp-actions,
    .rp-actions .st-btn {
        width: 100%;
    }
}

/* Cetak / simpan PDF */
@media print {
    .rp-no-print {
        display: none !important;
    }

    .rp-print-only {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        color: #52685d;
    }

    .rp-hero {
        padding: 0 0 12px;
        background: none;
        border: 0;
        border-bottom: 2px solid #14221d;
        border-radius: 0;
    }

    .rp-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .rp-panel,
    .rp-stats article {
        break-inside: avoid;
        box-shadow: none;
    }

    .rp-table td,
    .rp-table th {
        padding: 6px 8px;
        font-size: 11px;
    }

    .rp-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>
