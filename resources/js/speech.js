import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Text-to-speech berbasis Web Speech API bawaan browser (tanpa layanan luar).
 *
 * - Tombol "Dengarkan" selalu bisa dipakai karena diminta langsung oleh pengguna.
 * - Pembacaan otomatis (mis. hasil latihan) hanya berjalan bila preferensi
 *   "suara otomatis" aktif. Preferensi disimpan per perangkat di localStorage.
 */

const STORAGE_KEY = 'signtech_speech_auto';
const SPEECH_LANG = 'id-ID';
const SPEECH_RATE = 0.95;

export const isSpeechSupported = typeof window !== 'undefined'
    && 'speechSynthesis' in window
    && typeof window.SpeechSynthesisUtterance === 'function';

/** Teks yang sedang dibacakan (null bila diam) — dipakai tombol untuk status aktif. */
export const speakingText = ref(null);

/** Apakah perangkat punya suara Bahasa Indonesia. Tanpa itu teks dibaca berlogat asing. */
export const hasIndonesianVoice = ref(false);

/** Preferensi pembacaan otomatis. */
export const autoSpeechEnabled = ref(readAutoPreference());

let indonesianVoice = null;
let currentUtterance = null;
let unlocked = false;

function readAutoPreference() {
    try {
        return localStorage.getItem(STORAGE_KEY) !== 'off';
    } catch {
        return true;
    }
}

export function setAutoSpeech(enabled) {
    autoSpeechEnabled.value = Boolean(enabled);
    try {
        localStorage.setItem(STORAGE_KEY, enabled ? 'on' : 'off');
    } catch {
        // penyimpanan lokal tidak tersedia — pilihan tetap berlaku sampai halaman dimuat ulang
    }
    if (!enabled) {
        stopSpeech();
    }
}

/** Daftar suara dimuat asinkron oleh browser, jadi dipilih ulang setiap kali berubah. */
function refreshVoices() {
    const voices = window.speechSynthesis.getVoices();
    indonesianVoice = voices.find((voice) => voice.lang === SPEECH_LANG)
        || voices.find((voice) => voice.lang?.toLowerCase().replace('_', '-').startsWith('id'))
        || null;
    hasIndonesianVoice.value = indonesianVoice !== null;
}

/**
 * Bacakan teks. Mengembalikan false bila tidak diucapkan
 * (browser tidak mendukung, teks kosong, atau pembacaan otomatis dimatikan).
 *
 * @param {string} text
 * @param {{ auto?: boolean }} [options] auto=true untuk pembacaan yang tidak diminta langsung
 */
export function speak(text, { auto = false } = {}) {
    const content = String(text || '').trim();
    if (!isSpeechSupported || !content) return false;
    if (auto && !autoSpeechEnabled.value) return false;

    const synth = window.speechSynthesis;
    synth.cancel();
    // Chrome kadang tertahan dalam status "paused" setelah tab berpindah
    synth.resume();

    const utterance = new SpeechSynthesisUtterance(content);
    utterance.lang = SPEECH_LANG;
    utterance.rate = SPEECH_RATE;
    if (indonesianVoice) {
        utterance.voice = indonesianVoice;
    }

    // Event dari ucapan lama (yang dibatalkan) diabaikan agar status tidak tertimpa
    const finish = () => {
        if (currentUtterance === utterance) {
            currentUtterance = null;
            speakingText.value = null;
        }
    };
    utterance.onend = finish;
    utterance.onerror = finish;

    // Referensi disimpan agar ucapan tidak dibersihkan garbage collector sebelum selesai
    currentUtterance = utterance;
    speakingText.value = content;
    synth.speak(utterance);

    return true;
}

export function stopSpeech() {
    if (!isSpeechSupported) return;
    currentUtterance = null;
    speakingText.value = null;
    window.speechSynthesis.cancel();
}

export function isSpeaking(text) {
    return speakingText.value !== null && speakingText.value === String(text || '').trim();
}

/** Ubah angka persen menjadi kata agar dibaca "85 persen", bukan "85 tanda persen". */
export function percentText(value) {
    return `${Math.round(Number(value) || 0)} persen`;
}

/**
 * Safari/iOS hanya mengizinkan suara setelah pengguna berinteraksi.
 * Ucapan kosong pada interaksi pertama "membuka" mesin suara, sehingga
 * pembacaan otomatis setelah hitung mundur kamera tetap terdengar.
 */
function unlockOnFirstInteraction() {
    if (unlocked) return;
    unlocked = true;
    const silent = new SpeechSynthesisUtterance('');
    silent.volume = 0;
    window.speechSynthesis.speak(silent);
    window.removeEventListener('pointerdown', unlockOnFirstInteraction);
    window.removeEventListener('keydown', unlockOnFirstInteraction);
}

if (isSpeechSupported) {
    refreshVoices();
    window.speechSynthesis.addEventListener?.('voiceschanged', refreshVoices);
    window.addEventListener('pointerdown', unlockOnFirstInteraction);
    window.addEventListener('keydown', unlockOnFirstInteraction);

    // Suara dihentikan saat pindah ke halaman lain atau menutup tab. Reload parsial
    // di alamat yang sama (mis. memperbarui XP setelah latihan) tidak memotong suara.
    router.on('start', (event) => {
        const targetPath = event.detail.visit?.url?.pathname;
        if (!targetPath || targetPath !== window.location.pathname) {
            stopSpeech();
        }
    });
    window.addEventListener('pagehide', stopSpeech);
}
