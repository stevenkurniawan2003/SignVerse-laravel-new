<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    BookOpen,
    Flame,
    Gauge,
    Star,
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
            const res = await fetch('/api/dashboard', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
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

        <div class="st-page">
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <span class="st-badge st-badge--primary">Statistik Belajar</span>
                    <h1>Progress Belajar</h1>
                    <p>Pantau performa latihan, akurasi gesture, dan penguasaan materi bahasa isyaratmu.</p>
                </div>
                <div class="st-page-header-aside st-summary-box">
                    <span class="st-icon st-icon--primary"><Award :size="22" /></span>
                    <div>
                        <b>{{ summaryData.level?.name || 'Beginner' }}</b>
                        <small>{{ summaryData.xp }} XP terkumpul</small>
                    </div>
                </div>
            </header>

            <section class="st-stat-grid" aria-label="Ringkasan progress">
                <article class="st-stat">
                    <div class="st-stat-head">
                        <span class="st-stat-label">Streak Saat Ini</span>
                        <span class="st-icon st-icon--gold"><Flame :size="20" /></span>
                    </div>
                    <div class="st-stat-value">{{ summaryData.streak }}<small>hari</small></div>
                    <div class="st-stat-foot">Rekor: {{ summaryData.longest_streak || 0 }} hari</div>
                </article>
                <article class="st-stat">
                    <div class="st-stat-head">
                        <span class="st-stat-label">Total XP</span>
                        <span class="st-icon st-icon--primary"><Star :size="20" /></span>
                    </div>
                    <div class="st-stat-value">{{ summaryData.xp }}<small>XP</small></div>
                    <div class="st-stat-foot">{{ summaryData.sessions || 0 }} sesi latihan</div>
                </article>
                <article class="st-stat">
                    <div class="st-stat-head">
                        <span class="st-stat-label">Modul Dikuasai</span>
                        <span class="st-icon st-icon--green"><BookOpen :size="20" /></span>
                    </div>
                    <div class="st-stat-value">{{ summaryData.lessons_mastered }}<small>/ {{ summaryData.lessons_total }}</small></div>
                    <div class="st-stat-foot">Rata-rata akurasi {{ summaryData.avg_score || 0 }}%</div>
                </article>
                <article class="st-stat">
                    <div class="st-stat-head">
                        <span class="st-stat-label">Kurikulum Selesai</span>
                        <span class="st-icon st-icon--mint"><Gauge :size="20" /></span>
                    </div>
                    <div class="st-stat-value">{{ masteryPercent }}<small>%</small></div>
                    <div class="st-stat-foot">
                        <div class="st-track"><i :style="{ width: `${masteryPercent}%` }"></i></div>
                    </div>
                </article>
            </section>

            <!-- Grafik aktivitas 7 hari -->
            <section class="st-panel">
                <div class="st-panel-head">
                    <div>
                        <span class="st-eyebrow">Grafik Aktivitas</span>
                        <h2 class="st-panel-title">Latihan 7 Hari Terakhir</h2>
                        <p class="st-panel-desc">Jumlah sesi latihan yang kamu selesaikan setiap hari.</p>
                    </div>
                    <span class="st-badge st-badge--primary">{{ summaryData.sessions || 0 }} total sesi</span>
                </div>

                <div v-if="weekDays.length > 0" class="chart-bars">
                    <div v-for="day in weekDays" :key="day.date" class="bar-col" :class="{ 'is-today': day.isToday }">
                        <small class="bar-count">{{ day.count || 0 }}</small>
                        <div class="bar-track">
                            <div class="bar-fill" :style="{ height: `${((day.count || 0) / maxWeekCount) * 100}%` }"></div>
                        </div>
                        <span class="bar-label">{{ day.label }}</span>
                    </div>
                </div>
                <div v-else class="st-empty">Belum ada data aktivitas minggu ini.</div>
            </section>

            <!-- Progress per kategori -->
            <section class="st-panel">
                <div class="st-panel-head">
                    <div>
                        <span class="st-eyebrow">Penguasaan Materi</span>
                        <h2 class="st-panel-title">Kemajuan per Kategori</h2>
                    </div>
                    <Link href="/learn" class="st-link">
                        Buka materi <ArrowRight :size="14" />
                    </Link>
                </div>

                <div v-if="categoryProgress.length > 0" class="category-list">
                    <div v-for="cat in categoryProgress" :key="cat.id">
                        <div class="st-progress-meta">
                            <b>{{ cat.name }}</b>
                            <span>{{ cat.done }} / {{ cat.total }} materi · {{ cat.percent }}%</span>
                        </div>
                        <div class="st-track">
                            <i :style="{ width: `${cat.percent}%` }"></i>
                        </div>
                    </div>
                </div>
                <div v-else class="st-empty">Belum ada progress kategori. Mulai belajar untuk melihat perkembanganmu.</div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.chart-bars {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 12px;
    height: 200px;
}

.bar-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    min-width: 0;
}

.bar-track {
    display: flex;
    flex: 1;
    align-items: flex-end;
    width: 100%;
    max-width: 36px;
    overflow: hidden;
    background: var(--st-line-soft);
    border-radius: 10px;
}

.bar-fill {
    width: 100%;
    min-height: 4px;
    background: var(--st-primary);
    border-radius: 10px;
    transition: height 0.4s ease;
}

.bar-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--st-text);
}

.bar-count {
    font-size: 12px;
    font-weight: 700;
    color: var(--st-muted);
}

.bar-col.is-today .bar-label {
    color: var(--st-primary-dark);
}

.bar-col.is-today .bar-fill {
    background: var(--st-primary-dark);
}

.category-list {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

@media (max-width: 480px) {
    .chart-bars {
        gap: 6px;
        height: 170px;
    }
}
</style>
