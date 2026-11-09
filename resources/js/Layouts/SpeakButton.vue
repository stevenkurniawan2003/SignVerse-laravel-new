<script setup>
import { computed } from 'vue';
import { Square, Volume2 } from '@lucide/vue';
import { hasIndonesianVoice, isSpeaking, isSpeechSupported, speak, stopSpeech } from '../speech';

/**
 * Tombol "Dengarkan" untuk membacakan teks. Klik kedua saat sedang dibacakan
 * akan menghentikan suara. Tidak tampil di browser tanpa dukungan suara.
 */
const props = defineProps({
    text: { type: String, required: true },
    label: { type: String, default: 'Dengarkan' },
    iconOnly: { type: Boolean, default: false },
    variant: { type: String, default: 'soft' },
    // Teks lain milik langkah yang sama (mis. umpan balik jawaban kuis). Selama
    // salah satunya dibacakan, tombol ini tampil "Berhenti" dan bisa menghentikannya.
    relatedTexts: { type: Array, default: () => [] },
});

const active = computed(() => isSpeaking(props.text) || props.relatedTexts.some((text) => text && isSpeaking(text)));

const title = computed(() => {
    if (active.value) return 'Hentikan suara';
    return hasIndonesianVoice.value
        ? props.label
        : `${props.label} (suara Bahasa Indonesia tidak tersedia di perangkat ini, logat bisa berbeda)`;
});

function toggle() {
    if (active.value) {
        stopSpeech();
        return;
    }
    speak(props.text);
}
</script>

<template>
    <button
        v-if="isSpeechSupported"
        type="button"
        class="speak-btn"
        :class="[`speak-btn--${variant}`, { 'is-active': active, 'is-icon': iconOnly }]"
        :aria-label="title"
        :aria-pressed="active"
        :title="title"
        @click="toggle"
    >
        <span class="speak-icon" aria-hidden="true">
            <Square v-if="active" :size="12" fill="currentColor" />
            <Volume2 v-else :size="16" />
        </span>
        <span v-if="!iconOnly">{{ active ? 'Berhenti' : label }}</span>
        <span v-if="active" class="speak-wave" aria-hidden="true"><i></i><i></i><i></i></span>
    </button>
</template>

<style scoped>
.speak-btn {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 7px;
    min-height: 36px;
    padding: 0 13px 0 10px;
    font-size: 13px;
    font-weight: 700;
    color: var(--st-primary-dark);
    background: var(--st-primary-soft);
    border: 1px solid var(--st-primary-line);
    border-radius: 999px;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.speak-btn:hover {
    border-color: var(--st-primary);
}

.speak-btn:focus-visible {
    outline: 2px solid var(--st-primary);
    outline-offset: 2px;
}

.speak-btn.is-active {
    color: #fff;
    background: var(--st-primary);
    border-color: var(--st-primary);
}

/* Varian di atas latar gelap/berwarna */
.speak-btn--light {
    color: var(--st-ink);
    background: #fff;
    border-color: var(--st-line);
}

.speak-btn.is-icon {
    justify-content: center;
    width: 36px;
    padding: 0;
}

.speak-icon {
    display: inline-flex;
}

.speak-wave {
    display: inline-flex;
    align-items: flex-end;
    gap: 2px;
    height: 12px;
}

.speak-btn.is-icon .speak-wave {
    display: none;
}

.speak-wave i {
    width: 3px;
    height: 100%;
    background: currentColor;
    border-radius: 2px;
    animation: speak-wave 0.9s ease-in-out infinite;
}

.speak-wave i:nth-child(2) {
    animation-delay: 0.15s;
}

.speak-wave i:nth-child(3) {
    animation-delay: 0.3s;
}

@keyframes speak-wave {
    0%, 100% { transform: scaleY(0.35); }
    50% { transform: scaleY(1); }
}

@media (prefers-reduced-motion: reduce) {
    .speak-wave i {
        animation: none;
    }
}
</style>
