<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList, Gauge, Trophy, UserRound } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    assignment: { type: Object, required: true },
    stats: { type: Object, default: () => ({}) },
    submissions: { type: Array, default: () => [] },
});

const stats = ref({ ...props.stats });
const submissions = ref([...props.submissions]);

let timer = null;

async function poll() {
    router.reload({ only: ['stats', 'submissions'] });
}

onMounted(() => { timer = setInterval(poll, 15000); });
onBeforeUnmount(() => timer && clearInterval(timer));

function statusLabel(status) {
    if (status === 'completed') return 'Selesai';
    if (status === 'in_progress') return 'Dikerjakan';
    return 'Belum dikerjakan';
}

function timeAgo(iso) {
    if (!iso) return '-';
    const min = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
    if (min < 1) return 'Baru saja';
    if (min < 60) return `${min} menit lalu`;
    const hour = Math.floor(min / 60);
    if (hour < 24) return `${hour} jam lalu`;
    return new Date(iso).toLocaleDateString('id-ID');
}
</script>

<template>
    <AppLayout>
        <Head :title="`Tugas: ${assignment.title}`" />

        <div class="st-page">
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <Link href="/teacher/assignments" class="st-back"><ArrowLeft :size="14" /> Kembali ke daftar tugas</Link>
                    <span class="st-badge st-badge--primary">Detail Tugas</span>
                    <h1>{{ assignment.title }}</h1>
                    <p>
                        <span class="st-chip">{{ assignment.classroom }}</span>
                        {{ assignment.lesson }} ({{ assignment.language }}) · skor minimal {{ assignment.min_score }}% · bonus +{{ assignment.xp_reward }} XP
                        · deadline {{ assignment.due_at ? new Date(assignment.due_at).toLocaleDateString('id-ID') : 'tanpa batas' }}
                    </p>
                    <p v-if="assignment.description" class="st-description">{{ assignment.description }}</p>
                </div>
                <div class="st-page-header-aside st-summary-box">
                    <span class="st-icon st-icon--primary"><Gauge :size="22" /></span>
                    <div>
                        <b>{{ stats.avg_score != null ? `Rata-rata ${stats.avg_score}%` : 'Belum ada nilai' }}</b>
                        <small>{{ stats.completed ?? 0 }}/{{ stats.total ?? 0 }} sudah selesai</small>
                    </div>
                </div>
            </header>

            <section class="st-stat-grid">
                <article class="st-stat">
                    <div class="st-stat-head"><span class="st-stat-label">Dibagikan ke</span><span class="st-icon st-icon--primary"><UserRound :size="20" /></span></div>
                    <div class="st-stat-value">{{ stats.total ?? 0 }}<small>siswa</small></div>
                </article>
                <article class="st-stat">
                    <div class="st-stat-head"><span class="st-stat-label">Belum dikerjakan</span><span class="st-icon st-icon--gold"><ClipboardList :size="20" /></span></div>
                    <div class="st-stat-value">{{ stats.assigned ?? 0 }}</div>
                </article>
                <article class="st-stat">
                    <div class="st-stat-head"><span class="st-stat-label">Sedang dikerjakan</span><span class="st-icon st-icon--green"><ClipboardList :size="20" /></span></div>
                    <div class="st-stat-value">{{ stats.in_progress ?? 0 }}</div>
                </article>
                <article class="st-stat">
                    <div class="st-stat-head"><span class="st-stat-label">Selesai</span><span class="st-icon st-icon--mint"><Trophy :size="20" /></span></div>
                    <div class="st-stat-value">{{ stats.completed ?? 0 }}</div>
                </article>
            </section>

            <article class="st-card">
                <div class="st-card-head">
                    <h2><UserRound :size="18" /> Pengerjaan Siswa</h2>
                    <Link :href="`/teacher/assignments`" class="st-link">Semua tugas →</Link>
                </div>
                <div v-if="submissions.length" class="st-table-wrap">
                    <table class="st-table">
                        <thead>
                            <tr><th>Siswa</th><th>Status</th><th>Skor</th><th>XP didapat</th><th>Waktu selesai</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in submissions" :key="row.student_id">
                                <td>
                                    <Link :href="`/teacher/students/${row.student_id}`" class="st-link"><b>{{ row.name }}</b></Link>
                                </td>
                                <td>
                                    <span class="st-badge" :class="row.status === 'completed' ? 'st-badge--success' : 'st-badge--pending'">
                                        {{ statusLabel(row.status) }}
                                    </span>
                                </td>
                                <td>
                                    <span v-if="row.score != null" class="st-score" :class="row.score >= assignment.min_score ? 'st-score--pass' : 'st-score--fail'">{{ row.score }}%</span>
                                    <span v-else class="st-muted">—</span>
                                </td>
                                <td>{{ row.status === 'completed' ? `+${row.xp_earned}` : '—' }}</td>
                                <td>{{ timeAgo(row.completed_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="st-empty">Belum ada siswa penerima tugas ini.</p>
            </article>
        </div>
    </AppLayout>
</template>

<style scoped>
.st-back {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12.5px;
    font-weight: 700;
    color: #1e7253;
    text-decoration: none;
    margin-bottom: 6px;
}

.st-description {
    background: #fbfdfc;
    border: 1px solid #e2ede6;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
    color: #41564c;
}

.st-card-head h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 800;
}

.st-chip {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    background: #eefaf4;
    color: #1e7253;
    border: 1px solid #d5f3e5;
    border-radius: 999px;
    padding: 2px 8px;
    margin-right: 6px;
}

.st-score {
    font-size: 12px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 999px;
}

.st-score--pass { background: #eefaf4; color: #1e7253; }
.st-score--fail { background: #fdf2f2; color: #d9534f; }

.st-muted { color: #a9bcagb3; color: #a9bcb3; }

.st-empty {
    padding: 14px;
    background: #fbfdfc;
    border: 1px dashed #d5e2dc;
    border-radius: 12px;
    color: #52685d;
    font-size: 13px;
}
</style>
