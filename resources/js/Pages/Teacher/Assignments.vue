<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ClipboardList, Plus, Trash2, Users } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    assignments: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
    lessons: { type: Array, default: () => [] },
});

const showCreate = ref(false);

const createForm = useForm({
    title: '',
    description: '',
    classroom_id: '',
    lesson_id: '',
    min_score: 60,
    xp_reward: 50,
    due_at: '',
});

function create() {
    createForm
        .transform((data) => ({ ...data, due_at: data.due_at || null }))
        .post('/teacher/assignments', {
            onSuccess: () => {
                showCreate.value = false;
                createForm.reset();
            },
        });
}

function destroy(row) {
    if (!confirm(`Hapus tugas "${row.title}"?`)) return;
    useForm({}).delete(`/teacher/assignments/${row.id}`);
}
</script>

<template>
    <AppLayout>
        <Head title="Tugas Siswa" />

        <div class="st-page">
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <span class="st-badge st-badge--primary">Tugas</span>
                    <h1>Tugas untuk Siswa</h1>
                    <p>Tugas akan muncul otomatis di menu Tugas siswa anggota kelasnya, dan selesai sendiri saat mereka lulus peragaan AI.</p>
                </div>
                <div class="st-page-header-aside">
                    <button type="button" class="st-btn st-btn--primary" @click="showCreate = !showCreate" :disabled="classes.length === 0">
                        <Plus :size="16" /> {{ showCreate ? 'Tutup Formulir' : 'Beri Tugas Baru' }}
                    </button>
                </div>
            </header>

            <p v-if="classes.length === 0" class="st-empty">
                Anda belum punya kelas. <Link href="/teacher/classes" class="st-link">Buat kelas</Link> dulu — tugas selalu dibagikan lewat kelas.
            </p>

            <transition name="fade">
                <form v-if="showCreate" class="st-card st-form" @submit.prevent="create">
                    <h2><ClipboardList :size="18" /> Tugas Baru</h2>
                    <div class="st-form-row">
                        <label class="st-field">
                            <span>Kelas tujuan <em>*</em></span>
                            <select v-model="createForm.classroom_id" required>
                                <option v-for="klass in classes" :key="klass.id" :value="klass.id">
                                    {{ klass.name }} ({{ klass.students_count }} siswa)
                                </option>
                            </select>
                        </label>
                        <label class="st-field">
                            <span>Pelajaran yang dinilai AI <em>*</em></span>
                            <select v-model="createForm.lesson_id" required>
                                <option v-for="lesson in lessons" :key="lesson.id" :value="lesson.id">
                                    {{ lesson.title }} — {{ lesson.language }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <label class="st-field">
                        <span>Judul tugas <em>*</em></span>
                        <input v-model="createForm.title" type="text" required maxlength="200" placeholder="cth. Latihan Huruf A sampai F" />
                    </label>
                    <label class="st-field">
                        <span>Instruksi (opsional)</span>
                        <textarea v-model="createForm.description" rows="2" maxlength="2000" placeholder="Petunjuk pengerjaan untuk siswa" />
                    </label>
                    <div class="st-form-row">
                        <label class="st-field">
                            <span>Skor minimal kelulusan (%) <em>*</em></span>
                            <input v-model.number="createForm.min_score" type="number" min="60" max="100" />
                            <small>AI menilai lulus mulai skor 60; tugas selesai otomatis saat siswa mengcapai skor ini.</small>
                        </label>
                        <label class="st-field">
                            <span>Bonus XP <em>*</em></span>
                            <input v-model.number="createForm.xp_reward" type="number" min="1" max="500" />
                        </label>
                        <label class="st-field">
                            <span>Batas waktu (opsional)</span>
                            <input v-model="createForm.due_at" type="datetime-local" />
                        </label>
                    </div>
                    <p v-if="createForm.errors.min_score" class="st-field-error">{{ createForm.errors.min_score }}</p>
                    <p v-if="createForm.errors.due_at" class="st-field-error">{{ createForm.errors.due_at }}</p>
                    <p v-if="createForm.errors.lesson_id" class="st-field-error">{{ createForm.errors.lesson_id }}</p>
                    <div class="st-form-actions">
                        <button type="submit" :disabled="createForm.processing" class="st-btn st-btn--primary">
                            {{ createForm.processing ? 'Membagikan tugas...' : 'Bagikan ke Kelas' }}
                        </button>
                    </div>
                </form>
            </transition>

            <article class="st-card">
                <div class="st-card-head">
                    <h2><ClipboardList :size="18" /> Daftar Tugas</h2>
                </div>
                <div v-if="assignments.length" class="st-table-wrap">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Tugas</th>
                                <th>Kelas</th>
                                <th>Isyarat</th>
                                <th>Skor min.</th>
                                <th>XP</th>
                                <th>Deadline</th>
                                <th>Progres</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in assignments" :key="row.id">
                                <td><Link :href="`/teacher/assignments/${row.id}`" class="st-link"><b>{{ row.title }}</b></Link></td>
                                <td><span class="st-chip">{{ row.classroom }}</span></td>
                                <td>{{ row.lesson }} ({{ row.language }})</td>
                                <td>{{ row.min_score }}%</td>
                                <td>+{{ row.xp_reward }}</td>
                                <td>{{ row.due_at ? new Date(row.due_at).toLocaleDateString('id-ID') : '-' }}</td>
                                <td>
                                    <span class="st-progress-pill">
                                        <Users :size="13" />
                                        {{ row.completed_count }}/{{ row.submissions_count }}
                                    </span>
                                </td>
                                <td>
                                    <div class="st-row-actions">
                                        <Link :href="`/teacher/assignments/${row.id}`" class="st-btn st-btn--sm">Detail</Link>
                                        <button type="button" class="st-btn st-btn--sm st-btn--danger" :title="`Hapus tugas ${row.title}`" @click="destroy(row)"><Trash2 :size="13" /> Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="st-empty">Belum ada tugas. Klik “Beri Tugas Baru” — tugasnya langsung muncul di dashboard siswa yang tergabung di kelas.</p>
            </article>
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

.st-field small {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    color: #859c90;
}

.st-card-head h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 800;
}

.st-chip {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    background: #eefaf4;
    color: #1e7253;
    border: 1px solid #d5f3e5;
    border-radius: 999px;
    padding: 2px 8px;
}

.st-progress-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 800;
    color: #1e7253;
}

.st-row-actions {
    display: flex;
    gap: 6px;
}

.st-empty {
    padding: 14px;
    background: #fbfdfc;
    border: 1px dashed #d5e2dc;
    border-radius: 12px;
    color: #52685d;
    font-size: 13px;
}

.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
