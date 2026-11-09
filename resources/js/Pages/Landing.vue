<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    BookOpen,
    Camera,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Clock,
    Flame,
    Hand,
    HeartHandshake,
    HelpCircle,
    Layers,
    Lightbulb,
    Menu,
    Plus,
    RotateCw,
    School,
    ShieldCheck,
    Smile,
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

// Interactive Audience Tab (Siswa vs Guru)
const activeAudience = ref('siswa');

// Modal state for gesture detail
const selectedGesture = ref(null);

function openGestureModal(gesture) {
    selectedGesture.value = gesture;
}

function closeGestureModal() {
    selectedGesture.value = null;
}

// FAQ Accordion state
const openFaqIndex = ref(0);
function toggleFaq(index) {
    openFaqIndex.value = openFaqIndex.value === index ? null : index;
}

// Footer Contact Form state ala Menuin
const contactMessage = ref('');
function sendContactMessage() {
    if (!contactMessage.value.trim()) return;
    const text = encodeURIComponent(`Halo Tim SignTech, saya punya pertanyaan: ${contactMessage.value.trim()}`);
    window.open(`https://wa.me/6282235594074?text=${text}`, '_blank');
    contactMessage.value = '';
}

// Gesture dataset for interactive explorer
const sampleGestures = {
    SIBI: [
        {
            code: 'A',
            title: 'Huruf Isyarat A',
            category: 'Alfabet SIBI',
            difficulty: 'Pemula',
            handCount: '1 Tangan',
            description: 'Kepalkan tangan menghadap ke depan, lalu tempelkan ibu jari tegak lurus di sisi luar jari telunjuk.',
            tips: 'Pastikan jari-jari terkepal rapat dan pergelangan tangan tegak lurus dengan kamera.',
        },
        {
            code: 'B',
            title: 'Huruf Isyarat B',
            category: 'Alfabet SIBI',
            difficulty: 'Pemula',
            handCount: '1 Tangan',
            description: 'Buka keempat jari tegak lurus ke atas, lalu tekuk ibu jari merapat di depan telapak tangan.',
            tips: 'Jari telunjuk, tengah, manis, dan kelingking saling bersentuhan lurus ke atas.',
        },
        {
            code: 'C',
            title: 'Huruf Isyarat C',
            category: 'Alfabet SIBI',
            difficulty: 'Pemula',
            handCount: '1 Tangan',
            description: 'Lengkungkan seluruh jari membentuk busur setengah lingkaran seperti huruf C ke samping.',
            tips: 'Bentuk tangan santai menyerupai saat sedang menggenggam cangkir kecil.',
        },
        {
            code: 'L',
            title: 'Huruf Isyarat L',
            category: 'Alfabet SIBI',
            difficulty: 'Pemula',
            handCount: '1 Tangan',
            description: 'Bentuk sudut 90 derajat antara ibu jari dan jari telunjuk. Tiga jari lainnya ditekuk merapat.',
            tips: 'Arahkan telapak tangan lurus ke depan menghadap lawan bicara.',
        },
        {
            code: 'Halo',
            title: 'Kata Sapaan "Halo"',
            category: 'Komunikasi Sehari-hari',
            difficulty: 'Dasar',
            handCount: '1 Tangan',
            description: 'Letakkan telapak tangan terbuka di dekat pelipis, lalu ayunkan sedikit ke samping luar dengan ramah.',
            tips: 'Sertakan senyuman ramah dan kontak mata hangat saat mempraktikkannya.',
        },
        {
            code: 'Terima Kasih',
            title: 'Ungkapan "Terima Kasih"',
            category: 'Komunikasi Sehari-hari',
            difficulty: 'Dasar',
            handCount: '1 Tangan',
            description: 'Sentuhkan ujung jemari tangan kanan ke dagu atau bibir bawah, lalu ayunkan ke arah lawan bicara.',
            tips: 'Sedikit anggukan kepala menandakan rasa hormat dan ketulusan.',
        },
    ],
    BISINDO: [
        {
            code: 'A',
            title: 'Huruf Isyarat A (2 Tangan)',
            category: 'Alfabet BISINDO',
            difficulty: 'Pemula',
            handCount: '2 Tangan',
            description: 'Tangan kiri sebagai alas mendatar, ujung telunjuk tangan kanan disentuhkan tegak lurus ke telapak kiri.',
            tips: 'Gerakan dilakukan di area dada agar mudah dilihat oleh teman bicara.',
        },
        {
            code: 'B',
            title: 'Huruf Isyarat B (2 Tangan)',
            category: 'Alfabet BISINDO',
            difficulty: 'Pemula',
            handCount: '2 Tangan',
            description: 'Tangan kiri membentuk tiang tegak, tangan kanan membentuk dua lengkungan menyentuh tiang kiri.',
            tips: 'Pertahankan posisi tangan stabil di depan dada.',
        },
        {
            code: 'C',
            title: 'Huruf Isyarat C',
            category: 'Alfabet BISINDO',
            difficulty: 'Pemula',
            handCount: '1 Tangan',
            description: 'Bentuk lengkungan busur C besar di ruang depan dada secara proporsional.',
            tips: 'Posisikan telapak tangan menghadap ke samping kiri.',
        },
        {
            code: 'Belajar',
            title: 'Kata "Belajar"',
            category: 'Kosakata Sekolah',
            difficulty: 'Menengah',
            handCount: '2 Tangan',
            description: 'Tangan terbuka seolah mengambil ilmu dari buku lalu diarahkan ke kening secara bergantian.',
            tips: 'Lakukan gerakan dengan tenang mencerminkan proses menyerap ilmu.',
        },
        {
            code: 'Sama-Sama',
            title: 'Ungkapan "Sama-Sama"',
            category: 'Komunikasi Sehari-hari',
            difficulty: 'Dasar',
            handCount: '2 Tangan',
            description: 'Kedua tangan terbuka menghadap ke atas, digerakkan bersilang lembut ke depan.',
            tips: 'Sikap tubuh ramah dan senyum mendukung keakraban.',
        },
        {
            code: 'Semangat',
            title: 'Ekspresi "Semangat"',
            category: 'Motivasi',
            difficulty: 'Dasar',
            handCount: '1 Tangan',
            description: 'Kepalkan tangan di depan dada lalu ayunkan ke bawah dengan mantap dan percaya diri.',
            tips: 'Ekspresi wajah bersemangat memperkuat makna isyarat.',
        },
    ],
};

// FAQ dataset
const faqs = [
    {
        question: 'Apakah aplikasi ini memerlukan alat atau kamera khusus?',
        answer: 'Tidak sama sekali. Anda hanya membutuhkan kamera standar dari laptop, komputer, atau ponsel. Sistem akan langsung membaca gerakan tangan Anda melalui browser tanpa perlu instalasi perangkat tambahan.',
    },
    {
        question: 'Bagaimana cara sistem memeriksa bentuk tangan saya?',
        answer: 'Saat Anda mempraktikkan isyarat di depan kamera, sistem secara otomatis membaca posisi jari dan telapak tangan Anda. Jika bentuk tangan sudah tepat sesuai kaidah, layar akan langsung memunculkan tanda centang hijau dan memberikan poin keberhasilan.',
    },
    {
        question: 'Apakah platform ini cocok untuk siswa yang belum pernah belajar isyarat?',
        answer: 'Sangat cocok. Materi disusun dari tingkat paling dasar (pengenalan huruf A sampai Z) dengan foto panduan posisi jari yang jelas. Ada juga hitungan mundur 3 detik sebelum kamera memeriksa, sehingga siswa bisa bersiap dengan santai tanpa terburu-buru.',
    },
    {
        question: 'Bagaimana peran guru dalam platform ini?',
        answer: 'Guru memiliki ruang khusus untuk membuat kelas, membagikan kode kelas ke siswa, memberikan tugas latihan terarah, dan memantau kemajuan belajar murid satu kelas secara langsung tanpa repot mencatat manual.',
    },
    {
        question: 'Apa perbedaan SIBI dan BISINDO di SignTech?',
        answer: 'SIBI (Sistem Isyarat Bahasa Indonesia) adalah sistem resmi yang banyak digunakan di kurikulum sekolah formal dan umumnya memakai satu tangan. Sementara BISINDO (Bahasa Isyarat Indonesia) adalah bahasa isyarat alami komunitas tuli Indonesia yang umumnya memakai dua tangan. Keduanya tersedia lengkap di SignTech.',
    },
];

const page = usePage();
const authUser = computed(() => page.props.auth?.user || null);
const loginHref = computed(() => (authUser.value ? (authUser.value.role === 'teacher' ? '/teacher/dashboard' : '/student/dashboard') : '/login'));
const loginLabel = computed(() => (authUser.value ? 'Buka Dashboard' : 'Masuk Akun'));
</script>

<template>
    <Head title="SignTech — Belajar Bahasa Isyarat Interaktif untuk Sekolah Luar Biasa" />

    <div class="landing-page font-sans bg-[#f7faf8] text-[#14221d] min-h-screen flex flex-col selection:bg-[#34B182]/20 selection:text-[#167b71]">
        <!-- Top Navigation Header (Clean & Modern Navbar ala Menuin) -->
        <header class="sticky top-0 z-40 border-b border-[#e5ebe7] bg-white/95 backdrop-blur-md transition-all">
            <div class="mx-auto flex h-18 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <!-- Brand Logo & Name -->
                <Link href="/" class="flex items-center gap-2.5 group">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#dff2e9] p-1.5 shadow-xs transition group-hover:scale-105">
                        <img :src="'/LOGO.png'" alt="SignTech" class="h-full w-full object-contain" />
                    </div>
                    <div>
                        <span class="text-xl font-display font-extrabold tracking-tight text-[#14221d]">Sign<span class="text-[#34B182]">Tech</span></span>
                        <span class="block text-[10px] font-bold text-[#8ba098] -mt-1 tracking-wider uppercase">Belajar Isyarat SLB</span>
                    </div>
                </Link>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-7 text-xs font-bold text-[#627974]">
                    <a href="#fitur" class="hover:text-[#167b71] transition-colors">Fitur Unggulan</a>
                    <a href="#cara-kerja" class="hover:text-[#167b71] transition-colors">Cara Belajar</a>
                    <a href="#kamus" class="hover:text-[#167b71] transition-colors">Kamus Isyarat</a>
                    <a href="#manfaat" class="hover:text-[#167b71] transition-colors">Untuk Siswa & Guru</a>
                    <a href="#faq" class="hover:text-[#167b71] transition-colors">Tanya Jawab</a>
                </nav>

                <!-- Desktop Auth CTA Buttons -->
                <div class="hidden md:flex items-center gap-3">
                    <Link
                        :href="loginHref"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-xl border border-[#d8e3dc] bg-white text-xs font-bold text-[#14221d] hover:bg-[#f4f8f6] shadow-xs transition-colors"
                    >
                        {{ loginLabel }}
                    </Link>
                    <Link
                        href="/register"
                        class="inline-flex items-center justify-center gap-1.5 px-5 py-2 rounded-xl bg-[#34B182] hover:bg-[#28996e] text-xs font-bold text-white shadow-sm shadow-[#34B182]/25 transition-all transform hover:-translate-y-0.5"
                    >
                        <span>Daftar Gratis</span>
                        <ArrowRight :size="14" class="stroke-[2.5]" />
                    </Link>
                </div>

                <!-- Mobile Hamburger Button -->
                <button
                    type="button"
                    class="md:hidden flex h-10 w-10 items-center justify-center rounded-xl border border-[#e5ece7] text-[#14221d] hover:bg-[#f4f8f6] transition"
                    aria-label="Buka menu navigasi"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <component :is="mobileMenuOpen ? X : Menu" :size="20" />
                </button>
            </div>

            <!-- Mobile Navigation Drawer -->
            <div v-if="mobileMenuOpen" class="md:hidden border-b border-[#e5ebe7] bg-white px-5 py-5 shadow-lg">
                <nav class="flex flex-col gap-3 text-sm font-bold text-[#14221d]">
                    <a href="#fitur" class="py-2 hover:text-[#34B182]" @click="mobileMenuOpen = false">Fitur Unggulan</a>
                    <a href="#cara-kerja" class="py-2 hover:text-[#34B182]" @click="mobileMenuOpen = false">Cara Belajar</a>
                    <a href="#kamus" class="py-2 hover:text-[#34B182]" @click="mobileMenuOpen = false">Kamus Isyarat</a>
                    <a href="#manfaat" class="py-2 hover:text-[#34B182]" @click="mobileMenuOpen = false">Untuk Siswa & Guru</a>
                    <a href="#faq" class="py-2 hover:text-[#34B182]" @click="mobileMenuOpen = false">Tanya Jawab</a>
                    <div class="mt-3 pt-3 border-t border-[#edf2ef] flex flex-col gap-2.5">
                        <Link :href="loginHref" class="w-full py-2.5 text-center text-xs font-bold border border-[#d8e3dc] rounded-xl" @click="mobileMenuOpen = false">
                            {{ loginLabel }}
                        </Link>
                        <Link href="/register" class="w-full py-2.5 text-center text-xs font-bold bg-[#34B182] text-white rounded-xl shadow-xs" @click="mobileMenuOpen = false">
                            Daftar Gratis
                        </Link>
                    </div>
                </nav>
            </div>
        </header>

        <!-- Hero Section (Clean, Human, Conversion-Driven ala Menuin) -->
        <section class="relative overflow-hidden pt-12 pb-18 sm:pt-20 sm:pb-24 border-b border-[#e7eee9]">
            <!-- Subtle background glows -->
            <div class="pointer-events-none absolute -top-32 -right-32 h-96 w-96 rounded-full bg-[#34B182]/10 blur-3xl"></div>
            <div class="pointer-events-none absolute top-48 -left-32 h-80 w-80 rounded-full bg-[#C29B40]/10 blur-3xl"></div>

            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Text Header Area -->
                <div class="mx-auto max-w-3xl text-center">
                    <!-- Top Pill Tag -->
                    <div class="inline-flex items-center gap-2 rounded-full border border-[#cde8dc] bg-[#eefaf4] px-4 py-1.5 text-xs font-bold text-[#1a6e55] shadow-xs">
                        <Sparkles :size="14" class="text-[#C29B40]" />
                        <span>Platform Belajar Bahasa Isyarat Interaktif untuk Sekolah Luar Biasa</span>
                    </div>

                    <!-- Main Catchy Headline -->
                    <h1 class="mt-6 font-display font-extrabold text-3xl sm:text-5xl lg:text-6xl text-[#14221d] leading-[1.18] tracking-tight">
                        Belajar Bahasa Isyarat Jadi Lebih Mudah, Seru, dan Percaya Diri
                    </h1>

                    <!-- Clear Awam-Friendly Subtitle -->
                    <p class="mx-auto mt-5 max-w-2xl text-sm sm:text-base text-[#617770] leading-relaxed font-medium">
                        Dirancang khusus untuk siswa SLB dan teman tuli. Latihan mandiri di depan kamera dengan koreksi posisi tangan seketika, kuis visual bergambar, dan pemantauan guru secara langsung.
                    </p>

                    <!-- CTAs -->
                    <div class="mt-8 flex flex-col items-center justify-center gap-3.5 sm:flex-row">
                        <Link
                            href="/register"
                            class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-7 py-3.5 rounded-2xl bg-[#34B182] hover:bg-[#28996e] text-white text-sm font-bold shadow-lg shadow-[#34B182]/25 transition-all transform hover:-translate-y-0.5"
                        >
                            <span>Mulai Belajar Sekarang</span>
                            <ArrowRight :size="16" class="stroke-[2.5]" />
                        </Link>
                        <Link
                            href="/practice"
                            class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-6 py-3.5 rounded-2xl border border-[#d8e3dc] bg-white text-[#14221d] text-sm font-bold hover:bg-[#f4f8f6] shadow-xs transition-colors"
                        >
                            <Camera :size="16" class="text-[#34B182]" />
                            <span>Coba Latihan Depan Kamera</span>
                        </Link>
                    </div>

                    <!-- Trust Checklist Bar -->
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-5 sm:gap-7 text-xs font-bold text-[#556d67]">
                        <span class="flex items-center gap-1.5">
                            <CheckCircle2 :size="16" class="text-[#34B182]" />
                            <span>100% Panduan Visual Ramah SLB</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <CheckCircle2 :size="16" class="text-[#34B182]" />
                            <span>Koreksi Posisi Jari Seketika</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <CheckCircle2 :size="16" class="text-[#C29B40]" />
                            <span>Dilengkapi Timer Hitung Mundur</span>
                        </span>
                    </div>
                </div>

                <!-- Interactive App Showcase / Mockup Preview (ala Menuin) -->
                <div class="mt-14 max-w-5xl mx-auto rounded-3xl bg-white border border-[#e2ede6] p-4 sm:p-7 shadow-xl shadow-[#14221d]/5">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-center">
                        <!-- Left Mockup: Live Camera Practice Simulator -->
                        <div class="lg:col-span-7 bg-[#0f2a24] rounded-2xl p-5 text-white relative overflow-hidden flex flex-col justify-between min-h-[340px]">
                            <div class="flex items-center justify-between z-10">
                                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-[#34B182]/20 text-[#6fe3b7] border border-[#34B182]/30">
                                    <span class="w-2 h-2 rounded-full bg-[#34B182] animate-ping"></span>
                                    <span>Kamera Latihan Aktif</span>
                                </span>
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white/10 text-white">
                                    Target: Huruf A (SIBI)
                                </span>
                            </div>

                            <!-- Mockup Center: Hand Target Visual + Verification Box -->
                            <div class="my-6 flex flex-col items-center justify-center text-center z-10">
                                <div class="w-28 h-28 rounded-2xl border-2 border-dashed border-[#34B182] bg-white/5 flex items-center justify-center relative mb-3">
                                    <Hand :size="60" class="text-[#34B182]" />
                                    <div class="absolute -bottom-3 px-2.5 py-0.5 rounded-full bg-[#34B182] text-[#14221d] font-extrabold text-[11px] shadow-sm">
                                        98% Tepat!
                                    </div>
                                </div>
                                <h4 class="font-display font-bold text-base text-white">
                                    Bentuk Tangan Sesuai Kaidah Isyarat
                                </h4>
                                <p class="text-xs text-[#9bbcb3] mt-1 max-w-xs">
                                    Ibu jari tegak menempel di sisi luar jari telunjuk. Lanjutkan ke huruf berikutnya!
                                </p>
                            </div>

                            <div class="flex items-center justify-between pt-3 border-t border-white/10 text-xs text-[#9bbcb3] z-10">
                                <span>Poin Didapat: <b class="text-[#f4cd6f]">+20 XP</b></span>
                                <span class="text-[#34B182] font-bold">Lulus Evaluasi ✓</span>
                            </div>
                        </div>

                        <!-- Right Mockup: Student Learning Dashboard Card -->
                        <div class="lg:col-span-5 flex flex-col gap-4">
                            <!-- Card 1: Student Level Card -->
                            <div class="p-4 rounded-2xl bg-[#f8fbf9] border border-[#e5ece7]">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold text-[#627a72]">Peringkat Belajar</span>
                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-[#b48825] bg-[#fef7ee] px-2 py-0.5 rounded-full">
                                        <Star :size="12" class="fill-[#C29B40] text-[#C29B40]" />
                                        <span>530 XP</span>
                                    </span>
                                </div>
                                <div class="font-display font-extrabold text-lg text-[#14221d]">
                                    Penjelajah Isyarat
                                </div>
                                <div class="w-full h-2 rounded-full bg-[#e3ece7] mt-2 overflow-hidden">
                                    <div class="h-full rounded-full bg-[#34B182]" style="width: 75%"></div>
                                </div>
                                <small class="text-[10px] text-[#788e86] mt-1 block">70 XP lagi menuju level Mahir</small>
                            </div>

                            <!-- Card 2: Streak Card -->
                            <div class="p-4 rounded-2xl bg-[#fffaf5] border border-[#fce9d7] flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-[#e8633a] text-white flex items-center justify-center font-bold shrink-0 shadow-sm">
                                    <Flame :size="20" class="fill-white" />
                                </div>
                                <div>
                                    <div class="font-display font-bold text-sm text-[#14221d]">7 Hari Berturut-turut</div>
                                    <p class="text-xs text-[#7d8f88]">Luar biasa! Konsistensi latihanmu terjaga minggu ini.</p>
                                </div>
                            </div>

                            <!-- Card 3: Class Task -->
                            <div class="p-4 rounded-2xl bg-[#f5f8ff] border border-[#e1e9fa] flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] font-bold text-[#355bb0] uppercase tracking-wider">Tugas dari Guru</span>
                                    <div class="font-display font-bold text-xs text-[#14221d] mt-0.5">Latihan Huruf Vokal A - E</div>
                                </div>
                                <span class="px-2.5 py-1 rounded-lg bg-[#355bb0] text-white text-[11px] font-bold">
                                    Selesai ✓
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4 Highlights Metrics Bar (ala Menuin) -->
                <div class="mx-auto mt-12 grid max-w-4xl grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
                    <div class="rounded-2xl border border-[#e5ebe7] bg-white p-5 text-center shadow-xs">
                        <div class="font-display text-2xl sm:text-3xl font-extrabold text-[#14221d]">2 Sistem</div>
                        <div class="text-xs font-bold text-[#627a72] mt-1">SIBI & BISINDO</div>
                        <div class="text-[11px] text-[#8aa098] mt-0.5">Satu & Dua Tangan</div>
                    </div>
                    <div class="rounded-2xl border border-[#e5ebe7] bg-white p-5 text-center shadow-xs">
                        <div class="font-display text-2xl sm:text-3xl font-extrabold text-[#34B182]">A–Z</div>
                        <div class="text-xs font-bold text-[#627a72] mt-1">Alfabet & Angka</div>
                        <div class="text-[11px] text-[#8aa098] mt-0.5">Materi Terstruktur</div>
                    </div>
                    <div class="rounded-2xl border border-[#e5ebe7] bg-white p-5 text-center shadow-xs">
                        <div class="font-display text-2xl sm:text-3xl font-extrabold text-[#C29B40]">100% Visual</div>
                        <div class="text-xs font-bold text-[#627a72] mt-1">Bebas Beban Audio</div>
                        <div class="text-[11px] text-[#8aa098] mt-0.5">Ramah Siswa Tuli</div>
                    </div>
                    <div class="rounded-2xl border border-[#e5ebe7] bg-white p-5 text-center shadow-xs">
                        <div class="font-display text-2xl sm:text-3xl font-extrabold text-[#167b71]">Seketika</div>
                        <div class="text-xs font-bold text-[#627a72] mt-1">Koreksi Bentuk Jari</div>
                        <div class="text-[11px] text-[#8aa098] mt-0.5">Umpan Balik Cepat</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Fitur Unggulan (Core Pillars ala Menuin) -->
        <section id="fitur" class="py-20 bg-white border-b border-[#e7eee9]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-[#eefaf4] text-[#1b735a] border border-[#d2efe2]">
                        <Sparkles :size="13" class="text-[#C29B40]" />
                        <span>Fitur Utama SignTech</span>
                    </div>
                    <h2 class="mt-3 font-display font-extrabold text-2xl sm:text-4xl text-[#14221d] tracking-tight">
                        Semua yang Dibutuhkan untuk Belajar Isyarat
                    </h2>
                    <p class="mt-3 text-sm sm:text-base text-[#617770] font-medium leading-relaxed">
                        Mulai dari latihan mandiri di depan kamera, kuis pemahaman bergambar, hingga pemantauan kemajuan kelas oleh bapak/ibu guru.
                    </p>
                </div>

                <!-- 4 Feature Cards Grid -->
                <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Feature 1 -->
                    <div class="rounded-3xl border border-[#e5ece7] bg-[#fbfcfa] p-6 flex flex-col justify-between hover:shadow-lg hover:-translate-y-1 transition-all">
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-[#e8f7f0] text-[#167b71] flex items-center justify-center mb-5 shadow-xs">
                                <Camera :size="24" class="stroke-[2.2]" />
                            </div>
                            <h3 class="font-display font-extrabold text-lg text-[#14221d] mb-2">
                                Latihan Depan Kamera
                            </h3>
                            <p class="text-xs text-[#637973] leading-relaxed">
                                Siswa memperagakan bentuk tangan di depan kamera ponsel atau laptop. Layar otomatis mendeteksi posisi jari dan memberi tahu jika gerakan sudah tepat.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-[#edf2ee]">
                            <Link href="/practice" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#167b71] hover:text-[#34B182] transition-colors">
                                <span>Coba Latihan Kamera</span>
                                <ArrowRight :size="13" class="stroke-[2.5]" />
                            </Link>
                        </div>
                    </div>

                    <!-- Feature 2 -->
                    <div class="rounded-3xl border border-[#e5ece7] bg-[#fbfcfa] p-6 flex flex-col justify-between hover:shadow-lg hover:-translate-y-1 transition-all">
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-[#fef7ee] text-[#C29B40] flex items-center justify-center mb-5 shadow-xs">
                                <BookOpen :size="24" class="stroke-[2.2]" />
                            </div>
                            <h3 class="font-display font-extrabold text-lg text-[#14221d] mb-2">
                                Kuis Isyarat Bergambar
                            </h3>
                            <p class="text-xs text-[#637973] leading-relaxed">
                                Belajar mengenali bentuk isyarat melalui foto peragaan nyata dan pertanyaan pilihan ganda yang santai tanpa perlu instruksi audio yang rumit.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-[#edf2ee]">
                            <Link href="/learn" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#C29B40] hover:text-[#a57f2a] transition-colors">
                                <span>Lihat Materi Kuis</span>
                                <ArrowRight :size="13" class="stroke-[2.5]" />
                            </Link>
                        </div>
                    </div>

                    <!-- Feature 3 -->
                    <div class="rounded-3xl border border-[#e5ece7] bg-[#fbfcfa] p-6 flex flex-col justify-between hover:shadow-lg hover:-translate-y-1 transition-all">
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-[#edf8f2] text-[#1b735a] flex items-center justify-center mb-5 shadow-xs">
                                <School :size="24" class="stroke-[2.2]" />
                            </div>
                            <h3 class="font-display font-extrabold text-lg text-[#14221d] mb-2">
                                Ruang Kelas & Pantauan Guru
                            </h3>
                            <p class="text-xs text-[#637973] leading-relaxed">
                                Guru SLB dapat membuat kelas, membagikan kode kelas, memberikan tugas latihan, dan melihat daftar huruf yang sering keliru untuk dibimbing bersama.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-[#edf2ee]">
                            <Link href="/classroom" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#1b735a] hover:text-[#34B182] transition-colors">
                                <span>Masuk Ruang Kelas</span>
                                <ArrowRight :size="13" class="stroke-[2.5]" />
                            </Link>
                        </div>
                    </div>

                    <!-- Feature 4 -->
                    <div class="rounded-3xl border border-[#e5ece7] bg-[#fbfcfa] p-6 flex flex-col justify-between hover:shadow-lg hover:-translate-y-1 transition-all">
                        <div>
                            <div class="w-12 h-12 rounded-2xl bg-[#fff0e6] text-[#e8633a] flex items-center justify-center mb-5 shadow-xs">
                                <Trophy :size="24" class="stroke-[2.2]" />
                            </div>
                            <h3 class="font-display font-extrabold text-lg text-[#14221d] mb-2">
                                Poin XP & Misi Harian
                            </h3>
                            <p class="text-xs text-[#637973] leading-relaxed">
                                Setiap keberhasilan latihan menghasilkan poin pengalaman (XP), medali prestasi, dan rekor streak hari aktif agar anak terdorong belajar rutin setiap hari.
                            </p>
                        </div>
                        <div class="mt-6 pt-4 border-t border-[#edf2ee]">
                            <Link href="/challenge" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#e8633a] hover:text-[#c44f2b] transition-colors">
                                <span>Lihat Misi Harian</span>
                                <ArrowRight :size="13" class="stroke-[2.5]" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Cara Belajar yang Sangat Mudah (How It Works ala Menuin) -->
        <section id="cara-kerja" class="py-20 bg-[#f7faf8] border-b border-[#e7eee9]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#eefaf4] text-[#1e7253] text-xs font-bold rounded-full border border-[#d5f3e5]">
                        <HeartHandshake :size="14" />
                        <span>Alur Belajar Siswa</span>
                    </div>
                    <h2 class="mt-3 font-display font-extrabold text-2xl sm:text-4xl text-[#14221d] tracking-tight">
                        Cara Belajar yang Tenang & Sangat Mudah
                    </h2>
                    <p class="mt-3 text-sm sm:text-base text-[#617770] font-medium leading-relaxed">
                        4 langkah praktis yang dirancang bebas hambatan suara dan memberi siswa kendali penuh saat mempraktikkan isyarat.
                    </p>
                </div>

                <!-- 4 Steps Visual Cards Grid -->
                <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Step 1 -->
                    <div class="rounded-3xl border border-[#e5ece7] bg-white p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="px-2.5 py-1 rounded-xl text-xs font-extrabold bg-[#e8f7f0] text-[#1b735a]">
                                    LANGKAH 1
                                </span>
                                <Hand :size="22" class="text-[#34B182]" />
                            </div>
                            <h3 class="font-display font-extrabold text-base text-[#14221d] mb-2">
                                Pilih Isyarat Materi
                            </h3>
                            <p class="text-xs text-[#667e76] leading-relaxed">
                                Siswa memilih huruf atau kosakata yang ingin dipelajari, lengkap dengan foto peragaan bentuk tangan yang mudah dicontoh.
                            </p>
                        </div>
                        <div class="mt-6 pt-3 border-t border-[#f0f5f2] text-[11px] font-bold text-[#1b735a]">
                            Visual & Ramah Pemula
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="rounded-3xl border border-[#e5ece7] bg-white p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="px-2.5 py-1 rounded-xl text-xs font-extrabold bg-[#fef7ee] text-[#b88c2b]">
                                    LANGKAH 2
                                </span>
                                <Timer :size="22" class="text-[#C29B40]" />
                            </div>
                            <h3 class="font-display font-extrabold text-base text-[#14221d] mb-2">
                                Hitung Mundur 3 Detik
                            </h3>
                            <p class="text-xs text-[#667e76] leading-relaxed">
                                Siswa menekan tombol mulai dan melihat hitungan mundur visual. Waktu persiapan yang tenang agar posisi jemari pas di depan kamera.
                            </p>
                        </div>
                        <div class="mt-6 pt-3 border-t border-[#f0f5f2] text-[11px] font-bold text-[#b88c2b]">
                            Persiapan Nyaman
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="rounded-3xl border border-[#e5ece7] bg-white p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="px-2.5 py-1 rounded-xl text-xs font-extrabold bg-[#edf8f2] text-[#167b71]">
                                    LANGKAH 3
                                </span>
                                <Camera :size="22" class="text-[#167b71]" />
                            </div>
                            <h3 class="font-display font-extrabold text-base text-[#14221d] mb-2">
                                Cek Bentuk Tangan
                            </h3>
                            <p class="text-xs text-[#667e76] leading-relaxed">
                                Kamera membaca formasi jemari secara langsung. Jika gerakan sudah pas, tanda centang hijau otomatis menyala di layar.
                            </p>
                        </div>
                        <div class="mt-6 pt-3 border-t border-[#f0f5f2] text-[11px] font-bold text-[#167b71]">
                            Umpan Balik Instan
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="rounded-3xl border border-[#e5ece7] bg-white p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="px-2.5 py-1 rounded-xl text-xs font-extrabold bg-[#fff0e6] text-[#e8633a]">
                                    LANGKAH 4
                                </span>
                                <Trophy :size="22" class="text-[#e8633a]" />
                            </div>
                            <h3 class="font-display font-extrabold text-base text-[#14221d] mb-2">
                                Kumpulkan Poin & Laporan
                            </h3>
                            <p class="text-xs text-[#667e76] leading-relaxed">
                                Keberhasilan latihan langsung menambah poin XP siswa dan tercatat rapi di dasbor pemantauan bapak/ibu guru di sekolah.
                            </p>
                        </div>
                        <div class="mt-6 pt-3 border-t border-[#f0f5f2] text-[11px] font-bold text-[#e8633a]">
                            Hasil Belajar Otomatis
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Kamus Contoh Isyarat (SIBI & BISINDO Explorer) -->
        <section id="kamus" class="py-20 bg-white border-b border-[#e7eee9]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <div class="text-xs font-bold uppercase tracking-wider text-[#167b71]">Kamus Contoh Isyarat</div>
                    <h2 class="mt-2 font-display font-extrabold text-2xl sm:text-4xl text-[#14221d] tracking-tight">
                        Jelajahi Contoh Bentuk Isyarat
                    </h2>
                    <p class="mt-3 text-sm sm:text-base text-[#617770]">
                        Pilih sistem isyarat di bawah untuk melihat bentuk tangan dan tips mempraktikkannya.
                    </p>

                    <!-- System Switcher Buttons -->
                    <div class="mt-6 inline-flex items-center rounded-2xl bg-[#eef5f1] p-1.5 border border-[#dce7e1]">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all"
                            :class="activeSystem === 'SIBI' ? 'bg-white text-[#14221d] shadow-sm' : 'text-[#657d76] hover:text-[#14221d]'"
                            @click="activeSystem = 'SIBI'"
                        >
                            <Hand :size="15" class="text-[#34B182]" />
                            <span>SIBI (Satu Tangan)</span>
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all"
                            :class="activeSystem === 'BISINDO' ? 'bg-white text-[#14221d] shadow-sm' : 'text-[#657d76] hover:text-[#14221d]'"
                            @click="activeSystem = 'BISINDO'"
                        >
                            <HeartHandshake :size="15" class="text-[#C29B40]" />
                            <span>BISINDO (Dua Tangan)</span>
                        </button>
                    </div>
                </div>

                <!-- Cards Grid -->
                <div class="mt-12 mx-auto max-w-5xl grid gap-4 grid-cols-2 sm:grid-cols-3">
                    <div
                        v-for="gesture in sampleGestures[activeSystem]"
                        :key="gesture.code"
                        class="p-4 rounded-2xl border border-[#e5ece7] bg-[#fafcfb] hover:bg-white hover:border-[#34B182]/50 hover:shadow-md cursor-pointer transition-all group"
                        @click="openGestureModal(gesture)"
                    >
                        <div class="flex items-start justify-between">
                            <span
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl font-display font-extrabold text-base"
                                :class="activeSystem === 'SIBI' ? 'bg-[#e8f7f0] text-[#1b735a]' : 'bg-[#fef7ee] text-[#b88c2b]'"
                            >
                                {{ gesture.code.length <= 2 ? gesture.code : gesture.code.charAt(0) }}
                            </span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border border-[#d8e3dc] text-[#6e857e]">
                                {{ gesture.difficulty }}
                            </span>
                        </div>
                        <h4 class="mt-3 font-display font-bold text-sm text-[#14221d] group-hover:text-[#167b71] transition-colors">
                            {{ gesture.title }}
                        </h4>
                        <p class="text-[11px] text-[#718780] line-clamp-2 mt-1 leading-snug">
                            {{ gesture.description }}
                        </p>
                        <div class="mt-3 pt-2.5 border-t border-[#f0f4f1] flex items-center justify-between text-[10px] font-bold text-[#869b93]">
                            <span>{{ gesture.handCount }}</span>
                            <span class="text-[#34B182] group-hover:underline">Buka Panduan →</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Nilai Manfaat untuk Siswa & Guru (ala Menuin) -->
        <section id="manfaat" class="py-20 bg-[#f7faf8] border-b border-[#e7eee9]">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#eefaf4] text-[#1e7253] text-xs font-bold rounded-full border border-[#d5f3e5]">
                        <Users :size="14" />
                        <span>Dirancang untuk Komunitas SLB</span>
                    </div>
                    <h2 class="mt-3 font-display font-extrabold text-2xl sm:text-4xl text-[#14221d] tracking-tight">
                        Manfaat Nyata bagi Siswa dan Guru
                    </h2>
                    <p class="mt-3 text-sm sm:text-base text-[#617770] font-medium leading-relaxed">
                        SignTech menjembatani kemandirian belajar anak di rumah dengan pendampingan guru di sekolah.
                    </p>
                </div>

                <!-- 2-Column Comparison Cards -->
                <div class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-7 max-w-5xl mx-auto">
                    <!-- Column Siswa -->
                    <div class="p-6 sm:p-8 rounded-3xl bg-white border border-[#e5ece7] shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-[#e8f7f0] text-[#1b735a] mb-4">
                                <Smile :size="14" />
                                <span>Untuk Siswa Tunarungu & SLB</span>
                            </div>
                            <h3 class="font-display font-extrabold text-xl text-[#14221d] mb-4">
                                Belajar Mandiri Tanpa Cemas Salah
                            </h3>
                            <ul class="space-y-3.5 text-xs text-[#556d67] font-medium">
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 :size="16" class="text-[#34B182] shrink-0 mt-0.5" />
                                    <span><strong>Panduan 100% Visual:</strong> Tidak ada ketergantungan pada instruksi suara, sangat ramah untuk teman tuli.</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 :size="16" class="text-[#34B182] shrink-0 mt-0.5" />
                                    <span><strong>Bebas Mengulang:</strong> Siswa dapat mencoba bentuk tangan berkali-kali hingga benar dengan rasa tenang.</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 :size="16" class="text-[#34B182] shrink-0 mt-0.5" />
                                    <span><strong>Timer Hitung Mundur:</strong> Memberikan waktu 3 detik yang cukup untuk memposisikan jemari di depan kamera.</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 :size="16" class="text-[#34B182] shrink-0 mt-0.5" />
                                    <span><strong>Semangat Poin XP:</strong> Setiap pencapaian dihargai dengan kenaikan level dan medali prestasi.</span>
                                </li>
                            </ul>
                        </div>
                        <div class="mt-8 pt-4 border-t border-[#f0f4f1]">
                            <Link href="/register" class="inline-flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-[#34B182] text-white text-xs font-bold hover:bg-[#28996e] shadow-xs transition-colors">
                                <span>Daftar Sebagai Siswa</span>
                                <ArrowRight :size="14" class="stroke-[2.5]" />
                            </Link>
                        </div>
                    </div>

                    <!-- Column Guru -->
                    <div class="p-6 sm:p-8 rounded-3xl bg-white border border-[#e5ece7] shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-[#edf8f2] text-[#167b71] mb-4">
                                <School :size="14" />
                                <span>Untuk Guru & Sekolah Luar Biasa</span>
                            </div>
                            <h3 class="font-display font-extrabold text-xl text-[#14221d] mb-4">
                                Pantau Kemajuan Murid Tanpa Ribet
                            </h3>
                            <ul class="space-y-3.5 text-xs text-[#556d67] font-medium">
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 :size="16" class="text-[#167b71] shrink-0 mt-0.5" />
                                    <span><strong>Kode Kelas Instan:</strong> Bagikan kode kelas ke siswa, murid otomatis terdaftar dalam daftar bimbingan guru.</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 :size="16" class="text-[#167b71] shrink-0 mt-0.5" />
                                    <span><strong>Pemberian Tugas Terstruktur:</strong> Berikan tugas latihan huruf atau kata tertentu lengkap dengan batas tanggal.</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 :size="16" class="text-[#167b71] shrink-0 mt-0.5" />
                                    <span><strong>Laporan Huruf yang Sering Keliru:</strong> Guru mengetahui bentuk isyarat mana yang paling sulit bagi siswa untuk dibimbing di kelas.</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 :size="16" class="text-[#167b71] shrink-0 mt-0.5" />
                                    <span><strong>Riwayat Latihan Siswa:</strong> Setiap sesi latihan tuntas tersimpan di database dan dapat dipantau realtime.</span>
                                </li>
                            </ul>
                        </div>
                        <div class="mt-8 pt-4 border-t border-[#f0f4f1]">
                            <Link href="/register" class="inline-flex items-center justify-center gap-2 w-full py-3 rounded-xl border border-[#d8e3dc] bg-[#f7faf8] text-[#14221d] text-xs font-bold hover:bg-[#eaf2ee] transition-colors">
                                <span>Daftar Sebagai Guru SLB</span>
                                <ArrowRight :size="14" class="stroke-[2.5]" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Tanya Jawab / FAQ (Accordion ala Menuin) -->
        <section id="faq" class="py-20 bg-white border-b border-[#e7eee9]">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#eefaf4] text-[#1e7253] text-xs font-bold rounded-full border border-[#d5f3e5]">
                        <HelpCircle :size="14" />
                        <span>Pertanyaan yang Sering Ditanyakan</span>
                    </div>
                    <h2 class="mt-3 font-display font-extrabold text-2xl sm:text-4xl text-[#14221d] tracking-tight">
                        Jawaban Seputar SignTech
                    </h2>
                    <p class="mt-2 text-sm text-[#617770]">
                        Informasi praktis seputar cara belajar dan penggunaan platform.
                    </p>
                </div>

                <!-- FAQ Accordion Items -->
                <div class="space-y-3.5">
                    <div
                        v-for="(faq, idx) in faqs"
                        :key="idx"
                        class="rounded-2xl border border-[#e5ece7] bg-[#fbfdfc] overflow-hidden transition-all"
                    >
                        <button
                            type="button"
                            class="w-full p-5 text-left flex items-center justify-between gap-4 font-display font-bold text-sm sm:text-base text-[#14221d] hover:text-[#167b71] transition-colors"
                            @click="toggleFaq(idx)"
                        >
                            <span>{{ faq.question }}</span>
                            <ChevronDown
                                :size="18"
                                class="shrink-0 transition-transform duration-200"
                                :class="{ 'rotate-180 text-[#34B182]': openFaqIndex === idx }"
                            />
                        </button>
                        <div
                            v-if="openFaqIndex === idx"
                            class="px-5 pb-5 text-xs sm:text-sm text-[#607770] leading-relaxed border-t border-[#f0f4f1] pt-3"
                        >
                            {{ faq.answer }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Final CTA Banner (Warm Forest Green ala Menuin) -->
        <section class="py-16 sm:py-20 bg-gradient-to-b from-[#143d34] to-[#0c2822] text-white relative overflow-hidden">
            <div class="pointer-events-none absolute -top-24 -left-24 w-80 h-80 rounded-full bg-[#34B182]/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 -right-24 w-80 h-80 rounded-full bg-[#C29B40]/20 blur-3xl"></div>

            <div class="relative mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 text-center">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-white/10 text-[#7de0b9] border border-white/15 mb-4">
                    <Sparkles :size="13" class="text-[#f4cd6f]" />
                    <span>Mulai Petualangan Belajarmu</span>
                </span>

                <h2 class="font-display font-extrabold text-3xl sm:text-5xl text-white tracking-tight leading-tight">
                    Siap Menguasai Bahasa Isyarat dengan Menyenangkan?
                </h2>

                <p class="mt-4 text-sm sm:text-base text-[#9bc0b7] max-w-xl mx-auto leading-relaxed">
                    Daftar gratis sekarang dan buka kemudahan belajar isyarat bagi siswa, guru, dan keluarga inklusif di seluruh Indonesia.
                </p>

                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3.5">
                    <Link
                        href="/register"
                        class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-[#C29B40] hover:bg-[#d8b04e] text-[#14221d] text-sm font-bold shadow-lg shadow-[#C29B40]/25 transition-all transform hover:-translate-y-0.5"
                    >
                        <span>Daftar Akun Gratis</span>
                        <ArrowRight :size="16" class="stroke-[2.5]" />
                    </Link>
                    <Link
                        href="/login"
                        class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-7 py-3.5 rounded-2xl border border-white/20 bg-white/10 text-white text-sm font-bold hover:bg-white/15 transition-colors"
                    >
                        <span>Masuk ke Akun</span>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer Section (Identik ala Menuin.id) -->
        <footer class="w-full min-h-[85vh] bg-[#0E59F9] text-white overflow-hidden pt-16 sm:pt-20 lg:pt-24 pb-8 sm:pb-12 px-6 sm:px-10 lg:px-14 select-none flex flex-col justify-between">
            <div class="mx-auto w-full max-w-[1400px] flex-1 flex flex-col justify-between">
                <!-- Top 4 Columns Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-10 lg:gap-8 items-start">
                    <!-- Column 1: Contact Us -->
                    <div class="lg:col-span-4 flex flex-col justify-start">
                        <h4 class="font-semibold uppercase text-[11px] sm:text-[12px] text-white/50 tracking-[0.22em] mb-5">
                            Contact Us
                        </h4>
                        <p class="text-[22px] sm:text-[24px] lg:text-[26px] font-medium leading-tight tracking-[-0.02em] text-white mb-10">
                            Ada pertanyaan?
                        </p>
                        <form @submit.prevent="sendContactMessage" class="max-w-sm">
                            <div class="relative border-b-2 border-white/70 focus-within:border-white pb-2 flex items-center justify-between group transition-colors">
                                <input
                                    v-model="contactMessage"
                                    type="text"
                                    placeholder="Ketik pesan Anda..."
                                    class="bg-transparent text-[15px] sm:text-[16px] font-normal placeholder:text-white/60 focus:outline-none w-full text-white pr-3"
                                />
                                <button
                                    type="submit"
                                    aria-label="Kirim ke WhatsApp"
                                    class="text-[14px] font-semibold text-white hover:text-white/80 active:scale-95 transition-all shrink-0 cursor-pointer flex items-center gap-1"
                                >
                                    <span>Kirim</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Column 2: Navigation -->
                    <div class="lg:col-span-3 flex flex-col justify-start">
                        <h4 class="font-semibold uppercase text-[11px] sm:text-[12px] text-white/50 tracking-[0.22em] mb-5">
                            Navigation
                        </h4>
                        <ul class="space-y-2 text-[22px] sm:text-[24px] lg:text-[26px] font-medium leading-tight tracking-[-0.02em] text-white">
                            <li><a class="hover:text-white/75 transition-colors inline-block" href="#alur">Tentang</a></li>
                            <li><a class="hover:text-white/75 transition-colors inline-block" href="#fitur">Fitur</a></li>
                            <li><a class="hover:text-white/75 transition-colors inline-block" href="#kamus">Kamus Isyarat</a></li>
                            <li><a class="hover:text-white/75 transition-colors inline-block" href="#manfaat">Untuk Guru</a></li>
                            <li><a class="hover:text-white/75 transition-colors inline-block" href="#faq">Tanya jawab</a></li>
                        </ul>
                    </div>

                    <!-- Column 3: Our Mission -->
                    <div class="lg:col-span-3 flex flex-col justify-start">
                        <h4 class="font-semibold uppercase text-[11px] sm:text-[12px] text-white/50 tracking-[0.22em] mb-5">
                            Our Mission
                        </h4>
                        <p class="text-[22px] sm:text-[24px] lg:text-[26px] font-medium leading-[1.3] tracking-[-0.02em] text-white max-w-[340px]">
                            Mewujudkan pembelajaran bahasa isyarat yang mudah, interaktif, dan inklusif bagi semua.
                        </p>
                    </div>

                    <!-- Column 4: Follow Us -->
                    <div class="lg:col-span-2 flex flex-col justify-start">
                        <h4 class="font-semibold uppercase text-[11px] sm:text-[12px] text-white/50 tracking-[0.22em] mb-5">
                            Follow Us
                        </h4>
                        <div class="flex items-center gap-5 pt-1 text-white/85">
                            <a href="https://twitter.com" target="_blank" rel="noopener noreferrer" aria-label="Twitter / X" class="hover:text-white hover:scale-110 transition-all duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    <path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"></path>
                                </svg>
                            </a>
                            <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" aria-label="Instagram" class="hover:text-white hover:scale-110 transition-all duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    <rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect>
                                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                    <line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line>
                                </svg>
                            </a>
                            <a href="https://wa.me/6282235594074" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp" class="hover:text-white hover:scale-110 transition-all duration-200">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                                </svg>
                            </a>
                            <a href="https://tiktok.com" target="_blank" rel="noopener noreferrer" aria-label="TikTok" class="hover:text-white hover:scale-110 transition-all duration-200">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"></path>
                                </svg>
                            </a>
                            <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" aria-label="Facebook" class="hover:text-white hover:scale-110 transition-all duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Bottom Centered Area: Caption + Huge Wordmark -->
                <div class="w-full flex flex-col items-center pt-16 sm:pt-20">
                    <div class="pt-2 pb-0 text-center">
                        <p class="text-[14px] sm:text-[16px] font-semibold tracking-[-0.01em] text-white">
                            Cara Menyenangkan Belajar Bahasa Isyarat
                        </p>
                    </div>
                    <div class="w-full -mt-4 sm:-mt-8 md:-mt-12 lg:-mt-16 flex items-center justify-center">
                        <svg
                            viewBox="0 0 1200 240"
                            class="w-full max-w-[1300px] h-auto select-none pointer-events-none drop-shadow-sm"
                            aria-label="signtech."
                        >
                            <text
                                x="50%"
                                y="70%"
                                text-anchor="middle"
                                dominant-baseline="middle"
                                font-family="'Giphurs', 'Poppins', system-ui, sans-serif"
                                font-weight="900"
                                font-size="210"
                                letter-spacing="-0.04em"
                                fill="#FFFFFF"
                            >
                                signtech.
                            </text>
                        </svg>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Detail Gesture Modal -->
        <div
            v-if="selectedGesture"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#14221d]/50 backdrop-blur-xs"
            @click.self="closeGestureModal"
        >
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl border border-[#e5ece7] relative">
                <button
                    type="button"
                    class="absolute top-4 right-4 w-8 h-8 rounded-full bg-[#f4f7f5] flex items-center justify-center text-[#6e857e] hover:bg-[#e8edea]"
                    aria-label="Tutup modal"
                    @click="closeGestureModal"
                >
                    <X :size="16" />
                </button>

                <div class="flex items-center gap-3 mb-4">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[#e8f7f0] text-[#1b735a] font-display font-extrabold text-xl shadow-xs">
                        {{ selectedGesture.code }}
                    </span>
                    <div>
                        <h3 class="font-display font-extrabold text-lg text-[#14221d]">{{ selectedGesture.title }}</h3>
                        <span class="text-xs text-[#718780] font-medium">{{ selectedGesture.category }} • {{ selectedGesture.handCount }}</span>
                    </div>
                </div>

                <div class="space-y-3.5 text-xs text-[#556d67]">
                    <div class="p-3.5 rounded-2xl bg-[#f8fbf9] border border-[#e5ece7]">
                        <b class="text-[#14221d] block mb-1">Cara Pembentukan Tangan:</b>
                        <p class="leading-relaxed">{{ selectedGesture.description }}</p>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-[#fef7ee] border border-[#faebd4] text-[#8f6d23]">
                        <b class="block mb-1">Tips Gerakan:</b>
                        <p class="leading-relaxed">{{ selectedGesture.tips }}</p>
                    </div>
                </div>

                <div class="mt-6 pt-3 flex items-center gap-2">
                    <Link
                        href="/practice"
                        class="inline-flex items-center justify-center gap-1.5 flex-1 py-2.5 rounded-xl bg-[#34B182] text-white font-bold text-xs shadow-xs hover:bg-[#28996e] transition-colors"
                    >
                        <span>Coba Praktik Sekarang</span>
                        <ArrowRight :size="13" class="stroke-[2.5]" />
                    </Link>
                    <button
                        type="button"
                        class="px-4 py-2.5 rounded-xl border border-[#d8e3dc] text-xs font-bold text-[#14221d] hover:bg-[#f4f8f6]"
                        @click="closeGestureModal"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.landing-page {
    font-family: 'Giphurs', 'Poppins', system-ui, sans-serif;
}
</style>
