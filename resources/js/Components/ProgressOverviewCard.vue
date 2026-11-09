<script setup>
import { computed } from 'vue';
import { BarChart2, CheckCircle2, RotateCw, Target } from '@lucide/vue';

const props = defineProps({
    title: {
        type: String,
        default: 'Kemajuan Belajar',
    },
    periodLabel: {
        type: String,
        default: 'Bulan ini',
    },
    percent: {
        type: Number,
        default: 0,
    },
    ringLabel: {
        type: String,
        default: 'Akurasi',
    },
    trendText: {
        type: String,
        default: '+15% dibanding awal',
    },
    tiles: {
        type: Array,
        default: () => [
            { id: 'completed', label: 'Tuntas', value: 0, icon: CheckCircle2, color: 'purple' },
            { id: 'in_progress', label: 'Berjalan', value: 0, icon: RotateCw, color: 'amber' },
            { id: 'upcoming', label: 'Target', value: 0, icon: Target, color: 'rose' },
        ],
    },
});

// SVG ring math
const size = 110;
const strokeWidth = 9;
const radius = (size - strokeWidth) / 2;
const circumference = 2 * Math.PI * radius;

const strokeDashoffset = computed(() => {
    const p = Math.min(Math.max(props.percent, 0), 100);
    return circumference - (p / 100) * circumference;
});
</script>

<template>
    <div
        class="progress-card bg-white rounded-[24px] p-5 sm:p-6 border border-[#e8efe9] shadow-sm flex flex-col justify-between transition-all duration-200 hover:shadow-md"
    >
        <!-- Card Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-display font-extrabold text-[17px] text-[#14221d] tracking-tight">
                {{ title }}
            </h3>
            <div
                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-[#f4f8f5] text-[#55766c] border border-[#e2ece6]"
            >
                <span>{{ periodLabel }}</span>
            </div>
        </div>

        <!-- Content: Circular Ring on Left + 3 Metric Tiles on Right -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
            <!-- Left: Circular SVG Progress Ring -->
            <div class="flex flex-col items-center justify-center p-3 rounded-2xl bg-[#f9fcfb] border border-[#eaf2ee]">
                <div class="relative w-[110px] h-[110px] flex items-center justify-center">
                    <svg :width="size" :height="size" class="transform -rotate-90">
                        <!-- Background ring track -->
                        <circle
                            :cx="size / 2"
                            :cy="size / 2"
                            :r="radius"
                            fill="transparent"
                            stroke="#e8f3ee"
                            :stroke-width="strokeWidth"
                        />
                        <!-- Filled progress arc -->
                        <circle
                            :cx="size / 2"
                            :cy="size / 2"
                            :r="radius"
                            fill="transparent"
                            stroke="#34B182"
                            :stroke-width="strokeWidth"
                            stroke-linecap="round"
                            :stroke-dasharray="circumference"
                            :stroke-dashoffset="strokeDashoffset"
                            class="transition-all duration-1000 ease-out"
                        />
                    </svg>

                    <!-- Center Ring Label -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span class="font-display font-extrabold text-2xl text-[#14221d] tracking-tight leading-none">
                            {{ Math.round(percent) }}%
                        </span>
                        <span class="text-[10px] font-bold text-[#738881] uppercase tracking-wider mt-1">
                            {{ ringLabel }}
                        </span>
                    </div>
                </div>

                <!-- Footnote comparison below ring -->
                <div class="flex items-center gap-1.5 mt-3 text-[11px] font-medium text-[#657d76]">
                    <BarChart2 :size="13" class="text-[#34B182]" />
                    <span>{{ trendText }}</span>
                </div>
            </div>

            <!-- Right: 3 Metric Tiles Stacked -->
            <div class="flex flex-col gap-2.5">
                <div
                    v-for="tile in tiles"
                    :key="tile.id"
                    class="flex items-center gap-3 p-2.5 sm:p-3 rounded-2xl border transition-all duration-200"
                    :class="[
                        tile.color === 'purple'
                            ? 'bg-[#f6f2fc] border-[#ece4f7] hover:bg-[#efe8fa]'
                            : tile.color === 'amber'
                            ? 'bg-[#fef7ee] border-[#faebd4] hover:bg-[#fdf0df]'
                            : tile.color === 'rose'
                            ? 'bg-[#fef1f0] border-[#fadcd9] hover:bg-[#fde6e4]'
                            : 'bg-[#edf8f3] border-[#d7efe3] hover:bg-[#e3f4ec]',
                    ]"
                >
                    <!-- Icon Badge -->
                    <div
                        class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-sm"
                        :class="[
                            tile.color === 'purple'
                                ? 'bg-[#7a4ec2] text-white'
                                : tile.color === 'amber'
                                ? 'bg-[#C29B40] text-white'
                                : tile.color === 'rose'
                                ? 'bg-[#e25353] text-white'
                                : 'bg-[#34B182] text-white',
                        ]"
                    >
                        <component :is="tile.icon" :size="18" class="stroke-[2.2]" />
                    </div>

                    <!-- Label and Count -->
                    <div class="min-w-0 flex-1">
                        <div class="font-display font-extrabold text-lg text-[#14221d] leading-none mb-0.5">
                            {{ tile.value }}
                        </div>
                        <div class="text-[11px] font-semibold text-[#667a74] truncate">
                            {{ tile.label }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.progress-card {
    min-height: 250px;
}
</style>

