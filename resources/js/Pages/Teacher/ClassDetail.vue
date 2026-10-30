<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ClipboardList,
    KeyRound,
    Pencil,
    RefreshCw,
    Save,
    Trash2,
    UserPlus,
    UserRoundMinus,
    Users,
} from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    classroom: { type: Object, required: true },
    students: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
    status: { type: String, default: '' },
    error: { type: String, default: '' },
});

const classroom = ref({ ...props.classroom });
const students = ref([...props.students]);
const showEdit = ref(false);
const inviteForm = useForm({ email: '' });
const editForm = useForm({ name: classroom.value.name, description: classroom.value.description ?? '' });

let timer = null;
const base = `/teacher/classes/${classroom.value.id}`;

async function poll() {
    try {
        const res = await fetch(`${base}/live`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            if (data.students) students.value = data.students;
        }
    } catch {}
}

function removeStudent(row) {
    if (!confirm(`Keluarkan ${row.name} dari kelas ${classroom.value.name}? Riwayat belajarnya tetap tersimpan.`)) return;
    useForm({}).delete(`${base}/students/${row.id}`);
}

function destroy() {
    if (!confirm(`Hapus kelas ${classroom.value.name}? Tugas yang belum dikerjakan ikut terhapus. Akun siswa, riwayat belajar, dan XP tugas yang sudah selesai tetap aman.`)) return;
    useForm({}).delete(base);
}

function save() {
    editForm.put(base);
}

function invite() {
    inviteForm
        .transform((data) => ({ email: data.email.trim() }))
        .post(`${base}/students`, { onSuccess: () => inviteForm.reset() });
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
        <Head :title="`Kelas ${classroom.name}`" />

        <div class="st-page">
            <header class="st-page-header">
                <div class="st-page-header-main">
                    <Link href="/teacher/classes" class="st-back"><ArrowLeft :size="14" /> Kembali ke daftar kelas</Link>
                    <span class="st-badge st-badge--primary">Detail Kelas</span>
                    <h1>{{ classroom.name }}</h1>
                    <p>{{ classroom.description || 'Tanpa deskripsi.' }}</p>
                </div>
                <div class="st-page-header-aside st-code-box">
                    <small>Kode bergabung</small>
                    <b class="st-code">{{ classroom.code }}</b>
                    <small>Minta siswa memasukkan kode ini di menu Tugas mereka.</small>
                    <button type="button" class="st-btn st-btn--sm" @click="useForm({}).post(`${base}/code`)">
                        <RefreshCw :size="13" /> Acak Ulang Kode
                    </button>
                </div>
            </header>

            <div class="st-toolbar">
                <button type="button" class="st-btn" @click="showEdit = !showEdit"><Pencil :size="15" /> {{ showEdit ? 'Tutup' : 'Edit Kelas' }}</button>
                <Link :href="`/teacher/assignments`" class="st-btn"><ClipboardList :size="15" /> Beri Tugas Kelas Ini</Link>
                <button type="button" class="st-btn st-btn--danger" @click="destroy"><Trash2 :size="15" /> Hapus Kelas</button>
            </div>

            <transition name="fade">
                <form v-if="showEdit" class="st-card st-form" @submit.prevent="save">
                    <label class="st-field">
                        <span>Nama kelas <em>*</em></span>
                        <input v-model="editForm.name" type="text" required maxlength="120" />
                    </label>
                    <label class="st-field">
                        <span>Deskripsi</span>
                        <textarea v-model="editForm.description" rows="2" maxlength="500"></textarea>
                    </label>
                    <div class="st-form-actions">
                        <button type="submit" :disabled="editForm.processing" class="st-btn st-btn--primary">
                            <Save :size="14" /> {{ editForm.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </transition>

            <article class="st-card">
                <div class="st-card-head">
                    <h2><Users :size="18" /> Siswa Kelas &nbsp;<small class="st-page-header-aside">{{ students.length }} orang</small></h2>
                    <span class="st-live-dot">Live</span>
                </div>

                <form class="st-invite" @submit.prevent="invite">
                    <input
                        v-model="inviteForm.email"
                        type="email"
                        required
                        placeholder="Email siswa yang sudah punya akun"
                    />
                    <button type="submit" :disabled="inviteForm.processing" class="st-btn st-btn--primary">
                        <UserPlus :size="14" /> {{ inviteForm.processing ? 'Menambahkan...' : 'Tambah ke Kelas' }}
                    </button>
                </form>
                <p v-if="inviteForm.errors.email" class="st-field-error">{{ inviteForm.errors.email }}</p>

                <div v-if="students.length" class="st-table-wrap">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Latihan</th>
                                <th>Skor rata-rata</th>
                                <th>Dikuasai</th>
                                <th>XP</th>
                                <th>Aktivitas terakhir</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in students" :key="row.id">
                                <td>
                                    <div class="st-cell-user">
                                        <Link :href="`/teacher/students/${row.id}`" class="st-link"><b>{{ row.name }}</b></Link>
                                        <small>{{ row.email }}</small>
                                    </div>
                                </td>
                                <td>{{ row.sessions }}</td>
                                <td>{{ row.avg_score }}%</td>
                                <td>{{ row.mastered }} isyarat</td>
                                <td>{{ row.xp }}</td>
                                <td>{{ timeAgo(row.last_activity) }}</td>
                                <td>
                                    <button type="button" class="st-btn st-btn--sm st-btn--danger" @click="removeStudent(row)">
                                        <UserRoundMinus :size="13" /> Keluarkan
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="st-empty">
                    Belum ada siswa di kelas ini. Tambah lewat email di atas (untuk akun yang sudah ada), buatkan akun baru di
                    <Link href="/teacher/students" class="st-link">Manajemen Siswa</Link>, atau bagikan kode kelasnya agar siswa bergabung sendiri.
                </p>
            </article>

            <article class="st-card">
                <div class="st-card-head">
                    <h2><ClipboardList :size="18" /> Tugas Kelas Ini</h2>
                </div>
                <div v-if="assignments.length" class="st-table-wrap">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Tugas</th>
                                <th>Isyarat</th>
                                <th>Deadline</th>
                                <th>Progres selesai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in assignments" :key="row.id">
                                <td><Link :href="`/teacher/assignments/${row.id}`" class="st-link">{{ row.title }}</Link></td>
                                <td>{{ row.lesson }} ({{ row.language }})</td>
                                <td>{{ row.due_at ? new Date(row.due_at).toLocaleDateString('id-ID') : 'Tanpa deadline' }}</td>
                                <td>{{ row.completed_count }} / {{ row.submissions_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="st-empty">Belum ada tugas untuk kelas ini. <Link href="/teacher/assignments" class="st-link">Buat tugas pertama</Link>.</p>
            </article>
        </div>
    </AppLayout>
</template>

<style scoped>
.st-back {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12.5px;
    font-weight: 700;
    color: #1e7253;
    text-decoration: none;
    margin-bottom: 6px;
}

.st-code-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    text-align: center;
    background: #fff;
    border: 1px solid #e2ede6;
    border-radius: 16px;
    padding: 16px 20px;
    box-shadow: 0 10px 30px rgba(20, 34, 29, 0.06);
}

.st-code-box small {
    color: #7b9186;
    font-weight: 600;
}

.st-code {
    font-family: monospace;
    font-size: 24px;
    font-weight: 800;
    letter-spacing: 4px;
    background: #f5fbf7;
    border: 1px dashed #9fd6ba;
    color: #1e7253;
    border-radius: 10px;
    padding: 6px 16px;
}

.st-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
}

.st-card-head h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 800;
}

.st-form {
    margin-bottom: 18px;
}

.st-invite {
    display: flex;
    gap: 10px;
    margin-bottom: 14px;
}

.st-invite input {
    flex: 1;
    padding: 9px 14px;
    border: 1px solid #d5e2dc;
    border-radius: 10px;
    font-size: 13.5px;
    background: #fbfdfc;
}

.st-table-wrap td small {
    display: block;
    color: #7b9186;
    font-size: 11px;
}

.st-cell-user {
    display: flex;
    flex-direction: column;
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

.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
