<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Check, Plus, Sparkles, Target } from '@lucide/vue';

const props = defineProps({
    title: {
        type: String,
        default: 'Daftar Tugas & Misi',
    },
    tasks: {
        type: Array,
        default: () => [],
    },
    isTeacher: {
        type: Boolean,
        default: false,
    },
});

const currentFilter = ref('all'); // 'all' | 'open' | 'done'

/**
 * Status selesai hanya berasal dari data server (misi harian tercapai, tugas guru
 * dikumpulkan, dsb.), jadi centang terisi otomatis dan tidak bisa diubah manual.
 */
function isDone(task) {
    return Boolean(task.is_completed || task.completed);
}

const filteredTasks = computed(() => props.tasks.filter((task) => {
    if (currentFilter.value === 'open') return !isDone(task);
    if (currentFilter.value === 'done') return isDone(task);
    return true;
}));

const counts = computed(() => {
    const done = props.tasks.filter(isDone).length;
    return {
        all: props.tasks.length,
        open: props.tasks.length - done,
        done,
    };
});

function progressPercent(task) {
    if (!task.target) return 0;
    return Math.min(100, Math.round((Number(task.progress || 0) / Number(task.target)) * 100));
}
</script>

<template>
    <div class="todo-card bg-white rounded-[24px] p-5 border border-[#e8efe9] shadow-sm">
        <!-- Header with Title & Action Button -->
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-display font-extrabold text-[16px] text-[#14221d] tracking-tight">
                {{ title }}
            </h3>
            <Link
                :href="isTeacher ? '/teacher/assignments' : '/challenge'"
                class="w-7 h-7 rounded-full flex items-center justify-center bg-[#f0f7f3] text-[#1e7861] hover:bg-[#34B182] hover:text-white transition-colors"
                title="Lihat Semua / Tambah"
            >
                <Plus :size="16" class="stroke-[2.5]" />
            </Link>
        </div>

        <!-- Filter Tabs: Semua | Buka | Selesai -->
        <div class="flex items-center gap-1.5 mb-4 text-[11px] font-bold">
            <button
                type="button"
                class="px-2.5 py-1 rounded-full transition-all"
                :class="[
                    currentFilter === 'all'
                        ? 'bg-[#143d34] text-white shadow-sm'
                        : 'bg-[#f4f8f5] text-[#69827b] hover:bg-[#eaf2ee]',
                ]"
                @click="currentFilter = 'all'"
            >
                Semua <span class="opacity-75">({{ counts.all }})</span>
            </button>
            <button
                type="button"
                class="px-2.5 py-1 rounded-full transition-all"
                :class="[
                    currentFilter === 'open'
                        ? 'bg-[#143d34] text-white shadow-sm'
                        : 'bg-[#f4f8f5] text-[#69827b] hover:bg-[#eaf2ee]',
                ]"
                @click="currentFilter = 'open'"
            >
                Buka <span class="opacity-75">({{ counts.open }})</span>
            </button>
            <button
                type="button"
                class="px-2.5 py-1 rounded-full transition-all"
                :class="[
                    currentFilter === 'done'
                        ? 'bg-[#143d34] text-white shadow-sm'
                        : 'bg-[#f4f8f5] text-[#69827b] hover:bg-[#eaf2ee]',
                ]"
                @click="currentFilter = 'done'"
            >
                Selesai <span class="opacity-75">({{ counts.done }})</span>
            </button>
        </div>

        <!-- Task List Items -->
        <div v-if="filteredTasks.length" class="flex flex-col gap-2.5">
            <div
                v-for="task in filteredTasks"
                :key="task.id"
                class="group p-3 rounded-2xl border transition-all duration-200"
                :class="[
                    isDone(task)
                        ? 'bg-[#fafcfb] border-[#edf2ef] opacity-75'
                        : 'bg-[#fbfdfc] border-[#e7eee9] hover:border-[#34B182]/50 hover:bg-white hover:shadow-sm',
                ]"
            >
                <div class="flex items-start gap-3">
                    <!-- Status selesai (otomatis dari sistem, bukan tombol) -->
                    <span
                        class="w-5 h-5 rounded-lg border flex items-center justify-center shrink-0 mt-0.5 transition-all"
                        :class="[
                            isDone(task)
                                ? 'bg-[#34B182] border-[#34B182] text-white'
                                : task.overdue
                                ? 'border-[#e8a39b] bg-[#fdf2f2]'
                                : 'border-[#b5ccc3] bg-white',
                        ]"
                        role="img"
                        :aria-label="isDone(task) ? 'Sudah selesai' : task.overdue ? 'Terlewat' : 'Belum selesai'"
                        :title="isDone(task) ? 'Selesai otomatis' : 'Tercentang otomatis setelah kamu menyelesaikannya'"
                    >
                        <Check v-if="isDone(task)" :size="13" class="stroke-[3]" />
                    </span>

                    <!-- Task details -->
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="task.link || (isTeacher ? '/teacher/assignments' : '/practice')"
                            class="block font-display font-bold text-[13px] leading-tight text-[#14221d] hover:text-[#19755d] transition-colors"
                            :class="{ 'line-through text-[#8b9c96]': isDone(task) }"
                        >
                            {{ task.title }}
                        </Link>
                        <p
                            v-if="task.subtitle"
                            class="text-[11px] text-[#718780] leading-snug mt-0.5 truncate"
                        >
                            {{ task.subtitle }}
                        </p>

                        <!-- Progres misi (mis. 2/5 huruf) -->
                        <div v-if="task.target" class="flex items-center gap-2 mt-1.5">
                            <div class="flex-1 h-1.5 rounded-full bg-[#eef5f1] overflow-hidden">
                                <div class="h-full rounded-full bg-[#34B182] transition-all duration-500" :style="{ width: `${progressPercent(task)}%` }"></div>
                            </div>
                            <span class="text-[10px] font-bold text-[#52685d] shrink-0">{{ Math.min(task.progress || 0, task.target) }}/{{ task.target }}</span>
                        </div>

                        <!-- Task Footer: Date & Badge -->
                        <div class="flex items-center justify-between gap-2 mt-2 pt-1 border-t border-[#f0f4f1]">
                            <span class="text-[10px] font-medium" :class="task.overdue && !isDone(task) ? 'text-[#b2382d] font-bold' : 'text-[#849a92]'">
                                {{ task.due_date || task.date || 'Hari ini' }}
                            </span>

                            <span
                                v-if="task.reward_xp"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-[#fdf5e7] text-[#9b7625]"
                            >
                                <Sparkles :size="10" class="text-[#C29B40]" />
                                <span>+{{ task.reward_xp }} XP</span>
                            </span>
                            <span
                                v-else-if="task.badge"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-[#edf8f2] text-[#1b735a]"
                            >
                                <span>{{ task.badge }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="text-center py-6 px-3 bg-[#fafcfb] rounded-2xl border border-dashed border-[#d9e5df]">
            <Target :size="24" class="mx-auto text-[#94a8a0] mb-2" />
            <p class="text-xs font-semibold text-[#667a73]">
                {{ tasks.length ? 'Tidak ada tugas pada filter ini.' : 'Belum ada tugas atau misi saat ini.' }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.todo-card {
    user-select: none;
}
</style>

