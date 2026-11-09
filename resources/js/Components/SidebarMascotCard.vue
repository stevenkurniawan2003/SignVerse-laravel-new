<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from '@lucide/vue';
import MascotIllustration from './MascotIllustration.vue';

const props = defineProps({
    isTeacher: {
        type: Boolean,
        default: false,
    },
});

const title = computed(() => (props.isTeacher ? 'Kelola Kelas & Tugas' : 'Latihan Mandiri AI'));
const description = computed(() =>
    props.isTeacher
        ? 'Buat tugas baru dan pantau kemajuan gesture siswa secara realtime.'
        : 'Asah ketepatan isyaratmu dengan deteksi kamera AI real-time sekarang.'
);
const ctaLabel = computed(() => (props.isTeacher ? 'Beri Tugas' : 'Mulai Latihan'));
const ctaLink = computed(() => (props.isTeacher ? '/teacher/assignments' : '/practice'));
</script>

<template>
    <div class="sidebar-mascot-wrapper relative mt-auto pt-10 px-2 pb-2">
        <!-- Floating mascot character popping up from top edge -->
        <div class="mascot-pop absolute -top-12 left-1/2 -translate-x-1/2 pointer-events-none z-10">
            <MascotIllustration :variant="isTeacher ? 'teacher' : 'student'" size="md" />
        </div>

        <!-- Dark teal playful container card -->
        <div
            class="mascot-card relative overflow-hidden rounded-[22px] bg-gradient-to-b from-[#18483e] via-[#123830] to-[#0d2a24] text-white p-4 pt-14 text-center shadow-lg border border-[#23584c]"
        >
            <!-- Decorative ambient glow inside card -->
            <div
                class="absolute -top-10 -right-10 w-28 h-28 bg-[#34B182]/20 rounded-full blur-2xl pointer-events-none"
            ></div>
            <div
                class="absolute -bottom-8 -left-8 w-24 h-24 bg-[#C29B40]/15 rounded-full blur-xl pointer-events-none"
            ></div>

            <div class="relative z-10 flex flex-col items-center">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase bg-[#34B182]/20 text-[#7ce0b8] border border-[#34B182]/30 mb-2"
                >
                    <Sparkles :size="11" class="text-[#f4cd6f]" />
                    <span>{{ isTeacher ? 'Studio Guru' : 'AI Isyarat SLB' }}</span>
                </span>

                <h4 class="font-display font-extrabold text-[15px] leading-tight text-white mb-1.5 tracking-tight">
                    {{ title }}
                </h4>

                <p class="text-[11px] leading-snug text-[#9cc0b7] mb-3 px-1">
                    {{ description }}
                </p>

                <Link
                    :href="ctaLink"
                    class="inline-flex items-center justify-center gap-1.5 w-full py-2.5 px-3 rounded-xl bg-gradient-to-r from-[#C29B40] to-[#d8b04e] hover:from-[#d1a745] hover:to-[#e4be5b] text-[#14221d] font-bold text-xs shadow-md shadow-[#C29B40]/25 transition-all duration-200 transform hover:-translate-y-0.5 active:translate-y-0"
                >
                    <span>{{ ctaLabel }}</span>
                    <ArrowRight :size="13" class="stroke-[2.5]" />
                </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.sidebar-mascot-wrapper {
    margin-top: 18px;
    margin-bottom: 8px;
}
.mascot-card {
    box-shadow: 0 12px 28px -6px rgba(13, 42, 36, 0.45), 0 0 0 1px rgba(52, 177, 130, 0.15);
}
</style>
