<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BookOpen, BookPlus, ChevronDown, FilePlus, Pencil, Plus, Trash2 } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const openMaterial = ref(null);
const editingMaterial = ref(null);
const editingLesson = ref(null);

const materialForm = useForm({
    id: null,
    category_id: props.categories[0]?.id ?? '',
    title: '',
    language: 'sibi',
    difficulty: 2,
    description: '',
});

const lessonForm = useForm({
    material_id: null,
    title: '',
    xp_reward: 10,
    difficulty: 1,
});

const isAlphabet = computed(() =>
    props.categories.find((c) => c.id === materialForm.category_id)?.slug === 'alphabet'
    && editingMaterial.value === null,
);

function submitMaterial() {
    if (materialForm.id) {
        materialForm.put(`/teacher/modules/${materialForm.id}`, {
            onSuccess: () => (editingMaterial.value = null),
        });
    } else {
        materialForm.post('/teacher/modules', {
            onSuccess: () => materialForm.reset('title', 'description'),
        });
    }
}

function submitLesson() {
    if (lessonForm.material_id) {
        lessonForm.post(`/teacher/modules/${lessonForm.material_id}/lessons`, {
            onSuccess: () => lessonForm.reset('title'),
        });
    }
}

function submitEditLesson() {
    editingLesson.value._form.put(`/teacher/lessons/${editingLesson.value.id}`, {
        onSuccess: () => (editingLesson.value = null),
    });
}

function destroyMaterial(material) {
    if (!confirm(`Hapus materi "${material.title}" beserta pelajarannya?`)) return;
    useForm({}).delete(`/teacher/modules/${material.id}`);
}

function destroyLesson(lesson) {
    if (!confirm(`Hapus pelajaran "${lesson.title}"?`)) return;
    useForm({}).delete(`/teacher/lessons/${lesson.id}`);
}

let nextEditForm = null;
function startLessonEdit(lesson) {
    // Form dinamis per lesson agar tak membagi state antar modal.
    nextEditForm = useForm({ title: lesson.title, xp_reward: lesson.xp_reward, difficulty: lesson.difficulty });
    nextEditForm._id = lesson.id;
    editingLesson.value = Object.assign({}, lesson, { _form: nextEditForm, lesson });
}

function startMaterialEdit(material) {
    editingMaterial.value = material;
    materialForm.id = material.id;
    materialForm.category_id = material.category_id;
    materialForm.title = material.title;
    materialForm.language = material.language;
    materialForm.difficulty = material.difficulty;
    materialForm.description = material.description ?? '';
}

function toggleCreate(materialId) {
    openMaterial.value = openMaterial.value === materialId ? null : materialId;
    editingLesson.value = null;
    lessonForm.material_id = materialId;
    lessonForm.reset();
}
</script>

<template>
    <AppLayout>
        <Head title="Manajemen Modul" />

        <div class="st-page">
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <span class="st-badge st-badge--primary">Manajemen Modul</span>
                    <h1>Modul &amp; Pelajaran</h1>
                    <p>Kelola materi dan pelajaran bahasa isyarat yang dipakai siswa saat belajar dan berlatih AI.</p>
                </div>
                <div class="st-page-header-aside">
                    <button
                        type="button"
                        class="st-btn st-btn--primary"
                        :disabled="editingMaterial !== null"
                        @click="editingMaterial !== null ? (editingMaterial = null) : startMaterialEdit({ id: null, category_id: categories[0]?.id, title: '', language: 'sibi', difficulty: 2, description: '' })"
                    >
                        <BookPlus :size="16" /> Materi Baru
                    </button>
                </div>
            </header>

            <form v-if="editingMaterial !== null" class="st-card st-form" @submit.prevent="submitMaterial">
                <h2>{{ materialForm.id ? 'Edit Materi' : 'Materi Baru' }}</h2>
                <div class="st-form-row">
                    <label class="st-field">
                        <span>Kategori <em>*</em></span>
                        <select v-model="materialForm.category_id" required :disabled="Boolean(materialForm.id)">
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </label>
                    <label class="st-field">
                        <span>Sistem isyarat <em>*</em></span>
                        <select v-model="materialForm.language" required>
                            <option value="sibi">SIBI</option>
                            <option value="bisindo">BISINDO</option>
                        </select>
                    </label>
                </div>
                <label class="st-field">
                    <span>Judul materi <em>*</em></span>
                    <input v-model="materialForm.title" type="text" required maxlength="200" placeholder="cth. Huruf SIBI Dasar" />
                </label>
                <div class="st-form-row">
                    <label class="st-field">
                        <span>Tingkat kesulitan (1–5)</span>
                        <input v-model.number="materialForm.difficulty" type="number" min="1" max="5" />
                    </label>
                    <label class="st-field">
                        <span>Deskripsi</span>
                        <input v-model="materialForm.description" type="text" maxlength="2000" />
                    </label>
                </div>
                <div class="st-form-actions">
                    <button type="button" class="st-btn" @click="editingMaterial = null">Batal</button>
                    <button type="submit" :disabled="materialForm.processing" class="st-btn st-btn--primary">
                        {{ materialForm.processing ? 'Menyimpan...' : 'Simpan Materi' }}
                    </button>
                </div>
            </form>

            <section class="st-module-list">
                <article v-for="category in categories" :key="category.id" class="st-card">
                    <div class="st-card-head">
                        <h2><BookOpen :size="18" /> {{ category.name }}</h2>
                        <span
                            v-if="category.ai_supported"
                            class="st-chip"
                            title="Isyarat pada kategori ini bisa dinilai AI lewat kamera"
                        >Dinilai AI</span>
                        <span
                            v-else
                            class="st-chip st-chip--muted"
                            title="AI SignTech baru mengenali huruf alfabet; pelajaran ini belum bisa diselesaikan lewat kamera"
                        >Belum dinilai AI</span>
                    </div>

                    <div
                        v-for="material in category.materials"
                        :key="material.id"
                        class="st-material"
                    >
                        <div class="st-material-head" @click="openMaterial = openMaterial === material.id ? null : material.id">
                            <ChevronDown :size="14" class="st-chevron" :class="{ open: openMaterial === material.id }" />
                            <b>{{ material.title }}</b>
                            <span class="st-chip">{{ material.language.toUpperCase() }}</span>
                            <small>{{ material.lessons.length }} pelajaran</small>
                            <span class="st-material-actions" @click.stop>
                                <button type="button" class="st-btn st-btn--sm" @click="startMaterialEdit(material)"><Pencil :size="13" /></button>
                                <button type="button" class="st-btn st-btn--sm st-btn--danger" @click="destroyMaterial(material)"><Trash2 :size="13" /></button>
                            </span>
                        </div>

                        <div v-if="openMaterial === material.id" class="st-lessons">
                            <ul>
                                <li v-for="lesson in material.lessons" :key="lesson.id">
                                    <b>{{ lesson.title }}</b>
                                    <small>+{{ lesson.xp_reward }} XP · sulit {{ lesson.difficulty }}</small>
                                    <span v-if="lesson.used" class="st-chip st-chip--muted" title="Sudah dipakai latihan siswa, tidak bisa dihapus">Terpakai</span>
                                    <span class="st-lesson-actions">
                                        <button type="button" class="st-btn st-btn--sm" @click="startLessonEdit(lesson)"><Pencil :size="12" /></button>
                                        <button
                                            type="button"
                                            class="st-btn st-btn--sm st-btn--danger"
                                            :disabled="lesson.used"
                                            :title="lesson.used ? 'Sudah dipakai latihan siswa' : 'Hapus pelajaran'"
                                            @click="!lesson.used && destroyLesson(lesson)"
                                        ><Trash2 :size="12" /></button>
                                    </span>
                                </li>
                            </ul>

                            <form class="st-lesson-form" @submit.prevent="submitLesson">
                                <template v-if="category.slug === 'alphabet'">
                                    <input v-model="lessonForm.title" type="text" required maxlength="1" class="st-lesson-letter" placeholder="A" />
                                </template>
                                <template v-else>
                                    <input v-model="lessonForm.title" type="text" required maxlength="50" placeholder="Judul pelajaran" />
                                </template>
                                <input v-model.number="lessonForm.xp_reward" type="number" min="1" max="100" class="st-lesson-xp" />
                                <input v-model.number="lessonForm.difficulty" type="number" min="1" max="3" class="st-lesson-xp" />
                                <button type="submit" :disabled="lessonForm.processing" class="st-btn st-btn--sm st-btn--primary">
                                    <FilePlus :size="13" /> Tambah
                                </button>
                            </form>
                            <p v-if="lessonForm.errors.title" class="st-field-error">{{ lessonForm.errors.title }}</p>
                            <p class="st-lesson-help">
                                Pelajaran alfabet wajib satu huruf A–Z agar bisa dinilai oleh AI. Kolom kedua: XP, ketiga: tingkat kesulitan 1–3.
                            </p>
                        </div>
                    </div>

                    <p v-if="category.materials.length === 0" class="st-empty">
                        Belum ada materi di kategori ini. Klik “Materi Baru” di pojok kanan atas untuk menambahkan.
                    </p>
                </article>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.st-form h2 {
    margin: 0 0 12px;
    font-size: 16px;
    font-weight: 800;
}

.st-form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
}

.st-card-head h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 800;
}

.st-module-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.st-material {
    border: 1px solid #e2ede6;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 8px;
    background: #fbfdfc;
}

.st-material-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    cursor: pointer;
    background: #fff;
}

.st-material-head b {
    font-size: 14px;
}

.st-material-head small {
    color: #7b9186;
    font-size: 12px;
}

.st-chevron {
    transition: transform 0.15s;
    color: #7b9186;
}

.st-chevron.open {
    transform: rotate(180deg);
}

.st-material-actions {
    margin-left: auto;
    display: flex;
    gap: 6px;
}

.st-lessons {
    padding: 12px 14px;
    border-top: 1px solid #e2ede6;
}

.st-lessons ul {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 0;
    margin: 0 0 12px;
}

.st-lessons li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    background: #fff;
    border: 1px solid #e2ede6;
    border-radius: 9px;
    font-size: 13px;
}

.st-lessons li small {
    color: #7b9186;
}

.st-lesson-actions {
    margin-left: auto;
    display: flex;
    gap: 5px;
}

.st-lesson-form {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.st-lesson-form input {
    padding: 7px 12px;
    border: 1px solid #d5e2dc;
    border-radius: 9px;
    font-size: 13px;
    background: #fff;
    min-width: 140px;
}

.st-lesson-letter {
    width: 52px !important;
    min-width: 52px !important;
    text-align: center;
    text-transform: uppercase;
    font-weight: 800;
}

.st-lesson-xp {
    width: 76px !important;
    min-width: 76px !important;
}

.st-lesson-help {
    margin: 6px 0 0;
    font-size: 11.5px;
    color: #859c90;
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

.st-chip--muted {
    background: #f1f5f3;
    border-color: #e2ede6;
    color: #859c90;
}

.st-empty {
    padding: 14px;
    background: #fbfdfc;
    border: 1px dashed #d5e2dc;
    border-radius: 12px;
    color: #52685d;
    font-size: 13px;
}
</style>
