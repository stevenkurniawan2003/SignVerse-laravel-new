<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ClipboardList, Pencil, Plus, School, UserRound, Users } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    classes: { type: Array, default: () => [] },
    status: { type: String, default: '' },
    error: { type: String, default: '' },
});

const showCreate = ref(Boolean(props.error));

const createForm = useForm({
    name: '',
    description: '',
});

function create() {
    createForm.post('/teacher/classes', {
        onSuccess: () => createForm.reset(),
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Kelas Saya" />

        <div class="st-page">
            <header class="st-page-header st-page-header--tinted">
                <div class="st-page-header-main">
                    <span class="st-badge st-badge--primary">Manajemen Kelas</span>
                    <h1>Kelas Saya</h1>
                    <p>Buat kelas, bagikan kode bergabung ke siswa, lalu pantau dan tugaskan latihan dari sini.</p>
                </div>
                <div class="st-page-header-aside">
                    <button type="button" class="st-btn st-btn--primary" @click="showCreate = !showCreate">
                        <Plus :size="16" /> {{ showCreate ? 'Tutup Formulir' : 'Buat Kelas Baru' }}
                    </button>
                </div>
            </header>

            <transition name="fade">
                <form v-if="showCreate" class="st-card st-form" @submit.prevent="create">
                    <h2><School :size="18" /> Kelas Baru</h2>
                    <label class="st-field">
                        <span>Nama kelas <em>*</em></span>
                        <input
                            v-model="createForm.name"
                            type="text"
                            required
                            maxlength="120"
                            placeholder="cth. Kelas 7A — SIBI Dasar"
                        />
                    </label>
                    <label class="st-field">
                        <span>Deskripsi (opsional)</span>
                        <textarea
                            v-model="createForm.description"
                            rows="2"
                            maxlength="500"
                            placeholder="Catatan untuk kelas ini"
                        ></textarea>
                    </label>
                    <p v-if="createForm.errors.name" class="st-field-error">{{ createForm.errors.name }}</p>
                    <p v-if="createForm.errors.description" class="st-field-error">{{ createForm.errors.description }}</p>
                    <div class="st-form-actions">
                        <button type="submit" :disabled="createForm.processing" class="st-btn st-btn--primary">
                            {{ createForm.processing ? 'Menyimpan...' : 'Buat Kelas' }}
                        </button>
                    </div>
                </form>
            </transition>

            <section v-if="classes.length" class="st-card-grid">
                <article v-for="klass in classes" :key="klass.id" class="st-card st-card--link">
                    <div class="st-card-head">
                        <h2><School :size="16" /> {{ klass.name }}</h2>
                        <span class="st-code">{{ klass.code }}</span>
                    </div>
                    <p class="st-card-desc">{{ klass.description || 'Tanpa deskripsi.' }}</p>
                    <div class="st-card-meta">
                        <span><Users :size="14" /> {{ klass.students_count }} siswa</span>
                        <span><ClipboardList :size="14" /> {{ klass.assignments_count }} tugas</span>
                    </div>
                    <div class="st-card-actions">
                        <Link :href="`/teacher/classes/${klass.id}`" class="st-btn st-btn--secondary">
                            <UserRound :size="15" /> Buka Detail
                        </Link>
                    </div>
                </article>
            </section>
            <p v-else class="st-empty">Belum ada kelas. Klik “Buat Kelas Baru” untuk memulai, lalu bagikan kode kelasnya kepada siswa agar mereka bisa bergabung dari menu Tugas.</p>
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

.st-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.st-card--link {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.st-code {
    font-family: monospace;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 2px;
    background: #f5fbf7;
    border: 1px dashed #9fd6ba;
    color: #1e7253;
    border-radius: 8px;
    padding: 3px 10px;
}

.st-card-desc {
    margin: 0;
    font-size: 13px;
    color: #52685d;
}

.st-card-meta {
    display: flex;
    gap: 16px;
    font-size: 12.5px;
    font-weight: 600;
    color: #7b9186;
}

.st-card-meta span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.st-card-actions {
    margin-top: 6px;
}

.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
