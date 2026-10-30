<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Award,
    CheckCircle,
    ClipboardList,
    Search,
    Star,
    Users,
} from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    overview: { type: Object, default: () => null },
    students: { type: Array, default: () => null },
    errorAnalysis: { type: Array, default: () => null },
    assignments: { type: Array, default: () => null },
});

const loading = ref(false);
const search = ref('');

const classOverview = ref(props.overview || {
    students: 0,
    weekly_sessions: 0,
    avg_class_score: 0,
    active_assignments: 0,
});

const studentList = ref(props.students || []);

const errorData = ref(props.errorAnalysis || []);

const assignmentList = ref(props.assignments || []);

onMounted(async () => {
    if (!props.students) {
        try {
            const res = await fetch('/api/classroom', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.overview) classOverview.value = data.overview;
                if (data.students?.length) studentList.value = data.students;
                if (data.errorAnalysis?.length) errorData.value = data.errorAnalysis;
                if (data.activeAssignments?.length) assignmentList.value = data.activeAssignments;
            }
        } catch (e) {}
    }
});

const filteredStudents = computed(() => {
    if (!search.value) return studentList.value;
    const q = search.value.toLowerCase();
    return studentList.value.filter((s) => s.name.toLowerCase().includes(q));
});
</script>

<template>
    <AppLayout>
        <Head title="Ruang Kelas Guru" />

        <div class="classroom-page">
            <header class="classroom-heading">
                <div class="heading-badge">Portal Pengajar & Evaluasi Inklusif</div>
                <h1>Panel Monitoring Guru</h1>
                <p>Pantau ketercapaian kompetensi bahasa isyarat siswa, distribusi error isyarat, dan penugasan kelas.</p>
            </header>

            <!-- 4 Metric Cards -->
            <section class="overview-grid">
                <div class="card stat-box">
                    <span class="icon-wrap emerald"><Users :size="20" /></span>
                    <div>
                        <span class="stat-label">Siswa Terdaftar</span>
                        <b class="stat-val">{{ classOverview.students }} <small>orang</small></b>
                    </div>
                </div>
                <div class="card stat-box">
                    <span class="icon-wrap gold"><CheckCircle :size="20" /></span>
                    <div>
                        <span class="stat-label">Sesi Minggu Ini</span>
                        <b class="stat-val">{{ classOverview.weekly_sessions }} <small>sesi</small></b>
                    </div>
                </div>
                <div class="card stat-box">
                    <span class="icon-wrap green"><Award :size="20" /></span>
                    <div>
                        <span class="stat-label">Rata-rata Akurasi</span>
                        <b class="stat-val">{{ classOverview.avg_class_score }}<small>%</small></b>
                    </div>
                </div>
                <div class="card stat-box">
                    <span class="icon-wrap mint"><ClipboardList :size="20" /></span>
                    <div>
                        <span class="stat-label">Tugas Aktif</span>
                        <b class="stat-val">{{ classOverview.active_assignments }} <small>penugasan</small></b>
                    </div>
                </div>
            </section>

            <!-- 2 Columns: Student Table & Error Analysis -->
            <section class="split-section">
                <!-- Student Table Card -->
                <div class="card main-table-card">
                    <div class="card-header">
                        <div>
                            <span class="sub-label">Peserta Didik</span>
                            <h2>Daftar Kemajuan Siswa</h2>
                        </div>
                        <div class="search-box">
                            <Search :size="14" class="search-icon" />
                            <input v-model="search" type="search" placeholder="Cari siswa..." />
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nama Siswa</th>
                                    <th>Sesi</th>
                                    <th>Rata-rata</th>
                                    <th>XP</th>
                                    <th>Streak</th>
                                    <th>Aktivitas Terakhir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="student in filteredStudents" :key="student.id">
                                    <td><b>{{ student.name }}</b></td>
                                    <td>{{ student.sessions }}</td>
                                    <td><span class="score-pill">{{ student.avg_score }}%</span></td>
                                    <td><span class="xp-pill-badge"><Star :size="11" /> {{ student.xp }}</span></td>
                                    <td>{{ student.streak }} hari</td>
                                    <td><small>{{ student.last_activity }}</small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Error Analysis -->
                <div class="card side-analysis-card">
                    <div class="card-header">
                        <div>
                            <span class="sub-label">Analisis AI</span>
                            <h2>Gesture Paling Sering Keliru</h2>
                        </div>
                    </div>
                    <p class="section-desc">Gunakan data kesalahan gesture ini untuk memberikan bimbingan orientasi jemari saat tatap muka.</p>

                    <div class="error-list">
                        <div v-for="item in errorData" :key="item.gesture" class="error-item">
                            <div class="error-head">
                                <span class="error-tag"><AlertTriangle :size="12" /> {{ item.lesson }}</span>
                                <span class="error-count">{{ item.wrong }} dari {{ item.total }} keliru</span>
                            </div>
                            <div class="progress-track">
                                <i :style="{ width: `${item.accuracy}%` }"></i>
                            </div>
                            <div class="error-foot">
                                <span>Tingkat Keberhasilan</span>
                                <b>{{ item.accuracy }}%</b>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.classroom-page {
    display: flex;
    flex-direction: column;
    gap: 24px;
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

.overview-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.stat-box {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
}

.icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.icon-wrap.emerald { background: #eefaf4; color: #34B182; }
.icon-wrap.gold { background: #fdf9ef; color: #C29B40; }
.icon-wrap.green { background: #f1f9f3; color: #5FAB6F; }
.icon-wrap.mint { background: #f0f7f4; color: #279069; }

.stat-label {
    font-size: 11px;
    font-weight: 600;
    color: #859c90;
    display: block;
}

.stat-val {
    font-size: 22px;
    font-weight: 800;
    color: #14221d;
}

.stat-val small {
    font-size: 11px;
    font-weight: 600;
    color: #859c90;
}

.split-section {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
    gap: 16px;
}

.card-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
}

.sub-label {
    font-size: 10px;
    font-weight: 800;
    color: #859c90;
    text-transform: uppercase;
    display: block;
    margin-bottom: 2px;
}

.card-header h2 {
    font-size: 16px;
    font-weight: 800;
    color: #14221d;
    margin: 0;
}

.search-box {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f8faf9;
    border: 1px solid #e2ede6;
    border-radius: 8px;
    padding: 4px 10px;
}

.search-icon {
    color: #859c90;
}

.search-box input {
    min-width: 0;
    width: 100%;
    border: none;
    background: transparent;
    font-size: 11px;
    outline: none;
    color: #14221d;
}

.table-wrap {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

.data-table th {
    text-align: left;
    padding: 8px;
    border-bottom: 1px solid #eef3f0;
    color: #859c90;
    font-size: 10px;
    font-weight: 700;
}

.data-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #f6f9f7;
}

.score-pill {
    font-weight: 800;
    color: #14221d;
}

.xp-pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 700;
    color: #C29B40;
    background: #fdf9ef;
    padding: 2px 7px;
    border-radius: 6px;
}

.section-desc {
    font-size: 11px;
    color: #859c90;
    margin: 0 0 14px;
    line-height: 1.5;
}

.error-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.error-item {
    background: #fbfdfc;
    border: 1px solid #eef3f0;
    border-radius: 12px;
    padding: 12px;
}

.error-head {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    margin-bottom: 6px;
}

.error-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 700;
    color: #d9534f;
}

.error-count {
    color: #859c90;
    font-size: 10px;
}

.error-foot {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: #859c90;
    margin-top: 6px;
}

.error-foot b {
    color: #14221d;
}

@media (max-width: 900px) {
    .overview-grid { grid-template-columns: repeat(2, 1fr); }
    .split-section { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 480px) {
    .search-box { flex: 1 1 100%; }
}

@media (max-width: 360px) {
    .overview-grid { grid-template-columns: minmax(0, 1fr); }
}
</style>

