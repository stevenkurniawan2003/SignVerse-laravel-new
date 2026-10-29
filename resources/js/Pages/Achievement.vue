<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Award,
    BookOpen,
    Check,
    Compass,
    Flame,
    Hand,
    LockKeyhole,
    Star,
    Trophy,
} from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    achievements: { type: Array, default: () => [] },
    progress: { type: Object, default: () => ({}) },
    level: { type: Object, default: () => null },
    streak: { type: Number, default: 0 },
    total_xp: { type: Number, default: 0 },
});

const filter = ref('all');
const rawAchievements = ref(Array.isArray(props.achievements) && props.achievements.length > 0 ? props.achievements : []);
const progressMap = ref(props.progress || {});

onMounted(async () => {
    if (rawAchievements.value.length === 0) {
        try {
            const res = await fetch('/api/achievements');
            if (res.ok) {
                const data = await res.json();
                if (Array.isArray(data.achievements)) {
                    rawAchievements.value = data.achievements;
                }
                if (data.progress) {
                    progressMap.value = data.progress;
                }
            }
        } catch {}
    }
});

const iconMap = {
    first_practice: Hand,
    master_alfabet: BookOpen,
    master_angka: Star,
    sibi_explorer: Compass,
    bisindo_explorer: Compass,
    streak_7: Flame,
    challenger: Trophy,
    perfect_round: Award,
};

const processedBadges = computed(() => {
    return rawAchievements.value.map((badge) => {
        const p = progressMap.value[badge.key] || { current: badge.unlocked ? 1 : 0, target: 1 };
        const percent = p.target > 0 ? Math.min(100, Math.round((p.current / p.target) * 100)) : (badge.unlocked ? 100 : 0);
        return {
            ...badge,
            icon: iconMap[badge.key] || Award,
            color: badge.unlocked ? '#34B182' : '#C29B40',
            current: p.current,
            target: p.target,
            percent,
        };
    });
});

const unlockedCount = computed(() => processedBadges.value.filter((b) => b.unlocked).length);
const filteredBadges = computed(() => {
    if (filter.value === 'unlocked') return processedBadges.value.filter((b) => b.unlocked);
    if (filter.value === 'locked') return processedBadges.value.filter((b) => !b.unlocked);
    return processedBadges.value;
});
</script>

<template>
    <AppLayout>
        <Head title="Pencapaian & Badge" />

        <div class="achievement-page">
            <header class="achievement-welcome">
                <div>
                    <div class="heading-badge">Koleksi Prestasi · Basis Data SignTech</div>
                    <h1>Pencapaian & Lencana Gamifikasi</h1>
                    <p>Lencana resmi dibuka berdasarkan data latihan, akurasi, dan konsistensi belajarmu di database.</p>
                </div>
                <div class="unlocked-summary">
                    <b>{{ unlockedCount }} / {{ processedBadges.length }}</b>
                    <small>Lencana Terbuka</small>
                </div>
            </header>

            <div class="filter-row">
                <button :class="{ active: filter === 'all' }" @click="filter = 'all'">Semua ({{ processedBadges.length }})</button>
                <button :class="{ active: filter === 'unlocked' }" @click="filter = 'unlocked'">Terbuka ({{ unlockedCount }})</button>
                <button :class="{ active: filter === 'locked' }" @click="filter = 'locked'">Terkunci ({{ processedBadges.length - unlockedCount }})</button>
            </div>

            <div class="badge-grid">
                <div
                    v-for="badge in filteredBadges"
                    :key="badge.key"
                    class="badge-card card"
                    :class="{ unlocked: badge.unlocked }"
                >
                    <div
                        class="badge-icon-tile"
                        :style="{ backgroundColor: badge.unlocked ? '#eefaf4' : '#f8faf9', color: badge.unlocked ? '#34B182' : '#859c90' }"
                    >
                        <component :is="badge.icon" :size="24" />
                    </div>

                    <div class="badge-info">
                        <h3>{{ badge.name }}</h3>
                        <p>{{ badge.description }}</p>

                        <div class="badge-progress-line">
                            <div class="progress-track">
                                <i :style="{ width: `${badge.percent}%`, backgroundColor: badge.unlocked ? '#34B182' : '#C29B40' }"></i>
                            </div>
                            <span>{{ badge.unlocked ? 'Tercapai' : `${badge.current}/${badge.target}` }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.achievement-welcome {
    background: #eefaf4;
    border: 1px solid #d5f3e5;
    border-radius: 18px;
    padding: 24px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
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
.unlocked-summary {
    background: #ffffff;
    border: 1px solid #d5f3e5;
    border-radius: 12px;
    padding: 12px 18px;
    text-align: center;
}
.unlocked-summary b {
    display: block;
    font-size: 18px;
    font-weight: 800;
    color: #34B182;
}
.unlocked-summary small {
    font-size: 10px;
    color: #859c90;
}
.filter-row {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
}
.filter-row button {
    border: 1px solid #e2ede6;
    background: #ffffff;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #52685d;
    cursor: pointer;
    transition: 0.16s;
}
.filter-row button.active {
    background: #34B182;
    border-color: #34B182;
    color: #ffffff;
}
.badge-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
}
.badge-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 18px;
}
.badge-card:not(.unlocked) {
    opacity: 0.7;
}
.badge-icon-tile {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}
.badge-info {
    flex: 1;
}
.badge-info h3 {
    font-size: 14px;
    font-weight: 800;
    color: #14221d;
    margin: 0 0 4px;
}
.badge-info p {
    font-size: 11px;
    color: #859c90;
    margin: 0 0 10px;
    line-height: 1.4;
}
.badge-progress-line {
    display: flex;
    align-items: center;
    gap: 10px;
}
.badge-progress-line .progress-track {
    flex: 1;
    margin: 0;
}
.badge-progress-line span {
    font-size: 10px;
    font-weight: 700;
    color: #52685d;
}
@media (max-width: 760px) {
    .badge-grid { grid-template-columns: 1fr; }
}
</style>
