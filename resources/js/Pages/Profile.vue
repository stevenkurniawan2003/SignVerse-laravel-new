<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    Award,
    CheckCircle,
    Flame,
    KeyRound,
    Mail,
    Save,
    Shield,
    Sparkles,
    Star,
    User as UserIcon,
} from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const student = computed(() => page.props.auth?.student || {});

const profileForm = useForm({
    name: user.value.name || '',
    email: user.value.email || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const profileSuccess = ref('');
const passwordSuccess = ref('');

function submitProfile() {
    profileForm.patch('/profile', {
        preserveScroll: true,
        onSuccess: () => {
            profileSuccess.value = 'Profil berhasil diperbarui di database.';
            setTimeout(() => {
                profileSuccess.value = '';
            }, 3500);
        },
    });
}

function submitPassword() {
    passwordForm.put('/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            passwordSuccess.value = 'Kata sandi berhasil diperbarui.';
            setTimeout(() => {
                passwordSuccess.value = '';
            }, 3500);
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Profil Akun" />

        <div class="profile-page">
            <header class="profile-page-heading">
                <div class="heading-badge">Akun Belajar SignTech · Data Terverifikasi Database</div>
                <h1>Profil & Keamanan Akun</h1>
                <p>Kelola identitas akun Anda, tinjau ringkasan gamifikasi nyata, dan perbarui kredensial keamanan.</p>
            </header>

            <div class="profile-grid">
                <!-- Kolom Kiri: Kartu Identitas & Statistik Database -->
                <div class="profile-left-col">
                    <section class="card profile-overview-card">
                        <div class="overview-hero">
                            <div class="avatar-bubble">
                                {{ student.initials || user.name?.charAt(0)?.toUpperCase() || 'U' }}
                            </div>
                            <div class="overview-info">
                                <h2>{{ user.name }}</h2>
                                <span class="role-badge">{{ String(user.role || 'student').toUpperCase() }}</span>
                                <p class="email-text">{{ user.email }}</p>
                            </div>
                        </div>

                        <div class="stats-overview-list">
                            <div class="stat-overview-row">
                                <div class="stat-icon-circle emerald">
                                    <Star :size="16" />
                                </div>
                                <div class="stat-copy">
                                    <small>Total Pengalaman</small>
                                    <b>{{ student.total_xp || 0 }} XP</b>
                                </div>
                            </div>

                            <div class="stat-overview-row">
                                <div class="stat-icon-circle gold">
                                    <Flame :size="16" />
                                </div>
                                <div class="stat-copy">
                                    <small>Streak Harian</small>
                                    <b>{{ student.current_streak || 0 }} Hari Aktif</b>
                                </div>
                            </div>

                            <div class="stat-overview-row">
                                <div class="stat-icon-circle accent">
                                    <Award :size="16" />
                                </div>
                                <div class="stat-copy">
                                    <small>Peringkat Level</small>
                                    <b>{{ student.level_name || 'Beginner' }}</b>
                                </div>
                            </div>

                            <div class="stat-overview-row">
                                <div class="stat-icon-circle gray">
                                    <Shield :size="16" />
                                </div>
                                <div class="stat-copy">
                                    <small>Streak Terpanjang</small>
                                    <b>{{ student.longest_streak || 0 }} Hari</b>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Kolom Kanan: Form Ubah Profil & Password -->
                <div class="profile-right-col">
                    <!-- Form Ubah Data Profil -->
                    <section class="card form-card">
                        <div class="card-section-title">
                            <UserIcon :size="18" class="text-primary" />
                            <div>
                                <h3>Informasi Profil</h3>
                                <p>Perbarui nama akun dan alamat surel Anda langsung ke basis data.</p>
                            </div>
                        </div>

                        <div v-if="profileSuccess" class="alert-success-box" role="status">
                            <CheckCircle :size="16" />
                            <span>{{ profileSuccess }}</span>
                        </div>

                        <form class="account-form" @submit.prevent="submitProfile">
                            <div class="form-group">
                                <label for="profile_name">Nama Lengkap</label>
                                <input
                                    id="profile_name"
                                    v-model="profileForm.name"
                                    type="text"
                                    required
                                    maxlength="60"
                                    placeholder="Masukkan nama lengkap"
                                    :class="{ 'has-error': profileForm.errors.name }"
                                />
                                <span v-if="profileForm.errors.name" class="field-error">
                                    {{ profileForm.errors.name }}
                                </span>
                            </div>

                            <div class="form-group">
                                <label for="profile_email">Alamat Email</label>
                                <input
                                    id="profile_email"
                                    v-model="profileForm.email"
                                    type="email"
                                    required
                                    placeholder="nama@email.com"
                                    :class="{ 'has-error': profileForm.errors.email }"
                                />
                                <span v-if="profileForm.errors.email" class="field-error">
                                    {{ profileForm.errors.email }}
                                </span>
                            </div>

                            <div class="form-action-row">
                                <button
                                    type="submit"
                                    class="primary-button"
                                    :disabled="profileForm.processing"
                                >
                                    <Save :size="15" />
                                    <span>{{ profileForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Form Ganti Kata Sandi -->
                    <section class="card form-card">
                        <div class="card-section-title">
                            <KeyRound :size="18" class="text-secondary" />
                            <div>
                                <h3>Perbarui Kata Sandi</h3>
                                <p>Gunakan kombinasi karakter yang aman untuk menjaga akun Anda.</p>
                            </div>
                        </div>

                        <div v-if="passwordSuccess" class="alert-success-box" role="status">
                            <CheckCircle :size="16" />
                            <span>{{ passwordSuccess }}</span>
                        </div>

                        <form class="account-form" @submit.prevent="submitPassword">
                            <div class="form-group">
                                <label for="current_password">Kata Sandi Saat Ini</label>
                                <input
                                    id="current_password"
                                    v-model="passwordForm.current_password"
                                    type="password"
                                    required
                                    placeholder="Masukkan kata sandi saat ini"
                                    :class="{ 'has-error': passwordForm.errors.current_password }"
                                />
                                <span v-if="passwordForm.errors.current_password" class="field-error">
                                    {{ passwordForm.errors.current_password }}
                                </span>
                            </div>

                            <div class="form-group">
                                <label for="new_password">Kata Sandi Baru</label>
                                <input
                                    id="new_password"
                                    v-model="passwordForm.password"
                                    type="password"
                                    required
                                    placeholder="Minimal 8 karakter"
                                    :class="{ 'has-error': passwordForm.errors.password }"
                                />
                                <span v-if="passwordForm.errors.password" class="field-error">
                                    {{ passwordForm.errors.password }}
                                </span>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Konfirmasi Kata Sandi Baru</label>
                                <input
                                    id="confirm_password"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    required
                                    placeholder="Ulangi kata sandi baru"
                                    :class="{ 'has-error': passwordForm.errors.password_confirmation }"
                                />
                                <span v-if="passwordForm.errors.password_confirmation" class="field-error">
                                    {{ passwordForm.errors.password_confirmation }}
                                </span>
                            </div>

                            <div class="form-action-row">
                                <button
                                    type="submit"
                                    class="secondary-button"
                                    :disabled="passwordForm.processing"
                                >
                                    <KeyRound :size="15" />
                                    <span>{{ passwordForm.processing ? 'Memproses...' : 'Perbarui Kata Sandi' }}</span>
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.profile-page {
    padding-bottom: 40px;
}

.profile-page-heading {
    margin-bottom: 24px;
}

.heading-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    color: #1e7253;
    background: #eefaf4;
    padding: 4px 10px;
    border-radius: 8px;
    margin-bottom: 8px;
}

.profile-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 24px;
    align-items: start;
}

@media (max-width: 900px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
}

.profile-overview-card {
    padding: 24px;
}

.overview-hero {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 1px solid #eef3f0;
}

.avatar-bubble {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #eefaf4;
    color: #34B182;
    border: 2px solid #bcecd6;
    display: grid;
    place-items: center;
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 12px;
}

.overview-info h2 {
    font-size: 18px;
    font-weight: 800;
    color: #14221d;
    margin: 0 0 4px;
}

.role-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 800;
    color: #C29B40;
    background: #fdf9ef;
    border: 1px solid #f1deaf;
    padding: 2px 8px;
    border-radius: 6px;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.email-text {
    font-size: 12px;
    color: #6d8277;
    margin: 0;
}

.stats-overview-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 20px;
}

.stat-overview-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: #fbfdfc;
    border: 1px solid #eef3f0;
    border-radius: 10px;
}

.stat-icon-circle {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.stat-icon-circle.emerald {
    background: #eefaf4;
    color: #34B182;
}

.stat-icon-circle.gold {
    background: #fdf9ef;
    color: #C29B40;
}

.stat-icon-circle.accent {
    background: #eef7f0;
    color: #5FAB6F;
}

.stat-icon-circle.gray {
    background: #f1f5f3;
    color: #64748b;
}

.stat-copy {
    display: flex;
    flex-direction: column;
}

.stat-copy small {
    font-size: 11px;
    color: #7b9186;
}

.stat-copy b {
    font-size: 13px;
    color: #14221d;
}

.profile-right-col {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.form-card {
    padding: 24px 28px;
}

.card-section-title {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding-bottom: 16px;
    border-bottom: 1px solid #eef3f0;
    margin-bottom: 20px;
}

.card-section-title h3 {
    font-size: 16px;
    font-weight: 800;
    color: #14221d;
    margin: 0 0 2px;
}

.card-section-title p {
    font-size: 12px;
    color: #6d8277;
    margin: 0;
}

.alert-success-box {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #eefaf4;
    border: 1px solid #bcecd6;
    color: #1e7253;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
}

.account-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-size: 12px;
    font-weight: 700;
    color: #2b3d34;
}

.form-group input {
    border: 1px solid #d5e2dc;
    background: #ffffff;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 13px;
    color: #14221d;
    font-family: inherit;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.form-group input:focus {
    border-color: #34B182;
    box-shadow: 0 0 0 3px rgba(52, 177, 130, 0.15);
}

.form-group input.has-error {
    border-color: #e53e3e;
}

.field-error {
    font-size: 11px;
    color: #e53e3e;
    font-weight: 600;
}

.form-action-row {
    display: flex;
    justify-content: flex-end;
    margin-top: 6px;
}
</style>
