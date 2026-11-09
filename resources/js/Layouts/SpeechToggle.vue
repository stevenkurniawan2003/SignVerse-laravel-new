<script setup>
import { Volume2, VolumeX } from '@lucide/vue';
import { autoSpeechEnabled, isSpeechSupported, setAutoSpeech, speak } from '../speech';

/**
 * Saklar pembacaan otomatis (hasil latihan & umpan balik kuis).
 * Tombol "Dengarkan" tetap bisa dipakai walau saklar ini mati.
 */
defineProps({
    compact: { type: Boolean, default: false },
});

function toggle() {
    const enabled = !autoSpeechEnabled.value;
    setAutoSpeech(enabled);
    if (enabled) {
        speak('Suara otomatis aktif.');
    }
}
</script>

<template>
    <button
        v-if="isSpeechSupported"
        type="button"
        class="speech-toggle"
        :class="{ 'is-on': autoSpeechEnabled }"
        role="switch"
        :aria-checked="autoSpeechEnabled"
        :aria-label="autoSpeechEnabled ? 'Matikan suara otomatis' : 'Aktifkan suara otomatis'"
        :title="autoSpeechEnabled ? 'Suara otomatis aktif, hasil latihan dibacakan' : 'Suara otomatis mati'"
        @click="toggle"
    >
        <Volume2 v-if="autoSpeechEnabled" :size="16" />
        <VolumeX v-else :size="16" />
        <span v-if="!compact">Suara {{ autoSpeechEnabled ? 'On' : 'Off' }}</span>
    </button>
</template>

<style scoped>
.speech-toggle {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 38px;
    padding: 0 13px;
    font-size: 13px;
    font-weight: 700;
    color: var(--st-muted);
    background: #fff;
    border: 1px solid var(--st-line);
    border-radius: 999px;
    cursor: pointer;
    white-space: nowrap;
    transition: color 0.15s ease, background 0.15s ease, border-color 0.15s ease;
}

.speech-toggle:hover {
    border-color: var(--st-primary-line);
}

.speech-toggle:focus-visible {
    outline: 2px solid var(--st-primary);
    outline-offset: 2px;
}

.speech-toggle.is-on {
    color: var(--st-primary-dark);
    background: var(--st-primary-soft);
    border-color: var(--st-primary-line);
}
</style>
