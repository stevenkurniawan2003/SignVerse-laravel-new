<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ClipboardList, GraduationCap, KeyRound, Mail, Save, School, ShieldCheck, Users } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    profile: { type: Object, default: () => ({}) },
    teaching: { type: Object, default: () => ({}) },
    classes: { type: Array, default: () => [] },
});

const initials = computed(() =>
    (props.profile.name || 'G')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('')
);

const joinedLabel = computed(() =>
    props.profile.joined_at
        ? new Date(props.profile.joined_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
        : '-'
);

const profileForm = useForm({
    name: props.profile.name || '',
    email: props.profile.email || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submitProfile() {
    profileForm.patch('/profile', { preserveScroll: true });
}

function submitPassword() {
    passwordForm.put('/password', {
        preserveScroll: true,
        errorBag: 'updatePassword',
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Profil Guru" />

        <div class="st-page tp">
            <!-- Identitas guru -->
            <header class="tp-hero">
                <span class="tp-avatar">{{ initials }}</span>
                <div class="tp-identity">
                    <span class="tp-role"><GraduationCap :size="14" /> Guru</span>
                    <h1>{{ profile.name }}</h1>
                    <p><Mail :size="14" /> {{ profile.email }}</p>
                    <small>Bergabung sejak {{ joinedLabel }}</small>
                </div>
            </header>

            <!-- Ringkasan mengajar -->
            <section class="tp-stats" aria-label="Ringkasan mengajar">
                <article>
                    <span class="tp-stat-icon"><School :size="20" /></span>
                    <div><b>{{ teaching.classes ?? 0 }}</b><small>Kelas diampu</small></div>
                </article>
                <article>
                    <span class="tp-stat-icon"><Users :size="20" /></span>
                    <div><b>{{ teaching.students ?? 0 }}</b><small>Siswa terdaftar</small></div>
                </article>
                <article>
                    <span class="tp-stat-icon"><ClipboardList :size="20" /></span>
                    <div><b>{{ teaching.assignments ?? 0 }}</b><small>Tugas dibuat ({{ teaching.active_assignments ?? 0 }} aktif)</small></div>
                </article>
            </section>

            <div class="tp-grid">
                <!-- Data akun -->
                <section class="st-panel tp-panel">
                    <h2><ShieldCheck :size="18" /> Informasi Akun</h2>
                    <p class="tp-desc">Nama ini tampil di kelas dan tugas yang dilihat siswa.</p>

                    <form class="tp-form" @submit.prevent="submitProfile">
                        <label>
                            <span>Nama lengkap</span>
                            <input v-model="profileForm.name" type="text" required maxlength="255" autocomplete="name" />
                            <em v-if="profileForm.errors.name">{{ profileForm.errors.name }}</em>
                        </label>
                        <label>
                            <span>Email</span>
                            <input v-model="profileForm.email" type="email" required maxlength="255" autocomplete="email" />
                            <em v-if="profileForm.errors.email">{{ profileForm.errors.email }}</em>
                        </label>
                        <button type="submit" class="st-btn st-btn--primary" :disabled="profileForm.processing || !profileForm.isDirty">
                            <Save :size="16" /> {{ profileForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                        </button>
                    </form>
                </section>

                <!-- Kata sandi -->
                <section class="st-panel tp-panel">
                    <h2><KeyRound :size="18" /> Ubah Kata Sandi</h2>
                    <p class="tp-desc">Gunakan minimal 8 karakter dan jangan bagikan kata sandi ke siswa.</p>

                    <form class="tp-form" @submit.prevent="submitPassword">
                        <label>
                            <span>Kata sandi saat ini</span>
                            <input v-model="passwordForm.current_password" type="password" required autocomplete="current-password" />
                            <em v-if="passwordForm.errors.current_password">{{ passwordForm.errors.current_password }}</em>
                        </label>
                        <label>
                            <span>Kata sandi baru</span>
                            <input v-model="passwordForm.password" type="password" required autocomplete="new-password" />
                            <em v-if="passwordForm.errors.password">{{ passwordForm.errors.password }}</em>
                        </label>
                        <label>
                            <span>Ulangi kata sandi baru</span>
                            <input v-model="passwordForm.password_confirmation" type="password" required autocomplete="new-password" />
                        </label>
                        <button type="submit" class="st-btn st-btn--primary" :disabled="passwordForm.processing">
                            <KeyRound :size="16" /> {{ passwordForm.processing ? 'Menyimpan...' : 'Ganti Kata Sandi' }}
                        </button>
                    </form>
                </section>
            </div>

            <!-- Kelas yang diampu -->
            <section class="st-panel tp-panel">
                <div class="tp-panel-head">
                    <h2><School :size="18" /> Kelas yang Diampu</h2>
                    <Link href="/teacher/classes" class="st-link">Kelola kelas</Link>
                </div>
                <ul v-if="classes.length" class="tp-classes">
                    <li v-for="room in classes" :key="room.id">
                        <Link :href="`/teacher/classes/${room.id}`">
                            <b>{{ room.name }}</b>
                            <small>{{ room.students_count }} siswa</small>
                        </Link>
                        <span class="tp-code" title="Kode bergabung kelas">{{ room.code }}</span>
                    </li>
                </ul>
                <div v-else class="st-empty">Belum ada kelas. <Link href="/teacher/classes" class="st-link">Buat kelas pertama</Link>.</div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.tp {
    display: grid;
    gap: 18px;
    padding-bottom: 32px;
}

.tp-hero {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 24px;
    background: linear-gradient(120deg, #ffffff, #eefaf4);
    border: 1px solid #e2ede6;
    border-radius: 20px;
}

.tp-avatar {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 84px;
    height: 84px;
    font-size: 30px;
    font-weight: 800;
    color: #fff;
    background: #34b182;
    border-radius: 24px;
}

.tp-identity {
    display: grid;
    gap: 4px;
    min-width: 0;
}

.tp-role {
    display: inline-flex;
    align-items: center;
    justify-self: start;
    gap: 6px;
    padding: 3px 10px;
    font-size: 12px;
    font-weight: 700;
    color: #1e7253;
    background: #fff;
    border: 1px solid #d5f3e5;
    border-radius: 999px;
}

.tp-identity h1 {
    margin: 2px 0 0;
    font-size: 26px;
    font-weight: 800;
    color: #14221d;
}

.tp-identity p {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    font-size: 15px;
    color: #52685d;
    word-break: break-all;
}

.tp-identity small {
    font-size: 13px;
    color: #859c90;
}

.tp-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.tp-stats article {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    background: #fff;
    border: 1px solid #e2ede6;
    border-radius: 18px;
}

.tp-stat-icon {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 46px;
    height: 46px;
    color: #34b182;
    background: #eefaf4;
    border-radius: 14px;
}

.tp-stats b {
    display: block;
    font-size: 26px;
    font-weight: 800;
    color: #14221d;
}

.tp-stats small {
    font-size: 13px;
    color: #6b8176;
}

.tp-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    align-items: start;
}

.tp-panel {
    display: grid;
    gap: 12px;
    padding: 20px;
    border-radius: 20px;
}

.tp-panel h2 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 17px;
    font-weight: 800;
    color: #14221d;
}

.tp-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.tp-desc {
    margin: -4px 0 0;
    font-size: 14px;
    color: #6b8176;
}

.tp-form {
    display: grid;
    gap: 12px;
}

.tp-form label {
    display: grid;
    gap: 6px;
}

.tp-form label span {
    font-size: 13px;
    font-weight: 700;
    color: #14221d;
}

.tp-form input {
    padding: 11px 12px;
    font-size: 15px;
    background: #fff;
    border: 1px solid #d5e2dc;
    border-radius: 10px;
}

.tp-form input:focus {
    border-color: #34b182;
    outline: 3px solid #d5f3e5;
}

.tp-form em {
    font-size: 13px;
    font-style: normal;
    font-weight: 600;
    color: #b42318;
}

.tp-form .st-btn {
    justify-self: start;
}

.tp-classes {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 10px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.tp-classes li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: #fbfdfc;
    border: 1px solid #e2ede6;
    border-radius: 14px;
}

.tp-classes a {
    display: grid;
    flex: 1;
    min-width: 0;
    color: inherit;
}

.tp-classes b {
    font-size: 14px;
    color: #14221d;
}

.tp-classes small {
    font-size: 12px;
    color: #6b8176;
}

.tp-code {
    flex-shrink: 0;
    padding: 4px 10px;
    font-family: ui-monospace, monospace;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 1.5px;
    color: #1e7253;
    background: #eefaf4;
    border: 1px dashed #9fdcbf;
    border-radius: 10px;
}

@media (max-width: 900px) {
    .tp-grid,
    .tp-stats {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 560px) {
    .tp-hero {
        flex-direction: column;
        align-items: flex-start;
        padding: 18px 16px;
    }

    .tp-form .st-btn {
        justify-self: stretch;
    }
}
</style>
