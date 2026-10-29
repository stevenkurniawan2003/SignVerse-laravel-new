<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    Award,
    CheckCircle,
    Flame,
    KeyRound,
    Save,
    Shield,
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
            profileSuccess.value = 'Profil berhasil diperbarui.';
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

        <div class="st-page">
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <span class="st-badge st-badge--primary">Akun Saya</span>
                    <h1>Profil & Keamanan</h1>
                    <p>Kelola identitas akun, lihat ringkasan belajarmu, dan perbarui kata sandi.</p>
                </div>
            </header>

            <div class="profile-grid">
                <!-- Identitas & ringkasan -->
                <section class="st-panel profile-card">
                    <div class="identity-block">
                        <div class="avatar-bubble">
                            {{ user.initials || user.name?.charAt(0)?.toUpperCase() || 'U' }}
                        </div>
                        <h2>{{ user.name }}</h2>
                        <span class="st-badge st-badge--gold">{{ user.role === 'teacher' ? 'Guru' : 'Siswa' }}</span>
                        <p>{{ user.email }}</p>
                    </div>

                    <ul class="profile-stats">
                        <li>
                            <span class="st-icon st-icon--sm st-icon--primary"><Star :size="16" /></span>
                            <small>Total XP</small>
                            <b>{{ student.total_xp || 0 }} XP</b>
                        </li>
                        <li>
                            <span class="st-icon st-icon--sm st-icon--gold"><Flame :size="16" /></span>
                            <small>Streak harian</small>
                            <b>{{ student.current_streak || 0 }} hari</b>
                        </li>
                        <li>
                            <span class="st-icon st-icon--sm st-icon--green"><Award :size="16" /></span>
                            <small>Level</small>
                            <b>{{ student.level?.name || 'Beginner' }}</b>
                        </li>
                        <li>
                            <span class="st-icon st-icon--sm st-icon--neutral"><Shield :size="16" /></span>
                            <small>Streak terpanjang</small>
                            <b>{{ student.longest_streak || 0 }} hari</b>
                        </li>
                    </ul>
                </section>

                <div class="profile-forms">
                    <!-- Form profil -->
                    <section class="st-panel">
                        <div class="st-panel-head form-head">
                            <div class="form-title">
                                <span class="st-icon st-icon--primary"><UserIcon :size="18" /></span>
                                <div>
                                    <h2 class="st-panel-title">Informasi Profil</h2>
                                    <p class="st-panel-desc">Perbarui nama dan alamat email akunmu.</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="profileSuccess" class="st-alert st-alert--success form-alert" role="status">
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
                                    autocomplete="name"
                                    placeholder="Masukkan nama lengkap"
                                    :class="{ 'has-error': profileForm.errors.name }"
                                />
                                <span v-if="profileForm.errors.name" class="field-error">{{ profileForm.errors.name }}</span>
                            </div>

                            <div class="form-group">
                                <label for="profile_email">Alamat Email</label>
                                <input
                                    id="profile_email"
                                    v-model="profileForm.email"
                                    type="email"
                                    required
                                    autocomplete="email"
                                    placeholder="nama@email.com"
                                    :class="{ 'has-error': profileForm.errors.email }"
                                />
                                <span v-if="profileForm.errors.email" class="field-error">{{ profileForm.errors.email }}</span>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="st-btn st-btn--primary" :disabled="profileForm.processing">
                                    <Save :size="16" />
                                    <span>{{ profileForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- Form kata sandi -->
                    <section class="st-panel">
                        <div class="st-panel-head form-head">
                            <div class="form-title">
                                <span class="st-icon st-icon--gold"><KeyRound :size="18" /></span>
                                <div>
                                    <h2 class="st-panel-title">Ubah Kata Sandi</h2>
                                    <p class="st-panel-desc">Gunakan kata sandi yang kuat agar akunmu tetap aman.</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="passwordSuccess" class="st-alert st-alert--success form-alert" role="status">
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
                                    autocomplete="current-password"
                                    placeholder="Masukkan kata sandi saat ini"
                                    :class="{ 'has-error': passwordForm.errors.current_password }"
                                />
                                <span v-if="passwordForm.errors.current_password" class="field-error">{{ passwordForm.errors.current_password }}</span>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_password">Kata Sandi Baru</label>
                                    <input
                                        id="new_password"
                                        v-model="passwordForm.password"
                                        type="password"
                                        required
                                        autocomplete="new-password"
                                        placeholder="Minimal 8 karakter"
                                        :class="{ 'has-error': passwordForm.errors.password }"
                                    />
                                    <span v-if="passwordForm.errors.password" class="field-error">{{ passwordForm.errors.password }}</span>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">Konfirmasi Kata Sandi</label>
                                    <input
                                        id="confirm_password"
                                        v-model="passwordForm.password_confirmation"
                                        type="password"
                                        required
                                        autocomplete="new-password"
                                        placeholder="Ulangi kata sandi baru"
                                        :class="{ 'has-error': passwordForm.errors.password_confirmation }"
                                    />
                                    <span v-if="passwordForm.errors.password_confirmation" class="field-error">{{ passwordForm.errors.password_confirmation }}</span>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="st-btn st-btn--secondary" :disabled="passwordForm.processing">
                                    <KeyRound :size="16" />
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
.profile-grid {
    display: grid;
    grid-template-columns: 300px minmax(0, 1fr);
    gap: 20px;
    align-items: start;
}

.identity-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding-bottom: 20px;
    text-align: center;
    border-bottom: 1px solid var(--st-line-soft);
}

.avatar-bubble {
    display: grid;
    place-items: center;
    width: 76px;
    height: 76px;
    margin-bottom: 6px;
    font-size: 28px;
    font-weight: 800;
    color: var(--st-primary-dark);
    background: var(--st-primary-soft);
    border: 2px solid var(--st-primary-line);
    border-radius: 50%;
}

.identity-block h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: var(--st-ink);
}

.identity-block p {
    margin: 2px 0 0;
    font-size: 13px;
    color: var(--st-muted);
    word-break: break-all;
}

.profile-stats {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin: 18px 0 0;
    padding: 0;
    list-style: none;
}

.profile-stats li {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: var(--st-surface);
    border: 1px solid var(--st-line-soft);
    border-radius: 12px;
}

.profile-stats small {
    font-size: 13px;
    color: var(--st-muted);
}

.profile-stats b {
    font-size: 14px;
    color: var(--st-ink);
}

.profile-forms {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-head {
    padding-bottom: 16px;
    border-bottom: 1px solid var(--st-line-soft);
}

.form-title {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.form-title .st-panel-title {
    margin-top: 0;
}

.form-alert {
    margin-bottom: 16px;
}

.account-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-size: 13px;
    font-weight: 700;
    color: var(--st-ink);
}

.form-group input {
    min-height: 44px;
    padding: 10px 14px;
    font-family: inherit;
    font-size: 14px;
    color: var(--st-ink);
    background: #fff;
    border: 1px solid #d5e2dc;
    border-radius: 10px;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.form-group input:focus {
    border-color: var(--st-primary);
    box-shadow: 0 0 0 3px rgba(52, 177, 130, 0.15);
}

.form-group input.has-error {
    border-color: var(--st-danger);
}

.field-error {
    font-size: 12px;
    font-weight: 600;
    color: var(--st-danger);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
}

@media (max-width: 1000px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .form-row {
        grid-template-columns: 1fr;
    }

    .form-actions .st-btn {
        width: 100%;
    }
}
</style>

