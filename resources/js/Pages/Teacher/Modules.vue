<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { BookOpen, BookPlus, ChevronDown, Pencil, Plus, Trash2 } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ConfirmDialog from '../../Layouts/ConfirmDialog.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const ALPHABET_SLUG = 'alphabet';
const CATEGORY_LABELS = { alphabet: 'Alfabet', number: 'Angka', word: 'Kosakata', phrase: 'Frasa' };
const ITEM_LABELS = { alphabet: 'huruf', number: 'angka', word: 'kosakata', phrase: 'frasa' };
const VISIT_OPTIONS = { preserveState: true, preserveScroll: true };

function categoryLabel(category) {
    return CATEGORY_LABELS[category.slug] || category.name;
}

function itemLabel(category) {
    return ITEM_LABELS[category.slug] || 'isyarat';
}

function isAlphabet(category) {
    return category.slug === ALPHABET_SLUG;
}

function materialInUse(material) {
    return material.lessons.some((lesson) => lesson.used);
}

/** Modul yang sedang dibuka, sekaligus tujuan form tambah kosakata. */
const openMaterialId = ref(null);

function toggleMaterial(materialId) {
    openMaterialId.value = openMaterialId.value === materialId ? null : materialId;
    addLessonForm.reset();
    addLessonForm.clearErrors();
}

/* Tambah & edit modul */
const creatableCategories = computed(() => props.categories.filter((category) => !isAlphabet(category)));
const moduleDialogOpen = ref(false);
const editingMaterial = ref(null);
const materialForm = useForm({ category_id: '', title: '', language: 'sibi', description: '' });

function openCreateMaterial() {
    const defaultCategory = creatableCategories.value.find((category) => category.slug === 'word') ?? creatableCategories.value[0];
    editingMaterial.value = null;
    materialForm.clearErrors();
    materialForm.category_id = defaultCategory?.id ?? '';
    materialForm.title = '';
    materialForm.language = 'sibi';
    materialForm.description = '';
    moduleDialogOpen.value = true;
}

function openEditMaterial(material, category) {
    editingMaterial.value = { ...material, locked: isAlphabet(category) };
    materialForm.clearErrors();
    materialForm.category_id = category.id;
    materialForm.title = material.title;
    materialForm.language = material.language;
    materialForm.description = material.description ?? '';
    moduleDialogOpen.value = true;
}

function newestMaterialId(categoryId) {
    const materials = props.categories.find((category) => category.id === categoryId)?.materials ?? [];
    return materials.reduce((newestId, material) => Math.max(newestId, material.id), 0) || null;
}

function submitMaterial() {
    if (editingMaterial.value) {
        materialForm.put(`/teacher/modules/${editingMaterial.value.id}`, {
            ...VISIT_OPTIONS,
            onSuccess: () => (moduleDialogOpen.value = false),
        });
        return;
    }

    materialForm.post('/teacher/modules', {
        ...VISIT_OPTIONS,
        onSuccess: () => {
            moduleDialogOpen.value = false;
            openMaterialId.value = newestMaterialId(materialForm.category_id);
        },
    });
}

/* Tambah kosakata */
const addLessonForm = useForm({ title: '' });

function submitNewLesson(material) {
    addLessonForm.post(`/teacher/modules/${material.id}/lessons`, {
        ...VISIT_OPTIONS,
        onSuccess: () => addLessonForm.reset(),
    });
}

/* Edit kosakata */
const editingLesson = ref(null);
const editLessonForm = useForm({ title: '', xp_reward: 10 });

function openEditLesson(lesson, category) {
    editingLesson.value = { ...lesson, isLetter: isAlphabet(category), itemLabel: itemLabel(category) };
    editLessonForm.clearErrors();
    editLessonForm.title = lesson.title;
    editLessonForm.xp_reward = lesson.xp_reward;
}

function submitEditLesson() {
    editLessonForm.put(`/teacher/lessons/${editingLesson.value.id}`, {
        ...VISIT_OPTIONS,
        onSuccess: () => (editingLesson.value = null),
    });
}

/* Hapus */
const pendingDelete = ref(null);
const deleting = ref(false);

function askDeleteMaterial(material) {
    pendingDelete.value = {
        url: `/teacher/modules/${material.id}`,
        title: `Hapus modul ${material.title}?`,
        description: `${material.lessons.length} isi modul ini ikut terhapus.`,
    };
}

function askDeleteLesson(lesson) {
    pendingDelete.value = {
        url: `/teacher/lessons/${lesson.id}`,
        title: `Hapus ${lesson.title}?`,
        description: 'Siswa tidak akan melihatnya lagi di Materi Belajar.',
    };
}

function confirmDelete() {
    router.delete(pendingDelete.value.url, {
        ...VISIT_OPTIONS,
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            pendingDelete.value = null;
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Manajemen Modul" />

        <div class="st-page">
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <span class="st-badge st-badge--primary">Manajemen Modul</span>
                    <h1>Modul &amp; Kosakata</h1>
                    <p>Buat modul, lalu isi dengan kosakata yang akan muncul di Materi Belajar siswa.</p>
                </div>
                <div class="st-page-header-aside">
                    <button type="button" class="st-btn st-btn--primary" @click="openCreateMaterial">
                        <BookPlus :size="16" /> Tambah Modul
                    </button>
                </div>
            </header>

            <section class="st-module-list">
                <article v-for="category in categories" :key="category.id" class="st-card">
                    <div class="st-card-head">
                        <h2><BookOpen :size="18" /> {{ categoryLabel(category) }}</h2>
                        <span v-if="category.ai_supported" class="st-chip" title="Isyarat pada kategori ini dinilai AI lewat kamera">Dinilai AI</span>
                        <span v-else class="st-chip st-chip--muted" title="AI baru mengenali huruf alfabet, jadi isi kategori ini dilatih bersama guru">Latihan bersama guru</span>
                    </div>

                    <div v-for="material in category.materials" :key="material.id" class="st-material">
                        <div class="st-material-head">
                            <button
                                type="button"
                                class="st-material-toggle"
                                :aria-expanded="openMaterialId === material.id"
                                @click="toggleMaterial(material.id)"
                            >
                                <ChevronDown :size="14" class="st-chevron" :class="{ open: openMaterialId === material.id }" />
                                <b>{{ material.title }}</b>
                                <span class="st-chip">{{ material.language.toUpperCase() }}</span>
                                <small>{{ material.lessons.length }} {{ itemLabel(category) }}</small>
                            </button>
                            <span class="st-row-actions">
                                <button
                                    type="button"
                                    class="st-btn st-btn--sm"
                                    :aria-label="`Edit modul ${material.title}`"
                                    title="Edit modul"
                                    @click="openEditMaterial(material, category)"
                                ><Pencil :size="13" /></button>
                                <button
                                    v-if="!isAlphabet(category)"
                                    type="button"
                                    class="st-btn st-btn--sm st-btn--danger"
                                    :aria-label="`Hapus modul ${material.title}`"
                                    :title="materialInUse(material) ? 'Sudah dipakai siswa, tidak bisa dihapus' : 'Hapus modul'"
                                    :disabled="materialInUse(material)"
                                    @click="askDeleteMaterial(material)"
                                ><Trash2 :size="13" /></button>
                            </span>
                        </div>

                        <div v-if="openMaterialId === material.id" class="st-lessons">
                            <ul v-if="material.lessons.length">
                                <li v-for="lesson in material.lessons" :key="lesson.id">
                                    <b>{{ lesson.title }}</b>
                                    <small v-if="isAlphabet(category)">+{{ lesson.xp_reward }} XP</small>
                                    <span v-if="lesson.used" class="st-chip st-chip--muted" title="Sudah dipakai latihan atau tugas siswa">Terpakai</span>
                                    <span class="st-row-actions">
                                        <button
                                            type="button"
                                            class="st-btn st-btn--sm"
                                            :aria-label="`Edit ${lesson.title}`"
                                            title="Edit"
                                            @click="openEditLesson(lesson, category)"
                                        ><Pencil :size="12" /></button>
                                        <button
                                            v-if="!isAlphabet(category)"
                                            type="button"
                                            class="st-btn st-btn--sm st-btn--danger"
                                            :aria-label="`Hapus ${lesson.title}`"
                                            :title="lesson.used ? 'Sudah dipakai siswa, tidak bisa dihapus' : 'Hapus'"
                                            :disabled="lesson.used"
                                            @click="askDeleteLesson(lesson)"
                                        ><Trash2 :size="12" /></button>
                                    </span>
                                </li>
                            </ul>
                            <p v-else class="st-empty">Modul ini masih kosong. Tambahkan {{ itemLabel(category) }} pertama di bawah.</p>

                            <p v-if="isAlphabet(category)" class="st-lesson-help">Huruf A–Z selalu lengkap, jadi tidak bisa ditambah atau dihapus. XP tiap huruf tetap bisa diubah.</p>
                            <form v-else class="st-lesson-form" @submit.prevent="submitNewLesson(material)">
                                <input
                                    v-model="addLessonForm.title"
                                    type="text"
                                    required
                                    maxlength="100"
                                    :aria-label="`Nama ${itemLabel(category)} baru`"
                                    :placeholder="`Ketik ${itemLabel(category)} baru`"
                                    @input="addLessonForm.clearErrors('title')"
                                />
                                <button type="submit" :disabled="addLessonForm.processing" class="st-btn st-btn--primary">
                                    <Plus :size="15" /> {{ addLessonForm.processing ? 'Menyimpan...' : 'Tambah' }}
                                </button>
                            </form>
                            <p v-if="addLessonForm.errors.title" class="st-field-error">{{ addLessonForm.errors.title }}</p>
                        </div>
                    </div>

                    <p v-if="category.materials.length === 0" class="st-empty">
                        Belum ada modul di kategori ini. Klik “Tambah Modul” untuk membuatnya.
                    </p>
                </article>
            </section>
        </div>

        <!-- Pop-up tambah / edit modul -->
        <div v-if="moduleDialogOpen" class="st-modal-scrim" @click.self="moduleDialogOpen = false">
            <form class="st-card st-form st-modal" @submit.prevent="submitMaterial">
                <h2>{{ editingMaterial ? 'Edit Modul' : 'Tambah Modul' }}</h2>
                <label v-if="!editingMaterial" class="st-field">
                    <span>Kategori <em>*</em></span>
                    <select v-model="materialForm.category_id" required>
                        <option v-for="category in creatableCategories" :key="category.id" :value="category.id">{{ categoryLabel(category) }}</option>
                    </select>
                </label>
                <p v-if="materialForm.errors.category_id" class="st-field-error">{{ materialForm.errors.category_id }}</p>
                <label class="st-field">
                    <span>Nama modul <em>*</em></span>
                    <input v-model="materialForm.title" type="text" required maxlength="200" placeholder="cth. Kosakata Keluarga" />
                </label>
                <p v-if="materialForm.errors.title" class="st-field-error">{{ materialForm.errors.title }}</p>
                <label class="st-field">
                    <span>Sistem isyarat <em>*</em></span>
                    <select v-model="materialForm.language" required :disabled="editingMaterial?.locked">
                        <option value="sibi">SIBI</option>
                        <option value="bisindo">BISINDO</option>
                    </select>
                </label>
                <p v-if="materialForm.errors.language" class="st-field-error">{{ materialForm.errors.language }}</p>
                <label class="st-field">
                    <span>Deskripsi</span>
                    <input v-model="materialForm.description" type="text" maxlength="2000" placeholder="Boleh dikosongkan" />
                </label>
                <p v-if="materialForm.errors.description" class="st-field-error">{{ materialForm.errors.description }}</p>
                <div class="st-form-actions">
                    <button type="button" class="st-btn" @click="moduleDialogOpen = false">Batal</button>
                    <button type="submit" :disabled="materialForm.processing" class="st-btn st-btn--primary">
                        {{ materialForm.processing ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Pop-up edit kosakata -->
        <div v-if="editingLesson" class="st-modal-scrim" @click.self="editingLesson = null">
            <form class="st-card st-form st-modal" @submit.prevent="submitEditLesson">
                <h2>Edit {{ editingLesson.title }}</h2>
                <label v-if="editingLesson.isLetter" class="st-field">
                    <span>XP per huruf <em>*</em></span>
                    <input v-model.number="editLessonForm.xp_reward" type="number" required min="1" max="100" />
                </label>
                <label v-else class="st-field">
                    <span>Nama {{ editingLesson.itemLabel }} <em>*</em></span>
                    <input v-model="editLessonForm.title" type="text" required maxlength="100" />
                </label>
                <p v-if="editLessonForm.errors.title" class="st-field-error">{{ editLessonForm.errors.title }}</p>
                <p v-if="editLessonForm.errors.xp_reward" class="st-field-error">{{ editLessonForm.errors.xp_reward }}</p>
                <div class="st-form-actions">
                    <button type="button" class="st-btn" @click="editingLesson = null">Batal</button>
                    <button type="submit" :disabled="editLessonForm.processing" class="st-btn st-btn--primary">
                        {{ editLessonForm.processing ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </div>

        <ConfirmDialog
            :open="pendingDelete !== null"
            :title="pendingDelete?.title ?? ''"
            :description="pendingDelete?.description ?? ''"
            cancel-label="Batal"
            confirm-label="Ya, Hapus"
            tone="danger"
            :processing="deleting"
            @cancel="pendingDelete = null"
            @confirm="confirmDelete"
        >
            <template #icon><Trash2 :size="26" /></template>
        </ConfirmDialog>
    </AppLayout>
</template>

<style scoped>
.st-form h2 {
    margin: 0 0 12px;
    font-size: 16px;
    font-weight: 800;
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
    padding: 6px 14px 6px 0;
    background: #fff;
}

.st-material-toggle {
    display: flex;
    flex: 1;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    min-width: 0;
    min-height: 44px;
    padding: 4px 0 4px 14px;
    text-align: left;
    cursor: pointer;
    background: transparent;
    border: 0;
}

.st-material-toggle b {
    font-size: 14px;
}

.st-material-toggle small {
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

.st-row-actions {
    display: flex;
    flex-shrink: 0;
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

.st-lessons li .st-row-actions {
    margin-left: auto;
}

.st-lessons .st-empty {
    margin: 0 0 12px;
}

.st-lesson-form {
    display: flex;
    gap: 8px;
    align-items: center;
}

.st-lesson-form input {
    flex: 1;
    min-width: 0;
    min-height: 44px;
    padding: 8px 12px;
    border: 1px solid #d5e2dc;
    border-radius: 10px;
    font-size: 14px;
    background: #fff;
}

.st-lesson-form .st-btn {
    flex-shrink: 0;
}

.st-lesson-help {
    margin: 6px 0 0;
    font-size: 12.5px;
    color: #6b8176;
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
    color: #6b8176;
}

.st-empty {
    padding: 14px;
    background: #fbfdfc;
    border: 1px dashed #d5e2dc;
    border-radius: 12px;
    color: #52685d;
    font-size: 13px;
}

.st-modal-scrim {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(20, 34, 29, 0.45);
}

.st-modal {
    width: 100%;
    max-width: 440px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
}
</style>
