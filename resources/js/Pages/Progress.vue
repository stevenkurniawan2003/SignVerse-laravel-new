<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Award,
    BookOpen,
    CheckCircle,
    Flame,
    Gauge,
    Star,
    Target,
} from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    summary: { type: Object, default: () => null },
    progress: { type: Array, default: () => [] },
    weekActivity: { type: Array, default: () => [] },
    recentSessions: { type: Array, default: () => [] },
    user: { type: Object, default: () => null },
});

const summaryData = ref(props.summary || {
    xp: 0,
    level: { name: 'Beginner', color: '#94a3b8' },
    streak: 0,
    longest_streak: 0,
    sessions: 0,
    avg_score: 0,
    lessons_mastered: 0,
    lessons_total: 20,
});

const categoryProgress = ref(props.progress || []);
const weekDays = ref(props.weekActivity || []);

onMounted(async () => {
    if (!props.summary) {
        try {
            const res = await fetch('/api/dashboard');
            if (res.ok) {
                const data = await res.json();
                if (data.summary) summaryData.value = data.summary;
                if (Array.isArray(data.progress)) categoryProgress.value = data.progress;
                if (Array.isArray(data.weekActivity)) weekDays.value = data.weekActivity;
            }
        } catch {}
    }
});

const maxWeekCount = computed(() => {
    if (!weekDays.value || weekDays.value.length === 0) return 1;
    return Math.max(1, ...weekDays.value.map((d) => d.count || 0));
});

const masteryPercent = computed(() => {
    const total = summaryData.value.lessons_total || 20;
    const mastered = summaryData.value.lessons_mastered || 0;
    return Math.min(100, Math.round((mastered / total) * 100));
});
</script>

<template>
    <AppLayout>
        <Head title="Progress Belajar" />

        <div class="progress-page">
            <header class="progress-welcome">
                <div>
                    <div class="heading-badge">Statistik Perkembangan · SignTech Database</div>
                    <h1>Progress Belajar Mandiri</h1>
                    <p>Agregasi performa latihan, akurasi gesture, dan ketercapaian kompetensi bahasa isyarat.</p>
                </div>
                <div class="progress-level-badge">
                    <Award :size="24" class="text-primary" />
                    <div>
                        <b>{{ summaryData.level?.name || 'Beginner' }}</b>
                        <small>{{ summaryData.xp }} XP Terkumpul</small>
                    </div>
                </div>
            </header>

            <section class="progress-stats-grid">
                <div class="card stat-card">
                    <span class="icon-wrap gold"><Flame :size="20" /></span>
                    <div>
                        <span class="stat-lbl">Streak Saat Ini</span>
                        <b class="stat-num">{{ summaryData.streak }} <small>hari</small></b>
                    </div>
                </div>
                <div class="card stat-card">
                    <span class="icon-wrap emerald"><Star :size="20" /></span>
                    <div>
                        <span class="stat-lbl">Total Pengalaman</span>
                        <b class="stat-num">{{ summaryData.xp }} <small>XP</small></b>
                    </div>
                </div>
                <div class="card stat-card">
                    <span class="icon-wrap green"><BookOpen :size="20" /></span>
                    <div>
                        <span class="stat-lbl">Modul Dikuasai</span>
                        <b class="stat-num">{{ summaryData.lessons_mastered }} <small>/ {{ summaryData.lessons_total }}</small></b>
                    </div>
                </div>
                <div class="card stat-card">
                    <span class="icon-wrap mint"><Gauge :size="20" /></span>
                    <div>
                        <span class="stat-lbl">Kurikulum Selesai</span>
                        <b class="stat-num">{{ masteryPercent }}<small>%</small></b>
                    </div>
                </div>
            </section>

            <!-- 7-Day Habit Activity Bar Chart from Database -->
            <section class="card habit-chart-card">
                <div class="chart-header">
                    <div>
                        <span class="sub-label">GRAFIK AKTIVITAS</span>
                        <h2>Riwayat Latihan 7 Hari Terakhir</h2>
                    </div>
                    <span class="stat-badge">{{ summaryData.sessions }} Total Sesi</span>
                </div>
                <p class="chart-desc">Jumlah sesi latihan yang tuntas direkam di basis data.</p>

                <div class="chart-bars">
                    <div v-for="day in weekDays" :key="day.date" class="bar-col">
                        <div class="bar-track">
                            <div class="bar-fill" :style="{ height: `${((day.count || 0) / maxWeekCount) * 100}%` }"></div>
                        </div>
                        <span class="bar-label" :class="{ 'is-today': day.isToday }">{{ day.label }}</span>
                        <small class="bar-count">{{ day.count }}</small>
                    </div>
                </div>
            </section>

            <!-- Progress By Category from Database -->
            <section v-if="categoryProgress.length > 0" class="card category-progress-card">
                <div class="chart-header">
                    <div>
                        <span class="sub-label">DISTRIBUSI KOMPETENSI</span>
                        <h2>Kemajuan Per Kategori Materi</h2>
                    </div>
                </div>

                <div class="cat-progress-list">
                    <div v-for="cat in categoryProgress" :key="cat.id" class="cat-progress-row">
                        <div class="cat-row-info">
                            <b>{{ cat.name }}</b>
                            <span>{{ cat.done }} dari {{ cat.total }} materi tuntas ({{ cat.percent }}%)</span>
                        </div>
                        <div class="progress-track">
                            <i :style="{ width: `${cat.percent}%` }"></i>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.progress-page {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding-bottom: 36px;
}
.progress-welcome {
    background: #eefaf4;
    border: 1px solid #d5f3e5;
    border-radius: 18px;
    padding: 24px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.heading-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    color: #1e7253;
    background: #d5f3e5;
    padding: 3px 10px;
    border-radius: 6px;
    margin-bottom: 8px;
}
.progress-welcome h1 {
    font-size: 24px;
    font-weight: 800;
    color: #14221d;
    margin: 0 0 6px;
}
.progress-welcome p {
    font-size: 13px;
    color: #52685d;
    margin: 0;
}
.progress-level-badge {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #ffffff;
    border: 1px solid #d5f3e5;
    border-radius: 12px;
    padding: 12px 18px;
}
.progress-level-badge b {
    display: block;
    font-size: 16px;
    color: #14221d;
}
.progress-level-badge small {
    display: block;
    font-size: 11px;
    color: #859c90;
}
.progress-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}
.stat-card {
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
.icon-wrap.gold { background: #fdf9ef; color: #C29B40; }
.icon-wrap.emerald { background: #eefaf4; color: #34B182; }
.icon-wrap.green { background: #f1f9f3; color: #5FAB6F; }
.icon-wrap.mint { background: #f0f7f4; color: #279069; }
.stat-lbl {
    font-size: 11px;
    font-weight: 600;
    color: #859c90;
    display: block;
}
.stat-num {
    font-size: 22px;
    font-weight: 800;
    color: #14221d;
}
.stat-num small {
    font-size: 11px;
    color: #859c90;
}
.habit-chart-card, .category-progress-card {
    padding: 24px;
}
.chart-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.chart-header h2 {
    font-size: 16px;
    font-weight: 800;
    color: #14221d;
    margin: 0;
}
.sub-label {
    font-size: 10px;
    font-weight: 800;
    color: #859c90;
    text-transform: uppercase;
    display: block;
    margin-bottom: 2px;
}
.stat-badge {
    font-size: 11px;
    font-weight: 700;
    color: #1e7253;
    background: #eefaf4;
    padding: 4px 10px;
    border-radius: 6px;
}
.chart-desc {
    font-size: 11px;
    color: #859c90;
    margin: 0 0 20px;
}
.chart-bars {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    height: 160px;
    align-items: flex-end;
    padding-top: 20px;
}
.bar-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
}
.bar-track {
    flex: 1;
    width: 24px;
    background: #f1f5f3;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    align-items: flex-end;
}
.bar-fill {
    width: 100%;
    background: #34B182;
    border-radius: 8px;
    min-height: 4px;
    transition: height 0.4s ease;
}
.bar-label {
    font-size: 11px;
    font-weight: 700;
    color: #52685d;
    margin-top: 8px;
}
.bar-label.is-today {
    color: #34B182;
    font-weight: 800;
}
.bar-count {
    font-size: 9px;
    color: #859c90;
}
.cat-progress-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-top: 14px;
}
.cat-row-info {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 6px;
}
.cat-row-info b {
    color: #14221d;
}
.cat-row-info span {
    color: #859c90;
}
@media (max-width: 900px) {
    .progress-stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
