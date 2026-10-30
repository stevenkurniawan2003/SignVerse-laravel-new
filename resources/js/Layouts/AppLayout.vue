<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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
    School,
    Star,
    Target,
    TrendingUp,
    Trophy,
    UserRound,
    Users,
    X,
} from '@lucide/vue';

const page = usePage();

/*
 * Notifikasi flash dari server (session 'status' / 'error') ditampilkan
 * sebagai toast setelah setiap aksi, mis. "Kelas dibuat" atau alasan penolakan.
 */
const toast = ref(null);
let toastTimer = null;

// Partial reload (mis. polling) mempertahankan prop flash lama: tampilkan sekali saja
let lastFlash = null;

function showFlash(flash) {
    const message = flash?.error || flash?.status;
    if (!message || flash === lastFlash) return;
    lastFlash = flash;
    clearTimeout(toastTimer);
    toast.value = { type: flash.error ? 'error' : 'success', message };
    toastTimer = setTimeout(() => (toast.value = null), flash.error ? 8000 : 5000);
}

onMounted(() => showFlash(page.props.flash));
const removeFlashListener = router.on('success', (event) => showFlash(event.detail.page.props.flash));
onBeforeUnmount(() => {
    removeFlashListener();
    clearTimeout(toastTimer);
});
const menuOpen = ref(false);

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

// Pilihan sistem juga bisa diganti dari halaman lain (mis. AI Practice)
const syncSystem = (e) => (currentSystem.value = e.detail === 'BISINDO' ? 'BISINDO' : 'SIBI');
window.addEventListener('signtech:system-changed', syncSystem);

// Layar sempit: label sistem dipersingkat agar topbar tidak terpotong
const compactQuery = window.matchMedia('(max-width: 1400px)');
const isCompact = ref(compactQuery.matches);
const updateCompact = (e) => (isCompact.value = e.matches);
compactQuery.addEventListener('change', updateCompact);
onBeforeUnmount(() => {
    compactQuery.removeEventListener('change', updateCompact);
    window.removeEventListener('signtech:system-changed', syncSystem);
});

const isTeacher = computed(() => userRole.value === 'teacher');

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
              { to: '/practice', label: 'AI Practice', icon: Hand },
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
              { to: '/practice', label: 'Practice', icon: Hand },
              { to: '/student/assignments', label: 'Tugas', icon: CalendarCheck },
              { to: '/profile', label: 'Profil', icon: UserRound },
          ],
);

// Halaman yang sama juga tersedia di bawah prefix role (/student/*, /teacher/*)
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

// Tujuh hari terakhir (berakhir hari ini); hari yang termasuk streak aktif ditandai
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
    <div class="app-shell font-sans text-ink bg-surface min-h-screen" :class="{ 'is-teacher': isTeacher }">
        <!-- Mobile Trigger -->
        <button class="mobile-menu" aria-label="Buka navigasi" @click="menuOpen = true">
            <Menu :size="22" />
        </button>
        <button v-if="menuOpen" class="scrim" aria-label="Tutup navigasi" @click="menuOpen = false"></button>

        <!-- Sidebar Navigation -->
        <aside class="sidebar" :class="{ 'sidebar-open': menuOpen }">
            <Link href="/dashboard" class="brand" @click="menuOpen = false">
                <div class="brand-logo-container">
                    <img :src="'/LOGO.png'" alt="SignTech" class="brand-logo-img" />
                </div>
                <div>
                    <div class="brand-text">Sign<span class="brand-accent">Tech</span></div>
                    <small>Platform AI Bahasa Isyarat</small>
                </div>
                <button class="close-menu" aria-label="Tutup menu" @click.prevent="menuOpen = false">
                    <X :size="20" />
                </button>
            </Link>

            <div class="nav-label">Menu Utama</div>

            <nav>
                <Link
                    v-for="item in nav"
                    :key="item.to"
                    :href="item.to"
                    class="nav-link"
                    :class="{ active: activeLink(item.to) }"
                    @click="menuOpen = false"
                >
                    <component :is="item.icon" :size="18" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="sidebar-bottom">
                <div v-if="authUser" class="mini-user-container">
                    <Link href="/profile" class="mini-user" @click="menuOpen = false">
                        <div class="avatar">{{ userInitials }}</div>
                        <div class="min-w-0 flex-1">
                            <b :title="displayName">{{ displayName }}</b>
                            <small :title="authUser.email">{{ authUser.email }}</small>
                        </div>
                    </Link>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="logout-sidebar-btn"
                        title="Keluar dari Akun"
                    >
                        <LogOut :size="14" />
                        <span>Keluar</span>
                    </Link>
                </div>
                <div v-else class="guest-login-box">
                    <Link href="/login" class="login-sidebar-btn">
                        <span>Masuk ke Akun</span>
                        <ArrowRight :size="14" />
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main Workspace Column -->
        <main class="main-column">
            <!-- Topbar with System Switcher and Real Database Stats -->                <header class="top-stats">
                    <template v-if="isTeacher">
                        <span class="stat-pill">
                            <GraduationCap class="text-primary" :size="17" />
                            <b>Mode Guru</b>
                            <small>SignTech</small>
                        </span>
                    </template>
                    <template v-else>
                    <label class="system-select">
                    <Hand :size="16" class="text-primary" />
                    <select
                        aria-label="Pilih sistem bahasa isyarat"
                        :value="currentSystem"
                        @change="changeSystem"
                    >
                        <option value="SIBI">{{ isCompact ? 'SIBI' : 'SIBI (Sistem Isyarat Bahasa Indonesia)' }}</option>
                        <option value="BISINDO">{{ isCompact ? 'BISINDO' : 'BISINDO (Bahasa Isyarat Indonesia)' }}</option>
                    </select>
                </label>

                <span class="stat-pill">
                    <Flame class="flame text-secondary" :size="17" />
                    <b>{{ currentStreak }}</b>
                    <small>hari streak</small>
                </span>

                <span class="stat-pill xp-pill">
                    <Star class="text-secondary" :size="16" />
                    <b>{{ totalXp }}</b>
                    <small>XP</small>
                </span>

                <span class="level-pill">
                    <Award :size="16" class="text-primary" />
                    {{ level.name }}
                </span>
                    </template>
            </header>

            <slot />
        </main>

        <!-- Toast notifikasi aksi -->
        <Transition name="toast">
            <div v-if="toast" class="app-toast" :class="`app-toast--${toast.type}`" :role="toast.type === 'error' ? 'alert' : 'status'">
                <span>{{ toast.message }}</span>
                <button type="button" aria-label="Tutup notifikasi" @click="toast = null">&times;</button>
            </div>
        </Transition>

        <!-- Right Insights Sidebar Panel -->
        <aside v-if="!isTeacher" class="right-panel">
            <div class="panel-heading">
                <span>Aktivitas Belajar</span>
            </div>

            <!-- Streak Card -->
            <section class="side-card streak-card">
                <div class="side-card-top">
                    <span class="streak-icon">
                        <Flame :size="18" />
                    </span>
                    <span class="eyebrow">Konsistensi Harian</span>
                </div>
                <div class="streak-number">
                    {{ currentStreak }} <small>hari aktif</small>
                </div>
                <p>Pertahankan ritme harianmu untuk mempercepat penguasaan isyarat.</p>
                <div class="week-dots">
                    <span
                        v-for="(day, index) in streakWeek"
                        :key="index"
                        :class="{ 'day-done': day.done, 'day-today': day.isToday }"
                    >
                        {{ day.label }}
                    </span>
                </div>
            </section>

            <!-- Daily Challenge Quest Card -->
            <Link v-if="page.url !== '/challenge'" href="/challenge" class="side-card quest-card">
                <div class="side-card-top">
                    <span class="quest-icon">
                        <Target :size="18" />
                    </span>
                    <span class="eyebrow">Tantangan Harian</span>
                    <ArrowRight :size="14" class="muted-icon" />
                </div>
                <b>Selesaikan Misi Harian</b>
                <p>Latih gesture hari ini untuk mendapatkan bonus XP.</p>
                <small class="quest-reward">+20 XP bonus harian</small>
            </Link>

            <!-- Level Card -->
            <section class="side-card level-card">
                <div class="side-card-top">
                    <span class="level-icon">
                        <Award :size="18" />
                    </span>
                    <span class="eyebrow">Peringkat Level</span>
                </div>
                <div class="level-title">{{ level.name }}</div>
                <div class="level-xp">
                    {{ totalXp }} <span>XP Total</span>
                </div>
                <div class="progress-track">
                    <i
                        class="accent-progress"
                        :style="{ width: `${level.pct || 0}%` }"
                    ></i>
                </div>
                <small>
                    {{ level.next_xp ? `${Math.max(0, level.next_xp - totalXp)} XP lagi menuju level berikutnya` : 'Peringkat tertinggi tercapai!' }}
                </small>
            </section>
        </aside>

        <!-- Mobile Bottom Nav -->
        <nav class="mobile-bottom-nav" aria-label="Navigasi cepat mobile">
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
.brand {
    height: 64px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 4px;
    text-decoration: none;
    color: #14221d;
}

.brand-logo-container {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e2ede6;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(20, 34, 29, 0.06);
    flex-shrink: 0;
}

.brand-logo-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.brand-text {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 20px;
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.5px;
    color: #14221d;
}

.brand-accent {
    color: #34B182;
}

.brand small {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #859c90;
    margin-top: 2px;
}

.mini-user-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.logout-sidebar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #fdf2f2;
    border: 1px solid #fbd5d5;
    color: #d9534f;
    min-height: 40px;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.18s;
}

.logout-sidebar-btn:hover {
    background: #fce8e8;
}

.guest-login-box {
    padding-top: 8px;
}

.login-sidebar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    background: #34B182;
    color: #ffffff;
    min-height: 40px;
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.quest-card p {
    margin: 4px 0 0;
    font-size: 13px;
    line-height: 1.5;
    color: #6b8176;
}

.quest-reward {
    display: block;
    margin-top: 8px;
    font-size: 13px;
    font-weight: 700;
    color: #94711f;
}

.week-dots .day-today {
    box-shadow: 0 0 0 2px #34b182;
}

.accent-progress {
    background: #34B182 !important;
}

.streak-icon {
    background: #fdf9ef !important;
    color: #C29B40 !important;
}

.quest-icon {
    background: #eefaf4 !important;
    color: #34B182 !important;
}

.level-icon {
    background: #f1f9f3 !important;
    color: #5FAB6F !important;
}

.level-pill {
    background: #eefaf4 !important;
    border-color: #d5f3e5 !important;
    color: #1e7253 !important;
    font-weight: 700;
}

.flame {
    color: #C29B40 !important;
}

.xp-pill svg {
    color: #C29B40 !important;
}

.week-dots .day-done {
    background: #fdf9ef !important;
    color: #a58230 !important;
    border: 1px solid #f1deaf;
}

.nav-link.active {
    background: #eefaf4 !important;
    color: #1e7253 !important;
    font-weight: 700;
}

.app-toast {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 70;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    max-width: min(440px, calc(100vw - 32px));
    padding: 14px 16px;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.5;
    border-radius: 14px;
    box-shadow: 0 16px 40px rgba(20, 34, 29, 0.18);
}

.app-toast--success {
    color: #1e7253;
    background: #eefaf4;
    border: 1px solid #bfe8d4;
}

.app-toast--error {
    color: #b42318;
    background: #fef0ee;
    border: 1px solid #f7c7c0;
}

.app-toast button {
    flex-shrink: 0;
    font-size: 20px;
    line-height: 1;
    color: inherit;
    background: transparent;
    border: 0;
    cursor: pointer;
}

.toast-enter-active,
.toast-leave-active {
    transition: 0.25s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(12px);
}

@media (max-width: 760px) {
    .app-toast {
        right: 16px;
        bottom: 84px;
        left: 16px;
        max-width: none;
    }
}
</style>
