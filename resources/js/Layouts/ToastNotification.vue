<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck } from '@lucide/vue';
import { activeToast, dismissToast, showToast } from '../notifications';

const props = defineProps({
    initialFlash: { type: Object, default: null },
});

// Partial reload mempertahankan prop flash lama: tampilkan sekali saja
let lastFlash = null;

function showFlash(flash) {
    const message = flash?.error || flash?.status;
    if (!message || flash === lastFlash) return;
    lastFlash = flash;
    showToast(message, flash.error ? 'error' : 'success');
}

onMounted(() => showFlash(props.initialFlash));
const removeFlashListener = router.on('success', (event) => showFlash(event.detail.page.props.flash));
onBeforeUnmount(removeFlashListener);
</script>

<template>
    <Teleport to="body">
        <Transition name="toast">
            <div
                v-if="activeToast"
                class="app-toast"
                :class="`app-toast--${activeToast.type}`"
                :role="activeToast.type === 'error' ? 'alert' : 'status'"
            >
                <component :is="activeToast.type === 'error' ? CircleAlert : CircleCheck" :size="18" class="app-toast-icon" />
                <span>{{ activeToast.message }}</span>
                <button type="button" aria-label="Tutup notifikasi" @click="dismissToast">&times;</button>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* Di bawah topbar agar tombol menu dan navigasi tidak tertutup */
.app-toast {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 90;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    max-width: min(440px, calc(100vw - 32px));
    padding: 14px 16px;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.5;
    border-radius: 14px;
    box-shadow: 0 16px 40px rgba(20, 34, 29, 0.18);
}

.app-toast span {
    flex: 1;
}

.app-toast-icon {
    flex-shrink: 0;
    margin-top: 2px;
}

.app-toast--success {
    color: #1e7253;
    background: #eefaf4;
    border: 1px solid #bfe8d4;
}

.app-toast--error {
    color: #b42318;
    background: #fef0ee;
    border: 1px solid #f7c7c0;
}

.app-toast button {
    flex-shrink: 0;
    font-size: 20px;
    line-height: 1;
    color: inherit;
    background: transparent;
    border: 0;
    cursor: pointer;
}

.toast-enter-active,
.toast-leave-active {
    transition: 0.25s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}

@media (max-width: 760px) {
    .app-toast {
        right: 16px;
        left: 16px;
        max-width: none;
    }
}
</style>
