<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Award,
    Camera,
    CheckCircle2,
    ChevronRight,
    Clock,
    Eye,
    Hand,
    HeartHandshake,
    Layers,
    Menu,
    ShieldCheck,
    Sparkles,
    Star,
    Timer,
    Trophy,
    UserCheck,
    Users,
    X,
} from '@lucide/vue';

// Mobile menu toggle state
const mobileMenuOpen = ref(false);

// Interactive System Switcher (SIBI vs BISINDO)
const activeSystem = ref('SIBI');

// Modal state
const selectedGesture = ref(null);

function openGestureModal(gesture) {
    selectedGesture.value = gesture;
}

function closeGestureModal() {
    selectedGesture.value = null;
}

// Sample interactive gesture cards for SLB learners
const sampleGestures = {
    SIBI: [
        {
            code: 'A',
            title: 'Alfabet A',
            category: 'Alfabet SIBI',
            difficulty: 'Dasar',
            handCount: '1 Tangan',
            description: 'Kepalkan tangan dominan menghadap ke depan, posisi ibu jari tegak menempel di sisi luar jari telunjuk.',
            tips: 'Pastikan buku-buku jemari rapat dan pergelangan tangan tegak lurus.',
        },
        {
            code: 'B',
            title: 'Alfabet B',
            category: 'Alfabet SIBI',
            difficulty: 'Dasar',
            handCount: '1 Tangan',
            description: 'Buka keempat jari lurus tegak ke atas, lipat ibu jari merapat di depan telapak tangan.',
            tips: 'Jari telunjuk, tengah, manis, dan kelingking sejajar rapat.',
        },
        {
            code: 'C',
            title: 'Alfabet C',
            category: 'Alfabet SIBI',
            difficulty: 'Dasar',
            handCount: '1 Tangan',
            description: 'Lengkungkan seluruh jemari membentuk lengkungan huruf C menghadap ke samping luar.',
            tips: 'Bayangkan sedang memegang cangkir kecil dengan santai.',
        },
        {
            code: 'L',
            title: 'Alfabet L',
            category: 'Alfabet SIBI',
            difficulty: 'Dasar',
            handCount: '1 Tangan',
            description: 'Bentuk sudut 90 derajat antara ibu jari dan jari telunjuk. Tiga jari lainnya ditekuk rapat.',
            tips: 'Telapak tangan menghadap lurus ke depan menghadap lawan bicara.',
        },
        {
            code: 'Halo',
            title: 'Sapaan "Halo"',
            category: 'Komunikasi Harian',
            difficulty: 'Dasar',
            handCount: '1 Tangan',
            description: 'Letakkan telapak tangan terbuka di samping pelipis, lalu ayunkan sedikit ke samping luar.',
            tips: 'Sertai dengan ekspresi wajah ramah dan kontak mata yang hangat.',
        },
        {
            code: 'Terima Kasih',
            title: 'Kata "Terima Kasih"',
            category: 'Komunikasi Harian',
            difficulty: 'Menengah',
            handCount: '1 Tangan',
            description: 'Sentuhkan ujung jemari tangan dominan ke bibir atau dagu, lalu ayunkan ke arah lawan bicara.',
            tips: 'Sertakan sedikit anggukan santun untuk mengekspresikan ketulusan.',
        },
    ],
    BISINDO: [
        {
            code: 'A',
            title: 'Alfabet A (2 Tangan)',
            category: 'Alfabet BISINDO',
            difficulty: 'Dasar',
            handCount: '2 Tangan',
            description: 'Tangan non-dominan sebagai alas, ujung telunjuk tangan dominan bersentuhan membentuk garis visual.',
            tips: 'Interaksi dua tangan di bidang dada memudahkan pembacaan visual.',
        },
        {
            code: 'B',
            title: 'Alfabet B (2 Tangan)',
            category: 'Alfabet BISINDO',
            difficulty: 'Dasar',
            handCount: '2 Tangan',
            description: 'Tangan kiri membentuk tiang vertikal, tangan kanan membentuk dua lengkungan menyentuh tiang.',
            tips: 'Pertahankan orientasi tegak lurus di depan tubuh.',
        },
        {
            code: 'C',
            title: 'Alfabet C',
            category: 'Alfabet BISINDO',
            difficulty: 'Dasar',
            handCount: '1 Tangan',
            description: 'Bentuk lengkungan busur C besar di ruang netral depan dada secara proporsional.',
            tips: 'Ekspresi visual memperkuat ruang artikulasi isyarat.',
        },
        {
            code: 'Belajar',
            title: 'Kata "Belajar"',
            category: 'Kosakata Sekolah',
            difficulty: 'Menengah',
            handCount: '2 Tangan',
            description: 'Tangan terbuka seolah mengambil informasi dari buku lalu diarahkan ke kening secara berulang.',
            tips: 'Gerakan lembut mencerminkan penyerapan ilmu pengetahuan.',
        },
        {
            code: 'Sama-Sama',
            title: 'Frasa "Sama-Sama"',
            category: 'Komunikasi Harian',
            difficulty: 'Dasar',
            handCount: '2 Tangan',
            description: 'Kedua tangan terbuka telapak menghadap ke atas, digerakkan bersilang lembut ke depan.',
            tips: 'Sikap tubuh hangat dan senyum sangat mendukung keakraban.',
        },
        {
            code: 'Semangat',
            title: 'Ekspresi "Semangat"',
            category: 'Komunikasi Harian',
            difficulty: 'Dasar',
            handCount: '1 Tangan',
            description: 'Kepalkan tangan di depan dada lalu ayunkan ke bawah dengan tegas dan penuh keyakinan.',
            tips: 'Mimik wajah berseri-seri sangat penting untuk menyampaikan dorongan motivasi.',
        },
    ],
};
</script>

<template>
    <Head title="SignTech — Platform Pembelajaran Bahasa Isyarat untuk Siswa SLB" />

    <div class="landing-page font-sans bg-surface text-ink min-h-screen flex flex-col selection:bg-primary-100 selection:text-primary-dark">
        <!-- Navigation Header -->
        <header class="sticky top-0 z-40 border-b border-surface-border bg-white/90 backdrop-blur-md transition-all">
            <div class="mx-auto flex h-18 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <!-- Brand Logo & Name -->
                <Link href="/" class="flex items-center gap-3 group">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white p-1.5 shadow-sm ring-1 ring-surface-border transition group-hover:scale-105">
                        <img :src="'/LOGO.png'" alt="SignTech" class="h-full w-full object-contain" />
                    </div>
                    <div>
                        <span class="text-xl font-extrabold tracking-tight text-ink">Sign<span class="text-primary">Tech</span></span>
                        <span class="block text-[10px] font-semibold text-ink-muted -mt-1 tracking-wider uppercase">Inklusi & SLB</span>
                    </div>
                </Link>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-7 text-xs font-bold tracking-wide text-ink-muted">
                    <a href="#alur-kerja" class="hover:text-primary transition">Alur Belajar Siswa</a>
                    <a href="#fitur" class="hover:text-primary transition">Fitur Pembelajaran</a>
                    <a href="#sistem" class="hover:text-primary transition">SIBI & BISINDO</a>
                    <Link href="/learn" class="hover:text-primary transition">Katalog Materi</Link>
                </nav>

                <!-- Desktop Auth CTA -->
                <div class="hidden md:flex items-center gap-3">
                    <Link
                        href="/login"
                        class="btn-secondary px-4 py-2 text-xs"
                    >
                        Masuk Akun
                    </Link>
                    <Link
                        href="/register"
                        class="btn-primary px-5 py-2 text-xs"
                    >
                        <span>Daftar Siswa Baru</span>
                        <ArrowRight :size="14" />
                    </Link>
                </div>

                <!-- Mobile Hamburger Button -->
                <button
                    type="button"
                    class="md:hidden flex h-10 w-10 items-center justify-center rounded-xl border border-surface-border text-ink hover:bg-surface-soft transition"
                    aria-label="Buka menu navigasi"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <component :is="mobileMenuOpen ? X : Menu" :size="20" />
                </button>
            </div>

            <!-- Mobile Navigation Drawer -->
            <div v-if="mobileMenuOpen" class="md:hidden border-b border-surface-border bg-white px-4 py-5 shadow-lg">
                <nav class="flex flex-col gap-3 text-sm font-bold text-ink">
                    <a href="#alur-kerja" class="py-2 hover:text-primary" @click="mobileMenuOpen = false">Alur Belajar Siswa</a>
                    <a href="#fitur" class="py-2 hover:text-primary" @click="mobileMenuOpen = false">Fitur Pembelajaran</a>
                    <a href="#sistem" class="py-2 hover:text-primary" @click="mobileMenuOpen = false">SIBI & BISINDO</a>
                    <Link href="/learn" class="py-2 hover:text-primary" @click="mobileMenuOpen = false">Katalog Materi</Link>
                    <div class="mt-3 pt-3 border-t border-surface-border flex flex-col gap-2">
                        <Link href="/login" class="btn-secondary w-full text-center" @click="mobileMenuOpen = false">Masuk Akun</Link>
                        <Link href="/register" class="btn-primary w-full text-center" @click="mobileMenuOpen = false">Daftar Siswa Baru</Link>
                    </div>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative overflow-hidden bg-gradient-to-b from-[#eefaf4] via-surface to-white pt-16 pb-20 sm:pt-22 sm:pb-28">
            <div class="pointer-events-none absolute -top-40 -right-40 h-96 w-96 rounded-full bg-primary-100/60 blur-3xl"></div>
            <div class="pointer-events-none absolute top-40 -left-40 h-80 w-80 rounded-full bg-secondary-100/50 blur-3xl"></div>

            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <!-- Badge -->
                    <div class="inline-flex items-center gap-2 rounded-full border border-primary-200 bg-white/80 px-3.5 py-1 text-xs font-bold text-primary-darker shadow-sm">
                        <span class="h-2 w-2 rounded-full bg-primary animate-pulse"></span>
                        <span>Media Belajar Bahasa Isyarat Inklusif untuk Sekolah Luar Biasa</span>
                    </div>

                    <!-- Main Heading -->
                    <h1 class="mt-6 text-3xl font-extrabold tracking-tight text-ink sm:text-5xl lg:text-6xl leading-[1.15]">
                        Belajar bahasa isyarat jadi menyenangkan dan mudah dipahami
                    </h1>

                    <!-- Subtitle -->
                    <p class="mx-auto mt-6 max-w-2xl text-base text-ink-muted sm:text-lg leading-relaxed">
                        Dirancang khusus untuk siswa SLB (tunarungu) dan pembelajar inklusif. Siswa dapat berlatih di depan kamera dengan panduan visual ramah tanpa perlu instruksi audio, didukung pemantauan langsung guru di kelas.
                    </p>

                    <!-- Call To Action -->
                    <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Link href="/dashboard" class="btn-primary w-full px-8 py-3 text-base sm:w-auto">
                            <span>Mulai Belajar Sekarang</span>
                            <ArrowRight :size="17" />
                        </Link>
                        <Link href="/practice" class="btn-secondary w-full px-7 py-3 text-base sm:w-auto">
                            <Camera :size="17" class="text-primary" />
                            <span>Buka Latihan Isyarat</span>
                        </Link>
                    </div>

                    <!-- Trust indicators -->
                    <div class="mt-7 flex flex-wrap items-center justify-center gap-6 text-xs font-semibold text-ink-muted">
                        <span class="flex items-center gap-1.5">
                            <CheckCircle2 :size="15" class="text-primary" />
                            <span>Panduan 100% Visual Ramah Siswa Tuli</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <CheckCircle2 :size="15" class="text-accent" />
                            <span>Materi Standar SIBI & BISINDO Lengkap</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <CheckCircle2 :size="15" class="text-secondary" />
                            <span>Dilengkapi Timer Hitung Mundur Persiapan</span>
                        </span>
                    </div>
                </div>

                <!-- 4 Metrics -->
                <div class="mx-auto mt-14 grid max-w-4xl grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
                    <div class="rounded-xl border border-surface-border bg-white p-5 text-center shadow-sm">
                        <div class="text-2xl font-extrabold text-ink">2 Sistem</div>
                        <div class="text-xs font-bold text-ink-muted mt-1">SIBI & BISINDO</div>
                        <div class="text-[11px] text-ink-muted/80 mt-0.5">Satu & Dua Tangan</div>
                    </div>
                    <div class="rounded-xl border border-surface-border bg-white p-5 text-center shadow-sm">
                        <div class="text-2xl font-extrabold text-primary">A–Z</div>
                        <div class="text-xs font-bold text-ink-muted mt-1">Alfabet & Angka</div>
                        <div class="text-[11px] text-ink-muted/80 mt-0.5">Materi Berjenjang</div>
                    </div>
                    <div class="rounded-xl border border-surface-border bg-white p-5 text-center shadow-sm">
                        <div class="text-2xl font-extrabold text-secondary">100% Visual</div>
                        <div class="text-xs font-bold text-ink-muted mt-1">Tanpa Beban Audio</div>
                        <div class="text-[11px] text-ink-muted/80 mt-0.5">Dirancang Khusus SLB</div>
                    </div>
                    <div class="rounded-xl border border-surface-border bg-white p-5 text-center shadow-sm">
                        <div class="text-2xl font-extrabold text-accent">Instan</div>
                        <div class="text-xs font-bold text-ink-muted mt-1">Koreksi Bentuk Tangan</div>
                        <div class="text-[11px] text-ink-muted/80 mt-0.5">Umpan Balik Cepat</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Dedicated Section: Alur Kerja Pembelajaran Kami untuk Siswa SLB -->
        <section id="alur-kerja" class="py-20 border-t border-surface-border bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#eefaf4] text-[#1e7253] text-xs font-bold rounded-full border border-[#d5f3e5]">
                        <HeartHandshake :size="14" />
                        <span>Alur Kerja Pembelajaran Siswa SLB</span>
                    </div>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">
                        Bagaimana Siswa SLB Belajar Bersama SignTech
                    </h2>
                    <p class="mt-3 text-base text-ink-muted leading-relaxed">
                        Proses 4 langkah terstruktur yang tenang, bebas hambatan suara, dan memberi siswa kendali penuh saat mempraktikkan isyarat.
                    </p>
                </div>

                <!-- 4-Step Visual Workflow Cards -->
                <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Step 1 -->
                    <div class="workflow-card card flex flex-col justify-between border-t-4 border-t-primary p-6 hover:shadow-lg transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="step-badge bg-primary-50 text-primary font-extrabold text-xs px-2.5 py-1 rounded-lg">
                                    LANGKAH 01
                                </span>
                                <Hand :size="24" class="text-primary" />
                            </div>
                            <h3 class="text-lg font-bold text-ink">Pilih Huruf atau Kata</h3>
                            <p class="mt-2 text-xs leading-relaxed text-ink-muted">
                                Siswa memilih modul isyarat yang ingin dipelajari. Tersedia panduan foto dan posisi jari yang sangat jelas tanpa perlu mendengar aba-aba suara.
                            </p>
                        </div>
                        <div class="mt-6 pt-3 border-t border-surface-border text-[11px] font-semibold text-primary">
                            Visual & Intuitif
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="workflow-card card flex flex-col justify-between border-t-4 border-t-secondary p-6 hover:shadow-lg transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="step-badge bg-secondary-50 text-secondary-dark font-extrabold text-xs px-2.5 py-1 rounded-lg">
                                    LANGKAH 02
                                </span>
                                <Timer :size="24" class="text-secondary" />
                            </div>
                            <h3 class="text-lg font-bold text-ink">Hitung Mundur Persiapan</h3>
                            <p class="mt-2 text-xs leading-relaxed text-ink-muted">
                                Siswa menekan tombol dan melihat hitungan mundur visual (3 detik). Memberi waktu cukup untuk memposisikan jemari di depan kamera tanpa terburu-buru.
                            </p>
                        </div>
                        <div class="mt-6 pt-3 border-t border-surface-border text-[11px] font-semibold text-secondary-dark">
                            Timer 3 Detik Terpadu
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="workflow-card card flex flex-col justify-between border-t-4 border-t-accent p-6 hover:shadow-lg transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="step-badge bg-accent-50 text-accent-dark font-extrabold text-xs px-2.5 py-1 rounded-lg">
                                    LANGKAH 03
                                </span>
                                <Camera :size="24" class="text-accent" />
                            </div>
                            <h3 class="text-lg font-bold text-ink">Deteksi Gerakan Otomatis</h3>
                            <p class="mt-2 text-xs leading-relaxed text-ink-muted">
                                Kamera membaca formasi tangan secara otomatis. Tanda centang hijau dan petunjuk visual langsung muncul jika gerakan sudah sesuai kaidah isyarat.
                            </p>
                        </div>
                        <div class="mt-6 pt-3 border-t border-surface-border text-[11px] font-semibold text-accent-dark">
                            Koreksi Cepat & Jelas
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="workflow-card card flex flex-col justify-between border-t-4 border-t-primary p-6 hover:shadow-lg transition">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="step-badge bg-primary-50 text-primary font-extrabold text-xs px-2.5 py-1 rounded-lg">
                                    LANGKAH 04
                                </span>
                                <Trophy :size="24" class="text-primary" />
                            </div>
                            <h3 class="text-lg font-bold text-ink">Koleksi XP & Pantauan Guru</h3>
                            <p class="mt-2 text-xs leading-relaxed text-ink-muted">
                                Setiap keberhasilan menambah poin XP dan rekor hari berturut-turut (streak). Guru SLB di sekolah dapat langsung memantau capaian seluruh murid.
                            </p>
                        </div>
                        <div class="mt-6 pt-3 border-t border-surface-border text-[11px] font-semibold text-primary">
                            Gamifikasi & Monitoring
                        </div>
                    </div>
                </div>

                <!-- SLB Benefit Highlights Banner -->
                <div class="mt-12 rounded-2xl bg-[#eefaf4] border border-[#d5f3e5] p-6 sm:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="max-w-xl">
                        <div class="text-xs font-bold uppercase tracking-wider text-[#1e7253]">Didukung Pendekatan Pendidikan SLB</div>
                        <h3 class="text-xl font-extrabold text-[#14221d] mt-1">Ruang Latihan yang Aman, Ramah, dan Mandiri</h3>
                        <p class="text-xs sm:text-sm text-[#52685d] mt-2 leading-relaxed">
                            Siswa tunarungu dapat mengulang latihan berkali-kali tanpa rasa cemas. Guru di sekolah luar biasa dapat menugaskan huruf tertentu sesuai silabus kelas.
                        </p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <Link href="/register" class="btn-primary text-xs px-6 py-3">
                            <span>Daftarkan Siswa Sekarang</span>
                            <ArrowRight :size="15" />
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature Pillars -->
        <section id="fitur" class="border-t border-surface-border bg-surface py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <div class="text-xs font-bold uppercase tracking-wider text-primary">Fitur Unggulan</div>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">
                        Kemudahan Belajar bagi Siswa dan Guru
                    </h2>
                    <p class="mt-3 text-base text-ink-muted">
                        Mendukung ekosistem belajar yang mandiri, menyenangkan, dan terstruktur.
                    </p>
                </div>

                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div class="card card-hover flex flex-col justify-between border-t-4 border-t-primary p-6">
                        <div>
                            <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-primary-50 text-primary">
                                <Hand :size="24" />
                            </div>
                            <h3 class="mt-5 text-lg font-extrabold text-ink">Studio Latihan Kamera</h3>
                            <p class="mt-2 text-xs leading-relaxed text-ink-muted">
                                Siswa memperagakan bentuk tangan di depan kamera dengan panduan visual dan timer persiapan. Sistem membaca kecocokan gesture seketika.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-surface-border">
                            <Link href="/practice" class="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary-dark">
                                <span>Buka Studio Latihan</span>
                                <ArrowRight :size="13" />
                            </Link>
                        </div>
                    </div>

                    <div class="card card-hover flex flex-col justify-between border-t-4 border-t-secondary p-6">
                        <div>
                            <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-secondary-50 text-secondary-dark">
                                <Trophy :size="24" />
                            </div>
                            <h3 class="mt-5 text-lg font-extrabold text-ink">Gamifikasi & Misi Harian</h3>
                            <p class="mt-2 text-xs leading-relaxed text-ink-muted">
                                Membangun kebiasaan latihan harian melalui tantangan misi, lencana penghargaan, dan rekor streak hari aktif untuk memotivasi anak belajar.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-surface-border">
                            <Link href="/challenge" class="inline-flex items-center gap-1.5 text-xs font-bold text-secondary-dark hover:text-secondary">
                                <span>Lihat Misi Harian</span>
                                <ArrowRight :size="13" />
                            </Link>
                        </div>
                    </div>

                    <div class="card card-hover flex flex-col justify-between border-t-4 border-t-accent p-6">
                        <div>
                            <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-accent-50 text-accent-dark">
                                <Users :size="24" />
                            </div>
                            <h3 class="mt-5 text-lg font-extrabold text-ink">Ruang Pemantauan Guru</h3>
                            <p class="mt-2 text-xs leading-relaxed text-ink-muted">
                                Guru SLB dapat melihat progres masing-masing siswa, melihat daftar isyarat yang masih sulit, dan memberikan bimbingan tepat sasaran.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-surface-border">
                            <Link href="/classroom" class="inline-flex items-center gap-1.5 text-xs font-bold text-accent-dark hover:text-accent">
                                <span>Masuk Ruang Guru</span>
                                <ArrowRight :size="13" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SIBI vs BISINDO Explorer Section -->
        <section id="sistem" class="py-20 border-t border-surface-border bg-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <div class="text-xs font-bold uppercase tracking-wider text-primary">Kamus Contoh Isyarat</div>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-ink sm:text-4xl">
                        Jelajahi Bentuk Isyarat SIBI dan BISINDO
                    </h2>
                    <p class="mt-3 text-base text-ink-muted">
                        Pilih sistem isyarat di bawah untuk melihat cara pembentukan tangan dan tips praktiknya.
                    </p>

                    <div class="mt-6 inline-flex items-center rounded-xl bg-[#f4f8f6] p-1 border border-surface-border shadow-sm">
                        <button
                            class="flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold transition"
                            :class="activeSystem === 'SIBI' ? 'bg-primary text-white shadow-sm' : 'text-ink-muted hover:text-ink'"
                            @click="activeSystem = 'SIBI'"
                        >
                            <Hand :size="15" />
                            <span>SIBI (Satu Tangan)</span>
                        </button>
                        <button
                            class="flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold transition"
                            :class="activeSystem === 'BISINDO' ? 'bg-secondary text-white shadow-sm' : 'text-ink-muted hover:text-ink'"
                            @click="activeSystem = 'BISINDO'"
                        >
                            <HeartHandshake :size="15" />
                            <span>BISINDO (Dua Tangan)</span>
                        </button>
                    </div>
                </div>

                <!-- Cards Grid -->
                <div class="mt-10 mx-auto max-w-4xl grid gap-4 grid-cols-2 sm:grid-cols-3">
                    <div
                        v-for="gesture in sampleGestures[activeSystem]"
                        :key="gesture.code"
                        class="card card-hover p-4 cursor-pointer text-left group"
                        @click="openGestureModal(gesture)"
                    >
                        <div class="flex items-start justify-between">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl font-extrabold text-lg"
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
        <footer class="border-t border-surface-border bg-[#f8faf9] pt-12 pb-10 text-sm text-ink-muted">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white p-1 ring-1 ring-surface-border shadow-sm">
                            <img :src="'/LOGO.png'" alt="SignTech" class="h-full w-full object-contain" />
                        </div>
                        <span class="text-lg font-extrabold text-ink">Sign<span class="text-primary">Tech</span></span>
                    </div>
                    <div class="flex items-center gap-6 text-xs font-semibold">
                        <Link href="/dashboard" class="hover:text-primary transition">Dashboard</Link>
                        <Link href="/learn" class="hover:text-primary transition">Katalog Materi</Link>
                        <Link href="/practice" class="hover:text-primary transition">Latihan Isyarat</Link>
                        <Link href="/classroom" class="hover:text-primary transition">Ruang Guru</Link>
                    </div>
                </div>
                <div class="mt-8 pt-6 border-t border-surface-border text-center text-xs text-ink-muted">
                    SignTech © 2026 — Media Edukasi Bahasa Isyarat Inklusif (SIBI & BISINDO).
                </div>
            </div>
        </footer>

        <!-- Modal Detail Gesture -->
        <div v-if="selectedGesture" class="modal-backdrop" @click.self="closeGestureModal">
            <div class="lesson-modal max-w-md w-full bg-white rounded-2xl p-6 text-left shadow-2xl relative">
                <button class="modal-close" aria-label="Tutup modal" @click="closeGestureModal">
                    <X :size="18" />
                </button>
                <div class="text-xs font-bold text-primary mb-1">{{ activeSystem }} · {{ selectedGesture.category }}</div>
                <h3 class="text-xl font-extrabold text-ink">{{ selectedGesture.title }}</h3>
                <div class="rounded-xl bg-surface p-4 border border-surface-border text-xs leading-relaxed text-ink my-4">
                    <b>Cara Membentuk Isyarat:</b>
                    <p class="mt-1">{{ selectedGesture.description }}</p>
                </div>
                <div class="rounded-xl bg-secondary-50 p-3 border border-secondary-100 text-xs text-secondary-dark my-4">
                    <b>Tips Praktik:</b>
                    <p class="mt-1">{{ selectedGesture.tips }}</p>
                </div>
                <div class="flex items-center gap-3 mt-4">
                    <Link href="/practice" class="btn-primary flex-1 text-xs" @click="closeGestureModal">
                        Mulai Latih di Kamera
                    </Link>
                    <button class="btn-secondary px-4 text-xs" @click="closeGestureModal">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.landing-page {
    font-family: 'Poppins', sans-serif;
}
</style>
