<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { KeyRound, Pencil, Plus, Search, UserPlus, Users } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    students: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    status: { type: String, default: '' },
});

const students = ref([...props.students]);
const query = ref('');
const classFilter = ref('');

let timer = null;

async function poll() {
    try {
        const res = await fetch('/teacher/students/live', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            if (data.students) students.value = data.students;
        }
    } catch {}
}

const filtered = computed(() =>
    students.value.filter((row) => {
        const matchQuery =
            query.value.trim() === '' ||
            row.name.toLowerCase().includes(query.value.toLowerCase()) ||
            row.email.toLowerCase().includes(query.value.toLowerCase());
        const matchClass =
            classFilter.value === '' || row.classes.includes(classFilter.value);
        return matchQuery && matchClass;
    }),
);

const showCreate = ref(false);
const createForm = useForm({
    classroom_id: props.classes[0]?.id ?? '',
    name: '',
    email: '',
    password: '',
});

function create() {
    createForm.post('/teacher/students', {
        onSuccess: () => createForm.reset('name', 'email', 'password'),
    });
}

const editRow = ref(null);
const editForm = useForm({ id: null, name: '', email: '' });

function startEdit(row) {
    editRow.value = row;
    editForm.id = row.id;
    editForm.name = row.name;
    editForm.email = row.email;
}

function saveEdit() {
    editForm.put(`/teacher/students/${editForm.id}`, {
        onSuccess: () => (editRow.value = null),
    });
}

const pwRow = ref(null);
const pwForm = useForm({ id: null, password: '' });

function startPassword(row) {
    pwRow.value = row;
    pwForm.id = row.id;
    pwForm.password = '';
}

function savePassword() {
    pwForm.put(`/teacher/students/${pwForm.id}/password`, {
        onSuccess: () => (pwRow.value = null),
    });
}

function timeAgo(iso) {
    if (!iso) return 'Belum pernah aktif';
    const min = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
    if (min < 1) return 'Baru saja';
    if (min < 60) return `${min} menit lalu`;
    const hour = Math.floor(min / 60);
    if (hour < 24) return `${hour} jam lalu`;
    return `${Math.floor(hour / 24)} hari lalu`;
}

onMounted(() => { timer = setInterval(poll, 10000); });
onBeforeUnmount(() => timer && clearInterval(timer));
</script>

<template>
    <AppLayout>
        <Head title="Manajemen Siswa" />

        <div class="st-page">
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <span class="st-badge st-badge--primary">Manajemen Siswa</span>
                    <h1>Siswa Saya</h1>
                    <p>Pantau progress belajar siswa realtime dan kelola akun mereka: buat akun baru, ubah data, reset kata sandi.</p>
                </div>
                <div class="st-page-header-aside">
                    <button type="button" class="st-btn st-btn--primary" @click="showCreate = !showCreate">
                        <Plus :size="16" /> {{ showCreate ? 'Tutup Formulir' : 'Buat Akun Siswa' }}
                    </button>
                </div>
            </header>

            <transition name="fade">
                <form v-if="showCreate" class="st-card st-form" @submit.prevent="create">
                    <h2><UserPlus :size="18" /> Akun Siswa Baru</h2>
                    <div class="st-form-row">
                        <label class="st-field">
                            <span>Kelas <em>*</em></span>
                            <select v-model="createForm.classroom_id" required>
                                <option v-for="klass in classes" :key="klass.id" :value="klass.id">{{ klass.name }}</option>
                            </select>
                        </label>
                        <label class="st-field">
                            <span>Nama lengkap <em>*</em></span>
                            <input v-model="createForm.name" type="text" required maxlength="255" />
                        </label>
                    </div>
                    <div class="st-form-row">
                        <label class="st-field">
                            <span>Email <em>*</em></span>
                            <input v-model="createForm.email" type="email" required maxlength="255" />
                        </label>
                        <label class="st-field">
                            <span>Kata sandi (min. 8 karakter) <em>*</em></span>
                            <input v-model="createForm.password" type="text" required minlength="8" autocomplete="new-password" />
                        </label>
                    </div>
                    <p v-if="classes.length === 0" class="st-field-error">Anda belum punya kelas — <Link href="/teacher/classes" class="st-link">buat kelas dulu</Link>.</p>
                    <p v-if="createForm.errors.email" class="st-field-error">{{ createForm.errors.email }}</p>
                    <p v-if="createForm.errors.password" class="st-field-error">{{ createForm.errors.password }}</p>
                    <div class="st-form-actions">
                        <button type="submit" :disabled="createForm.processing || classes.length === 0" class="st-btn st-btn--primary">
                            {{ createForm.processing ? 'Membuat akun...' : 'Buat Akun & Masukkan ke Kelas' }}
                        </button>
                    </div>
                </form>
            </transition>

            <div class="st-student-filter">
                <label class="st-search">
                    <Search :size="15" />
                    <input v-model="query" type="search" placeholder="Cari nama atau email siswa..." />
                </label>
                <select v-model="classFilter" class="st-filter-select" aria-label="Filter kelas">
                    <option value="">Semua kelas</option>
                    <option v-for="klass in classes" :key="klass.name" :value="klass.name">{{ klass.name }}</option>
                </select>
            </div>

            <article class="st-card">
                <div class="st-card-head">
                    <h2><Users :size="18" /> Daftar Siswa &nbsp;<small>{{ filtered.length }}</small></h2>
                    <span class="st-live-dot">Live</span>
                </div>
                <div v-if="filtered.length" class="st-table-wrap">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Kelas</th>
                                <th>Latihan</th>
                                <th>Skor rata-rata</th>
                                <th>XP</th>
                                <th>Level</th>
                                <th>Aktivitas terakhir</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filtered" :key="row.id">
                                <td>
                                    <div class="st-cell-user">
                                        <Link :href="`/teacher/students/${row.id}`" class="st-link"><b>{{ row.name }}</b></Link>
                                        <small>{{ row.email }}</small>
                                    </div>
                                </td>
                                <td>
                                    <span v-for="name in row.classes" :key="name" class="st-chip">{{ name }}</span>
                                </td>
                                <td>{{ row.sessions }}</td>
                                <td>{{ row.avg_score }}%</td>
                                <td>{{ row.xp }}</td>
                                <td>{{ row.level }}</td>
                                <td>{{ timeAgo(row.last_activity) }}</td>
                                <td class="st-actions">
                                    <button type="button" class="st-btn st-btn--sm" title="Edit data" @click="startEdit(row)"><Pencil :size="13" /></button>
                                    <button type="button" class="st-btn st-btn--sm" title="Reset kata sandi" @click="startPassword(row)"><KeyRound :size="13" /></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="st-empty">Tidak ada siswa yang cocok. Buat kelas di <Link href="/teacher/classes" class="st-link">Manajemen Kelas</Link>, lalu buatkan akun siswa lewat tombol “Buat Akun Siswa”.</p>
            </article>

            <!-- Modal edit siswa -->
            <div v-if="editRow" class="st-modal-scrim" @click.self="editRow = null">
                <form class="st-card st-form st-modal" @submit.prevent="saveEdit">
                    <h2><Pencil :size="18" /> Edit Siswa</h2>
                    <label class="st-field">
                        <span>Nama <em>*</em></span>
                        <input v-model="editForm.name" type="text" required maxlength="255" />
                    </label>
                    <label class="st-field">
                        <span>Email <em>*</em></span>
                        <input v-model="editForm.email" type="email" required maxlength="255" />
                    </label>
                    <p v-if="editForm.errors.email" class="st-field-error">{{ editForm.errors.email }}</p>
                    <div class="st-form-actions">
                        <button type="button" class="st-btn" @click="editRow = null">Batal</button>
                        <button type="submit" :disabled="editForm.processing" class="st-btn st-btn--primary">Simpan</button>
                    </div>
                </form>
            </div>

            <!-- Modal reset sandi -->
            <div v-if="pwRow" class="st-modal-scrim" @click.self="pwRow = null">
                <form class="st-card st-form st-modal" @submit.prevent="savePassword">
                    <h2><KeyRound :size="18" /> Reset Kata Sandi — {{ pwRow.name }}</h2>
                    <label class="st-field">
                        <span>Kata sandi baru (min. 8 karakter) <em>*</em></span>
                        <input v-model="pwForm.password" type="text" required minlength="8" autocomplete="new-password" />
                    </label>
                    <p v-if="pwForm.errors.password" class="st-field-error">{{ pwForm.errors.password }}</p>
                    <div class="st-form-actions">
                        <button type="button" class="st-btn" @click="pwRow = null">Batal</button>
                        <button type="submit" :disabled="pwForm.processing" class="st-btn st-btn--primary">Simpan Sandi</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.st-form h2 {
    margin: 0 0 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 800;
}

.st-form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
}

.st-student-filter {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.st-search {
    flex: 1;
    min-width: 240px;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1px solid #e2ede6;
    border-radius: 10px;
    padding: 8px 14px;
    color: #7b9186;
}

.st-search input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 13.5px;
    background: transparent;
}

.st-filter-select {
    padding: 9px 14px;
    border: 1px solid #e2ede6;
    border-radius: 10px;
    background: #fff;
    font-size: 13px;
}

.st-card-head h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 800;
}

.st-cell-user {
    display: flex;
    flex-direction: column;
}

.st-cell-user small {
    color: #7b9186;
    font-size: 11px;
}

.st-chip {
    display: inline-block;
    margin: 1px 4px 1px 0;
    font-size: 11px;
    font-weight: 700;
    background: #eefaf4;
    color: #1e7253;
    border: 1px solid #d5f3e5;
    border-radius: 999px;
    padding: 2px 8px;
}

.st-actions {
    display: flex;
    gap: 6px;
}

.st-live-dot {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    color: #1e7253;
    background: #eefaf4;
    border: 1px solid #d5f3e5;
    border-radius: 999px;
    padding: 3px 10px;
}

.st-modal-scrim {
    position: fixed;
    inset: 0;
    background: rgba(20, 34, 29, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 20px;
}

.st-modal {
    width: 100%;
    max-width: 420px;
}

.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
