<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, CheckCircle, ClipboardList, KeyRound, School, Star, Target, Trophy } from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    pending: { type: Array, default: () => [] },
    completed: { type: Array, default: () => [] },
    classes: { type: Array, default: () => [] },
});

const joinForm = useForm({ code: '' });

function join() {
    joinForm
        .transform((data) => ({ code: data.code.trim().toUpperCase() }))
        .post('/student/classes/join', {
            preserveScroll: true,
            onSuccess: () => joinForm.reset(),
        });
}

/*
 * Realtime: kelas & tugas diperbarui tiap 10 detik, jadi siswa yang
 * ditambahkan guru ke kelas (atau menerima tugas baru) langsung melihatnya.
 */
const POLL_MS = 10000;
const knownClassIds = ref(new Set(props.classes.map((room) => room.id)));
const newClassIds = ref(new Set());
let pollTimer = null;

function refresh() {
    if (document.hidden) return;
    router.reload({
        only: ['pending', 'completed', 'classes'],
        onSuccess: (page) => {
            const fresh = page.props.classes.map((room) => room.id).filter((id) => !knownClassIds.value.has(id));
            if (fresh.length) {
                newClassIds.value = new Set([...newClassIds.value, ...fresh]);
                knownClassIds.value = new Set(page.props.classes.map((room) => room.id));
            }
        },
    });
}

onMounted(() => {
    pollTimer = setInterval(refresh, POLL_MS);
});

onBeforeUnmount(() => clearInterval(pollTimer));

function pendingCount(classId) {
    return props.pending.filter((task) => task.classroom_id === classId).length;
}

function dueLabel(task) {
    if (!task.due_at) return 'Tanpa batas waktu';
    const date = new Date(task.due_at).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' });
    return task.is_overdue ? `Terlewat (${date})` : `Sampai ${date}`;
}

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-';
}

const overdueCount = computed(() => props.pending.filter((task) => task.is_overdue).length);
const earnedXp = computed(() => props.completed.reduce((sum, task) => sum + (task.xp_reward || 0), 0));
</script>

<template>
    <AppLayout>
        <Head title="Tugas Saya" />

        <div class="st-page sa">
            <header class="sa-hero">
                <div>
                    <span class="sa-kicker"><ClipboardList :size="14" /> Tugas &amp; Kelas</span>
                    <h1>Tugas dari Gurumu</h1>
                    <p>Kerjakan tugas dengan memperagakan isyarat di kamera. Tugas selesai otomatis saat AI menyatakan peragaanmu benar.</p>
                </div>
                <dl class="sa-summary">
                    <div><dt>Kelas</dt><dd>{{ classes.length }}</dd></div>
                    <div><dt>Belum selesai</dt><dd>{{ pending.length }}</dd></div>
                    <div><dt>XP tugas</dt><dd>+{{ earnedXp }}</dd></div>
                </dl>
            </header>

            <!-- Kelas Saya -->
            <section class="st-panel sa-panel">
                <div class="sa-panel-head">
                    <h2><School :size="18" /> Kelas Saya</h2>
                </div>

                <div v-if="classes.length" class="sa-classes">
                    <article v-for="room in classes" :key="room.id" class="sa-class" :class="{ 'is-new': newClassIds.has(room.id) }">
                        <span class="sa-class-icon"><School :size="20" /></span>
                        <div class="sa-class-main">
                            <b>{{ room.name }} <span v-if="newClassIds.has(room.id)" class="sa-new">Baru</span></b>
                            <small>Guru: {{ room.teacher || '-' }}</small>
                            <small v-if="room.description">{{ room.description }}</small>
                        </div>
                        <span class="sa-class-count">{{ pendingCount(room.id) }} tugas</span>
                    </article>
                </div>

                <form class="sa-join" :class="{ 'is-primary': !classes.length }" @submit.prevent="join">
                    <div class="sa-join-text">
                        <b><KeyRound :size="16" /> {{ classes.length ? 'Gabung kelas lain' : 'Kamu belum tergabung di kelas' }}</b>
                        <small>{{ classes.length ? 'Punya kode kelas lain dari guru? Masukkan di sini.' : 'Masukkan kode kelas dari gurumu, atau minta guru menambahkanmu ke kelas.' }}</small>
                    </div>
                    <div class="sa-join-field">
                        <input
                            v-model="joinForm.code"
                            type="text"
                            required
                            maxlength="12"
                            placeholder="Contoh: 987ACA"
                            aria-label="Kode kelas"
                            autocomplete="off"
                        />
                        <button type="submit" :disabled="joinForm.processing" class="st-btn st-btn--primary">
                            {{ joinForm.processing ? 'Memproses...' : 'Gabung' }}
                        </button>
                    </div>
                    <p v-if="joinForm.errors.code" class="sa-error" role="alert">{{ joinForm.errors.code }}</p>
                </form>
            </section>

            <!-- Tugas belum selesai -->
            <section class="st-panel sa-panel">
                <div class="sa-panel-head">
                    <h2><Target :size="18" /> Tugas Belum Selesai</h2>
                    <span v-if="overdueCount" class="sa-overdue">{{ overdueCount }} terlewat</span>
                </div>

                <div v-if="pending.length" class="sa-tasks">
                    <article v-for="task in pending" :key="task.id" class="sa-task" :class="{ 'is-overdue': task.is_overdue }">
                        <span class="sa-letter">{{ task.lesson }}</span>
                        <div class="sa-task-main">
                            <b>{{ task.title }}</b>
                            <small>{{ task.classroom || 'Kelas' }} · Guru {{ task.teacher }}</small>
                            <p v-if="task.description">{{ task.description }}</p>
                            <div class="sa-tags">
                                <span>Isyarat {{ task.lesson }} · {{ task.language }}</span>
                                <span>Skor minimal {{ task.min_score }}%</span>
                                <span class="sa-tag-xp"><Star :size="12" /> +{{ task.xp_reward }} XP</span>
                                <span :class="{ 'sa-tag-late': task.is_overdue }"><CalendarClock :size="12" /> {{ dueLabel(task) }}</span>
                            </div>
                        </div>
                        <Link v-if="!task.is_overdue" :href="`/lesson/${task.lesson_id}`" class="st-btn st-btn--primary sa-do">Kerjakan</Link>
                        <span v-else class="sa-closed">Ditutup</span>
                    </article>
                </div>
                <div v-else class="st-empty">
                    {{ classes.length ? 'Tidak ada tugas yang menunggu. Tugas baru dari gurumu akan muncul di sini otomatis.' : 'Gabung ke kelas dulu untuk menerima tugas dari guru.' }}
                </div>
            </section>

            <!-- Tugas selesai -->
            <section class="st-panel sa-panel">
                <div class="sa-panel-head">
                    <h2><Trophy :size="18" /> Tugas Selesai</h2>
                </div>
                <ul v-if="completed.length" class="sa-done">
                    <li v-for="task in completed" :key="task.id">
                        <span class="sa-letter is-done">{{ task.lesson }}</span>
                        <div class="sa-task-main">
                            <b>{{ task.title }}</b>
                            <small>{{ task.classroom || 'Kelas' }} · selesai {{ formatDate(task.completed_at) }}</small>
                        </div>
                        <span class="sa-score"><CheckCircle :size="14" /> {{ task.score ?? '-' }}%</span>
                        <span class="sa-tag-xp">+{{ task.xp_reward }} XP</span>
                    </li>
                </ul>
                <div v-else class="st-empty">Belum ada tugas yang selesai.</div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.sa {
    display: grid;
    gap: 18px;
    padding-bottom: 32px;
}

.sa-hero {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 22px 24px;
    background: linear-gradient(120deg, #ffffff, #eefaf4);
    border: 1px solid #e2ede6;
    border-radius: 20px;
}

.sa-kicker {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 700;
    color: #1e7253;
    background: #fff;
    border: 1px solid #d5f3e5;
    border-radius: 999px;
}

.sa-hero h1 {
    margin: 10px 0 6px;
    font-size: 26px;
    font-weight: 800;
    color: #14221d;
}

.sa-hero p {
    max-width: 520px;
    margin: 0;
    font-size: 15px;
    line-height: 1.55;
    color: #52685d;
}

.sa-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin: 0;
}

.sa-summary div {
    min-width: 92px;
    padding: 10px 12px;
    text-align: center;
    background: #fff;
    border: 1px solid #e2ede6;
    border-radius: 14px;
}

.sa-summary dt {
    font-size: 12px;
    color: #6b8176;
}

.sa-summary dd {
    margin: 2px 0 0;
    font-size: 22px;
    font-weight: 800;
    color: #14221d;
}

.sa-panel {
    display: grid;
    gap: 14px;
    padding: 18px 20px;
    border-radius: 20px;
}

.sa-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.sa-panel-head h2 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 17px;
    font-weight: 800;
    color: #14221d;
}

/* Kelas */
.sa-classes {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 10px;
}

.sa-class {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    background: #fbfdfc;
    border: 1px solid #e2ede6;
    border-radius: 14px;
    transition: 0.3s;
}

.sa-class.is-new {
    background: #eefaf4;
    border-color: #34b182;
    box-shadow: 0 0 0 3px #d5f3e5;
}

.sa-class-icon {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 44px;
    height: 44px;
    color: #34b182;
    background: #eefaf4;
    border-radius: 12px;
}

.sa-class-main {
    display: grid;
    flex: 1;
    min-width: 0;
}

.sa-class-main b {
    font-size: 15px;
    color: #14221d;
}

.sa-class-main small {
    font-size: 12px;
    color: #6b8176;
}

.sa-new {
    margin-left: 4px;
    padding: 1px 8px;
    font-size: 11px;
    color: #fff;
    background: #34b182;
    border-radius: 999px;
}

.sa-class-count {
    flex-shrink: 0;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 700;
    color: #1e7253;
    background: #fff;
    border: 1px solid #d5f3e5;
    border-radius: 999px;
}

/* Gabung kelas */
.sa-join {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px 16px;
    padding: 14px;
    background: #fbfdfc;
    border: 1px dashed #d5e2dc;
    border-radius: 14px;
}

.sa-join.is-primary {
    padding: 20px;
    background: #fdf9ef;
    border: 1px solid #f1deaf;
}

.sa-join-text {
    display: grid;
    gap: 2px;
}

.sa-join-text b {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 15px;
    color: #14221d;
}

.sa-join-text small {
    font-size: 13px;
    color: #6b8176;
}

.sa-join-field {
    display: flex;
    gap: 8px;
}

.sa-join-field input {
    width: 170px;
    padding: 10px 12px;
    font-family: ui-monospace, monospace;
    font-size: 16px;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
    background: #fff;
    border: 1px solid #d5e2dc;
    border-radius: 10px;
}

.sa-join-field input::placeholder {
    font-family: inherit;
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0;
    text-transform: none;
}

.sa-error {
    grid-column: 1 / -1;
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    color: #b42318;
}

/* Tugas */
.sa-tasks,
.sa-done {
    display: grid;
    gap: 10px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.sa-task {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px;
    background: #fff;
    border: 1px solid #e2ede6;
    border-radius: 14px;
}

.sa-task.is-overdue {
    background: #fffaf7;
    border-color: #f6d3bd;
}

.sa-letter {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 56px;
    height: 56px;
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    background: #34b182;
    border-radius: 14px;
}

.sa-letter.is-done {
    width: 42px;
    height: 42px;
    font-size: 20px;
    color: #1e7253;
    background: #eefaf4;
}

.sa-task-main {
    display: grid;
    flex: 1;
    gap: 2px;
    min-width: 0;
}

.sa-task-main b {
    font-size: 15px;
    color: #14221d;
}

.sa-task-main small {
    font-size: 12px;
    color: #6b8176;
}

.sa-task-main p {
    margin: 4px 0 0;
    font-size: 13px;
    line-height: 1.5;
    color: #52685d;
}

.sa-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 6px;
}

.sa-tags span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    font-size: 12px;
    font-weight: 600;
    color: #52685d;
    background: #f5f8f6;
    border-radius: 999px;
}

.sa-tags .sa-tag-xp,
.sa-tag-xp {
    font-weight: 800;
    color: #94711f;
    background: #fdf9ef;
}

.sa-tags .sa-tag-late {
    color: #b4562a;
    background: #fdeee4;
}

.sa-do {
    flex-shrink: 0;
}

.sa-closed {
    flex-shrink: 0;
    font-size: 13px;
    font-weight: 700;
    color: #b4562a;
}

.sa-overdue {
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 700;
    color: #b4562a;
    background: #fdeee4;
    border-radius: 999px;
}

.sa-done li {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: #fbfdfc;
    border: 1px solid #eef3f0;
    border-radius: 12px;
}

.sa-score {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    font-weight: 800;
    color: #1e7253;
}

.sa-done .sa-tag-xp {
    flex-shrink: 0;
    padding: 3px 9px;
    font-size: 12px;
    border-radius: 999px;
}

@media (max-width: 640px) {
    .sa-hero {
        padding: 18px 16px;
    }

    .sa-summary {
        width: 100%;
    }

    .sa-join {
        grid-template-columns: 1fr;
    }

    .sa-join-field input {
        flex: 1;
        width: auto;
    }

    .sa-task {
        flex-wrap: wrap;
    }

    .sa-task-main {
        flex-basis: calc(100% - 70px);
    }

    .sa-do {
        width: 100%;
    }
}
</style>
