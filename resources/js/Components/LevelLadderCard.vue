<script setup>
import { computed } from 'vue';
import { Award, CheckCircle2, Compass, Crown, Lock, MessageSquare, Sprout, Star } from '@lucide/vue';

const props = defineProps({
    levels: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
});

const levelIcons = {
    Beginner: Sprout,
    Explorer: Compass,
    Communicator: MessageSquare,
    'Inclusive Champion': Crown,
};

const totalXp = computed(() => Number(props.summary?.xp ?? props.summary?.total_xp ?? 0));

const currentLevel = computed(() => props.summary?.level || { name: 'Beginner', next_xp: null, pct: 0 });

const levelPercent = computed(() =>
    currentLevel.value.next_xp ? Math.min(100, Math.max(0, Math.round(Number(currentLevel.value.pct || 0)))) : 100
);

function formatXp(xp) {
    return Number(xp).toLocaleString('id-ID');
}

/**
 * Urutan tingkatan beserta status tiap tingkatan dan XP yang masih dibutuhkan.
 */
const levelSteps = computed(() => {
    const reachedCount = props.levels.filter((level) => totalXp.value >= level.min_xp).length;

    return props.levels.map((level, index) => {
        const remainingXp = level.min_xp - totalXp.value;
        const isCurrent = index === reachedCount - 1;

        return {
            ...level,
            position: index + 1,
            icon: levelIcons[level.name] || Award,
            isCurrent,
            isNext: index === reachedCount,
            isReached: index < reachedCount,
            requirement: level.min_xp === 0 ? 'Tingkat awal' : `${formatXp(level.min_xp)} XP`,
            status: remainingXp > 0 ? `Butuh ${formatXp(remainingXp)} XP lagi` : isCurrent ? 'Tingkatmu sekarang' : 'Sudah tercapai',
        };
    });
});

const xpToNextLevel = computed(() =>
    currentLevel.value.next_xp ? Math.max(0, Number(currentLevel.value.next_xp) - totalXp.value) : 0
);
</script>

<template>
    <div v-if="levelSteps.length" class="bg-white rounded-[24px] p-5 sm:p-6 border border-[#e8efe9] shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-[#eefaf4] text-[#1e7253] flex items-center justify-center">
                    <Award :size="18" class="stroke-[2.2]" />
                </div>
                <div>
                    <h3 class="font-display font-extrabold text-[17px] text-[#14221d] tracking-tight">Tingkatan Level</h3>
                    <p class="text-[11px] text-[#738881]">
                        <template v-if="currentLevel.next_xp">{{ formatXp(xpToNextLevel) }} XP lagi menuju tingkat berikutnya</template>
                        <template v-else>Kamu sudah mencapai tingkat tertinggi!</template>
                    </p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#fdf5e7] text-[11px] font-extrabold text-[#9b7625]">
                <Star :size="12" class="fill-[#C29B40] text-[#C29B40]" /> {{ formatXp(totalXp) }} XP kamu
            </span>
        </div>

        <ol class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <li
                v-for="step in levelSteps"
                :key="step.name"
                class="relative flex flex-col gap-1.5 p-3.5 rounded-2xl border transition-colors"
                :class="[
                    step.isCurrent
                        ? 'bg-[#eefaf4] border-[#34B182] ring-2 ring-[#34B182]/20'
                        : step.isReached
                        ? 'bg-[#fafcfb] border-[#d5ebe0]'
                        : step.isNext
                        ? 'bg-[#fefbf3] border-[#f6e1ba]'
                        : 'bg-[#fafcfb] border-[#eef3f0]',
                ]"
                :aria-current="step.isCurrent ? 'step' : undefined"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="w-9 h-9 rounded-xl flex items-center justify-center"
                        :class="step.isReached ? 'bg-[#34B182] text-white' : step.isNext ? 'bg-[#C29B40] text-white' : 'bg-[#eef3f0] text-[#94a8a0]'"
                    >
                        <component :is="step.icon" :size="18" />
                    </span>
                    <span class="text-[10px] font-extrabold text-[#94a8a0]">Lv {{ step.position }}</span>
                </div>
                <b class="font-display text-[13px] leading-tight text-[#14221d]">{{ step.name }}</b>
                <small class="text-[11px] text-[#738881]">{{ step.requirement }}</small>

                <div
                    v-if="step.isNext"
                    class="h-1.5 rounded-full bg-[#f3e8cf] overflow-hidden"
                    role="progressbar"
                    :aria-valuenow="levelPercent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div class="h-full rounded-full bg-[#C29B40] transition-all duration-500" :style="{ width: `${levelPercent}%` }"></div>
                </div>

                <span
                    class="inline-flex items-center gap-1 text-[10px] font-bold"
                    :class="step.isReached ? 'text-[#1b735a]' : step.isNext ? 'text-[#9b7625]' : 'text-[#94a8a0]'"
                >
                    <CheckCircle2 v-if="step.isReached" :size="12" />
                    <Lock v-else :size="11" />
                    {{ step.status }}
                </span>
            </li>
        </ol>
    </div>
</template>
