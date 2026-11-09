<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, Camera, ChevronLeft, ChevronRight, Clock, Plus, Sparkles } from '@lucide/vue';

const props = defineProps({
    title: {
        type: String,
        default: 'Materi Sedang Berjalan',
    },
    categories: {
        type: Array,
        default: () => [],
    },
    isTeacher: {
        type: Boolean,
        default: false,
    },
});

const currentTab = ref('all'); // 'all' | 'active' | 'completed'
const searchQuery = ref('');
const currentPage = ref(0);
const pageSize = 3;

// Default fallback courses if user just started or categories list is small
const defaultCourses = [
    {
        id: 'alfabet',
        slug: 'alphabet',
        badge: 'ALFABET',
        badgeColor: 'teal',
        title: 'Alfabet Isyarat A - Z',
        level: 'Tingkat Pemula',
        total: 26,
        done: 10,
        percent: 38,
        unitsLabel: 'Huruf',
        duration: '15 mnt',
        xpReward: 260,
        link: '/practice',
    },
    {
        id: 'angka',
        slug: 'numbers',
        badge: 'ANGKA',
        badgeColor: 'amber',
        title: 'Angka & Bilangan 1 - 10',
        level: 'Tingkat Dasar',
        total: 10,
        done: 4,
        percent: 40,
        unitsLabel: 'Angka',
        duration: '10 mnt',
        xpReward: 100,
        link: '/learn',
    },
    {
        id: 'kata',
        slug: 'phrases',
        badge: 'KATA',
        badgeColor: 'blue',
        title: 'Kata Sapaan Sehari-hari',
        level: 'Tingkat Lanjut',
        total: 15,
        done: 2,
        percent: 13,
        unitsLabel: 'Kata',
        duration: '20 mnt',
        xpReward: 150,
        link: '/learn',
    },
];

// Map real categories from database into course cards
const coursesList = computed(() => {
    if (!props.categories || props.categories.length === 0) {
        return defaultCourses;
    }

    return props.categories.map((cat, idx) => {
        const total = Number(cat.total) || 10;
        const done = Number(cat.done) || 0;
        const percent = Math.min(100, Math.round((done / Math.max(1, total)) * 100));

        const colors = ['teal', 'amber', 'purple', 'rose'];
        const badgeColor = colors[idx % colors.length];

        return {
            id: cat.id || cat.slug || idx,
            slug: cat.slug || 'category',
            badge: (cat.slug || cat.name || 'MODUL').toUpperCase(),
            badgeColor,
            title: cat.name || 'Modul Isyarat',
            level: percent === 100 ? 'Selesai' : percent > 0 ? 'Sedang Belajar' : 'Tingkat Pemula',
            total,
            done,
            percent,
            unitsLabel: 'Materi',
            duration: `${total * 2} mnt`,
            xpReward: total * 10,
            link: cat.slug === 'alphabet' ? '/practice' : '/learn',
        };
    });
});

// Filter by tab
const filteredCourses = computed(() => {
    return coursesList.value.filter((course) => {
        if (currentTab.value === 'active') {
            return course.percent < 100 && course.percent > 0;
        }
        if (currentTab.value === 'completed') {
            return course.percent === 100;
        }
        return true;
    });
});

// Pagination
const maxPages = computed(() => Math.ceil(filteredCourses.value.length / pageSize));
const visibleCourses = computed(() => {
    const start = currentPage.value * pageSize;
    return filteredCourses.value.slice(start, start + pageSize);
});

function prevPage() {
    if (currentPage.value > 0) currentPage.value--;
}

function nextPage() {
    if (currentPage.value < maxPages.value - 1) currentPage.value++;
}
</script>

<template>
    <div class="courses-in-progress">
        <!-- Section Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            <div>
                <h3 class="font-display font-extrabold text-xl text-[#14221d] tracking-tight">
                    {{ title }}
                </h3>
            </div>

            <div class="flex items-center justify-between sm:justify-end gap-3">
                <!-- Filter Tabs: Semua | Aktif | Selesai -->
                <div class="inline-flex items-center p-1 rounded-full bg-[#eef4f0] border border-[#dce7e1] text-xs font-bold text-[#5e7770]">
                    <button
                        type="button"
                        class="px-3 py-1 rounded-full transition-all"
                        :class="[
                            currentTab === 'all'
                                ? 'bg-white text-[#14221d] shadow-sm'
                                : 'hover:text-[#14221d]',
                        ]"
                        @click="currentTab = 'all'"
                    >
                        Semua
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1 rounded-full transition-all"
                        :class="[
                            currentTab === 'active'
                                ? 'bg-white text-[#14221d] shadow-sm'
                                : 'hover:text-[#14221d]',
                        ]"
                        @click="currentTab = 'active'"
                    >
                        Aktif
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1 rounded-full transition-all"
                        :class="[
                            currentTab === 'completed'
                                ? 'bg-white text-[#14221d] shadow-sm'
                                : 'hover:text-[#14221d]',
                        ]"
                        @click="currentTab = 'completed'"
                    >
                        Selesai
                    </button>
                </div>

                <!-- Action Button & Pagination Controls -->
                <div class="flex items-center gap-2">
                    <Link
                        :href="isTeacher ? '/teacher/modules' : '/learn'"
                        class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-[#e8f7f0] text-[#1b735a] hover:bg-[#34B182] hover:text-white transition-colors"
                    >
                        <span>{{ isTeacher ? 'Kelola Modul' : 'Semua Materi' }}</span>
                        <Plus :size="13" class="stroke-[2.5]" />
                    </Link>

                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="w-7 h-7 rounded-full flex items-center justify-center border border-[#dce7e1] bg-white text-[#5e7770] hover:text-[#14221d] hover:border-[#34B182] disabled:opacity-40 transition-colors"
                            :disabled="currentPage === 0"
                            aria-label="Halaman sebelumnya"
                            @click="prevPage"
                        >
                            <ChevronLeft :size="15" />
                        </button>
                        <button
                            type="button"
                            class="w-7 h-7 rounded-full flex items-center justify-center border border-[#dce7e1] bg-white text-[#5e7770] hover:text-[#14221d] hover:border-[#34B182] disabled:opacity-40 transition-colors"
                            :disabled="currentPage >= maxPages - 1"
                            aria-label="Halaman berikutnya"
                            @click="nextPage"
                        >
                            <ChevronRight :size="15" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3 Course Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
            <div
                v-for="course in visibleCourses"
                :key="course.id"
                class="course-card bg-white rounded-[24px] p-5 sm:p-6 border border-[#e8efe9] shadow-sm flex flex-col justify-between transition-all duration-300 hover:shadow-lg hover:-translate-y-1 group"
            >
                <div>
                    <!-- Top Category & Level Row -->
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span
                            class="px-2.5 py-1 rounded-xl text-[11px] font-extrabold tracking-wider uppercase"
                            :class="[
                                course.badgeColor === 'teal'
                                    ? 'bg-[#e8f7f0] text-[#18755c]'
                                    : course.badgeColor === 'amber'
                                    ? 'bg-[#fdf4e7] text-[#ab832a]'
                                    : course.badgeColor === 'purple'
                                    ? 'bg-[#f4effc] text-[#784ec0]'
                                    : 'bg-[#edf5f8] text-[#2c728e]',
                            ]"
                        >
                            {{ course.badge }}
                        </span>
                        <span class="text-[11px] font-semibold text-[#7c928b]">
                            {{ course.level }}
                        </span>
                    </div>

                    <!-- Course Title -->
                    <h4 class="font-display font-extrabold text-[17px] text-[#14221d] leading-snug group-hover:text-[#18755c] transition-colors mb-3">
                        {{ course.title }}
                    </h4>

                    <!-- Metadata row: lessons count, duration, XP -->
                    <div class="flex items-center gap-3 text-[11px] font-semibold text-[#667e76] mb-5">
                        <div class="flex items-center gap-1">
                            <BookOpen :size="13" class="text-[#34B182]" />
                            <span>{{ course.total }} {{ course.unitsLabel }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Clock :size="13" class="text-[#C29B40]" />
                            <span>{{ course.duration }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Sparkles :size="13" class="text-[#f19f38]" />
                            <span>+{{ course.xpReward }} XP</span>
                        </div>
                    </div>
                </div>

                <div>
                    <!-- Progress Bar & Status -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                            <span class="text-[#14221d]">{{ course.percent }}% Selesai</span>
                            <span class="text-[11px] text-[#869b93] font-medium">
                                {{ course.done }}/{{ course.total }} {{ course.unitsLabel }}
                            </span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-[#eef5f1] overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-700 ease-out"
                                :style="{ width: `${course.percent}%` }"
                                :class="[
                                    course.percent === 100
                                        ? 'bg-[#34B182]'
                                        : 'bg-gradient-to-r from-[#2da677] to-[#45c998]',
                                ]"
                            ></div>
                        </div>
                    </div>

                    <!-- Card Footer: jenis latihan & tombol aksi. Materi ini latihan mandiri
                         dengan kamera, bukan kelas atau tugas guru, jadi tidak ada nama guru. -->
                    <div class="flex items-center justify-between gap-2 pt-3 border-t border-[#f0f5f2]">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-7 h-7 rounded-full bg-[#dff3ea] text-[#1e7861] flex items-center justify-center shrink-0">
                                <Camera :size="14" class="stroke-[2.2]" />
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-[#14221d] truncate">
                                    Latihan Mandiri
                                </div>
                                <div class="text-[10px] text-[#8aa098] truncate">
                                    Diperiksa lewat kamera
                                </div>
                            </div>
                        </div>

                        <Link
                            :href="course.link"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-[#f4f8f5] hover:bg-[#34B182] text-[#196b54] hover:text-white font-bold text-xs transition-colors shrink-0"
                        >
                            <span>{{ course.percent > 0 ? 'Lanjut' : 'Mulai' }}</span>
                            <ArrowRight :size="12" class="stroke-[2.5]" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.course-card {
    min-height: 230px;
}
</style>

