<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ClipboardList,
    Pencil,
    RefreshCw,
    Save,
    School,
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
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <div class="st-back-bar">
                        <Link href="/teacher/classes" class="st-back">
                            <ArrowLeft :size="15" />
                            <span>Kembali ke daftar kelas</span>
                        </Link>
                        <span class="st-back-divider" aria-hidden="true"></span>
                        <span class="st-badge st-badge--primary">
                            <School :size="13" />
                            Detail Kelas
                        </span>
                    </div>

                    <h1>{{ classroom.name }}</h1>

                    <ul class="st-meta" aria-label="Informasi kelas">
                        <li class="is-primary"><Users :size="14" /> {{ students.length }} siswa</li>
                        <li><ClipboardList :size="14" /> {{ assignments.length }} tugas</li>
                    </ul>

                    <p class="st-description">{{ classroom.description || 'Belum ada deskripsi untuk kelas ini.' }}</p>
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
                    <h2>
                        <Users :size="18" /> Siswa Kelas
                        <span class="st-badge st-badge--neutral">{{ students.length }} orang</span>
                    </h2>
                    <span class="st-live-dot"><i aria-hidden="true"></i> Live</span>
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
                <div v-else class="st-empty">
                    <span>
                        Belum ada siswa di kelas ini. Tambah lewat email di atas, bagikan kode kelas
                        <b>{{ classroom.code }}</b> agar siswa bergabung sendiri, atau buatkan akun baru.
                    </span>
                    <Link href="/teacher/students" class="st-btn st-btn--outline st-btn--sm">
                        <UserPlus :size="13" /> Buka Manajemen Siswa
                    </Link>
                </div>
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
                <div v-else class="st-empty">
                    <span>Belum ada tugas untuk kelas ini.</span>
                    <Link href="/teacher/assignments" class="st-btn st-btn--outline st-btn--sm">
                        <ClipboardList :size="13" /> Buat Tugas Pertama
                    </Link>
                </div>
            </article>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Kotak kode bergabung di sisi kanan header */
.st-code-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    width: 260px;
    padding: 18px 20px;
    text-align: center;
    background: #fff;
    border: 1px solid var(--st-line);
    border-radius: var(--st-radius);
    box-shadow: var(--st-shadow);
}

.st-code-box small {
    font-size: 12px;
    font-weight: 600;
    line-height: 1.45;
    color: var(--st-muted);
}

.st-code {
    padding: 6px 16px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 24px;
    font-weight: 800;
    letter-spacing: 4px;
    color: var(--st-primary-dark);
    background: var(--st-primary-soft);
    border: 1px dashed var(--st-primary);
    border-radius: var(--st-radius-sm);
}

.st-code-box .st-btn {
    width: 100%;
}

.st-card-head h2 .st-badge {
    margin-left: 2px;
    font-size: 11.5px;
}

.st-invite {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}

.st-invite input {
    flex: 1 1 240px;
    min-width: 0;
    min-height: 44px;
    padding: 0 14px;
    font-size: 13.5px;
    background: var(--st-surface);
    border: 1px solid var(--st-line);
    border-radius: var(--st-radius-sm);
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.st-invite input:focus {
    background: #fff;
    border-color: var(--st-primary);
    box-shadow: 0 0 0 3px var(--st-primary-soft);
}

.st-table-wrap td small {
    display: block;
    font-size: 11.5px;
    color: var(--st-muted);
}

.st-cell-user {
    display: flex;
    flex-direction: column;
}

.st-live-dot {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    font-size: 11px;
    font-weight: 700;
    color: var(--st-primary-dark);
    background: var(--st-primary-soft);
    border: 1px solid var(--st-primary-line);
    border-radius: 999px;
}

.st-live-dot i {
    width: 7px;
    height: 7px;
    background: var(--st-primary);
    border-radius: 50%;
    animation: live-pulse 1.6s ease-in-out infinite;
}

@keyframes live-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.35; }
}

@media (max-width: 760px) {
    .st-code-box {
        width: auto;
    }

    .st-invite .st-btn {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .st-live-dot i {
        animation: none;
    }
}

.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
