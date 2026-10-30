<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpenCheck,
    Flame,
    Mail,
    Star,
    Target,
    TrendingUp,
} from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    student: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    letters: { type: Object, default: () => ({}) },
    recentSessions: { type: Array, default: () => [] },
    errorAnalysis: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
});

const masteryPercent = computed(() => {
    const total = props.summary.lessons_total || 0;
    const done = props.summary.lessons_mastered || 0;
    return total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
});

function timeAgo(iso) {
    if (!iso) return '-';
    const min = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
    if (min < 1) return 'Baru saja';
    if (min < 60) return `${min} menit lalu`;
    const hour = Math.floor(min / 60);
    if (hour < 24) return `${hour} jam lalu`;
    return `${Math.floor(hour / 24)} hari lalu`;
}
</script>

<template>
    <AppLayout>
        <Head :title="`Progress ${student.name}`" />

        <div class="st-page">
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <Link href="/teacher/students" class="st-back"><ArrowLeft :size="14" /> Kembali ke daftar siswa</Link>
                    <span class="st-badge st-badge--primary">Progress Belajar Siswa</span>
                    <h1>{{ student.name }}</h1>
                    <p class="st-mail"><Mail :size="14" /> {{ student.email }}</p>
                </div>
                <div class="st-page-header-aside st-summary-box">
                    <span class="st-icon st-icon--primary"><Star :size="22" /></span>
                    <div>
                        <b>{{ summary.level?.name || 'Beginner' }}</b>
                        <small>{{ summary.xp || 0 }} XP · streak {{ summary.streak || 0 }} hari</small>
                    </div>
                </div>
            </header>

            <div v-if="student.classes?.length" class="st-toolbar">
                <span class="st-chip" v-for="klass in student.classes" :key="klass.id">{{ klass.name }}</span>
            </div>

            <section class="st-stat-grid" aria-label="Ringkasan siswa">
                <article class="st-stat">
                    <div class="st-stat-head">
                        <span class="st-stat-label">Total XP</span>
                        <span class="st-icon st-icon--primary"><Star :size="20" /></span>
                    </div>
                    <div class="st-stat-value">{{ summary.xp || 0 }}<small>XP</small></div>
                    <div class="st-stat-foot">{{ summary.sessions || 0 }} sesi latihan</div>
                </article>
                <article class="st-stat">
                    <div class="st-stat-head">
                        <span class="st-stat-label">Skor Rata-rata</span>
                        <span class="st-icon st-icon--green"><TrendingUp :size="20" /></span>
                    </div>
                    <div class="st-stat-value">{{ summary.avg_score || 0 }}<small>%</small></div>
                    <div class="st-stat-foot">akurasi peragaan AI</div>
                </article>
                <article class="st-stat">
                    <div class="st-stat-head">
                        <span class="st-stat-label">Isyarat Dikuasai</span>
                        <span class="st-icon st-icon--mint"><BookOpenCheck :size="20" /></span>
                    </div>
                    <div class="st-stat-value">{{ summary.lessons_mastered || 0 }}<small>/ {{ summary.lessons_total || 0 }}</small></div>
                    <div class="st-stat-foot"><div class="st-track"><i :style="{ width: `${masteryPercent}%` }"></i></div></div>
                </article>
                <article class="st-stat">
                    <div class="st-stat-head">
                        <span class="st-stat-label">Streak</span>
                        <span class="st-icon st-icon--gold"><Flame :size="20" /></span>
                    </div>
                    <div class="st-stat-value">{{ summary.streak || 0 }}<small>hari</small></div>
                    <div class="st-stat-foot">Rekor: {{ Math.max(student.longest_streak || 0, summary.longest_streak || 0) }} hari</div>
                </article>
            </section>

            <article class="st-card">
                <div class="st-card-head">
                    <h2><BookOpenCheck :size="18" /> Penguasaan Alphabet A–Z per Sistem Isyarat</h2>
                </div>
                <div v-if="letters && Object.keys(letters).length" class="st-letters">
                    <div v-for="(list, system) in letters" :key="system" class="st-letter-system">
                        <b>{{ system }}</b>
                        <div class="st-letter-grid">
                            <span
                                v-for="l in list"
                                :key="system + l.letter"
                                class="st-letter"
                                :class="{ mastered: l.mastered }"
                                :title="`Skor terbaik: ${l.best_score}%`"
                            >{{ l.letter }}</span>
                        </div>
                    </div>
                </div>
                <p v-else class="st-empty">Belum ada data alfabet. Siswa belum pernah berlatih huruf.</p>
            </article>

            <article class="st-card">
                <div class="st-card-head">
                    <h2><Target :size="18" /> Sesi Latihan Terakhir</h2>
                </div>
                <div v-if="recentSessions.length" class="st-table-wrap">
                    <table class="st-table">
                        <thead>
                            <tr><th>Isyarat</th><th>Sistem</th><th>Skor</th><th>XP</th><th>Waktu</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in recentSessions" :key="row.id">
                                <td>{{ row.lesson }}</td>
                                <td>{{ row.language }}</td>
                                <td><span class="st-score" :class="row.score >= 60 ? 'st-score--pass' : 'st-score--fail'">{{ row.score }}%</span></td>
                                <td>+{{ row.xp }}</td>
                                <td>{{ timeAgo(row.at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="st-empty">Belum ada sesi latihan yang selesai.</p>
            </article>

            <article class="st-card">
                <div class="st-card-head">
                    <h2><Star :size="18" /> Isyarat yang Sering Salah Siswa Ini</h2>
                </div>
                <ul v-if="errorAnalysis.length" class="st-analysis-list">
                    <li v-for="(row, index) in errorAnalysis" :key="index">
                        <b class="st-gesture">{{ row.gesture }}</b>
                        <span class="st-analysis-lesson">{{ row.lesson }}</span>
                        <span class="st-accuracy" :class="row.accuracy < 60 ? 'st-accuracy--low' : ''">{{ row.accuracy }}% benar</span>
                        <div class="st-track"><i :style="{ width: `${row.accuracy}%` }"></i></div>
                        <small>{{ row.wrong }} dari {{ row.total }} percobaan salah</small>
                    </li>
                </ul>
                <p v-else class="st-empty">Tidak ada kesalahan berarti — siswa konsisten benar! 🎉</p>
            </article>

            <article class="st-card">
                <div class="st-card-head">
                    <h2><BookOpenCheck :size="18" /> Tugas dari Anda</h2>
                </div>
                <div v-if="assignments.length" class="st-table-wrap">
                    <table class="st-table">
                        <thead>
                            <tr><th>Tugas</th><th>Isyarat</th><th>Status</th><th>Skor</th><th>Deadline</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in assignments" :key="row.id">
                                <td>{{ row.title }}</td>
                                <td>{{ row.lesson }} ({{ row.language }})</td>
                                <td>
                                    <span class="st-badge" :class="row.status === 'completed' ? 'st-badge--success' : 'st-badge--pending'">
                                        {{ row.status === 'completed' ? 'Selesai' : 'Belum selesai' }}
                                    </span>
                                </td>
                                <td>{{ row.score ?? '-' }}</td>
                                <td>{{ row.due_at ? new Date(row.due_at).toLocaleDateString('id-ID') : 'Tanpa deadline' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="st-empty">
                    Siswa ini belum menerima tugas.
                    <Link href="/teacher/assignments" class="st-link">Beri tugas</Link> untuk kelasnya.
                </p>
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

.st-mail {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #52685d;
}

.st-toolbar {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.st-chip {
    display: inline-block;
    font-size: 12px;
    font-weight: 700;
    background: #eefaf4;
    color: #1e7253;
    border: 1px solid #d5f3e5;
    border-radius: 999px;
    padding: 4px 12px;
}

.st-card-head h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 800;
}

.st-letters {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.st-letter-system > b {
    font-size: 12px;
    letter-spacing: 2px;
    color: #1e7253;
}

.st-letter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(38px, 1fr));
    gap: 6px;
    margin-top: 6px;
}

.st-letter {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 34px;
    border-radius: 9px;
    background: #fbfdfc;
    border: 1px solid #e2ede6;
    font-weight: 800;
    font-size: 13px;
    color: #859c90;
}

.st-letter.mastered {
    background: #eefaf4;
    border-color: #bcecd6;
    color: #1e7253;
}

.st-analysis-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.st-analysis-list li {
    position: relative;
    padding: 12px 14px 14px;
    background: #fbfdfc;
    border: 1px solid #e2ede6;
    border-radius: 12px;
}

.st-gesture {
    font-weight: 800;
    font-size: 14px;
    margin-right: 10px;
    background: #f5fbf7;
    border: 1px solid #d5e2dc;
    border-radius: 7px;
    padding: 2px 10px;
}

.st-analysis-lesson {
    font-size: 13px;
    color: #52685d;
}

.st-accuracy {
    float: right;
    font-size: 12px;
    font-weight: 800;
    color: #1e7253;
}

.st-accuracy--low { color: #d9534f; }

.st-analysis-list .st-track {
    margin: 10px 0 6px;
    clear: both;
}

.st-analysis-list small {
    font-size: 12px;
    color: #7b9186;
}

.st-empty {
    padding: 14px;
    background: #fbfdfc;
    border: 1px dashed #d5e2dc;
    border-radius: 12px;
    color: #52685d;
    font-size: 13px;
}

.st-score {
    font-size: 12px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 999px;
}

.st-score--pass { background: #eefaf4; color: #1e7253; }
.st-score--fail { background: #fdf2f2; color: #d9534f; }
</style>
