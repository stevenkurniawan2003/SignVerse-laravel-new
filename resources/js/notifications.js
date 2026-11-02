import { ref } from 'vue';

const SUCCESS_DURATION_MS = 5000;
const ERROR_DURATION_MS = 8000;

/** Notifikasi yang sedang tampil di pojok kanan atas. */
export const activeToast = ref(null);

let dismissTimer = null;

export function dismissToast() {
    clearTimeout(dismissTimer);
    activeToast.value = null;
}

export function showToast(message, type = 'success') {
    clearTimeout(dismissTimer);
    activeToast.value = { message, type };
    dismissTimer = setTimeout(dismissToast, type === 'error' ? ERROR_DURATION_MS : SUCCESS_DURATION_MS);
}
