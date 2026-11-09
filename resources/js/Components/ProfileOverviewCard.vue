<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Check, Flame, MoreVertical, Sparkles, Star } from '@lucide/vue';

const props = defineProps({
    user: {
        type: Object,
        default: () => null,
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    isTeacher: {
        type: Boolean,
        default: false,
    },
});

const displayName = computed(() => props.user?.name || 'Pelajar Isyarat');
const userRole = computed(() => {
    if (props.isTeacher) return 'Guru Pembimbing';
    return 'Siswa SLB';
});

const initials = computed(() => {
    if (props.user?.initials) return props.user.initials;
    const name = displayName.value;
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w.charAt(0).toUpperCase())
        .join('');
});

const totalXp = computed(() => props.summary?.total_xp ?? 0);
const streak = computed(() => props.summary?.current_streak ?? 0);
const levelName = computed(() => props.summary?.level?.name || 'Beginner');

// Progress ring around avatar (e.g. 70% of level completion)
const levelPct = computed(() => props.summary?.level?.pct || 65);
const ringSize = 88;
const strokeWidth = 5;
const radius = (ringSize - strokeWidth) / 2;
const circumference = 2 * Math.PI * radius;
const strokeDashoffset = computed(() => {
    const p = Math.min(Math.max(levelPct.value, 0), 100);
    return circumference - (p / 100) * circumference;
});
</script>

<template>
    <div class="profile-card bg-white rounded-[24px] p-5 sm:p-6 border border-[#e8efe9] shadow-sm relative text-center">
        <!-- Top action menu button -->
        <Link
            :href="isTeacher ? '/teacher/profile' : '/profile'"
            class="absolute top-4 right-4 w-7 h-7 rounded-full flex items-center justify-center text-[#738881] hover:text-[#14221d] hover:bg-[#f0f5f2] transition-colors"
            title="Pengaturan Profil"
        >
            <MoreVertical :size="16" />
        </Link>

        <!-- Centered Avatar with SVG Progress Arc -->
        <div class="relative w-[88px] h-[88px] mx-auto mb-3.5 flex items-center justify-center">
            <!-- Circular Progress Arc around Avatar -->
            <svg :width="ringSize" :height="ringSize" class="absolute inset-0 transform -rotate-90">
                <circle
                    :cx="ringSize / 2"
                    :cy="ringSize / 2"
                    :r="radius"
                    fill="transparent"
                    stroke="#eaf3ee"
                    :stroke-width="strokeWidth"
                />
                <circle
                    :cx="ringSize / 2"
                    :cy="ringSize / 2"
                    :r="radius"
                    fill="transparent"
                    stroke="#34B182"
                    :stroke-width="strokeWidth"
                    stroke-linecap="round"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="strokeDashoffset"
                />
            </svg>

            <!-- Inner Avatar Circle -->
            <div
                class="w-[72px] h-[72px] rounded-full bg-gradient-to-tr from-[#ffdfcb] to-[#ffece0] text-[#a65b39] font-display font-extrabold text-2xl flex items-center justify-center shadow-inner select-none"
            >
                {{ initials }}
            </div>
        </div>

        <!-- User Name & Verified Checkmark -->
        <div class="flex items-center justify-center gap-1.5 mb-0.5">
            <h3 class="font-display font-extrabold text-[17px] text-[#14221d] tracking-tight truncate max-w-[190px]">
                {{ displayName }}
            </h3>
            <span
                class="w-4 h-4 rounded-full bg-[#34B182] text-white flex items-center justify-center shrink-0 shadow-sm"
                title="Akun Terverifikasi"
            >
                <Check :size="11" class="stroke-[3]" />
            </span>
        </div>

        <!-- Role / Level Subtitle -->
        <p class="text-xs font-semibold text-[#738881] mb-4">
            {{ userRole }} • <span class="text-[#20755f] font-bold">{{ levelName }}</span>
        </p>

        <!-- Stats row: XP & Streak pills -->
        <div class="flex items-center justify-center gap-2 pt-3 border-t border-[#f0f5f2]">
            <div
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-[#fef7ee] text-[#b48825] border border-[#fbe9cd]"
            >
                <Star :size="13" class="fill-[#C29B40] text-[#C29B40]" />
                <span>{{ totalXp }} XP</span>
            </div>

            <div
                v-if="!isTeacher"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-[#fdf2ec] text-[#d46535] border border-[#fcdccf]"
            >
                <Flame :size="13" class="fill-[#E8633A] text-[#E8633A]" />
                <span>{{ streak }} Hari</span>
            </div>
            <div
                v-else
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-[#edf8f2] text-[#1b735a] border border-[#d7efe3]"
            >
                <Sparkles :size="13" class="text-[#34B182]" />
                <span>Guru Aktif</span>
            </div>
        </div>
    </div>
</template>
