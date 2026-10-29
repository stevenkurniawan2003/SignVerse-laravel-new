<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle,
    Eye,
    EyeOff,
    Hand,
    Lock,
    Mail,
    Shield,
    Sparkles,
    User,
    UserPlus,
} from '@lucide/vue';

const showPassword = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Daftar Akun Siswa" />

    <div class="auth-wrapper font-sans min-h-screen bg-[#f4f8f6] text-[#14221d] flex flex-col justify-center py-10 px-4 sm:px-6 lg:px-8">
        <div class="auth-container max-w-md w-full mx-auto">
            <!-- Brand Header -->
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center gap-3 transition hover:opacity-90">
                    <img :src="'/LOGO.png'" alt="Logo SignTech" class="h-11 w-11 object-contain" />
                    <span class="text-2xl font-bold tracking-tight text-[#14221d]">SignTech</span>
                </Link>
                <div class="inline-block mt-3 px-3 py-1 bg-[#eefaf4] text-[#1e7253] text-xs font-semibold rounded-full border border-[#d5f3e5]">
                    Pendaftaran Siswa Baru SLB & Inklusi
                </div>
                <h1 class="mt-4 text-2xl sm:text-3xl font-extrabold text-[#14221d] tracking-tight">
                    Mulai Belajar Bahasa Isyarat
                </h1>
                <p class="mt-1.5 text-sm text-[#52685d]">
                    Daftar akun gratis untuk mengakses latihan mandiri dan modul interaktif.
                </p>
            </div>

            <!-- Form Card -->
            <div class="card bg-white rounded-2xl shadow-xl shadow-[#14221d]/5 border border-[#e2ede6] p-6 sm:p-8">
                <form class="space-y-4" @submit.prevent="submit">
                    <!-- Name Field -->
                    <div>
                        <label for="name" class="block text-xs font-semibold text-[#14221d] mb-1.5">
                            Nama Lengkap Siswa
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#7b9186]">
                                <User :size="16" />
                            </div>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                autofocus
                                autocomplete="name"
                                placeholder="Masukkan nama lengkap"
                                class="w-full pl-10 pr-4 py-2.5 bg-[#fbfdfc] border border-[#d5e2dc] rounded-xl text-sm text-[#14221d] placeholder-[#94a39b] focus:bg-white focus:outline-none focus:border-[#34B182] focus:ring-3 focus:ring-[#34B182]/20 transition"
                                :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-100': form.errors.name }"
                            />
                        </div>
                        <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-600 font-medium">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Email Field -->
                    <div>
                        <label for="email" class="block text-xs font-semibold text-[#14221d] mb-1.5">
                            Alamat Email
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#7b9186]">
                                <Mail :size="16" />
                            </div>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="username"
                                placeholder="nama@sekolah.id"
                                class="w-full pl-10 pr-4 py-2.5 bg-[#fbfdfc] border border-[#d5e2dc] rounded-xl text-sm text-[#14221d] placeholder-[#94a39b] focus:bg-white focus:outline-none focus:border-[#34B182] focus:ring-3 focus:ring-[#34B182]/20 transition"
                                :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-100': form.errors.email }"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-600 font-medium">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-xs font-semibold text-[#14221d] mb-1.5">
                            Kata Sandi (Minimal 8 Karakter)
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#7b9186]">
                                <Lock :size="16" />
                            </div>
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                placeholder="Buat kata sandi yang aman"
                                class="w-full pl-10 pr-11 py-2.5 bg-[#fbfdfc] border border-[#d5e2dc] rounded-xl text-sm text-[#14221d] placeholder-[#94a39b] focus:bg-white focus:outline-none focus:border-[#34B182] focus:ring-3 focus:ring-[#34B182]/20 transition"
                                :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-100': form.errors.password }"
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#7b9186] hover:text-[#14221d] transition"
                                :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                @click="showPassword = !showPassword"
                            >
                                <component :is="showPassword ? EyeOff : Eye" :size="16" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-600 font-medium">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Password Confirmation -->
                    <div>
                        <label for="password_confirmation" class="block text-xs font-semibold text-[#14221d] mb-1.5">
                            Konfirmasi Kata Sandi
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#7b9186]">
                                <Lock :size="16" />
                            </div>
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                placeholder="Ulangi kata sandi"
                                class="w-full pl-10 pr-4 py-2.5 bg-[#fbfdfc] border border-[#d5e2dc] rounded-xl text-sm text-[#14221d] placeholder-[#94a39b] focus:bg-white focus:outline-none focus:border-[#34B182] focus:ring-3 focus:ring-[#34B182]/20 transition"
                                :class="{ 'border-red-400 focus:border-red-500 focus:ring-red-100': form.errors.password_confirmation }"
                            />
                        </div>
                        <p v-if="form.errors.password_confirmation" class="mt-1.5 text-xs text-red-600 font-medium">
                            {{ form.errors.password_confirmation }}
                        </p>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full mt-3 h-11 bg-[#34B182] hover:bg-[#279069] active:bg-[#1e7253] text-white font-semibold text-sm rounded-xl shadow-md shadow-[#34B182]/25 flex items-center justify-center gap-2 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <UserPlus :size="16" />
                        <span>{{ form.processing ? 'Mendaftarkan Akun...' : 'Daftar Sekarang' }}</span>
                    </button>
                </form>

                <!-- Login Call to Action -->
                <div class="mt-6 pt-4 text-center border-t border-[#edf3f0]">
                    <p class="text-xs text-[#52685d]">
                        Sudah memiliki akun SignTech?
                        <Link href="/login" class="font-bold text-[#34B182] hover:text-[#279069] ml-1 transition">
                            Masuk ke Akun
                        </Link>
                    </p>
                </div>
            </div>

            <!-- Footer note -->
            <div class="text-center mt-6 text-xs text-[#859c90]">
                <Link href="/" class="hover:text-[#14221d] transition">Kembali ke Beranda Utama</Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-wrapper {
    font-family: 'Poppins', sans-serif;
}
</style>
