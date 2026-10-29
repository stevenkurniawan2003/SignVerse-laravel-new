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
            const res = await fetch('/api/achievements', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
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
            current: p.current,
            target: p.target,
            percent,
        };
    });
});

const unlockedCount = computed(() => processedBadges.value.filter((b) => b.unlocked).length);
const filterOptions = computed(() => [
    { value: 'all', label: 'Semua', count: processedBadges.value.length },
    { value: 'unlocked', label: 'Terbuka', count: unlockedCount.value },
    { value: 'locked', label: 'Terkunci', count: processedBadges.value.length - unlockedCount.value },
]);

const filteredBadges = computed(() => {
    if (filter.value === 'unlocked') return processedBadges.value.filter((b) => b.unlocked);
    if (filter.value === 'locked') return processedBadges.value.filter((b) => !b.unlocked);
    return processedBadges.value;
});
</script>

<template>
    <AppLayout>
        <Head title="Pencapaian & Badge" />

        <div class="st-page">
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <span class="st-badge st-badge--primary">Koleksi Prestasi</span>
                    <h1>Pencapaian & Lencana</h1>
                    <p>Lencana terbuka seiring kemajuan latihan, akurasi, dan konsistensi belajarmu.</p>
                </div>
                <div class="st-page-header-aside st-summary-box">
                    <span class="st-icon st-icon--gold"><Trophy :size="22" /></span>
                    <div>
                        <b>{{ unlockedCount }} / {{ processedBadges.length }}</b>
                        <small>lencana terbuka</small>
                    </div>
                </div>
            </header>

            <section class="st-section">
                <div class="collection-head">
                    <div>
                        <span class="st-eyebrow">Koleksi</span>
                        <h2 class="st-panel-title">Daftar Lencana</h2>
                    </div>
                    <div class="st-seg" role="group" aria-label="Filter lencana">
                        <button
                            v-for="option in filterOptions"
                            :key="option.value"
                            type="button"
                            :class="{ 'is-active': filter === option.value }"
                            :aria-pressed="filter === option.value"
                            @click="filter = option.value"
                        >
                            {{ option.label }} ({{ option.count }})
                        </button>
                    </div>
                </div>

                <div v-if="filteredBadges.length > 0" class="st-grid-2">
                    <article
                        v-for="badge in filteredBadges"
                        :key="badge.key"
                        class="st-panel achievement-card"
                        :class="{ 'is-locked': !badge.unlocked }"
                    >
                        <span class="st-icon st-icon--lg" :class="badge.unlocked ? 'st-icon--primary' : 'st-icon--neutral'">
                            <component :is="badge.icon" :size="24" />
                        </span>

                        <div class="badge-info">
                            <div class="badge-info-head">
                                <h3>{{ badge.name }}</h3>
                                <span v-if="badge.unlocked" class="st-badge st-badge--primary">
                                    <Check :size="12" /> Tercapai
                                </span>
                                <span v-else class="st-badge st-badge--neutral">
                                    <LockKeyhole :size="12" /> Terkunci
                                </span>
                            </div>
                            <p>{{ badge.description }}</p>

                            <div class="badge-progress">
                                <div class="st-track" :class="{ 'st-track--gold': !badge.unlocked }">
                                    <i :style="{ width: `${badge.percent}%` }"></i>
                                </div>
                                <span>{{ badge.current }}/{{ badge.target }}</span>
                            </div>
                        </div>
                    </article>
                </div>
                <div v-else class="st-empty">Belum ada lencana pada kategori ini.</div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.collection-head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
}

.achievement-card {
    flex-direction: row;
    align-items: flex-start;
    gap: 14px;
    padding: 18px;
}

.achievement-card.is-locked {
    background: var(--st-surface);
}

.badge-info {
    flex: 1;
    min-width: 0;
}

.badge-info-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
}

.badge-info h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    color: var(--st-ink);
}

.badge-info p {
    margin: 4px 0 12px;
    font-size: 13px;
    line-height: 1.5;
    color: var(--st-muted);
}

.badge-progress {
    display: flex;
    align-items: center;
    gap: 10px;
}

.badge-progress span {
    font-size: 12px;
    font-weight: 700;
    color: var(--st-text);
    white-space: nowrap;
}
</style>
