<script setup>
import { computed, ref } from 'vue';
import { ChevronLeft, ChevronRight } from '@lucide/vue';

const props = defineProps({
    todayDate: {
        type: Date,
        default: () => new Date(),
    },
});

const emit = defineEmits(['select-date']);

const currentOffset = ref(0); // 0 = current week, -1 = last week, +1 = next week
const selectedDateString = ref(new Date().toDateString());

const monthNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const dayNames = ['M', 'S', 'S', 'R', 'K', 'J', 'S'];

// Compute 7 days of the active week centered or ending today
const weekDays = computed(() => {
    const today = new Date(props.todayDate);
    const dayOfWeek = today.getDay(); // 0 is Sunday
    // Start on Monday (or Sunday)
    const sunday = new Date(today);
    sunday.setDate(today.getDate() - dayOfWeek + currentOffset.value * 7);

    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(sunday);
        d.setDate(sunday.getDate() + i);
        const isToday = d.toDateString() === new Date().toDateString();
        const isSelected = d.toDateString() === selectedDateString.value;
        return {
            date: d,
            dateNum: d.getDate(),
            dayLabel: dayNames[i],
            isToday,
            isSelected,
            dateString: d.toDateString(),
        };
    });
});

const currentMonthLabel = computed(() => {
    const activeDate = weekDays.value[3]?.date || new Date();
    return `${monthNames[activeDate.getMonth()]} ${activeDate.getFullYear()}`;
});

function prevWeek() {
    currentOffset.value--;
}

function nextWeek() {
    currentOffset.value++;
}

function selectDay(day) {
    selectedDateString.value = day.dateString;
    emit('select-date', day.date);
}
</script>

<template>
    <div class="mini-calendar bg-white rounded-[24px] p-5 border border-[#e8efe9] shadow-sm">
        <!-- Month and Navigation Header -->
        <div class="flex items-center justify-between mb-4">
            <button
                type="button"
                class="w-7 h-7 rounded-full flex items-center justify-center text-[#738881] hover:text-[#14221d] hover:bg-[#f0f5f2] transition-colors"
                aria-label="Minggu sebelumnya"
                @click="prevWeek"
            >
                <ChevronLeft :size="16" />
            </button>

            <span class="font-display font-extrabold text-[15px] text-[#14221d] tracking-tight">
                {{ currentMonthLabel }}
            </span>

            <button
                type="button"
                class="w-7 h-7 rounded-full flex items-center justify-center text-[#738881] hover:text-[#14221d] hover:bg-[#f0f5f2] transition-colors"
                aria-label="Minggu berikutnya"
                @click="nextWeek"
            >
                <ChevronRight :size="16" />
            </button>
        </div>

        <!-- Days of Week and Numbers (7 Columns) -->
        <div class="grid grid-cols-7 gap-1 text-center">
            <div
                v-for="day in weekDays"
                :key="day.dateString"
                class="flex flex-col items-center cursor-pointer transition-all duration-200"
                @click="selectDay(day)"
            >
                <!-- Day initial (M, S, S, R, K, J, S) -->
                <span class="text-[11px] font-bold text-[#8c9e97] mb-2 uppercase">
                    {{ day.dayLabel }}
                </span>

                <!-- Day number container -->
                <div
                    class="w-8 h-10 sm:h-11 rounded-xl flex items-center justify-center text-xs font-bold transition-all duration-200"
                    :class="[
                        day.isToday
                            ? 'bg-[#143d34] text-white shadow-md shadow-[#143d34]/30 scale-105'
                            : day.isSelected
                            ? 'bg-[#e2f2ea] text-[#167b71] ring-2 ring-[#34B182]'
                            : 'text-[#425952] hover:bg-[#f3f7f5]',
                    ]"
                >
                    {{ day.dateNum }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.mini-calendar {
    user-select: none;
}
</style>
