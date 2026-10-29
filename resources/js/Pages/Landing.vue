<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Camera,
    CheckCircle2,
    ChevronRight,
    Hand,
    HeartHandshake,
    Layers,
    Menu,
    ShieldCheck,
    Trophy,
    Users,
    X,
} from '@lucide/vue';

// Mobile menu toggle state
const mobileMenuOpen = ref(false);

// Interactive System Switcher (SIBI vs BISINDO)
const activeSystem = ref('SIBI');

// Interactive simulated AI demo in hero
const isAnalyzing = ref(false);
const demoSuccess = ref(true);
const demoScore = ref(98.4);

function testAiRecognition() {
    isAnalyzing.value = true;
    setTimeout(() => {
        isAnalyzing.value = false;
        demoScore.value = +(96 + Math.random() * 3.5).toFixed(1);
        demoSuccess.value = true;
    }, 800);
}

// Sample interactive gesture cards
const selectedGesture = ref(null);

const sampleGestures = {
    SIBI: [
        {
            code: 'A',
            title: 'Alfabet A',
            category: 'Alfabet SIBI',
            difficulty: 'Mudah',
            handCount: '1 Tangan',
            description: 'Kepalkan tangan dominan menghadap ke depan, posisi ibu jari tegak menempel di sisi luar jari telunjuk.',
            tips: 'Pastikan buku-buku jemari rapat dan pergelangan tangan tidak miring ke samping.',
        },
        {
            code: 'B',
            title: 'Alfabet B',
            category: 'Alfabet SIBI',
            difficulty: 'Mudah',
            handCount: '1 Tangan',
            description: 'Buka keempat jari lurus tegak ke atas, lipat ibu jari merapat di depan telapak tangan.',
            tips: 'Jari telunjuk, tengah, manis, dan kelingking harus sejajar tanpa celah.',
        },
        {
            code: 'C',
            title: 'Alfabet C',
            category: 'Alfabet SIBI',
            difficulty: 'Mudah',
            handCount: '1 Tangan',
            description: 'Lengkungkan seluruh jemari membentuk lengkungan huruf C menghadap ke samping luar.',
            tips: 'Bayangkan sedang memegang cangkir atau silinder kecil dengan rileks.',
        },
        {
            code: 'L',
            title: 'Alfabet L',
            category: 'Alfabet SIBI',
            difficulty: 'Mudah',
            handCount: '1 Tangan',
            description: 'Bentuk sudut 90 derajat antara ibu jari dan jari telunjuk. Tiga jari lainnya ditekuk rapat.',
            tips: 'Telapak tangan menghadap lurus ke depan menghadap lawan bicara.',
        },
        {
            code: 'Halo',
            title: 'Sapaan "Halo"',
            category: 'Frasa Sosial',
            difficulty: 'Mudah',
            handCount: '1 Tangan',
            description: 'Letakkan telapak tangan terbuka di samping pelipis, lalu ayunkan sedikit ke samping luar.',
            tips: 'Sertai dengan ekspresi ramah dan kontak mata natural.',
        },
        {
            code: 'Terima Kasih',
            title: 'Kata "Terima Kasih"',
            category: 'Frasa Sosial',
            difficulty: 'Sedang',
            handCount: '1 Tangan',
            description: 'Sentuhkan ujung jemari tangan dominan ke bibir atau dagu, lalu ayunkan ke arah lawan bicara.',
            tips: 'Sertakan sedikit anggukan santun untuk menambah kesopanan.',
        },
    ],
    BISINDO: [
        {
            code: 'A',
            title: 'Alfabet A (2 Tangan)',
            category: 'Alfabet BISINDO',
            difficulty: 'Mudah',
            handCount: '2 Tangan',
            description: 'Tangan non-dominan sebagai alas, ujung telunjuk tangan dominan bersentuhan membentuk garis visual.',
            tips: 'Interaksi dua tangan di bidang dada memudahkan pembacaan visual.',
        },
        {
            code: 'B',
            title: 'Alfabet B (2 Tangan)',
            category: 'Alfabet BISINDO',
            difficulty: 'Mudah',
            handCount: '2 Tangan',
            description: 'Tangan kiri membentuk tiang vertikal, tangan kanan membentuk dua lengkungan menyentuh tiang.',
            tips: 'Pertahankan orientasi tegak lurus di depan tubuh.',
        },
        {
            code: 'C',
            title: 'Alfabet C',
            category: 'Alfabet BISINDO',
            difficulty: 'Mudah',
            handCount: '1 Tangan',
            description: 'Bentuk lengkungan busur C besar di ruang netral depan dada secara proporsional.',
            tips: 'Ekspresi visual memperkuat ruang artikulasi isyarat.',
        },
        {
            code: 'Belajar',
            title: 'Kata "Belajar"',
            category: 'Kosakata',
            difficulty: 'Sedang',
            handCount: '2 Tangan',
            description: 'Tangan terbuka seolah mengambil informasi dari buku lalu diarahkan ke kening secara berulang.',
            tips: 'Melambangkan penyerapan ilmu pengetahuan ke dalam akal dan pikiran.',
        },
        {
            code: 'Sama-Sama',
            title: 'Frasa "Sama-Sama"',
            category: 'Frasa Sosial',
            difficulty: 'Mudah',
            handCount: '2 Tangan',
            description: 'Kedua tangan terbuka telapak menghadap ke atas, digerakkan bersilang lembut ke depan.',
            tips: 'Sikap tubuh hangat dan senyum sangat mendukung keakraban.',
        },
        {
            code: 'Semangat',
            title: 'Ekspresi "Semangat"',
            category: 'Frasa Ekspresif',
            difficulty: 'Mudah',
            handCount: '2 Tangan',
            description: 'Kedua tangan mengepal di samping dada lalu diayunkan tegas serempak ke depan.',
            tips: 'Gerakan tegas dipadukan dengan ekspresi wajah yang penuh tekad.',
        },
    ],
};

function openGestureModal(gesture) {
    selectedGesture.value = gesture;
}

function closeGestureModal() {
    selectedGesture.value = null;
}
</script>

<template>
    <Head title="Platform AI Belajar Bahasa Isyarat Indonesia" />

    <div class="landing-page min-h-screen bg-surface font-sans text-ink">
        <!-- Sticky Public Navbar -->
        <header class="sticky top-0 z-40 border-b border-surface-border/80 bg-white/90 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <!-- Brand Logo & Name -->
                <Link href="/" class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white p-1.5 shadow-sm ring-1 ring-surface-border">
                        <img :src="'/LOGO.png'" alt="SignTech Logo" class="h-full w-full object-contain" />
                    </div>
                    <div>
                        <span class="text-xl font-extrabold tracking-tight text-ink font-sans">
                            Sign<span class="text-primary">Tech</span>
                        </span>
                        <div class="hidden text-[10px] font-semibold text-ink-muted sm:block">
                            Platform AI Bahasa Isyarat
                        </div>
                    </div>
                </Link>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-7 text-sm font-semibold text-ink-muted">
                    <a href="#fitur" class="transition hover:text-primary">Fitur Unggulan</a>
                    <a href="#sistem" class="transition hover:text-primary">Sistem SIBI & BISINDO</a>
                    <a href="#cara-kerja" class="transition hover:text-primary">Cara Belajar</a>
                    <Link href="/practice" class="flex items-center gap-1.5 transition hover:text-primary">
                        <Camera :size="15" class="text-primary" />
                        <span>AI Practice</span>
                    </Link>
                    <Link href="/classroom" class="flex items-center gap-1.5 transition hover:text-primary">
                        <Users :size="15" class="text-accent" />
                        <span>Ruang Guru</span>
                    </Link>
                </nav>

                <!-- Right Action Buttons -->
                <div class="hidden sm:flex items-center gap-3">
                    <Link href="/dashboard" class="btn-secondary text-xs sm:text-sm px-4">
                        Masuk Dashboard
                    </Link>
                    <Link href="/practice" class="btn-primary text-xs sm:text-sm px-5">
                        <span>Mulai Belajar</span>
                        <ArrowRight :size="15" />
                    </Link>
                </div>

                <!-- Mobile Menu Button -->
                <button
                    class="md:hidden flex h-10 w-10 items-center justify-center rounded-xl border border-surface-border bg-white text-ink shadow-sm"
                    aria-label="Buka navigasi"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <component :is="mobileMenuOpen ? X : Menu" :size="20" />
                </button>
            </div>

            <!-- Mobile Dropdown -->
            <div v-if="mobileMenuOpen" class="md:hidden border-b border-surface-border bg-white px-4 pt-3 pb-6 shadow-xl">
                <div class="flex flex-col gap-2">
                    <a href="#fitur" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-ink hover:bg-surface" @click="mobileMenuOpen = false">
                        Fitur Unggulan
                    </a>
                    <a href="#sistem" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-ink hover:bg-surface" @click="mobileMenuOpen = false">
                        Sistem SIBI & BISINDO
                    </a>
                    <a href="#cara-kerja" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-ink hover:bg-surface" @click="mobileMenuOpen = false">
                        Cara Belajar
                    </a>
                    <Link href="/practice" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-ink hover:bg-surface" @click="mobileMenuOpen = false">
                        <Camera :size="18" class="text-primary" />
                        <span>AI Practice Studio</span>
                    </Link>
                    <Link href="/classroom" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-ink hover:bg-surface" @click="mobileMenuOpen = false">
                        <Users :size="18" class="text-accent" />
                        <span>Ruang Kelas Guru</span>
                    </Link>
                    <div class="mt-4 pt-4 border-t border-surface-border flex flex-col gap-2.5">
                        <Link href="/dashboard" class="btn-secondary w-full text-center" @click="mobileMenuOpen = false">
                            Masuk Dashboard
                        </Link>
                        <Link href="/practice" class="btn-primary w-full text-center" @click="mobileMenuOpen = false">
                            <span>Mulai Belajar Sekarang</span>
                            <ArrowRight :size="16" />
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative overflow-hidden pt-12 pb-20 md:pt-20 md:pb-28">
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto">
                    <!-- Clean Label -->
                    <div class="inline-flex items-center gap-2 rounded-full border border-surface-border bg-white px-4 py-1 text-xs font-semibold text-ink-muted shadow-sm">
                        <span>Pembelajaran Isyarat Interaktif Berbasis AI</span>
                    </div>

                    <!-- Main Headline -->
                    <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-ink sm:text-5xl lg:text-6xl leading-[1.12]">
                        Belajar Bahasa Isyarat Indonesia dengan
                        <span class="block mt-1 text-primary">bantuan kecerdasan buatan</span>
                    </h1>

                    <!-- Subtitle -->
                    <p class="mx-auto mt-6 max-w-2xl text-base text-ink-muted sm:text-lg leading-relaxed">
                        Latihan langsung di depan kamera, dapatkan koreksi otomatis seketika, bangun kebiasaan harian dengan gamifikasi terarah, dan pantau perkembangan belajar secara terukur.
                    </p>

                    <!-- Call To Action -->
                    <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Link href="/dashboard" class="btn-primary w-full px-8 py-3 text-base sm:w-auto">
                            <span>Mulai Belajar Gratis</span>
                            <ArrowRight :size="17" />
                        </Link>
                        <Link href="/practice" class="btn-secondary w-full px-7 py-3 text-base sm:w-auto">
                            <Camera :size="17" class="text-primary" />
                            <span>Buka AI Practice</span>
                        </Link>
                    </div>

                    <!-- Trust indicators -->
                    <div class="mt-7 flex flex-wrap items-center justify-center gap-6 text-xs font-semibold text-ink-muted">
                        <span class="flex items-center gap-1.5">
                            <CheckCircle2 :size="15" class="text-primary" />
                            <span>Kamera Web Langsung Tanpa Alat Tambahan</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <CheckCircle2 :size="15" class="text-accent" />
                            <span>Mendukung SIBI & BISINDO Lengkap</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <CheckCircle2 :size="15" class="text-secondary" />
                            <span>Akses Pendidikan Inklusif</span>
                        </span>
                    </div>
                </div>

                <!-- Product Preview Studio Box -->
                <div class="mt-14 max-w-3xl mx-auto rounded-2xl border border-surface-border bg-white p-4 sm:p-5 shadow-lg">
                    <div class="rounded-xl border border-surface-border bg-ink text-white overflow-hidden">
                        <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 bg-white/5 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-primary"></span>
                                <span class="font-semibold text-white/80">SignTech AI Studio · Deteksi 21 Landmark Tangan</span>
                            </div>
                            <span class="text-white/60 text-[11px]">30 FPS</span>
                        </div>

                        <div class="relative min-h-[260px] flex flex-col items-center justify-center p-6 bg-[#14221d]">
                            <div class="text-center">
                                <div class="inline-flex h-20 w-20 items-center justify-center rounded-full bg-primary/15 border border-primary/30 text-primary mb-3">
                                    <Hand :size="44" />
                                </div>
                                <div class="text-sm font-bold text-white">Target Isyarat: SIBI Huruf B</div>
                                <div class="text-xs text-white/70 mt-1">Empat jari tegak rapat, ibu jari mengunci di depan telapak</div>
                                <div class="mt-3 inline-block rounded-lg bg-black/40 px-3 py-1 text-xs font-bold text-accent">
                                    Tingkat Akurasi: {{ demoScore }}%
                                </div>
                            </div>

                            <div class="mt-5 flex items-center gap-3">
                                <button
                                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-dark transition disabled:opacity-50"
                                    :disabled="isAnalyzing"
                                    @click="testAiRecognition"
                                >
                                    <Activity :size="14" :class="{ 'animate-spin': isAnalyzing }" />
                                    <span>{{ isAnalyzing ? 'Memeriksa...' : 'Uji Deteksi AI' }}</span>
                                </button>
                                <Link
                                    href="/practice"
                                    class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-xs font-bold text-white hover:bg-white/20 transition"
                                >
                                    <Camera :size="14" />
                                    <span>Buka Kamera Langsung</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4 Metrics -->
                <div class="mx-auto mt-12 grid max-w-4xl grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
                    <div class="rounded-xl border border-surface-border bg-white p-4 text-center">
                        <div class="text-2xl font-extrabold text-ink">2</div>
                        <div class="text-xs font-bold text-ink-muted mt-1">Sistem Isyarat</div>
                        <div class="text-[11px] text-ink-muted/80 mt-0.5">SIBI & BISINDO</div>
                    </div>
                    <div class="rounded-xl border border-surface-border bg-white p-4 text-center">
                        <div class="text-2xl font-extrabold text-primary">A–Z</div>
                        <div class="text-xs font-bold text-ink-muted mt-1">Alfabet & Angka</div>
                        <div class="text-[11px] text-ink-muted/80 mt-0.5">Deteksi Kamera Cepat</div>
                    </div>
                    <div class="rounded-xl border border-surface-border bg-white p-4 text-center">
                        <div class="text-2xl font-extrabold text-secondary">100%</div>
                        <div class="text-xs font-bold text-ink-muted mt-1">Akses Terbuka</div>
                        <div class="text-[11px] text-ink-muted/80 mt-0.5">Mendukung Sekolah Inklusif</div>
                    </div>
                    <div class="rounded-xl border border-surface-border bg-white p-4 text-center">
                        <div class="text-2xl font-extrabold text-accent">&lt; 50ms</div>
                        <div class="text-xs font-bold text-ink-muted mt-1">Latensi Inferensi</div>
                        <div class="text-[11px] text-ink-muted/80 mt-0.5">Umpan Balik Instan</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature Pillars -->
        <section id="fitur" class="border-y border-surface-border bg-white py-18">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <div class="text-xs font-bold uppercase tracking-wider text-primary">Fitur Unggulan</div>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">
                        Semua yang kamu butuhkan untuk fasih berbahasa isyarat
                    </h2>
                    <p class="mt-3 text-base text-ink-muted">
                        Dirancang secara khusus dengan pendekatan pedagogis inklusif, teknologi computer vision, dan gamifikasi terarah.
                    </p>
                </div>

                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div class="card card-hover flex flex-col justify-between border-t-4 border-t-primary">
                        <div>
                            <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary-50 text-primary">
                                <Hand :size="24" />
                            </div>
                            <h3 class="mt-5 text-lg font-extrabold text-ink">AI Practice Studio</h3>
                            <p class="mt-2 text-sm leading-relaxed text-ink-muted">
                                Latihan isyarat interaktif menggunakan kamera. Model AI mengevaluasi orientasi telapak tangan dan formasi jemari secara langsung.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-surface-border">
                            <Link href="/practice" class="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary-dark">
                                <span>Buka AI Practice</span>
                                <ArrowRight :size="13" />
                            </Link>
                        </div>
                    </div>

                    <div class="card card-hover flex flex-col justify-between border-t-4 border-t-secondary">
                        <div>
                            <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-secondary-50 text-secondary-dark">
                                <Trophy :size="24" />
                            </div>
                            <h3 class="mt-5 text-lg font-extrabold text-ink">Gamifikasi & Habit Harian</h3>
                            <p class="mt-2 text-sm leading-relaxed text-ink-muted">
                                Bangun konsistensi belajar melalui streak harian, poin XP, peringkat level dari Pemula hingga Maestro, dan tantangan misi harian.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-surface-border">
                            <Link href="/challenge" class="inline-flex items-center gap-1.5 text-xs font-bold text-secondary-dark hover:text-secondary">
                                <span>Lihat Tantangan</span>
                                <ArrowRight :size="13" />
                            </Link>
                        </div>
                    </div>

                    <div class="card card-hover flex flex-col justify-between border-t-4 border-t-accent">
                        <div>
                            <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-accent-50 text-accent-dark">
                                <Users :size="24" />
                            </div>
                            <h3 class="mt-5 text-lg font-extrabold text-ink">Panel Monitoring Guru</h3>
                            <p class="mt-2 text-sm leading-relaxed text-ink-muted">
                                Pantau keaktifan siswa kelas, bagikan materi penugasan terstruktur, dan analisis bentuk gesture yang sering keliru untuk bimbingan terarah.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-surface-border">
                            <Link href="/classroom" class="inline-flex items-center gap-1.5 text-xs font-bold text-accent-dark hover:text-accent">
                                <span>Portal Guru</span>
                                <ArrowRight :size="13" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SIBI vs BISINDO Explorer -->
        <section id="sistem" class="py-18 bg-surface">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <div class="text-xs font-bold uppercase tracking-wider text-primary">Ragam Bahasa Isyarat</div>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">
                        Eksplorasi SIBI dan BISINDO
                    </h2>
                    <p class="mt-3 text-base text-ink-muted">
                        Pilih sistem isyarat di bawah dan klik kartu untuk melihat panduan gerakannya.
                    </p>

                    <div class="mt-6 inline-flex items-center rounded-xl bg-white p-1 border border-surface-border shadow-sm">
                        <button
                            class="flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold transition"
                            :class="activeSystem === 'SIBI' ? 'bg-primary text-white shadow-sm' : 'text-ink-muted hover:text-ink'"
                            @click="activeSystem = 'SIBI'"
                        >
                            <Hand :size="15" />
                            <span>SIBI (1 Tangan)</span>
                        </button>
                        <button
                            class="flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold transition"
                            :class="activeSystem === 'BISINDO' ? 'bg-secondary text-white shadow-sm' : 'text-ink-muted hover:text-ink'"
                            @click="activeSystem = 'BISINDO'"
                        >
                            <HeartHandshake :size="15" />
                            <span>BISINDO (2 Tangan)</span>
                        </button>
                    </div>
                </div>

                <!-- Cards Grid -->
                <div class="mt-8 mx-auto max-w-4xl grid gap-4 grid-cols-2 sm:grid-cols-3">
                    <div
                        v-for="gesture in sampleGestures[activeSystem]"
                        :key="gesture.code"
                        class="card card-hover p-4 cursor-pointer text-left group"
                        @click="openGestureModal(gesture)"
                    >
                        <div class="flex items-start justify-between">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl font-bold text-lg"
                                  :class="activeSystem === 'SIBI' ? 'bg-primary-50 text-primary' : 'bg-secondary-50 text-secondary-dark'">
                                {{ gesture.code.length <= 2 ? gesture.code : gesture.code.charAt(0) }}
                            </span>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border border-surface-border text-ink-muted">
                                {{ gesture.difficulty }}
                            </span>
                        </div>
                        <h4 class="mt-3 font-bold text-ink text-sm group-hover:text-primary transition">
                            {{ gesture.title }}
                        </h4>
                        <p class="text-[11px] text-ink-muted line-clamp-2 mt-1">
                            {{ gesture.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Public Footer -->
        <footer class="border-t border-surface-border bg-white pt-12 pb-10 text-sm text-ink-muted">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white p-1 ring-1 ring-surface-border">
                            <img :src="'/LOGO.png'" alt="SignTech" class="h-full w-full object-contain" />
                        </div>
                        <span class="text-lg font-extrabold text-ink">Sign<span class="text-primary">Tech</span></span>
                    </div>
                    <div class="flex items-center gap-6 text-xs font-semibold">
                        <Link href="/dashboard" class="hover:text-primary transition">Dashboard</Link>
                        <Link href="/learn" class="hover:text-primary transition">Materi Belajar</Link>
                        <Link href="/practice" class="hover:text-primary transition">AI Practice</Link>
                        <Link href="/classroom" class="hover:text-primary transition">Ruang Guru</Link>
                    </div>
                </div>
                <div class="mt-8 pt-6 border-t border-surface-border text-center text-xs text-ink-muted">
                    SignTech © 2026 — Platform AI Pembelajaran Bahasa Isyarat Indonesia · SIBI & BISINDO. All rights reserved.
                </div>
            </div>
        </footer>

        <!-- Modal -->
        <div v-if="selectedGesture" class="modal-backdrop" @click.self="closeGestureModal">
            <div class="lesson-modal max-w-md w-full bg-white rounded-2xl p-6 text-left shadow-2xl relative">
                <button class="modal-close" aria-label="Tutup" @click="closeGestureModal">
                    <X :size="18" />
                </button>
                <div class="text-xs font-bold text-primary mb-1">{{ activeSystem }} · {{ selectedGesture.category }}</div>
                <h3 class="text-xl font-extrabold text-ink">{{ selectedGesture.title }}</h3>
                <div class="rounded-xl bg-surface p-4 border border-surface-border text-xs leading-relaxed text-ink my-4">
                    <b>Cara Membentuk Isyarat:</b>
                    <p class="mt-1">{{ selectedGesture.description }}</p>
                </div>
                <div class="rounded-xl bg-secondary-50 p-3 border border-secondary-100 text-xs text-secondary-dark my-4">
                    <b>Tips Latihan:</b>
                    <p class="mt-1">{{ selectedGesture.tips }}</p>
                </div>
                <div class="flex items-center gap-3 mt-4">
                    <Link href="/practice" class="btn-primary flex-1 text-xs" @click="closeGestureModal">
                        Praktikkan di AI Studio
                    </Link>
                    <button class="btn-secondary px-4 text-xs" @click="closeGestureModal">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

