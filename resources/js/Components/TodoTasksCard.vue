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

// Local state for toggled tasks
const completedTaskIds = ref(new Set());

// Initialize completed tasks from props
props.tasks.forEach((t) => {
    if (t.is_completed || t.completed) {
        completedTaskIds.value.add(t.id);
    }
});

function toggleTask(id) {
    if (completedTaskIds.value.has(id)) {
        completedTaskIds.value.delete(id);
    } else {
        completedTaskIds.value.add(id);
    }
}

const filteredTasks = computed(() => {
    return props.tasks.filter((task) => {
        const isDone = completedTaskIds.value.has(task.id) || task.is_completed || task.completed;
        if (currentFilter.value === 'open') return !isDone;
        if (currentFilter.value === 'done') return isDone;
        return true;
    });
});

const counts = computed(() => {
    const total = props.tasks.length;
    let done = 0;
    props.tasks.forEach((t) => {
        if (completedTaskIds.value.has(t.id) || t.is_completed || t.completed) done++;
    });
    return {
        all: total,
        open: total - done,
        done,
    };
});
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
                    completedTaskIds.has(task.id)
                        ? 'bg-[#fafcfb] border-[#edf2ef] opacity-75'
                        : 'bg-[#fbfdfc] border-[#e7eee9] hover:border-[#34B182]/50 hover:bg-white hover:shadow-sm',
                ]"
            >
                <div class="flex items-start gap-3">
                    <!-- Checkbox button -->
                    <button
                        type="button"
                        class="w-5 h-5 rounded-lg border flex items-center justify-center shrink-0 mt-0.5 transition-all"
                        :class="[
                            completedTaskIds.has(task.id)
                                ? 'bg-[#34B182] border-[#34B182] text-white'
                                : 'border-[#b5ccc3] bg-white hover:border-[#34B182]',
                        ]"
                        :aria-label="completedTaskIds.has(task.id) ? 'Tandai belum selesai' : 'Tandai selesai'"
                        @click="toggleTask(task.id)"
                    >
                        <Check v-if="completedTaskIds.has(task.id)" :size="13" class="stroke-[3]" />
                    </button>

                    <!-- Task details -->
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="task.link || (isTeacher ? '/teacher/assignments' : '/practice')"
                            class="block font-display font-bold text-[13px] leading-tight text-[#14221d] hover:text-[#19755d] transition-colors"
                            :class="{ 'line-through text-[#8b9c96]': completedTaskIds.has(task.id) }"
                        >
                            {{ task.title }}
                        </Link>
                        <p
                            v-if="task.subtitle"
                            class="text-[11px] text-[#718780] leading-snug mt-0.5 truncate"
                        >
                            {{ task.subtitle }}
                        </p>

                        <!-- Task Footer: Date & Badge -->
                        <div class="flex items-center justify-between gap-2 mt-2 pt-1 border-t border-[#f0f4f1]">
                            <span class="text-[10px] font-medium text-[#849a92]">
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
            <p class="text-xs font-semibold text-[#667a73]">Tidak ada tugas pada filter ini.</p>
        </div>
    </div>
</template>

<style scoped>
.todo-card {
    user-select: none;
}
</style>

