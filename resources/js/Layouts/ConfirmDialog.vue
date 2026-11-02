<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Ya, Setuju' },
    cancelLabel: { type: String, default: 'Tidak' },
    processing: { type: Boolean, default: false },
    tone: { type: String, default: 'primary' },
});

const emit = defineEmits(['confirm', 'cancel']);

const cancelButton = ref(null);

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            await nextTick();
            cancelButton.value?.focus();
        }
    },
);

function cancel() {
    if (!props.processing) {
        emit('cancel');
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition name="confirm-dialog">
            <div v-if="open" class="confirm-backdrop" @click.self="cancel" @keydown.esc="cancel">
                <div
                    class="confirm-dialog"
                    :class="`confirm-dialog--${tone}`"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="confirm-dialog-title"
                    :aria-describedby="description ? 'confirm-dialog-description' : undefined"
                >
                    <span class="confirm-dialog-icon"><slot name="icon" /></span>
                    <h2 id="confirm-dialog-title">{{ title }}</h2>
                    <p v-if="description" id="confirm-dialog-description">{{ description }}</p>
                    <div class="confirm-dialog-actions">
                        <button ref="cancelButton" type="button" class="confirm-dialog-cancel" :disabled="processing" @click="cancel">
                            {{ cancelLabel }}
                        </button>
                        <button type="button" class="confirm-dialog-confirm" :disabled="processing" @click="emit('confirm')">
                            {{ processing ? 'Memproses...' : confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.confirm-backdrop {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: grid;
    place-items: center;
    padding: 16px;
    background: rgba(20, 34, 29, 0.55);
}

.confirm-dialog {
    --confirm-color: #34b182;
    --confirm-soft: #eefaf4;
    display: grid;
    justify-items: center;
    gap: 10px;
    width: min(100%, 400px);
    padding: 26px 22px 22px;
    text-align: center;
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 24px 60px rgba(20, 34, 29, 0.25);
}

.confirm-dialog--danger {
    --confirm-color: #d9534f;
    --confirm-soft: #fdf2f2;
}

.confirm-dialog-icon {
    display: grid;
    place-items: center;
    width: 60px;
    height: 60px;
    color: var(--confirm-color);
    background: var(--confirm-soft);
    border-radius: 50%;
}

.confirm-dialog h2 {
    margin: 4px 0 0;
    font-size: 20px;
    font-weight: 800;
    color: #14221d;
}

.confirm-dialog p {
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
    color: #52685d;
}

.confirm-dialog-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    width: 100%;
    margin-top: 10px;
}

.confirm-dialog-actions button {
    min-height: 46px;
    padding: 0 12px;
    font-size: 14px;
    font-weight: 700;
    border-radius: 12px;
    cursor: pointer;
}

.confirm-dialog-actions button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.confirm-dialog-cancel {
    color: #14221d;
    background: #ffffff;
    border: 1px solid #d5e2dc;
}

.confirm-dialog-confirm {
    color: #ffffff;
    background: var(--confirm-color);
    border: 1px solid var(--confirm-color);
}

.confirm-dialog-enter-active,
.confirm-dialog-leave-active {
    transition: opacity 0.18s;
}

.confirm-dialog-enter-from,
.confirm-dialog-leave-to {
    opacity: 0;
}
</style>
