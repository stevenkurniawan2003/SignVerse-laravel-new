<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { AlertCircle, ArrowRight, Check, Hand, Lightbulb, Lock, RotateCcw, X } from '@lucide/vue';
import PracticeCamera from '../Layouts/PracticeCamera.vue';
import PracticeResultModal from '../Layouts/PracticeResultModal.vue';
import SpeakButton from '../Layouts/SpeakButton.vue';
import SpeechToggle from '../Layouts/SpeechToggle.vue';
import { speak, stopSpeech } from '../speech';
import { shouldAnimatePageEntry } from '../pageTransition';

const isPageEntering = ref(shouldAnimatePageEntry(usePage().url));

const props = defineProps({
    id: { type: [String, Number], default: '1' },
    lesson: { type: Object, default: () => null },
    material: { type: Object, default: () => null },
    category: { type: Object, default: () => null },
    relatedLessons: { type: Array, default: () => [] },
    ai_supported: { type: Boolean, default: true },
    spelling: { type: Object, default: () => null },
});

function normalizeSpellingState(value) {
    if (!value || typeof value !== 'object' || !Array.isArray(value.letters)) return null;

    const letters = value.letters.map((letter) => String(letter || '').toUpperCase());
    const word = String(value.word || '').trim().toUpperCase();
    const letterIndex = Number(value.letter_index);
    const lettersDone = Number(value.letters_done);
    const wordCompleted = Boolean(value.word_completed);
    const currentLetter = value.current_letter === null || value.current_letter === undefined
        ? null
        : String(value.current_letter).toUpperCase();

    if (
        !word.trim()
        || !letters.length
        || letters.some((letter) => !/^[A-Z]$/.test(letter))
        || !Number.isInteger(letterIndex)
        || !Number.isInteger(lettersDone)
        || lettersDone < 0
        || lettersDone > letters.length
        || letterIndex !== lettersDone
        || word.replace(/\s+/g, '') !== letters.join('')
        || wordCompleted !== (lettersDone === letters.length)
    ) {
        return null;
    }

    if (currentLetter !== (letters[letterIndex] ?? null)) {
        return null;
    }

    return {
        word,
        letters,
        letter_index: letterIndex,
        letters_done: lettersDone,
        current_letter: currentLetter,
        word_completed: wordCompleted,
        progress_token: value.progress_token ? String(value.progress_token) : null,
        expires_at: value.expires_at ? String(value.expires_at) : null,
    };
}

function resumeSpellingFeedback(state) {
    if (!state || state.word_completed || state.letters_done === 0) return null;

    return {
        tone: 'sync',
        message: `Progres tersimpan: ${state.letters_done} dari ${state.letters.length} huruf selesai. Lanjutkan dari huruf ${state.current_letter}, posisi ${state.letter_index + 1}.`,
    };
}

const aiSupported = ref(props.ai_supported !== false);

const currentLesson = ref(props.lesson);
const currentMaterial = ref(props.material);
const currentCategory = ref(props.category);
const related = ref(props.relatedLessons || []);
const spelling = ref(normalizeSpellingState(props.spelling));

const step = ref(spelling.value?.letters_done > 0 ? 2 : 0);
const choice = ref('');
const checked = ref(false);

const cameraRef = ref(null);
const completionActionRef = ref(null);
const result = ref(null);
const resultOpen = ref(false);
const passed = ref(spelling.value?.word_completed === true);
const spellingFeedback = ref(resumeSpellingFeedback(spelling.value));
const spellingLoading = ref(false);
const spellingSyncRequired = ref(Boolean(spelling.value && !spelling.value.word_completed && !spelling.value.progress_token));
const lessonLoading = ref(!props.lesson);
const lessonError = ref('');
let lessonGeneration = 0;
let pendingProgressToken = null;
let removeNavigationListener = null;

function activeLessonId() {
    return currentLesson.value?.id ?? props.lesson?.id ?? props.id;
}

async function requestLesson(lessonId) {
    const response = await fetch(`/api/lessons/${encodeURIComponent(lessonId)}`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
    });
    const data = await response.json().catch(() => null);
    if (!response.ok || !data || data.status !== 'success') {
        throw new Error(data?.message || 'Materi belum dapat dimuat.');
    }
    return data;
}

function applyLessonPayload(data, { resume = true } = {}) {
    currentLesson.value = data.lesson || null;
    currentMaterial.value = data.material || null;
    currentCategory.value = data.category || null;
    related.value = data.relatedLessons || [];
    aiSupported.value = data.ai_supported !== false;
    spelling.value = normalizeSpellingState(data.spelling);
    pendingProgressToken = null;

    step.value = spelling.value && (spelling.value.word_completed || (resume && spelling.value.letters_done > 0)) ? 2 : 0;
    quizIndex.value = 0;
    choice.value = '';
    checked.value = false;
    result.value = null;
    resultOpen.value = false;
    passed.value = spelling.value?.word_completed === true;
    spellingFeedback.value = resume ? resumeSpellingFeedback(spelling.value) : null;
    spellingSyncRequired.value = Boolean(spelling.value && !spelling.value.word_completed && !spelling.value.progress_token);
    spellingLoading.value = false;
    lessonLoading.value = false;
    lessonError.value = '';
    cameraRef.value?.reset();
}

function cancelPendingCapture() {
    cameraRef.value?.cancelCountdown();
}

async function loadLesson(lessonId = activeLessonId()) {
    const generation = lessonGeneration;
    lessonLoading.value = true;
    lessonError.value = '';

    try {
        const data = await requestLesson(lessonId);
        if (generation === lessonGeneration) applyLessonPayload(data);
    } catch (error) {
        if (generation === lessonGeneration) lessonError.value = error.message;
    } finally {
        if (generation === lessonGeneration) lessonLoading.value = false;
    }
}

function onVisibilityChange() {
    if (document.hidden) cancelPendingCapture();
}

onMounted(() => {
    document.addEventListener('visibilitychange', onVisibilityChange);
    removeNavigationListener = router.on('start', (event) => {
        if (event.detail.visit.url.pathname === window.location.pathname) return;
        cancelPendingCapture();
        cameraRef.value?.reset();
        stopSpeech();
    });
    if (!currentLesson.value) loadLesson();
});

watch(
    () => String(props.lesson?.id ?? props.id),
    async (nextLessonId, previousLessonId) => {
        if (nextLessonId === previousLessonId) return;

        lessonGeneration += 1;
        cancelPendingCapture();
        stopSpeech();

        if (props.lesson && String(props.lesson.id) === nextLessonId) {
            applyLessonPayload({
                lesson: props.lesson,
                material: props.material,
                category: props.category,
                relatedLessons: props.relatedLessons,
                ai_supported: props.ai_supported,
                spelling: props.spelling,
            });
            return;
        }

        applyLessonPayload({ lesson: null, material: null, category: null, relatedLessons: [], ai_supported: false, spelling: null });
        await loadLesson(nextLessonId);
    },
);

const lessonTitleText = computed(() => {
    if (!currentLesson.value) return 'Pelajaran Isyarat';
    return `${currentLesson.value.title} (${String(currentMaterial.value?.language || 'SIBI').toUpperCase()})`;
});

const categorySlug = computed(() => String(currentCategory.value?.slug || '').toLowerCase());
const isVocabularyCategory = computed(() => ['word', 'phrase'].includes(categorySlug.value));
const isSpellingLesson = computed(() => spelling.value !== null);
const practiceSupported = computed(() => aiSupported.value && (!isVocabularyCategory.value || isSpellingLesson.value));
const spellingKind = computed(() => (categorySlug.value === 'phrase' ? 'frasa' : 'kata'));
const spellingKindTitle = computed(() => (spellingKind.value === 'frasa' ? 'Frasa' : 'Kata'));
const targetGesture = computed(() => (
    isSpellingLesson.value
        ? spelling.value.current_letter || spelling.value.letters[spelling.value.letters.length - 1] || ''
        : currentLesson.value?.title || 'A'
));
// Kuis ejaan menanyakan setiap huruf secara berurutan (pertama, kedua, dst.)
const quizIndex = ref(0);
const quizTarget = computed(() => (
    isSpellingLesson.value ? spelling.value.letters[quizIndex.value] || '' : targetGesture.value
));
const isLastQuizQuestion = computed(() => (
    !isSpellingLesson.value || quizIndex.value >= spelling.value.letters.length - 1
));
const ORDINALS = ['pertama', 'kedua', 'ketiga', 'keempat', 'kelima', 'keenam', 'ketujuh', 'kedelapan', 'kesembilan', 'kesepuluh'];

function ordinalOf(index) {
    return ORDINALS[index] ?? `ke-${index + 1}`;
}

const quizOrdinal = computed(() => ordinalOf(quizIndex.value));

const types = ['info', 'choice', 'practice'];
const currentType = computed(() => types[step.value] || 'info');
const languageLabel = computed(() => String(currentMaterial.value?.language || 'SIBI').toUpperCase());
const handTip = computed(() =>
    languageLabel.value === 'BISINDO'
        ? 'BISINDO umumnya memakai dua tangan. Pastikan kedua tangan masuk ke dalam bingkai kamera.'
        : 'SIBI memakai satu tangan. Pastikan hanya satu tangan yang terlihat di kamera.'
);
const lessonProgressPercent = computed(() => {
    if (passed.value) return 100;
    if (isSpellingLesson.value && step.value === 1) {
        return ((1 + (quizIndex.value / spelling.value.letters.length)) / types.length) * 100;
    }
    if (!isSpellingLesson.value || step.value < 2) return (step.value / types.length) * 100;
    return ((2 + (spelling.value.letters_done / spelling.value.letters.length)) / types.length) * 100;
});
const spellingGroups = computed(() => {
    if (!spelling.value) return [];

    let letterIndex = 0;
    const groups = spelling.value.word.trim().split(/\s+/).map((word) => (
        [...word].filter((character) => /^[A-Z]$/.test(character)).map(() => {
            const item = { letter: spelling.value.letters[letterIndex], index: letterIndex };
            letterIndex += 1;
            return item;
        })
    ));

    const groupedLetters = groups.flat().map((item) => item.letter).join('');
    if (groupedLetters === spelling.value.letters.join('')) return groups;
    return [spelling.value.letters.map((letter, index) => ({ letter, index }))];
});

function spellingPositionState(index) {
    if (!spelling.value) return 'pending';
    if (spelling.value.word_completed || index < spelling.value.letters_done) return 'completed';
    if (index === spelling.value.letter_index) return 'current';
    return 'pending';
}

function spellingPositionLabel(index) {
    const state = spellingPositionState(index);
    if (state === 'completed') return 'selesai';
    if (state === 'current') return 'huruf saat ini';
    return 'belum dikerjakan';
}

const options = computed(() => {
    const list = [quizTarget.value];
    // Pengecoh ejaan diambil dulu dari huruf lain di kata yang sama agar tidak terlalu mudah
    const candidates = isSpellingLesson.value
        ? spelling.value.letters
        : (related.value || []).map((item) => item.title);
    for (const option of candidates) {
        if (option && option !== quizTarget.value && !list.includes(option)) {
            list.push(option);
        }
        if (list.length >= 4) break;
    }
    const fallbacks = ['A', 'B', 'C', 'D', 'E', 'I', 'M', 'S'];
    for (const f of fallbacks) {
        if (!list.includes(f)) list.push(f);
        if (list.length >= 4) break;
    }

    // Acak agar jawaban benar tidak selalu berada di pilihan pertama
    const shuffled = list.slice(0, 4);
    for (let index = shuffled.length - 1; index > 0; index--) {
        const swapIndex = Math.floor(Math.random() * (index + 1));
        [shuffled[index], shuffled[swapIndex]] = [shuffled[swapIndex], shuffled[index]];
    }
    return shuffled;
});

function advance() {
    if (step.value !== 0 && !(step.value === 1 && checked.value && answerIsCorrect.value && isLastQuizQuestion.value)) return;

    stopSpeech();
    step.value += 1;
    choice.value = '';
    checked.value = false;
}

/** Lanjut ke pertanyaan huruf berikutnya dalam kuis ejaan. */
function nextQuizQuestion() {
    if (step.value !== 1 || !checked.value || !answerIsCorrect.value || isLastQuizQuestion.value) return;

    stopSpeech();
    quizIndex.value += 1;
    choice.value = '';
    checked.value = false;
    speak(quizSpeech.value, { auto: true });
}

function chooseAnswer(option) {
    if (checked.value) return;
    choice.value = option;
}

const answerIsCorrect = computed(() => choice.value === quizTarget.value);

/**
 * Umpan balik jawaban kuis yang dibacakan. Disimpan sebagai computed agar
 * tombol Dengarkan di kuis bisa mengenali suara ini dan berubah menjadi "Berhenti".
 */
const answerSpeech = computed(() => {
    if (!checked.value || !choice.value) return '';

    if (isSpellingLesson.value) {
        return answerIsCorrect.value
            ? `Jawaban benar! Huruf ${quizOrdinal.value} ${spellingKind.value} ${spelling.value.word} adalah ${quizTarget.value}. `
                + (isLastQuizQuestion.value ? 'Sekarang eja huruf demi huruf di depan kamera.' : 'Lanjut ke huruf berikutnya.')
            : `Kurang tepat. Kamu memilih huruf ${choice.value}. Perhatikan huruf ${quizOrdinal.value} ${spellingKind.value} ${spelling.value.word}.`;
    }

    return answerIsCorrect.value
        ? `Jawaban benar! Isyarat tadi adalah huruf ${targetGesture.value}. Sekarang saatnya memperagakan isyarat ini.`
        : `Kurang tepat. Kamu memilih huruf ${choice.value}. Coba ingat lagi bentuk isyarat yang tadi kamu amati.`;
});

function verifyAnswer() {
    if (checked.value || !choice.value || !options.value.includes(choice.value)) return;

    checked.value = true;
    speak(answerSpeech.value, { auto: true });
}

const infoSpeech = computed(() => {
    if (isSpellingLesson.value) {
        return `${spellingKindTitle.value} ${spelling.value.word} dieja ${spelling.value.letters.join(', ')}. `
            + `Ini latihan ejaan jari: kamera memeriksa satu huruf setiap kali, bukan satu isyarat utuh untuk ${spellingKind.value}.`;
    }
    return `Huruf ${targetGesture.value}, bahasa isyarat ${languageLabel.value}. `
        + `Amati dan peragakan bentuk jemari untuk isyarat ${targetGesture.value} sesuai kaidah ${languageLabel.value}. ${handTip.value}`;
});
const quizSpeech = computed(() => {
    const choices = options.value.map((option) => `huruf ${option}`).join(', ');
    if (isSpellingLesson.value) {
        return `Huruf ${quizOrdinal.value} ${spellingKind.value} ${spelling.value.word} apa? Pilihannya: ${choices}.`;
    }
    return `Isyarat yang baru kamu amati melambangkan apa? Pilihannya: ${choices}.`;
});
const practiceSpeech = computed(() => {
    if (isSpellingLesson.value) {
        if (spelling.value.word_completed) {
            return `${spellingKindTitle.value} ${spelling.value.word} selesai dieja. Semua ${spelling.value.letters.length} huruf sudah benar.`;
        }
        return `Eja ${spellingKind.value} ${spelling.value.word}. Sekarang huruf ${targetGesture.value}, `
            + `posisi ${spelling.value.letter_index + 1} dari ${spelling.value.letters.length}. ${handTip.value} `
            + 'Tekan Mulai Peragaan dan tahan bentuk tangan sampai hitung mundur selesai.';
    }
    return `Peragakan isyarat huruf ${targetGesture.value} di depan kamera. ${handTip.value} `
        + 'Tekan tombol Mulai Peragaan, lalu tahan bentuk tanganmu sampai hitung mundur selesai.';
});

watch(step, () => {
    if (!currentLesson.value || step.value === 0 || (step.value === 2 && !practiceSupported.value)) return;
    speak(step.value === 1 ? quizSpeech.value : practiceSpeech.value, { auto: true });
}, { flush: 'post' });

function retryAnswer() {
    stopSpeech();
    choice.value = '';
    checked.value = false;
    speak(quizSpeech.value, { auto: true });
}

// Pelajaran berikutnya dalam modul yang sama
const nextLesson = computed(() => {
    const position = related.value.findIndex((item) => String(item.id) === String(currentLesson.value?.id));
    return position >= 0 ? related.value[position + 1] ?? null : null;
});

// Dibuka dari tugas guru (?tugas=ID): tugas hanya untuk huruf ini,
// jadi setelah berhasil siswa kembali ke daftar tugas, bukan lanjut ke huruf berikutnya.
const page = usePage();
const isAssignmentMode = computed(() => {
    const query = String(page.url || '').split('?')[1] || '';
    return new URLSearchParams(query).has('tugas');
});

const continueLabel = computed(() => {
    if (isAssignmentMode.value) return 'Kembali ke Tugas';
    if (!nextLesson.value) return 'Kembali ke Materi';
    return isSpellingLesson.value
        ? `Lanjut ke ${spellingKindTitle.value} ${String(nextLesson.value.title).toUpperCase()}`
        : `Lanjut ke Huruf ${nextLesson.value.title}`;
});

function continueLesson() {
    cancelPendingCapture();
    stopSpeech();
    if (isAssignmentMode.value) {
        router.visit('/student/assignments');
        return;
    }
    router.visit(nextLesson.value ? `/lesson/${nextLesson.value.id}` : '/learn');
}

function failureBrief(detection) {
    if (detection.requestFailed) return detection.message || 'Percobaan belum dapat diproses. Coba lagi pada huruf yang sama.';
    if (!detection.evaluated) return 'Tangan belum terlihat jelas. Target tetap di huruf ini; atur posisi lalu coba lagi.';
    if (detection.recognized && detection.recognized !== detection.letter) {
        return `Terbaca sebagai huruf ${detection.recognized}. Target tetap huruf ${detection.letter}; perbaiki posisi lalu coba lagi.`;
    }
    return detection.message || `Huruf ${detection.letter} belum cukup jelas. Coba lagi pada posisi yang sama.`;
}

// Jeda persiapan kamera saat lanjut ke huruf ejaan berikutnya, agar siswa sempat mengganti bentuk tangan
const NEXT_LETTER_DELAY_SECONDS = 5;

// Popup huruf benar di tengah ejaan memakai tombol "Lanjut ke Huruf X"
const isLetterStepResult = computed(() => Boolean(result.value?.letterStep));
const resultNextLabel = computed(() => (
    isLetterStepResult.value && spelling.value?.current_letter
        ? `Lanjut ke Huruf ${spelling.value.current_letter}`
        : continueLabel.value
));

const canSelectSpellingLetter = computed(() => (
    isSpellingLesson.value
    && !spelling.value.word_completed
    && !resultOpen.value
    && !spellingLoading.value
    && !spellingSyncRequired.value
    && Boolean(spelling.value.progress_token)
    && !cameraRef.value?.busy
));

/**
 * Mulai peragaan huruf yang sedang ditunggu server (huruf berikutnya yang
 * belum benar). Ejaan selalu berurutan, jadi tidak ada huruf lain yang bisa dipilih.
 */
async function continueSpelling() {
    if (!isSpellingLesson.value || spelling.value.word_completed) return;

    stopSpeech();
    resultOpen.value = false;
    spellingFeedback.value = null;
    await nextTick();
    if (!canSelectSpellingLetter.value) return;

    // Tidak membacakan instruksi lagi: popup baru ditutup, siswa sedang bersiap
    // memperagakan, jadi suara harus berhenti bersama popup.
    // Huruf pertama mengikuti timer pilihan siswa; huruf berikutnya diberi jeda lebih lama
    cameraRef.value?.startDetection(
        spelling.value.letters_done > 0 ? { minimumSeconds: NEXT_LETTER_DELAY_SECONDS } : undefined,
    );
}

/**
 * Pilih huruf secara manual: huruf yang sudah benar tetap tercentang,
 * huruf saat ini langsung diperagakan, dan huruf sesudahnya ditolak
 * karena huruf sebelumnya belum diselesaikan.
 */
function selectSpellingLetter(index) {
    if (!canSelectSpellingLetter.value) return;

    const state = spellingPositionState(index);
    const letter = spelling.value.letters[index];

    if (state === 'current') {
        continueSpelling();
        return;
    }

    const message = state === 'completed'
        ? `Huruf ${letter} pada posisi ${index + 1} sudah benar dan tetap tersimpan.`
        : `Belum bisa memilih huruf ${letter}. Selesaikan huruf ${spelling.value.current_letter} (posisi ${spelling.value.letter_index + 1}) terlebih dulu.`;

    spellingFeedback.value = { tone: state === 'completed' ? 'success' : 'warning', message };
    speak(message, { auto: true });
}

function onDetectionResult(detection) {
    if (String(detection.lessonId) !== String(currentLesson.value?.id)) return;

    if (!isSpellingLesson.value) {
        result.value = detection;
        resultOpen.value = true;
        if (detection.success) {
            passed.value = true;
        }
        return;
    }

    cancelPendingCapture();
    const previousLetterIndex = spelling.value.letter_index;
    const previousLettersDone = spelling.value.letters_done;
    const serverSpelling = normalizeSpellingState(detection.spelling);

    if (detection.httpStatus === 409) {
        const stillProcessing = serverSpelling && !serverSpelling.word_completed
            && serverSpelling.progress_token === spelling.value.progress_token;
        pendingProgressToken = stillProcessing ? serverSpelling.progress_token : null;
        if (serverSpelling) spelling.value = serverSpelling;
        result.value = null;
        resultOpen.value = false;
        passed.value = serverSpelling?.word_completed === true;
        spellingSyncRequired.value = Boolean(stillProcessing || !serverSpelling || (!serverSpelling.word_completed && !serverSpelling.progress_token));
        spellingFeedback.value = {
            tone: spellingSyncRequired.value ? 'error' : 'sync',
            message: stillProcessing
                ? 'Peragaan sebelumnya masih diperiksa. Tunggu sebentar lalu tekan Perbarui Progres.'
                : serverSpelling?.current_letter
                ? `Progres ejaan disinkronkan. Lanjutkan dari huruf ${serverSpelling.current_letter}, posisi ${serverSpelling.letter_index + 1}.`
                : serverSpelling?.word_completed
                    ? `${spellingKindTitle.value} ${serverSpelling.word} sudah selesai dieja.`
                    : 'Progres ejaan belum tersedia. Perbarui progres sebelum mencoba lagi.',
        };
        speak(spellingFeedback.value.message, { auto: true });
        return;
    }

    if (serverSpelling) {
        spelling.value = serverSpelling;
        spellingSyncRequired.value = !serverSpelling.word_completed && !serverSpelling.progress_token;
    }

    if (!detection.success) {
        result.value = {
            ...detection,
            spelling: serverSpelling || spelling.value,
            spellingKind: spellingKind.value,
        };
        resultOpen.value = true;
        passed.value = false;
        spellingFeedback.value = { tone: 'error', message: failureBrief(detection) };
        return;
    }

    if (!serverSpelling) {
        result.value = null;
        resultOpen.value = false;
        passed.value = false;
        spellingSyncRequired.value = true;
        spellingFeedback.value = {
            tone: 'error',
            message: 'Huruf diterima, tetapi progres server belum dapat disinkronkan. Perbarui progres untuk mendapatkan target yang benar.',
        };
        return;
    }

    const completedLetter = detection.attemptedLetter || detection.letter;
    if (serverSpelling.word_completed) {
        result.value = { ...detection, spelling: serverSpelling, spellingKind: spellingKind.value };
        resultOpen.value = true;
        passed.value = true;
        spellingSyncRequired.value = false;
        spellingFeedback.value = { tone: 'success', message: `${spellingKindTitle.value} ${serverSpelling.word} selesai dieja.` };
        return;
    }

    const serverAdvanced = serverSpelling.letter_index > previousLetterIndex
        || serverSpelling.letters_done > previousLettersDone;
    if (!serverAdvanced) {
        result.value = null;
        resultOpen.value = false;
        passed.value = false;
        spellingSyncRequired.value = true;
        spellingFeedback.value = {
            tone: 'error',
            message: 'Huruf diterima, tetapi target berikutnya belum tersedia. Perbarui progres sebelum melanjutkan.',
        };
        return;
    }

    // Jeda setelah tiap huruf benar: popup menunggu siswa memilih Lanjut atau Kembali
    result.value = {
        ...detection,
        letter: completedLetter,
        letterStep: true,
        spelling: serverSpelling,
        spellingKind: spellingKind.value,
    };
    resultOpen.value = true;
    passed.value = false;
    spellingFeedback.value = {
        tone: 'success',
        message: `Huruf ${completedLetter} benar. Tekan huruf ${serverSpelling.current_letter} atau tombol Mulai Peragaan untuk melanjutkan.`,
    };
}

function onResultNext() {
    if (isLetterStepResult.value) {
        continueSpelling();
        return;
    }
    continueLesson();
}

async function closeResult() {
    cancelPendingCapture();
    resultOpen.value = false;
    await nextTick();
    if (!resultOpen.value && spelling.value?.word_completed) completionActionRef.value?.focus();
}

async function retryFromPopup() {
    cancelPendingCapture();
    stopSpeech();
    resultOpen.value = false;
    const generation = lessonGeneration;

    if (isSpellingLesson.value && spellingSyncRequired.value && !await refreshSpelling()) return;

    await nextTick();
    if (generation !== lessonGeneration || spellingLoading.value || spelling.value?.word_completed) return;

    spellingFeedback.value = null;
    cameraRef.value?.startDetection();
}

async function refreshSpelling({ repeat = false } = {}) {
    if (!isSpellingLesson.value || spellingLoading.value) {
        return false;
    }

    cancelPendingCapture();
    stopSpeech();
    cameraRef.value?.reset();
    resultOpen.value = false;
    spellingLoading.value = true;
    spellingSyncRequired.value = true;
    const generation = lessonGeneration;

    try {
        const data = await requestLesson(activeLessonId());
        if (generation !== lessonGeneration) return false;

        const freshSpelling = normalizeSpellingState(data.spelling);
        if (!freshSpelling || (!freshSpelling.word_completed && !freshSpelling.progress_token)) {
            throw new Error('Progres ejaan belum tersedia.');
        }
        if (pendingProgressToken && freshSpelling.progress_token === pendingProgressToken) {
            throw new Error('Peragaan sebelumnya masih diperiksa. Tunggu sebentar.');
        }
        if (repeat && freshSpelling.word_completed) {
            throw new Error('Sesi ejaan baru belum tersedia.');
        }

        applyLessonPayload(data, { resume: !repeat });
        step.value = 2;
        await nextTick();
        if (generation !== lessonGeneration) return false;
        // Instruksi tidak dibacakan otomatis di sini (dipanggil saat popup ditutup);
        // tombol Dengarkan tetap tersedia bila siswa ingin mendengarnya.
        return true;
    } catch (error) {
        if (generation !== lessonGeneration) return false;
        spellingFeedback.value = {
            tone: 'error',
            message: `${error.message} Tekan Perbarui Progres untuk mencoba lagi.`,
        };
        return false;
    } finally {
        if (generation === lessonGeneration) spellingLoading.value = false;
    }
}

onBeforeUnmount(() => {
    lessonGeneration += 1;
    cancelPendingCapture();
    stopSpeech();
    removeNavigationListener?.();
    document.removeEventListener('visibilitychange', onVisibilityChange);
});
</script>

<template>
    <Head :title="`Pelajaran: ${lessonTitleText}`" />

    <div
        class="focus-layout font-sans text-ink bg-surface min-h-screen"
        :class="{ 'page-slide-in': isPageEntering }"
        @animationend.self="isPageEntering = false"
    >
        <header class="lesson-header">
            <Link href="/learn" class="exit-lesson" aria-label="Keluar latihan">
                <X :size="20" />
            </Link>
            <div class="lesson-progress" role="progressbar" aria-label="Progres pelajaran" :aria-valuenow="Math.round(lessonProgressPercent)" aria-valuemin="0" aria-valuemax="100">
                <i :style="{ width: `${lessonProgressPercent}%` }"></i>
            </div>
            <div class="step-count">
                {{ step + 1 }} <small>/ {{ types.length }}</small>
            </div>
            <SpeechToggle compact />
        </header>

        <section v-if="currentLesson" class="st-panel exercise-card">
            <div class="exercise-kicker">
                <span class="activity-badge"><Hand :size="14" /></span>
                <span>{{ isSpellingLesson ? 'Ejaan jari' : languageLabel }} · {{ currentMaterial?.title || 'Kurikulum' }}</span>
                <span v-if="practiceSupported" class="st-badge st-badge--gold exercise-xp">+{{ currentLesson.xp_reward || 10 }} XP</span>
            </div>

            <!-- Step 1: Info Gesture -->
            <div v-if="currentType === 'info'">
                <div class="title-row">
                    <h1>{{ isSpellingLesson ? `Pelajari Ejaan ${spellingKindTitle} ${spelling.word}` : `Amati Bentuk Isyarat ${currentLesson.title}` }}</h1>
                    <SpeakButton :text="infoSpeech" />
                </div>
                <div class="gesture-demo" :class="{ 'gesture-demo--spelling': isSpellingLesson }">
                    <span class="gesture-hand"><Hand :size="80" class="text-primary" /></span>
                    <template v-if="isSpellingLesson">
                        <strong class="spelling-hero-word">{{ spelling.word }}</strong>
                        <small class="spelling-mode-note">Eja satu huruf setiap kali</small>
                    </template>
                    <span v-else class="gesture-letter">{{ currentLesson.title }}</span>
                </div>
                <p v-if="isSpellingLesson" class="exercise-detail">
                    Eja <strong>{{ spelling.word }}</strong> dengan fingerspelling {{ languageLabel }}. Kamera memeriksa setiap huruf secara terpisah, bukan satu isyarat {{ spellingKind }} utuh.
                </p>
                <p v-else class="exercise-detail">Peragakan bentuk jemari untuk isyarat <strong>{{ currentLesson.title }}</strong> sesuai kaidah {{ languageLabel }}.</p>
                <button type="button" class="st-btn st-btn--primary st-btn--block exercise-submit" @click="advance">
                    <span>Saya Siap Berlatih</span>
                    <ArrowRight :size="16" />
                </button>
            </div>

            <!-- Step 2: Kuis pemahaman -->
            <div v-else-if="currentType === 'choice'">
                <div class="title-row">
                    <h1>{{ isSpellingLesson ? `Huruf ${quizOrdinal} ${spellingKind} ini apa?` : 'Isyarat yang baru kamu amati melambangkan apa?' }}</h1>
                    <SpeakButton :text="quizSpeech" :related-texts="[answerSpeech]" />
                </div>
                <div class="gesture-demo" :class="{ 'gesture-demo--spelling': isSpellingLesson }">
                    <span class="gesture-hand"><Hand :size="80" class="text-primary" /></span>
                    <template v-if="isSpellingLesson">
                        <strong class="spelling-hero-word">{{ spelling.word }}</strong>
                        <div class="quiz-letters" :aria-label="`Pertanyaan ${quizIndex + 1} dari ${spelling.letters.length}`">
                            <span
                                v-for="(letter, index) in spelling.letters"
                                :key="index"
                                class="quiz-letter"
                                :class="{ 'is-answered': index < quizIndex, 'is-asked': index === quizIndex }"
                            >{{ index < quizIndex ? letter : index === quizIndex ? '?' : '' }}</span>
                        </div>
                        <small class="spelling-mode-note">Pertanyaan {{ quizIndex + 1 }} dari {{ spelling.letters.length }} · pilih huruf {{ quizOrdinal }}</small>
                    </template>
                </div>
                <div class="answer-grid">
                    <button
                        v-for="opt in options"
                        :key="opt"
                        type="button"
                        class="answer-option"
                        :class="{
                            selected: choice === opt,
                            correct: checked && opt === quizTarget,
                            incorrect: checked && choice === opt && opt !== quizTarget,
                        }"
                        :aria-pressed="choice === opt"
                        :disabled="checked"
                        @click="chooseAnswer(opt)"
                    >
                        <span class="choice-letter">{{ opt }}</span>
                        <span>{{ isSpellingLesson ? 'Huruf' : 'Isyarat' }} {{ opt }}</span>
                    </button>
                </div>

                <div v-if="!checked" class="step-actions">
                    <button type="button" class="st-btn st-btn--primary st-btn--block exercise-submit" :disabled="!choice" @click="verifyAnswer">
                        Periksa Jawaban
                    </button>
                </div>
                <div v-else class="step-actions">
                    <div class="answer-feedback" :class="answerIsCorrect ? 'good' : 'bad'" role="status">
                        {{ answerIsCorrect
                            ? (isSpellingLesson
                                ? (isLastQuizQuestion
                                    ? `Jawaban benar! Semua huruf ${spelling.word} sudah kamu kenali. Sekarang eja di depan kamera.`
                                    : `Jawaban benar! Huruf ${quizOrdinal} adalah ${quizTarget}.`)
                                : 'Jawaban benar! Sekarang saatnya memperagakan isyarat ini.')
                            : (isSpellingLesson ? `Kurang tepat. Perhatikan huruf ${quizOrdinal} ${spellingKind} ${spelling.word}.` : 'Kurang tepat. Coba ingat lagi bentuk isyarat yang tadi kamu amati.')
                        }}
                    </div>
                    <button v-if="answerIsCorrect && !isLastQuizQuestion" type="button" class="st-btn st-btn--primary st-btn--block exercise-submit" @click="nextQuizQuestion">
                        <span>Lanjut ke Huruf {{ ordinalOf(quizIndex + 1) }}</span>
                        <ArrowRight :size="16" />
                    </button>
                    <button v-else-if="answerIsCorrect" type="button" class="st-btn st-btn--primary st-btn--block exercise-submit" @click="advance">
                        <span>Lanjut ke Peragaan</span>
                        <ArrowRight :size="16" />
                    </button>
                    <button v-else type="button" class="st-btn st-btn--secondary st-btn--block exercise-submit" @click="retryAnswer">
                        <RotateCcw :size="16" />
                        <span>Jawab Lagi</span>
                    </button>
                </div>
            </div>

            <!-- Step 3 (isyarat dilatih bersama guru): tidak lewat kamera mandiri -->
            <div v-else-if="currentType === 'practice' && !practiceSupported">
                <h1>{{ isVocabularyCategory ? `Latihan Ejaan ${String(currentLesson.title).toUpperCase()}` : `Peragakan Isyarat ${targetGesture}` }}</h1>
                <div class="practice-feedback" role="status">
                    <b>Isyarat ini dilatih langsung bersama guru.</b>
                    <small v-if="isVocabularyCategory">Judul materi ini belum dapat dijadikan ejaan jari A–Z. Latih bersama guru di kelas atau pilih materi kata/frasa lain.</small>
                    <small v-else>Saat ini kamera latihan mandiri dikhususkan untuk latihan huruf alfabet serta ejaan kata dan frasa. Latih isyarat {{ targetGesture }} bersama bimbingan guru di kelas ya!</small>
                </div>
                <Link href="/learn" class="st-btn st-btn--primary st-btn--block">
                    <span>Kembali ke Materi</span>
                    <ArrowRight :size="16" />
                </Link>
            </div>

            <!-- Step 3: Peragaan di depan kamera, dinilai AI -->
            <div v-else-if="currentType === 'practice'" class="practice-step">
                <div class="title-row">
                    <h1>{{ isSpellingLesson ? spelling.word_completed ? `${spellingKindTitle} ${spelling.word} Selesai Dieja` : `Eja ${spellingKindTitle} ${spelling.word}: Huruf ${targetGesture}` : `Peragakan Isyarat ${targetGesture}` }}</h1>
                    <SpeakButton :text="practiceSpeech" :related-texts="[spellingFeedback?.message]" />
                </div>
                <div v-if="!isSpellingLesson || !spelling.word_completed" class="hand-tip">
                    <Lightbulb :size="16" />
                    <p>{{ isSpellingLesson ? `Kamera memeriksa satu huruf per foto. ${handTip}` : handTip }}</p>
                </div>

                <div v-if="isSpellingLesson" class="spelling-progress">
                    <div class="spelling-progress-head">
                        <div>
                            <small>{{ spellingKindTitle }} yang dieja</small>
                            <strong>{{ spelling.word }}</strong>
                        </div>
                        <span>{{ spelling.letters_done }} / {{ spelling.letters.length }} huruf</span>
                    </div>
                    <div class="spelling-boxes" role="list" :aria-label="`Progres ejaan ${spelling.word}`">
                        <div v-for="(group, groupIndex) in spellingGroups" :key="groupIndex" class="spelling-group" role="presentation">
                            <button
                                v-for="position in group"
                                :key="position.index"
                                type="button"
                                class="spelling-box"
                                :class="`is-${spellingPositionState(position.index)}`"
                                role="listitem"
                                :disabled="!canSelectSpellingLetter"
                                :aria-current="spellingPositionState(position.index) === 'current' ? 'step' : undefined"
                                :aria-label="`Posisi ${position.index + 1}, huruf ${position.letter}, ${spellingPositionLabel(position.index)}`"
                                @click="selectSpellingLetter(position.index)"
                            >
                                <Check v-if="spellingPositionState(position.index) === 'completed'" :size="12" aria-hidden="true" />
                                <Lock v-else-if="spellingPositionState(position.index) === 'pending'" :size="10" aria-hidden="true" />
                                <b>{{ position.letter }}</b>
                                <small v-if="spellingPositionState(position.index) === 'current'">Kini</small>
                                <span class="spelling-status">{{ spellingPositionLabel(position.index) }}</span>
                            </button>
                        </div>
                    </div>
                    <p v-if="!spelling.word_completed" class="spelling-save-note">Ketuk huruf yang disorot untuk memperagakannya. Huruf harus dieja berurutan dan tidak bisa dilompati. Progres tersimpan otomatis selama sesi ejaan masih aktif.</p>
                </div>

                <div
                    v-if="spellingFeedback"
                    class="spelling-feedback"
                    :class="`is-${spellingFeedback.tone}`"
                    :role="spellingFeedback.tone === 'error' ? 'alert' : 'status'"
                >
                    <Check v-if="spellingFeedback.tone === 'success'" :size="17" />
                    <AlertCircle v-else :size="17" />
                    <span>{{ spellingFeedback.message }}</span>
                </div>

                <button v-if="spellingSyncRequired" type="button" class="st-btn st-btn--secondary st-btn--block" :disabled="spellingLoading" @click="refreshSpelling()">
                    <RotateCcw :size="16" />
                    {{ spellingLoading ? 'Memperbarui Progres...' : 'Perbarui Progres' }}
                </button>

                <PracticeCamera
                    v-if="!isSpellingLesson || !spelling.word_completed"
                    ref="cameraRef"
                    :lesson-id="currentLesson.id"
                    :letter="targetGesture"
                    :system="languageLabel"
                    :attempted="isSpellingLesson ? Boolean(result && !result.success) : Boolean(result)"
                    :disabled="resultOpen || spellingLoading || (isSpellingLesson && (spellingSyncRequired || !spelling.progress_token))"
                    :progress-token="isSpellingLesson ? spelling.progress_token : null"
                    :spelling-mode="isSpellingLesson"
                    @result="onDetectionResult"
                />

                <div v-if="isSpellingLesson && spelling.word_completed" class="spelling-complete">
                    <p><Check :size="18" /> Semua {{ spelling.letters.length }} huruf sudah benar.</p>
                    <div class="spelling-complete-actions">
                        <button v-if="result?.success" ref="completionActionRef" type="button" class="st-btn st-btn--secondary" @click="resultOpen = true">Lihat Hasil</button>
                        <button type="button" class="st-btn st-btn--secondary" :disabled="spellingLoading" @click="refreshSpelling({ repeat: true })">
                            <RotateCcw :size="16" /> {{ spellingLoading ? 'Menyiapkan Latihan...' : 'Latih Lagi' }}
                        </button>
                    </div>
                </div>

                <button v-if="passed" type="button" class="st-btn st-btn--secondary st-btn--block" @click="continueLesson">
                    <span>{{ continueLabel }}</span>
                    <ArrowRight :size="16" />
                </button>
            </div>
        </section>

        <section v-else class="st-panel exercise-card">
            <div class="st-empty">
                <span :role="lessonError ? 'alert' : 'status'">{{ lessonError || 'Memuat materi...' }}</span>
                <button v-if="!lessonLoading" type="button" class="st-btn st-btn--primary" @click="loadLesson()">Coba Muat Lagi</button>
                <Link href="/learn" class="st-btn st-btn--outline">Kembali ke Materi</Link>
            </div>
        </section>

        <PracticeResultModal
            :open="resultOpen"
            :result="result"
            :next-label="resultNextLabel"
            :repeat-action="isSpellingLesson"
            @close="closeResult"
            @retry="retryFromPopup"
            @repeat="refreshSpelling({ repeat: true })"
            @next="onResultNext"
        />
    </div>
</template>

<style scoped>
.focus-layout {
    max-width: 680px;
    margin: 0 auto;
    padding: 24px 16px;
}
.lesson-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}
.exit-lesson {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #e2ede6;
    display: grid;
    place-items: center;
    color: #52685d;
}
.lesson-progress {
    flex: 1;
    height: 8px;
    background: #eef3f0;
    border-radius: 8px;
    overflow: hidden;
}
.lesson-progress i {
    display: block;
    height: 100%;
    background: #34B182;
    transition: width 0.3s ease;
}
.step-count {
    font-size: 14px;
    font-weight: 700;
    color: #52685d;
}
.exercise-card {
    padding: 28px;
    border-radius: 20px;
}
.step-actions {
    margin-top: 18px;
}
.step-actions .answer-feedback {
    margin: 0 0 12px;
}
.exercise-kicker {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    color: var(--st-muted);
    margin-bottom: 14px;
}
.exercise-xp {
    margin-left: auto;
}
.exercise-card h1 {
    font-size: 22px;
    font-weight: 800;
    color: #14221d;
    margin: 0 0 16px;
}
.gesture-demo {
    height: 180px;
    background: #f6fbf8;
    border: 1px solid #d5f3e5;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    margin-bottom: 16px;
}
.gesture-letter {
    position: absolute;
    top: 14px;
    right: 18px;
    font-size: 24px;
    font-weight: 800;
    color: #34B182;
}
.gesture-demo--spelling {
    align-content: center;
    flex-direction: column;
    gap: 8px;
    height: auto;
    min-height: 180px;
    padding: 24px 18px;
    text-align: center;
}
.gesture-demo--spelling .gesture-hand {
    display: grid;
    place-items: center;
}
.spelling-hero-word {
    max-width: 100%;
    font-size: clamp(24px, 7vw, 38px);
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: 0.08em;
    color: #14221d;
    overflow-wrap: anywhere;
}
.spelling-mode-note {
    font-size: 12px;
    font-weight: 700;
    color: #52685d;
}
.exercise-detail {
    font-size: 15px;
    color: #52685d;
    line-height: 1.6;
    margin-bottom: 20px;
}
.answer-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 16px;
}
.answer-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: #ffffff;
    border: 1.5px solid #e2ede6;
    border-radius: 12px;
    min-height: 52px;
    font-size: 15px;
    font-weight: 700;
    color: #14221d;
    cursor: pointer;
    transition: 0.16s;
}
.answer-option.selected {
    border-color: #34B182;
    background: #eefaf4;
}
.answer-option.correct {
    border-color: #34B182;
    background: #d5f3e5;
    color: #1e7253;
}
.answer-option.incorrect {
    border-color: #d9534f;
    background: #fdf2f2;
    color: #d9534f;
}
.choice-letter {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: #f1f5f3;
    display: grid;
    place-items: center;
    font-size: 12px;
}
.practice-step {
    display: grid;
    gap: 14px;
}
.practice-step h1 {
    margin: 0;
}
/* Judul langkah + tombol Dengarkan */
.title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}
.exercise-card .title-row h1 {
    margin: 0;
    min-width: 0;
}
.practice-step .title-row {
    margin-bottom: 0;
}
@media (max-width: 480px) {
    .title-row {
        flex-direction: column;
        gap: 10px;
    }
}
.hand-tip {
    display: flex;
    gap: 8px;
    padding: 12px;
    color: #1e7253;
    background: #eefaf4;
    border-radius: 14px;
}
.hand-tip svg {
    flex-shrink: 0;
    margin-top: 2px;
}
.hand-tip p {
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
    color: var(--st-text);
}
.spelling-progress {
    display: grid;
    gap: 12px;
    padding: 14px;
    background: #f8faf9;
    border: 1px solid #e2ede6;
    border-radius: 14px;
}
.spelling-progress-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
}
.spelling-progress-head > div {
    display: grid;
    min-width: 0;
    gap: 2px;
}
.spelling-progress-head small {
    font-size: 12px;
    color: #6b8176;
}
.spelling-progress-head strong {
    font-size: 19px;
    font-weight: 800;
    line-height: 1.3;
    letter-spacing: 0.06em;
    color: #14221d;
    overflow-wrap: anywhere;
}
.spelling-progress-head > span {
    flex-shrink: 0;
    padding: 4px 9px;
    font-size: 12px;
    font-weight: 700;
    color: #1e7253;
    background: #eefaf4;
    border-radius: 999px;
}
.spelling-boxes {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px 14px;
}
.spelling-group {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    min-width: 0;
    max-width: 100%;
}
.spelling-box {
    position: relative;
    display: grid;
    flex: 0 0 42px;
    place-items: center;
    width: 42px;
    height: 54px;
    padding-top: 2px;
    color: #52685d;
    background: #fff;
    border: 1.5px solid #dce7e1;
    border-radius: 11px;
}
.spelling-box b {
    font-size: 19px;
    font-weight: 800;
    line-height: 1;
}
.spelling-box > svg {
    position: absolute;
    top: 4px;
    right: 4px;
}
.spelling-box > small {
    font-size: 10px;
    font-weight: 800;
    line-height: 1;
}
.quiz-letters {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 5px;
    max-width: 100%;
}
.quiz-letter {
    display: grid;
    place-items: center;
    width: 30px;
    height: 34px;
    font-size: 16px;
    font-weight: 800;
    color: #9aaba2;
    background: #fff;
    border: 1.5px dashed #dce7e1;
    border-radius: 8px;
}
.quiz-letter.is-answered {
    color: #fff;
    background: #1e7253;
    border: 1.5px solid #155a41;
}
.quiz-letter.is-asked {
    color: #795a16;
    background: #fff8e8;
    border: 1.5px solid #c29b40;
}
.spelling-box {
    font: inherit;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.spelling-box:disabled {
    cursor: default;
}
.spelling-box:not(:disabled):hover {
    transform: translateY(-2px);
}
.spelling-box:focus-visible {
    outline: 3px solid #34b182;
    outline-offset: 2px;
}
.spelling-box.is-pending {
    color: #9aaba2;
    background: #f6f8f7;
}
.spelling-box.is-pending:not(:disabled) {
    cursor: not-allowed;
}
.spelling-box.is-completed {
    color: #fff;
    background: #1e7253;
    border-color: #155a41;
}
.spelling-box.is-current {
    color: #795a16;
    background: #fff8e8;
    border-color: #c29b40;
    box-shadow: 0 0 0 3px #f7e9c5;
}
.spelling-status {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
.spelling-feedback {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 11px 12px;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.45;
    border: 1px solid;
    border-radius: 12px;
}
.spelling-feedback svg {
    flex-shrink: 0;
    margin-top: 1px;
}
.spelling-feedback.is-success {
    color: #1e7253;
    background: #eefaf4;
    border-color: #bfe8d4;
}
.spelling-feedback.is-error {
    color: #b2382d;
    background: #fdf2f2;
    border-color: #f6d0cc;
}
.spelling-feedback.is-warning,
.spelling-feedback.is-sync {
    color: #795a16;
    background: #fff8e8;
    border-color: #ecd9a8;
}
.spelling-save-note {
    margin: 0;
    font-size: 12px;
    line-height: 1.5;
    color: #52685d;
}
.spelling-complete {
    display: grid;
    gap: 12px;
    padding: 14px;
    color: #1e7253;
    background: #eefaf4;
    border: 1px solid #bfe8d4;
    border-radius: 14px;
}
.spelling-complete p {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 14px;
    font-weight: 700;
}
.spelling-complete-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 8px;
}
.practice-feedback {
    display: grid;
    gap: 4px;
    margin-bottom: 14px;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 14px;
    background: #fff5eb;
    color: #9a6a2f;
}
.practice-feedback small {
    font-size: 13px;
    color: var(--st-text);
}
@media (max-width: 520px) {
    .answer-grid {
        grid-template-columns: 1fr;
    }
    .exercise-card {
        padding: 20px;
    }
    .spelling-progress-head {
        align-items: flex-start;
        flex-direction: column;
        gap: 7px;
    }
    .spelling-boxes {
        justify-content: flex-start;
    }
    .spelling-group {
        justify-content: flex-start;
    }
    .spelling-box {
        flex-basis: 38px;
        width: 38px;
        height: 50px;
    }
}
</style>
