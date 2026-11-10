<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { CalendarClock, CheckCircle2, ChevronLeft, ChevronRight, Dumbbell, RotateCw } from '@lucide/vue';

/**
 * Kalender mingguan dashboard yang terhubung ke /api/calendar:
 *   - titik hijau : ada latihan yang selesai di tanggal itu
 *   - titik oranye: ada tenggat tugas guru di tanggal itu
 * Klik tanggal untuk melihat ringkasan latihan dan daftar tugas yang tenggatnya hari itu.
 */
const props = defineProps({
    todayDate: {
        type: Date,
        default: () => new Date(),
    },
    // 'student' = latihanku & tugasku, 'teacher' = latihan siswa di kelas & tugas yang kubuat
    audience: {
        type: String,
        default: 'student',
    },
});

const emit = defineEmits(['select-date']);

const currentOffset = ref(0); // 0 = minggu ini, -1 = minggu lalu, +1 = minggu depan
const selectedDateString = ref(new Date().toDateString());

const monthNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
const dayNames = ['M', 'S', 'S', 'R', 'K', 'J', 'S'];
const fullDayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

/** Format tanggal lokal (bukan UTC) agar cocok dengan tanggal dari server. */
function dateKey(date) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
}

// Data per tanggal dari server, disimpan per minggu agar navigasi bolak-balik tidak memuat ulang
const daysByDate = ref({});
const loadedWeeks = new Set();
const loading = ref(false);
const loadError = ref(false);
let requestGeneration = 0;

const weekDays = computed(() => {
    const today = new Date(props.todayDate);
    const sunday = new Date(today);
    sunday.setDate(today.getDate() - today.getDay() + currentOffset.value * 7);

    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(sunday);
        d.setDate(sunday.getDate() + i);
        const key = dateKey(d);
        const info = daysByDate.value[key] || null;
        const deadlines = info?.deadlines || [];
        return {
            date: d,
            key,
            dateNum: d.getDate(),
            dayLabel: dayNames[i],
            isToday: d.toDateString() === new Date().toDateString(),
            isSelected: d.toDateString() === selectedDateString.value,
            dateString: d.toDateString(),
            hasPractice: Boolean(info?.practice),
            hasDeadline: deadlines.length > 0,
            hasOpenDeadline: deadlines.some((deadline) => !deadline.completed),
        };
    });
});

const currentMonthLabel = computed(() => {
    const activeDate = weekDays.value[3]?.date || new Date();
    return `${monthNames[activeDate.getMonth()]} ${activeDate.getFullYear()}`;
});

async function loadWeek(force = false) {
    const start = weekDays.value[0].key;
    const end = weekDays.value[6].key;
    const weekKey = `${start}|${end}`;
    if (!force && loadedWeeks.has(weekKey)) return;

    const generation = ++requestGeneration;
    loading.value = true;
    loadError.value = false;

    try {
        const res = await fetch(`/api/calendar?start=${start}&end=${end}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || data.status !== 'success') throw new Error(String(res.status));
        if (generation !== requestGeneration) return;

        daysByDate.value = { ...daysByDate.value, ...(data.days || {}) };
        loadedWeeks.add(weekKey);
    } catch {
        if (generation === requestGeneration) loadError.value = true;
    } finally {
        if (generation === requestGeneration) loading.value = false;
    }
}

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

watch(currentOffset, () => loadWeek());
onMounted(() => loadWeek());

// Detail tanggal terpilih
const selectedDay = computed(() => {
    const date = new Date(selectedDateString.value);
    const info = daysByDate.value[dateKey(date)] || null;
    return {
        label: `${fullDayNames[date.getDay()]}, ${date.getDate()} ${monthNames[date.getMonth()]}`,
        isToday: date.toDateString() === new Date().toDateString(),
        practice: info?.practice || null,
        deadlines: info?.deadlines || [],
    };
});

const isTeacher = computed(() => props.audience === 'teacher');
const practiceTitle = computed(() => (isTeacher.value ? 'Latihan siswa di kelasmu' : 'Latihanmu'));
const deadlineTitle = computed(() => (isTeacher.value ? 'Tenggat tugas yang kamu buat' : 'Tenggat tugas'));
const emptyText = computed(() => (
    isTeacher.value
        ? 'Tidak ada latihan siswa atau tenggat tugas di tanggal ini.'
        : 'Belum ada latihan atau tenggat tugas di tanggal ini.'
));

defineExpose({ loadWeek, daysByDate, selectedDay });
</script>

<template>
    <div class="mini-calendar bg-white rounded-[24px] p-5 border border-[#e8efe9] shadow-sm">
        <!-- Bulan dan navigasi minggu -->
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

        <!-- Hari dan tanggal (7 kolom) -->
        <div class="grid grid-cols-7 gap-1 text-center" :class="{ 'opacity-60': loading }">
            <button
                v-for="day in weekDays"
                :key="day.dateString"
                type="button"
                class="flex flex-col items-center transition-all duration-200"
                :aria-pressed="day.isSelected"
                :aria-label="`${day.dateNum} ${monthNames[day.date.getMonth()]}${day.hasPractice ? ', ada latihan' : ''}${day.hasDeadline ? ', ada tenggat tugas' : ''}`"
                @click="selectDay(day)"
            >
                <span class="text-[11px] font-bold text-[#8c9e97] mb-2 uppercase">
                    {{ day.dayLabel }}
                </span>

                <span
                    class="w-8 h-10 sm:h-11 rounded-xl flex items-center justify-center text-xs font-bold transition-all duration-200"
                    :class="[
                        day.isToday
                            ? 'bg-[#34B182] text-white shadow-md shadow-[#34B182]/30 scale-105'
                            : day.isSelected
                            ? 'bg-[#e2f2ea] text-[#167b71]'
                            : 'text-[#425952] hover:bg-[#f3f7f5]',
                        day.isSelected ? 'ring-2 ring-[#34B182] ring-offset-1' : '',
                    ]"
                >
                    {{ day.dateNum }}
                </span>

                <!-- Titik penanda: hijau = latihan, oranye = tenggat tugas -->
                <span class="flex items-center justify-center gap-0.5 h-2 mt-1.5" aria-hidden="true">
                    <i v-if="day.hasPractice" class="w-1.5 h-1.5 rounded-full bg-[#34B182]"></i>
                    <i
                        v-if="day.hasDeadline"
                        class="w-1.5 h-1.5 rounded-full"
                        :class="day.hasOpenDeadline ? 'bg-[#f19f38]' : 'bg-[#c9d6d0]'"
                    ></i>
                </span>
            </button>
        </div>

        <!-- Keterangan titik -->
        <div class="flex items-center justify-center gap-4 mt-3 text-[10px] font-semibold text-[#738881]">
            <span class="inline-flex items-center gap-1"><i class="w-1.5 h-1.5 rounded-full bg-[#34B182]"></i> Latihan</span>
            <span class="inline-flex items-center gap-1"><i class="w-1.5 h-1.5 rounded-full bg-[#f19f38]"></i> Tenggat tugas</span>
        </div>

        <!-- Detail tanggal terpilih -->
        <div class="mt-4 pt-4 border-t border-[#f0f5f2]" aria-live="polite">
            <div class="flex items-center justify-between gap-2 mb-3">
                <h4 class="font-display font-extrabold text-[13px] text-[#14221d]">
                    {{ selectedDay.label }}
                    <span v-if="selectedDay.isToday" class="ml-1 text-[10px] font-extrabold text-[#34B182]">Hari ini</span>
                </h4>
                <button
                    v-if="loadError"
                    type="button"
                    class="inline-flex items-center gap-1 text-[11px] font-bold text-[#b2382d] hover:text-[#14221d]"
                    @click="loadWeek(true)"
                >
                    <RotateCw :size="12" /> Gagal memuat, coba lagi
                </button>
            </div>

            <div v-if="selectedDay.practice || selectedDay.deadlines.length" class="space-y-3">
                <!-- Ringkasan latihan -->
                <div v-if="selectedDay.practice" class="p-3 rounded-2xl bg-[#eefaf4] border border-[#d5f3e5]">
                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-[#1e7253] mb-1">
                        <Dumbbell :size="13" /> {{ practiceTitle }}
                    </div>
                    <div class="text-xs font-semibold text-[#14221d]">
                        {{ selectedDay.practice.sessions }} latihan · {{ selectedDay.practice.passed }} berhasil
                        <template v-if="selectedDay.practice.xp > 0"> · +{{ selectedDay.practice.xp }} XP</template>
                    </div>
                    <div v-if="selectedDay.practice.lessons.length" class="flex flex-wrap gap-1 mt-2">
                        <span
                            v-for="lesson in selectedDay.practice.lessons"
                            :key="lesson"
                            class="px-2 py-0.5 rounded-lg bg-white text-[10px] font-bold text-[#1e7253] border border-[#d5f3e5]"
                        >{{ lesson }}</span>
                    </div>
                </div>

                <!-- Tenggat tugas -->
                <div v-if="selectedDay.deadlines.length">
                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-[#ab832a] mb-1.5">
                        <CalendarClock :size="13" /> {{ deadlineTitle }}
                    </div>
                    <ul class="space-y-1.5">
                        <li v-for="deadline in selectedDay.deadlines" :key="deadline.id">
                            <Link
                                :href="deadline.link"
                                class="flex items-center gap-2 p-2.5 rounded-xl border transition-colors hover:border-[#34B182]"
                                :class="deadline.completed ? 'bg-[#fafcfb] border-[#eef3f0]' : deadline.overdue ? 'bg-[#fdf2f2] border-[#f6d0cc]' : 'bg-[#fffaf0] border-[#f6e1ba]'"
                            >
                                <CheckCircle2 v-if="deadline.completed" :size="15" class="text-[#34B182] shrink-0" />
                                <CalendarClock v-else :size="15" class="shrink-0" :class="deadline.overdue ? 'text-[#b2382d]' : 'text-[#f19f38]'" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold text-[#14221d] truncate">{{ deadline.title }}</div>
                                    <div class="text-[10px] text-[#738881] truncate">
                                        <template v-if="deadline.lesson_title">{{ deadline.lesson_title }} · </template>
                                        Pukul {{ deadline.due_time }} · {{ deadline.status_label }}
                                    </div>
                                </div>
                                <ChevronRight :size="14" class="text-[#a3b5ad] shrink-0" />
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <p v-else class="text-[11px] text-[#8aa098] text-center py-2">
                {{ loading ? 'Memuat aktivitas...' : emptyText }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.mini-calendar {
    user-select: none;
}
</style>
