<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useSlots } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    BookOpen,
    CalendarCheck,
    ClipboardList,
    FileBarChart,
    Flame,
    GraduationCap,
    Hand,
    LayoutDashboard,
    LogOut,
    Menu,
    PanelLeft,
    School,
    Sparkles,
    Star,
    Target,
    TrendingUp,
    Trophy,
    UserRound,
    Users,
    X,
} from '@lucide/vue';
import ConfirmDialog from './ConfirmDialog.vue';
import SpeechToggle from './SpeechToggle.vue';
import SidebarMascotCard from '../Components/SidebarMascotCard.vue';
import { shouldAnimatePageEntry } from '../pageTransition';

const slots = useSlots();
const page = usePage();
const isPageEntering = ref(shouldAnimatePageEntry(page.url));

// Mobile drawer open state
const menuOpen = ref(false);

// Collapsible sidebar state (persisted in localStorage)
const isSidebarCollapsed = ref(false);

onMounted(() => {
    try {
        isSidebarCollapsed.value = localStorage.getItem('signtech_sidebar_collapsed') === 'true';
    } catch {
        isSidebarCollapsed.value = false;
    }
    window.addEventListener('keydown', handleKeyDown);
});

function toggleSidebar() {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    try {
        localStorage.setItem('signtech_sidebar_collapsed', String(isSidebarCollapsed.value));
    } catch {
        // ignore
    }
}

// Keyboard shortcut: Ctrl+B or Cmd+B to toggle sidebar
function handleKeyDown(e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) {
        e.preventDefault();
        toggleSidebar();
    }
}

// Pop-up konfirmasi sebelum keluar dari akun
const logoutDialogOpen = ref(false);
const loggingOut = ref(false);

function openLogoutDialog() {
    menuOpen.value = false;
    logoutDialogOpen.value = true;
}

function confirmLogout() {
    router.post('/logout', {}, {
        onStart: () => (loggingOut.value = true),
        onFinish: () => {
            loggingOut.value = false;
            logoutDialogOpen.value = false;
        },
    });
}

const authUser = computed(() => page.props.auth?.user || null);
const authStudent = computed(() => page.props.auth?.student || null);

const displayName = computed(() => authUser.value?.name || 'Pelajar');
const userRole = computed(() => authUser.value?.role || 'student');
const userInitials = computed(() => authUser.value?.initials || displayName.value.charAt(0).toUpperCase() || 'P');
const totalXp = computed(() => authStudent.value?.total_xp ?? 0);
const currentStreak = computed(() => authStudent.value?.current_streak ?? 0);
const level = computed(() => authStudent.value?.level || { name: 'Beginner', color: '#94a3b8', pct: 0, next_xp: 300 });

// System choice (SIBI or BISINDO)
const currentSystem = ref(localStorage.getItem('signtech_system') || 'SIBI');
function changeSystem(e) {
    currentSystem.value = e.target.value;
    localStorage.setItem('signtech_system', e.target.value);
    window.dispatchEvent(new CustomEvent('signtech:system-changed', { detail: e.target.value }));
}

const syncSystem = (e) => (currentSystem.value = e.detail === 'BISINDO' ? 'BISINDO' : 'SIBI');
window.addEventListener('signtech:system-changed', syncSystem);

// Responsive compact mode check
const compactQuery = window.matchMedia('(max-width: 1400px)');
const isCompact = ref(compactQuery.matches);
const updateCompact = (e) => (isCompact.value = e.matches);
compactQuery.addEventListener('change', updateCompact);

onBeforeUnmount(() => {
    compactQuery.removeEventListener('change', updateCompact);
    window.removeEventListener('signtech:system-changed', syncSystem);
    window.removeEventListener('keydown', handleKeyDown);
});

const isTeacher = computed(() => userRole.value === 'teacher');
const hasCustomRightPanel = computed(() => !!slots['right-panel']);

const teacherNav = [
    { to: '/teacher/dashboard', label: 'Dashboard Guru', icon: LayoutDashboard },
    { to: '/teacher/classes', label: 'Manajemen Kelas', icon: School },
    { to: '/teacher/students', label: 'Manajemen Siswa', icon: Users },
    { to: '/teacher/modules', label: 'Manajemen Modul', icon: BookOpen },
    { to: '/teacher/assignments', label: 'Tugas Siswa', icon: ClipboardList },
    { to: '/teacher/reports', label: 'Laporan', icon: FileBarChart },
];

const nav = computed(() => {
    const list = isTeacher.value
        ? [...teacherNav]
        : [
              { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
              { to: '/learn', label: 'Materi Belajar', icon: BookOpen },
              { to: '/practice', label: 'Latihan Mandiri', icon: Hand },
              { to: '/challenge', label: 'Challenge Harian', icon: Target },
              { to: '/student/assignments', label: 'Tugas Guru', icon: CalendarCheck },
              { to: '/achievement', label: 'Achievement', icon: Trophy },
              { to: '/progress', label: 'Progress Belajar', icon: TrendingUp },
          ];
    list.push({ to: '/profile', label: 'Profil Saya', icon: UserRound });
    return list;
});

const mobileNav = computed(() =>
    isTeacher.value
        ? [
              { to: '/teacher/dashboard', label: 'Beranda', icon: LayoutDashboard },
              { to: '/teacher/classes', label: 'Kelas', icon: School },
              { to: '/teacher/students', label: 'Siswa', icon: Users },
              { to: '/teacher/assignments', label: 'Tugas', icon: ClipboardList },
              { to: '/profile', label: 'Profil', icon: UserRound },
          ]
        : [
              { to: '/dashboard', label: 'Beranda', icon: LayoutDashboard },
              { to: '/learn', label: 'Materi', icon: BookOpen },
              { to: '/practice', label: 'Latihan', icon: Hand },
              { to: '/student/assignments', label: 'Tugas', icon: CalendarCheck },
              { to: '/profile', label: 'Profil', icon: UserRound },
          ],
);

const linkAliases = {
    '/dashboard': ['/', '/student/dashboard', '/teacher/dashboard'],
    '/learn': ['/lesson/', '/student/materials'],
    '/practice': ['/student/practice'],
    '/challenge': ['/student/challenge'],
    '/student/assignments': ['/student/assignments'],
    '/achievement': ['/student/achievements'],
    '/progress': ['/student/progress'],
    '/teacher/dashboard': ['/teacher/dashboard'],
    '/teacher/classes': ['/teacher/classes', '/classroom'],
    '/teacher/students': ['/teacher/students'],
    '/teacher/modules': ['/teacher/modules'],
    '/teacher/assignments': ['/teacher/assignments'],
    '/teacher/reports': ['/teacher/reports'],
};

function activeLink(to) {
    const path = page.url.split('?')[0];
    if (path === to) return true;
    return (linkAliases[to] || []).some((alias) => (alias === '/' ? path === '/' : path === alias || path.startsWith(alias.endsWith('/') ? alias : `${alias}/`)));
}

const pageTitle = computed(() => {
    const match = nav.value.find((item) => activeLink(item.to));
    if (match) return match.label;
    if (page.url.startsWith('/lesson')) return 'Pelajaran Isyarat';
    if (page.url.startsWith('/teacher/classes/')) return 'Detail Kelas';
    return isTeacher.value ? 'Dashboard Guru' : 'Dashboard Siswa';
});

const streakWeek = computed(() => {
    const names = ['M', 'S', 'S', 'R', 'K', 'J', 'S'];
    const activeDays = Math.min(7, currentStreak.value);
    return Array.from({ length: 7 }, (_, index) => {
        const d = new Date();
        d.setDate(d.getDate() - (6 - index));
        return { label: names[d.getDay()], done: index >= 7 - activeDays, isToday: index === 6 };
    });
});
</script>

<template>
    <div class="app-layout flex min-h-screen w-full bg-[#f6faf8] font-sans text-ink">
        <!-- Backdrop Scrim for Mobile Drawer -->
        <div
            v-if="menuOpen"
            class="fixed inset-0 bg-[#14221d]/40 backdrop-blur-xs z-40 lg:hidden transition-opacity"
            @click="menuOpen = false"
        ></div>

        <!-- Sidebar (shadcn Collapsible Architecture) -->
        <aside
            class="sidebar-shell fixed lg:sticky top-0 left-0 h-screen z-50 lg:z-30 flex flex-col border-r border-[#e5ebe7] bg-white transition-all duration-300 ease-in-out shrink-0 select-none overflow-y-auto overflow-x-hidden"
            :class="[
                isSidebarCollapsed ? 'w-[72px] px-2.5 py-4' : 'w-64 px-4 py-4',
                menuOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0',
            ]"
        >
            <!-- Sidebar Header / Brand -->
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-[#f0f4f1]">
                <!-- Expanded Brand Logo -->
                <Link
                    v-if="!isSidebarCollapsed"
                    href="/dashboard"
                    class="flex items-center gap-2.5 min-w-0"
                    @click="menuOpen = false"
                >
                    <div class="w-9 h-9 rounded-xl bg-[#dff2e9] flex items-center justify-center shrink-0 shadow-xs">
                        <img :src="'/LOGO.png'" alt="SignTech" class="w-6 h-6 object-contain" />
                    </div>
                    <div class="min-w-0">
                        <div class="font-display font-extrabold text-[17px] text-[#14221d] leading-none tracking-tight">
                            Sign<span class="text-[#34B182]">Tech</span>
                        </div>
                        <small class="text-[10px] text-[#8ea299] font-medium block truncate mt-0.5">
                            Belajar Isyarat Interaktif
                        </small>
                    </div>
                </Link>

                <!-- Collapsed Brand Logo (Center Icon Only) -->
                <Link
                    v-else
                    href="/dashboard"
                    class="mx-auto flex items-center justify-center py-1"
                    title="SignTech — Belajar Bahasa Isyarat Interaktif"
                    @click="menuOpen = false"
                >
                    <div class="w-10 h-10 rounded-xl bg-[#dff2e9] flex items-center justify-center shadow-xs hover:scale-105 transition-transform">
                        <img :src="'/LOGO.png'" alt="SignTech" class="w-6 h-6 object-contain" />
                    </div>
                </Link>

                <!-- Mobile Close Button -->
                <button
                    v-if="menuOpen"
                    class="lg:hidden p-1.5 rounded-lg text-[#667e76] hover:bg-[#f0f5f2]"
                    aria-label="Tutup menu"
                    @click="menuOpen = false"
                >
                    <X :size="18" />
                </button>
            </div>

            <!-- Nav Section Label -->
            <div
                v-if="!isSidebarCollapsed"
                class="px-2 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-[#91a39c]"
            >
                {{ isTeacher ? 'Ruang Guru' : 'Menu Utama' }}
            </div>

            <!-- Navigation Links -->
            <nav class="flex flex-col gap-1 flex-1">
                <Link
                    v-for="item in nav"
                    :key="item.to"
                    :href="item.to"
                    class="nav-link rounded-xl font-semibold text-xs transition-all relative group flex items-center"
                    :class="[
                        isSidebarCollapsed
                            ? 'w-11 h-11 mx-auto justify-center'
                            : 'px-3.5 py-2.5 gap-3',
                        activeLink(item.to)
                            ? 'bg-[#e8f5ef] text-[#167b71] font-bold shadow-xs'
                            : 'text-[#617772] hover:bg-[#f3f8f5] hover:text-[#167b71]',
                    ]"
                    :title="isSidebarCollapsed ? item.label : undefined"
                    @click="menuOpen = false"
                >
                    <component :is="item.icon" :size="18" class="shrink-0" />
                    <span v-if="!isSidebarCollapsed" class="truncate">{{ item.label }}</span>
                    <!-- Active Dot Indicator -->
                    <span
                        v-if="activeLink(item.to)"
                        class="w-1.5 h-1.5 rounded-full bg-[#34B182] shrink-0"
                        :class="[isSidebarCollapsed ? 'absolute right-1 top-1' : 'ml-auto']"
                    ></span>
                </Link>
            </nav>

            <!-- Sidebar Bottom: Mascot CTA Card & User Profile -->
            <div class="sidebar-bottom-section mt-auto pt-2 border-t border-[#f0f4f1]">
                <!-- Playful Mascot CTA Card -->
                <SidebarMascotCard :is-teacher="isTeacher" :collapsed="isSidebarCollapsed" />

                <!-- User Profile Container -->
                <div v-if="authUser" class="mt-2">
                    <!-- Expanded User Profile -->
                    <div
                        v-if="!isSidebarCollapsed"
                        class="flex items-center gap-2.5 p-2 rounded-2xl bg-[#fafcfb] border border-[#e8efe9]"
                    >
                        <Link href="/profile" class="flex items-center gap-2.5 min-w-0 flex-1" @click="menuOpen = false">
                            <div class="w-8 h-8 rounded-full bg-[#ffdfcb] text-[#ad6544] font-extrabold text-xs flex items-center justify-center shrink-0">
                                {{ userInitials }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <b class="block text-xs font-bold text-[#14221d] truncate" :title="displayName">
                                    {{ displayName }}
                                </b>
                                <small class="block text-[10px] text-[#869b93] truncate" :title="authUser.email">
                                    {{ authUser.email }}
                                </small>
                            </div>
                        </Link>
                        <button
                            type="button"
                            class="p-1.5 rounded-lg text-[#889d95] hover:text-[#d9534f] hover:bg-[#fef1f0] transition-colors"
                            title="Keluar dari Akun"
                            @click="openLogoutDialog"
                        >
                            <LogOut :size="15" />
                        </button>
                    </div>

                    <!-- Collapsed User Profile (Icon Rail) -->
                    <div v-else class="flex flex-col items-center gap-2 py-1">
                        <Link
                            href="/profile"
                            class="w-10 h-10 rounded-full bg-[#ffdfcb] text-[#ad6544] font-extrabold text-xs flex items-center justify-center hover:ring-2 hover:ring-[#34B182] transition-all"
                            :title="`Profil: ${displayName}`"
                            @click="menuOpen = false"
                        >
                            {{ userInitials }}
                        </Link>
                        <button
                            type="button"
                            class="w-8 h-8 rounded-lg flex items-center justify-center text-[#889d95] hover:text-[#d9534f] hover:bg-[#fef1f0] transition-colors"
                            title="Keluar dari Akun"
                            @click="openLogoutDialog"
                        >
                            <LogOut :size="14" />
                        </button>
                    </div>
                </div>

                <!-- Guest Login Box -->
                <div v-else class="mt-2">
                    <Link
                        href="/login"
                        class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-[#34B182] text-white font-bold text-xs shadow-xs hover:bg-[#289a6f] transition-colors"
                        :title="isSidebarCollapsed ? 'Masuk ke Akun' : undefined"
                    >
                        <span v-if="!isSidebarCollapsed">Masuk</span>
                        <ArrowRight :size="14" />
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main Workspace Area -->
        <div class="flex-1 flex flex-col min-w-0 min-h-screen bg-[#f6faf8]">
            <!-- shadcn Header / Topbar -->
            <header
                class="shadcn-topbar sticky top-0 z-20 flex h-16 w-full items-center justify-between border-b border-[#e5ebe7] bg-white/95 backdrop-blur-md px-4 sm:px-6"
            >
                <!-- Left: Sidebar Toggle + Breadcrumb -->
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <!-- Mobile Hamburger -->
                    <button
                        type="button"
                        class="lg:hidden p-2 rounded-xl text-[#556e66] hover:bg-[#f0f5f2] border border-[#e5ece7] shadow-xs"
                        aria-label="Buka navigasi"
                        @click="menuOpen = true"
                    >
                        <Menu :size="18" />
                    </button>

                    <!-- Desktop Sidebar Collapse Trigger (shadcn PanelLeft style) -->
                    <button
                        type="button"
                        class="hidden lg:flex w-9 h-9 rounded-xl items-center justify-center text-[#556e66] hover:text-[#14221d] hover:bg-[#f0f5f2] border border-[#e5ece7] shadow-xs transition-all"
                        :title="isSidebarCollapsed ? 'Buka Sidebar (Ctrl+B)' : 'Tutup Sidebar (Ctrl+B)'"
                        aria-label="Toggle sidebar"
                        @click="toggleSidebar"
                    >
                        <PanelLeft :size="17" class="transition-transform duration-200" :class="{ 'rotate-180': isSidebarCollapsed }" />
                    </button>

                    <!-- Vertical divider -->
                    <div class="h-4 w-px bg-[#dce6e0] hidden sm:block"></div>

                    <!-- Breadcrumbs -->
                    <div class="hidden sm:flex items-center gap-2 text-xs font-semibold text-[#6f857e]">
                        <Link href="/" class="hover:text-[#167b71] transition-colors flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-[#34B182]"></span>
                            <span>SignTech</span>
                        </Link>
                        <span class="text-[#a8b8b2]">/</span>
                        <span class="text-[#14221d] font-bold">{{ pageTitle }}</span>
                    </div>
                </div>

                <!-- Right: Stats & Switchers -->
                <div class="flex items-center gap-2 sm:gap-2.5">
                    <template v-if="isTeacher">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-[#edf8f2] text-[#1b735a] border border-[#d7efe3] shadow-xs">
                            <GraduationCap :size="15" class="text-primary" />
                            <span>Mode Guru</span>
                        </span>
                    </template>
                    <template v-else>
                        <!-- System Switcher -->
                        <label class="system-select">
                            <Hand :size="15" class="text-primary" />
                            <select
                                aria-label="Pilih sistem bahasa isyarat"
                                :value="currentSystem"
                                @change="changeSystem"
                            >
                                <option value="SIBI">{{ isCompact ? 'SIBI' : 'SIBI (Sistem Isyarat Bahasa Indonesia)' }}</option>
                                <option value="BISINDO">{{ isCompact ? 'BISINDO' : 'BISINDO (Bahasa Isyarat Indonesia)' }}</option>
                            </select>
                        </label>

                        <SpeechToggle :compact="isCompact" />

                        <!-- Streak Pill -->
                        <span class="stat-pill" title="Hari berturut-turut berlatih">
                            <Flame class="flame text-secondary" :size="16" />
                            <b>{{ currentStreak }}</b>
                            <small class="hidden md:inline">hari</small>
                        </span>

                        <!-- XP Pill -->
                        <span class="stat-pill xp-pill" title="Total perolehan XP">
                            <Star class="text-secondary" :size="15" />
                            <b>{{ totalXp }}</b>
                            <small class="hidden md:inline">XP</small>
                        </span>

                        <!-- Level Pill -->
                        <span class="level-pill hidden sm:inline-flex" :title="`Level: ${level.name}`">
                            <Award :size="15" class="text-primary" />
                            <span>{{ level.name }}</span>
                        </span>
                    </template>
                </div>
            </header>

            <!-- Main Content Viewport (Fluid & responsive, zero whitespace bottlenecks) -->
            <main class="main-column flex-1 w-full min-w-0 p-4 sm:p-6 lg:p-7 xl:p-8">
                <div :class="{ 'page-slide-in': isPageEntering }" @animationend.self="isPageEntering = false">
                    <slot />
                </div>
            </main>
        </div>

        <!-- Pop-up konfirmasi keluar -->
        <ConfirmDialog
            :open="logoutDialogOpen"
            title="Keluar dari akun?"
            description="Progres belajarmu tetap tersimpan. Kamu bisa masuk lagi kapan saja."
            cancel-label="Batal"
            confirm-label="Ya, Keluar"
            tone="danger"
            :processing="loggingOut"
            @cancel="logoutDialogOpen = false"
            @confirm="confirmLogout"
        >
            <template #icon><LogOut :size="26" /></template>
        </ConfirmDialog>

        <!-- Optional Companion Right Panel Slot (for pages explicitly requesting it) -->
        <aside v-if="hasCustomRightPanel" class="right-panel hidden 2xl:block w-80 shrink-0 border-l border-[#e5ebe7] bg-white p-6 sticky top-16 h-[calc(100vh-64px)] overflow-y-auto">
            <slot name="right-panel" />
        </aside>

        <!-- Mobile Bottom Nav -->
        <nav class="mobile-bottom-nav lg:hidden" aria-label="Navigasi cepat mobile">
            <Link
                v-for="item in mobileNav"
                :key="item.to"
                :href="item.to"
                :class="{ active: activeLink(item.to) }"
                :aria-label="item.label"
            >
                <component :is="item.icon" :size="20" />
                <span>{{ item.label }}</span>
            </Link>
        </nav>
    </div>
</template>

<style scoped>
.system-select {
    height: 36px;
    padding: 0 10px;
    border: 1px solid #e5ebe7;
    background: #fff;
    border-radius: 11px;
    display: flex;
    align-items: center;
    gap: 7px;
    font-weight: 700;
    font-size: 12px;
    color: #3e6661;
}
.system-select select {
    appearance: none;
    border: 0;
    background: transparent;
    color: inherit;
    font-weight: 700;
    outline: none;
    cursor: pointer;
}
.stat-pill, .level-pill {
    height: 35px;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 10px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #edf0ec;
    font-size: 12px;
}
.stat-pill small {
    color: #93a09c;
    font-size: 10px;
}
.flame {
    color: #f18d48;
    fill: #f7bb67;
}
.xp-pill svg {
    color: #e3ac32;
    fill: #f4cd6f;
}
.level-pill {
    background: #f2ecfb;
    border-color: #ece2f5;
    color: #8060a4;
    font-weight: 700;
}
</style>
