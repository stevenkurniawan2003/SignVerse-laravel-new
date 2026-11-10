import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { setImmediate } from 'node:timers/promises';
import { test } from 'node:test';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';
import * as Vue from 'vue';

function createBrowser(t) {
    const storage = new Map();
    const track = { stop: t.mock.fn() };
    const stream = { getTracks: () => [track] };
    const globals = {
        window: Object.assign(new EventTarget(), { csrfToken: 'csrf-token', location: { pathname: '/lesson/1' } }),
        document: Object.assign(new EventTarget(), { hidden: false, activeElement: null }),
        navigator: { mediaDevices: { getUserMedia: t.mock.fn(async () => stream) } },
        localStorage: { getItem: (key) => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value) },
    };
    const original = Object.fromEntries(Object.keys(globals).map((key) => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    for (const [key, value] of Object.entries(globals)) {
        Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
    }
    return {
        ...globals,
        storage,
        stream,
        track,
        restore() {
            for (const [key, descriptor] of Object.entries(original)) {
                if (descriptor) Object.defineProperty(globalThis, key, descriptor);
                else delete globalThis[key];
            }
        },
    };
}

function walk(node) {
    return [node, ...node.children.flatMap(walk)];
}

function textOf(node) {
    return node.text + node.children.map(textOf).join('');
}

function createNode(type, text = '') {
    return Vue.markRaw({
        type, text, props: {}, children: [], parent: null, style: {},
        get isConnected() {
            return Boolean(this.connected || this.parent?.isConnected);
        },
        focus() { document.activeElement = this; },
        // Dipakai direktif v-model; event input tidak disimulasikan di test ini
        addEventListener() {},
        removeEventListener() {},
        options: [],
        contains(node) { return walk(this).includes(node); },
        querySelectorAll() { return walk(this).filter((node) => node.type === 'button' && !node.props.disabled); },
        readyState: 4, videoWidth: 640, videoHeight: 480,
        play: async () => {},
        getContext: () => ({ drawImage() {} }),
        toDataURL: () => 'data:image/jpeg;base64,camera-frame',
    });
}

function stripImports(code) {
    return code.replace(/^import\s[\s\S]*?\sfrom\s['"][^'"]+['"];?\s*$/gm, '');
}

function stub(name) {
    return Vue.defineComponent({
        name,
        inheritAttrs: false,
        setup(_props, { attrs, slots }) {
            return () => Vue.h(`stub-${name.toLowerCase()}`, attrs, slots.default?.());
        },
    });
}

// Execute the real SFC scripts and templates with Vue's renderer; no DOM library or additional dependencies.
function loadComponent(file, bindings) {
    const { descriptor, errors } = parse(readFileSync(new URL(`../resources/js/${file}`, import.meta.url), 'utf8'));
    assert.equal(errors.length, 0);
    const script = compileScript(descriptor, { id: 'frontend-test', genDefaultAs: 'Component' });
    const imports = Object.entries(script.imports);
    const values = imports.map(([local, { source, imported }]) => {
        if (source === 'vue') return Vue[imported];
        if (source === '@inertiajs/vue3') return bindings.inertia[imported] || stub(local);
        if (source.endsWith('/speech')) return bindings.speech[imported];
        if (source.endsWith('/pageTransition')) return () => false;
        if (local === 'PracticeCamera') return bindings.camera;
        return stub(local);
    });
    const component = new Function(...imports.map(([local]) => local), `${stripImports(script.content)}\nreturn Component;`)(...values);
    const template = compileTemplate({
        source: descriptor.template.content,
        id: 'frontend-test',
        compilerOptions: { bindingMetadata: script.bindings },
    });
    assert.equal(template.errors.length, 0);
    const templateImports = [...template.code.matchAll(/import \{([^}]+)\} from ['"]vue['"]/g)]
        .flatMap((match) => match[1].split(',').map((entry) => entry.trim().split(/\s+as\s+/)));
    const transition = Vue.defineComponent({ inheritAttrs: false, setup: (_props, { slots }) => () => slots.default?.() });
    component.render = new Function(...templateImports.map(([name, alias]) => alias || name),
        `${stripImports(template.code).replace('export function render', 'function render')}\nreturn render;`)(
        ...templateImports.map(([name]) => name === 'Transition' ? transition : Vue[name]),
    );
    return component;
}

function mount(t, file, initialProps, { speech: speechOverrides = {} } = {}) {
    const browser = createBrowser(t);
    const root = Object.assign(createNode('root'), { connected: true });
    const body = Object.assign(createNode('body'), { connected: true });
    const cameraCalls = { startDetection: t.mock.fn(), cancelCountdown: t.mock.fn(), reset: t.mock.fn() };
    const page = Vue.reactive({ url: '/lesson/1', props: {} });
    const inertia = { usePage: () => page, router: { reload: t.mock.fn(), visit: t.mock.fn(), on: t.mock.fn() } };
    const speech = {
        speak: t.mock.fn(() => true),
        stopSpeech: t.mock.fn(),
        percentText: (value) => `${Math.round(value)} persen`,
        ...speechOverrides,
    };
    const camera = Vue.defineComponent({
        inheritAttrs: false,
        setup(_props, { attrs, expose }) {
            expose(cameraCalls);
            return () => Vue.h('camera-stub', attrs);
        },
    });
    const component = loadComponent(file, { inertia, speech, camera });
    const props = Vue.reactive(initialProps);
    function remove(node) {
        if (node.parent) node.parent.children.splice(node.parent.children.indexOf(node), 1);
        node.parent = null;
    }
    function insert(node, parent, anchor = null) {
        remove(node);
        const index = anchor ? parent.children.indexOf(anchor) : -1;
        parent.children.splice(index < 0 ? parent.children.length : index, 0, node);
        node.parent = parent;
    }
    const renderer = Vue.createRenderer({
        createElement: createNode,
        createText: (text) => createNode('#text', text),
        createComment: (text) => createNode('#comment', text),
        setText: (node, text) => { node.text = text; },
        setElementText: (node, text) => { node.children.forEach((child) => { child.parent = null; }); node.children = []; node.text = text; },
        patchProp: (node, key, _previous, value) => { node.props[key] = value; },
        insert, remove,
        parentNode: (node) => node.parent,
        nextSibling: (node) => node.parent?.children[node.parent.children.indexOf(node) + 1] || null,
        querySelector: () => body,
        setScopeId() {},
        insertStaticContent(content, parent, anchor) {
            const node = createNode('static', content);
            insert(node, parent, anchor);
            return [node, node];
        },
    });
    const app = renderer.createApp({ setup: () => () => Vue.h(component, { ...props }) });
    const vm = app.mount(root);
    let mounted = true;
    function unmount() {
        if (mounted) app.unmount();
        mounted = false;
    }
    t.after(() => { unmount(); browser.restore(); });
    return {
        props, root, body, browser, cameraCalls, page, inertia, speech, unmount,
        get state() { return vm.$.subTree.component.setupState; },
        nodes: () => [...walk(root), ...walk(body)],
    };
}

async function flush() {
    await setImmediate();
    await Vue.nextTick();
}

function spellingState(word = 'HALO', done = 0, token = `token-${done}`) {
    const letters = [...word.replace(/\s+/g, '').toUpperCase()];
    return {
        word: word.toUpperCase(), letters, letter_index: done, letters_done: done,
        current_letter: letters[done] ?? null, word_completed: done === letters.length,
        progress_token: done === letters.length ? null : token, expires_at: '2026-11-09T16:00:00Z',
    };
}

function lessonProps(word = 'HALO', done = 0, id = 1, slug = 'word') {
    return {
        lesson: { id, title: word, xp_reward: 20 },
        material: { title: 'Kosakata', language: 'sibi' },
        category: { slug }, ai_supported: true, spelling: spellingState(word, done),
        relatedLessons: [{ id, title: word }, { id: id + 1, title: 'AKU' }],
    };
}

function detection(overrides = {}) {
    return {
        lessonId: 1, letter: 'H', system: 'SIBI', success: false, evaluated: true,
        confidence: 90, passScore: 60, recognized: 'B', httpStatus: 200, xp: 0, streak: 1,
        alreadyMastered: false, newBadges: [], leveledUp: false, levelName: 'Pemula',
        assignmentsCompleted: [], challengeCompleted: false, requestFailed: false, spelling: null,
        ...overrides,
    };
}

function response(data, status = 200) {
    return { ok: status >= 200 && status < 300, status, json: async () => data };
}

test('spelling quiz asks every letter in order before the camera practice starts', async (t) => {
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('HALO'));
    const state = fixture.state;
    state.advance();
    assert.equal(state.step, 1);

    for (const [index, letter] of ['H', 'A', 'L', 'O'].entries()) {
        assert.equal(state.quizIndex, index);
        assert.equal(state.quizTarget, letter);
        assert.ok(state.options.includes(letter));
        assert.equal(new Set(state.options).size, 4);
        state.chooseAnswer(letter);
        state.verifyAnswer();
        assert.equal(state.answerIsCorrect, true);

        if (index < 3) {
            state.advance();
            assert.equal(state.step, 1, 'peragaan tidak boleh dimulai sebelum semua huruf ditanyakan');
            state.nextQuizQuestion();
            assert.equal(state.checked, false);
        }
    }

    assert.equal(state.quizOrdinal, 'keempat');
    assert.equal(state.isLastQuizQuestion, true);
    state.nextQuizQuestion();
    assert.equal(state.quizIndex, 3);
    state.advance();
    await flush();
    assert.equal(state.step, 2);
});

test('speak button shows stop while related feedback such as a quiz answer is being read', async (t) => {
    const speakingText = Vue.ref('Jawaban benar! Huruf pertama kata AKU adalah A.');
    const fixture = mount(t, 'Layouts/SpeakButton.vue', {
        text: 'Huruf pertama kata AKU apa?',
        relatedTexts: ['Jawaban benar! Huruf pertama kata AKU adalah A.'],
    }, {
        speech: {
            isSpeechSupported: true,
            hasIndonesianVoice: Vue.ref(true),
            isSpeaking: (text) => speakingText.value === text,
        },
    });
    const button = () => fixture.nodes().find((node) => node.type === 'button');

    assert.equal(fixture.state.active, true);
    assert.ok(textOf(button()).includes('Berhenti'));
    button().props.onClick();
    assert.equal(fixture.speech.stopSpeech.mock.callCount(), 1);
    assert.equal(fixture.speech.speak.mock.callCount(), 0);

    speakingText.value = null;
    await flush();
    assert.equal(fixture.state.active, false);
    assert.ok(textOf(button()).includes('Dengarkan'));
});

test('quiz answer feedback is spoken with the same text the speak button tracks', async (t) => {
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('AKU'));
    fixture.state.advance();
    fixture.state.chooseAnswer('A');
    fixture.state.verifyAnswer();
    const spoken = fixture.speech.speak.mock.calls.at(-1).arguments[0];
    assert.equal(spoken, fixture.state.answerSpeech);
    assert.match(spoken, /^Jawaban benar!/);
});

test('next spelling letter waits longer before the camera captures', async (t) => {
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('AKU', 1));
    fixture.speech.speak.mock.resetCalls();
    await fixture.state.continueSpelling();
    assert.deepEqual(fixture.cameraCalls.startDetection.mock.calls[0].arguments, [{ minimumSeconds: 5 }]);
    // Menutup popup lewat "Lanjut" menghentikan suara tanpa memulai pembacaan baru
    assert.ok(fixture.speech.stopSpeech.mock.callCount() > 0);
    assert.equal(fixture.speech.speak.mock.callCount(), 0);
});

test('first-letter quiz cannot be skipped and wrong answers can be retried', async (t) => {
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps());
    const state = fixture.state;
    state.advance();
    state.advance();
    assert.equal(state.step, 1);
    assert.equal(state.quizTarget, 'H');
    assert.equal(new Set(state.options).size, 4);
    assert.ok(state.options.every((letter) => /^[A-Z]$/.test(letter)));
    state.verifyAnswer();
    assert.equal(state.checked, false);
    state.chooseAnswer(state.options.find((letter) => letter !== 'H'));
    state.verifyAnswer();
    state.advance();
    await flush();
    assert.equal(state.step, 1);
    assert.ok(fixture.nodes().filter((node) => node.props.class?.includes('answer-option')).every((node) => node.props.disabled));
    state.retryAnswer();
    assert.equal(state.choice, '');
    assert.equal(state.checked, false);
    state.chooseAnswer('H');
    state.verifyAnswer();
    for (const letter of ['A', 'L', 'O']) {
        state.nextQuizQuestion();
        state.chooseAnswer(letter);
        state.verifyAnswer();
    }
    state.advance();
    await flush();
    assert.equal(state.step, 2);
    assert.equal(state.passed, false);
    assert.ok(fixture.speech.speak.mock.calls.some((call) => call.arguments[0].includes('Sekarang huruf H')));
});

test('resume restores individual positions for repeated letters and separates phrase words', (t) => {
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('Apa Kabar', 3, 1, 'phrase'));
    const state = fixture.state;
    assert.equal(state.step, 2);
    assert.equal(state.targetGesture, 'K');
    assert.equal(state.quizTarget, 'A');
    assert.deepEqual(state.spellingGroups.map((group) => group.map((item) => item.index)), [[0, 1, 2], [3, 4, 5, 6, 7]]);
    assert.match(state.spellingFeedback.message, /3 dari 8 huruf/);
    const boxes = fixture.nodes().filter((node) => node.props.role === 'listitem');
    assert.equal(boxes.length, 8);
    assert.ok(boxes[0].props.class.includes('is-completed'));
    assert.ok(boxes[2].props.class.includes('is-completed'));
    assert.equal(boxes[3].props['aria-current'], 'step');
    assert.ok(boxes[4].props.class.includes('is-pending'));
    assert.match(state.practiceSpeech, /posisi 4 dari 8/);
    assert.equal(state.normalizeSpellingState({ ...spellingState(), letters_done: 1 }), null);
    assert.equal(state.normalizeSpellingState({ ...spellingState(), word_completed: true }), null);
});

test('a verified intermediate letter pauses on a letter popup instead of capturing automatically', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('HALO', 1));
    fixture.state.onDetectionResult(detection({ success: true, letter: 'A', recognized: 'A', spelling: spellingState('HALO', 2) }));
    await flush();
    assert.equal(fixture.state.targetGesture, 'L');
    assert.equal(fixture.state.resultOpen, true);
    assert.equal(fixture.state.result.letterStep, true);
    assert.equal(fixture.state.resultNextLabel, 'Lanjut ke Huruf L');
    assert.equal(fixture.state.passed, false);
    assert.equal(fixture.nodes().find((node) => node.type === 'camera-stub').props['progress-token'], 'token-2');
    t.mock.timers.tick(5000);
    assert.equal(fixture.cameraCalls.startDetection.mock.callCount(), 0);

    await fixture.state.onResultNext();
    await flush();
    assert.equal(fixture.state.resultOpen, false);
    assert.equal(fixture.cameraCalls.startDetection.mock.callCount(), 1);
    assert.deepEqual(fixture.cameraCalls.startDetection.mock.calls[0].arguments, [{ minimumSeconds: 5 }]);
});

test('going back from a letter popup keeps finished letters checked and blocks skipping ahead', async (t) => {
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('AKU', 0));
    fixture.state.step = 2;
    await flush();
    fixture.state.onDetectionResult(detection({ success: true, letter: 'A', recognized: 'A', spelling: spellingState('AKU', 1) }));
    await flush();
    await fixture.state.closeResult();
    await flush();

    assert.equal(fixture.state.resultOpen, false);
    assert.equal(fixture.state.step, 2);
    assert.equal(fixture.state.spellingPositionState(0), 'completed');
    assert.equal(fixture.state.spellingPositionState(1), 'current');
    assert.equal(fixture.cameraCalls.startDetection.mock.callCount(), 0);

    fixture.state.selectSpellingLetter(2);
    await flush();
    assert.equal(fixture.cameraCalls.startDetection.mock.callCount(), 0);
    assert.equal(fixture.state.spellingFeedback.tone, 'warning');
    assert.match(fixture.state.spellingFeedback.message, /Belum bisa memilih huruf U\. Selesaikan huruf K/);

    fixture.state.selectSpellingLetter(0);
    await flush();
    assert.equal(fixture.cameraCalls.startDetection.mock.callCount(), 0);
    assert.equal(fixture.state.spellingPositionState(0), 'completed');

    fixture.state.selectSpellingLetter(1);
    await flush();
    assert.equal(fixture.cameraCalls.startDetection.mock.callCount(), 1);
    assert.equal(fixture.state.targetGesture, 'K');
});

test('wrong letters and undetected hands keep progress and retry with the new token', async (t) => {
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('LALA', 2));
    for (const [index, evaluated] of [true, false].entries()) {
        const spelling = spellingState('LALA', 2, `retry-${index}`);
        fixture.state.onDetectionResult(detection({ letter: 'L', evaluated, recognized: evaluated ? 'B' : null, spelling }));
        await flush();
        assert.equal(fixture.state.spelling.letters_done, 2);
        assert.equal(fixture.state.targetGesture, 'L');
        assert.equal(fixture.state.resultOpen, true);
        await fixture.state.retryFromPopup();
        await flush();
        assert.equal(fixture.state.resultOpen, false);
        assert.equal(fixture.nodes().find((node) => node.type === 'camera-stub').props['progress-token'], `retry-${index}`);
    }
    assert.equal(fixture.cameraCalls.startDetection.mock.callCount(), 2);
    assert.equal(fixture.state.passed, false);
});

test('stale or expired tokens synchronize without marking the attempted letter correct', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('HALO', 2));
    fixture.state.onDetectionResult(detection({ httpStatus: 409, requestFailed: true, evaluated: false, spelling: spellingState('HALO', 0, 'renewed') }));
    await flush();
    assert.equal(fixture.state.spelling.letters_done, 0);
    assert.equal(fixture.state.targetGesture, 'H');
    assert.equal(fixture.state.spelling.progress_token, 'renewed');
    assert.equal(fixture.state.resultOpen, false);
    assert.equal(fixture.state.spellingSyncRequired, false);
    t.mock.timers.tick(1000);
    assert.equal(fixture.cameraCalls.startDetection.mock.callCount(), 0);
});

test('an in-flight conflict blocks capture until the server publishes a new progress token', async (t) => {
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('HALO', 1));
    fixture.state.onDetectionResult(detection({ httpStatus: 409, requestFailed: true, evaluated: false, spelling: spellingState('HALO', 1) }));
    await flush();
    assert.equal(fixture.state.spellingSyncRequired, true);
    assert.equal(fixture.state.spelling.letters_done, 1);
    assert.match(fixture.state.spellingFeedback.message, /masih diperiksa/);
    assert.equal(fixture.nodes().find((node) => node.type === 'camera-stub').props.disabled, true);

    t.mock.method(globalThis, 'fetch', async () => response({ status: 'success', ...lessonProps('HALO', 1) }));
    assert.equal(await fixture.state.refreshSpelling(), false);
    assert.equal(fixture.state.spellingSyncRequired, true);

    globalThis.fetch = t.mock.fn(async () => response({ status: 'success', ...lessonProps('HALO', 2) }));
    assert.equal(await fixture.state.refreshSpelling(), true);
    assert.equal(fixture.state.spellingSyncRequired, false);
    assert.equal(fixture.state.targetGesture, 'L');
    assert.equal(fixture.state.passed, false);
    assert.equal(fixture.cameraCalls.startDetection.mock.callCount(), 0);
});

test('a missing server progress response blocks capture until refresh succeeds', async (t) => {
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('HALO', 1));
    fixture.state.onDetectionResult(detection({ success: true, spelling: null }));
    await flush();
    assert.equal(fixture.state.spellingSyncRequired, true);
    assert.equal(fixture.nodes().find((node) => node.type === 'camera-stub').props.disabled, true);
    assert.ok(fixture.nodes().some((node) => node.type === 'button' && textOf(node).includes('Perbarui Progres')));
    t.mock.method(globalThis, 'fetch', async () => response({ status: 'success', ...lessonProps('HALO', 2) }));
    assert.equal(await fixture.state.refreshSpelling(), true);
    await flush();
    assert.equal(fixture.state.targetGesture, 'L');
    assert.equal(fixture.state.spellingSyncRequired, false);
    assert.equal(fixture.state.passed, false);
});

test('final results persist after closing the popup and repeating starts a fresh spelling run', async (t) => {
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('HALO', 3));
    fixture.state.onDetectionResult(detection({ success: true, letter: 'O', recognized: 'O', confidence: 85, xp: 20, spelling: spellingState('HALO', 4) }));
    await flush();
    assert.equal(fixture.state.passed, true);
    assert.equal(fixture.state.lessonProgressPercent, 100);
    assert.equal(fixture.state.result.confidence, 85);
    assert.equal(fixture.nodes().some((node) => node.type === 'camera-stub'), false);
    fixture.state.closeResult();
    await flush();
    const viewResult = fixture.nodes().find((node) => node.type === 'button' && textOf(node) === 'Lihat Hasil');
    assert.equal(document.activeElement, viewResult);
    viewResult.props.onClick();
    assert.equal(fixture.state.resultOpen, true);
    t.mock.method(globalThis, 'fetch', async () => response({ status: 'success', ...lessonProps() }));
    assert.equal(await fixture.state.refreshSpelling({ repeat: true }), true);
    await flush();
    assert.equal(fixture.state.step, 2);
    assert.equal(fixture.state.spelling.letters_done, 0);
    assert.equal(fixture.state.passed, false);
    assert.equal(fixture.state.result, null);
    assert.ok(fixture.nodes().some((node) => node.type === 'camera-stub'));
});

test('navigation and hidden tabs cancel automatic captures and ignore old lesson results', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('HALO', 1));
    fixture.state.onDetectionResult(detection({ success: true, spelling: spellingState('HALO', 2) }));
    fixture.browser.document.hidden = true;
    fixture.browser.document.dispatchEvent(new Event('visibilitychange'));
    t.mock.timers.tick(900);
    assert.equal(fixture.cameraCalls.startDetection.mock.callCount(), 0);
    fixture.browser.document.hidden = false;
    fixture.state.onDetectionResult(detection({ success: true, spelling: spellingState('HALO', 3) }));
    Object.assign(fixture.props, lessonProps('AKU', 0, 2));
    await flush();
    t.mock.timers.tick(900);
    fixture.state.onDetectionResult(detection({ success: true, spelling: spellingState('HALO', 4) }));
    assert.equal(fixture.state.currentLesson.id, 2);
    assert.equal(fixture.state.step, 0);
    assert.equal(fixture.state.passed, false);
    assert.equal(fixture.cameraCalls.startDetection.mock.callCount(), 0);
});

test('starting navigation cancels pending capture without interrupting same-page auth reloads', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('HALO', 1));
    fixture.state.onDetectionResult(detection({ success: true, spelling: spellingState('HALO', 2) }));
    const onStart = fixture.inertia.router.on.mock.calls.find((call) => call.arguments[0] === 'start')?.arguments[1];
    assert.equal(typeof onStart, 'function');

    onStart({ detail: { visit: { url: { pathname: '/lesson/1' } } } });
    assert.equal(fixture.cameraCalls.reset.mock.callCount(), 0);
    onStart({ detail: { visit: { url: { pathname: '/learn' } } } });
    t.mock.timers.tick(1000);
    assert.equal(fixture.cameraCalls.startDetection.mock.callCount(), 0);
    assert.equal(fixture.cameraCalls.reset.mock.callCount(), 1);
});

test('failed progress refresh is retryable and an old refresh cannot overwrite the next lesson', async (t) => {
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps('HALO', 1));
    t.mock.method(globalThis, 'fetch', async () => { throw new Error('Jaringan terputus.'); });
    assert.equal(await fixture.state.refreshSpelling(), false);
    assert.equal(fixture.state.spellingLoading, false);
    assert.equal(fixture.state.spellingSyncRequired, true);
    let resolve;
    globalThis.fetch = t.mock.fn(() => new Promise((done) => { resolve = done; }));
    const pending = fixture.state.refreshSpelling();
    fixture.state.closeResult();
    Object.assign(fixture.props, lessonProps('AKU', 0, 2));
    await flush();
    resolve(response({ status: 'success', ...lessonProps('HALO', 2) }));
    await pending;
    assert.equal(fixture.state.currentLesson.id, 2);
    assert.equal(fixture.state.spellingLoading, false);
    assert.equal(fixture.state.spellingFeedback, null);
});

test('assignment completion returns to assignments instead of moving to the next word', (t) => {
    const fixture = mount(t, 'Pages/Lesson.vue', lessonProps());
    fixture.page.url = '/lesson/1?tugas=7';
    assert.equal(fixture.state.continueLabel, 'Kembali ke Tugas');
    fixture.state.continueLesson();
    assert.deepEqual(fixture.inertia.router.visit.mock.calls[0].arguments, ['/student/assignments']);
});

test('camera sends only server-bound progress and maps flat responses into spelling results', async (t) => {
    const results = t.mock.fn();
    const fixture = mount(t, 'Layouts/PracticeCamera.vue', { lessonId: 1, letter: 'A', system: 'BISINDO', spellingMode: true, progressToken: 'request-token', onResult: results });
    const request = t.mock.method(globalThis, 'fetch', async () => response({ correct: true, evaluated: true, confidence: 90, pass_score: 60, ...spellingState('AKU', 1) }));
    await flush();
    fixture.state.selectedTimer = 0;
    fixture.state.startDetection();
    await flush();
    const [url, options] = request.mock.calls[0].arguments;
    assert.equal(url, '/api/practice/attempt');
    assert.deepEqual(JSON.parse(options.body), { lesson_id: 1, image: 'data:image/jpeg;base64,camera-frame', mirrored: true, progress_token: 'request-token' });
    assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.equal(results.mock.calls[0].arguments[0].spelling.current_letter, 'K');
    assert.equal(results.mock.calls[0].arguments[0].success, true);
    assert.equal(fixture.state.scanning, false);
    assert.equal(fixture.inertia.router.reload.mock.callCount(), 0);
});

test('camera maps token conflicts without rewards and refreshes auth only after the final letter', async (t) => {
    const results = t.mock.fn();
    const fixture = mount(t, 'Layouts/PracticeCamera.vue', { lessonId: 1, letter: 'U', progressToken: 'old-token', spellingMode: true, onResult: results });
    t.mock.method(globalThis, 'fetch', async () => response({ correct: false, evaluated: false, ...spellingState('AKU', 2, 'new-token') }, 409));
    await flush();
    fixture.state.selectedTimer = 0;
    fixture.state.startDetection();
    await flush();
    assert.equal(results.mock.calls[0].arguments[0].httpStatus, 409);
    assert.equal(results.mock.calls[0].arguments[0].success, false);
    assert.equal(results.mock.calls[0].arguments[0].spelling.progress_token, 'new-token');
    assert.equal(fixture.inertia.router.reload.mock.callCount(), 0);
    fixture.props.progressToken = 'new-token';
    await flush();
    globalThis.fetch = t.mock.fn(async () => response({ correct: true, evaluated: true, confidence: 85, xp_earned: 20, ...spellingState('AKU', 3) }));
    fixture.state.startDetection();
    await flush();
    assert.equal(results.mock.calls[1].arguments[0].spelling.word_completed, true);
    assert.equal(results.mock.calls[1].arguments[0].xp, 20);
    assert.equal(fixture.inertia.router.reload.mock.callCount(), 1);
});

test('an unready or unreadable camera frame shows an error without sending an attempt', async (t) => {
    const fixture = mount(t, 'Layouts/PracticeCamera.vue', { lessonId: 1, letter: 'A' });
    const request = t.mock.method(globalThis, 'fetch', t.mock.fn());
    await flush();
    fixture.state.selectedTimer = 0;
    fixture.state.videoRef.videoWidth = 0;
    fixture.state.startDetection();
    await flush();
    assert.match(fixture.state.frameError, /Gagal mengambil gambar/);
    assert.equal(request.mock.callCount(), 0);
    assert.equal(fixture.state.scanning, false);
    fixture.state.videoRef.videoWidth = 640;
    fixture.state.canvasRef.toDataURL = () => { throw new Error('Frame unavailable'); };
    fixture.state.startDetection();
    await flush();
    assert.equal(request.mock.callCount(), 0);
    assert.ok(fixture.nodes().some((node) => node.props.role === 'alert'));
});

test('reset aborts a capture, releases the busy state, and ignores its late response', async (t) => {
    let resolve;
    const results = t.mock.fn();
    const fixture = mount(t, 'Layouts/PracticeCamera.vue', { lessonId: 1, letter: 'A', onResult: results });
    const request = t.mock.method(globalThis, 'fetch', () => new Promise((done) => { resolve = done; }));
    await flush();
    fixture.state.selectedTimer = 0;
    fixture.state.startDetection();
    assert.equal(fixture.state.scanning, true);
    fixture.props.lessonId = 2;
    fixture.props.letter = 'B';
    await flush();
    assert.equal(request.mock.calls[0].arguments[1].signal.aborted, true);
    assert.equal(fixture.state.scanning, false);
    resolve(response({ correct: true, recognized: 'A', confidence: 90 }));
    await flush();
    assert.equal(results.mock.callCount(), 0);
    fixture.props.disabled = true;
    await flush();
    fixture.state.startDetection();
    assert.equal(request.mock.callCount(), 1);
});

test('unmounting while camera permission is pending stops the eventual media stream', async (t) => {
    const fixture = mount(t, 'Layouts/PracticeCamera.vue', { lessonId: 1, letter: 'A' });
    let resolve;
    fixture.browser.navigator.mediaDevices.getUserMedia = () => new Promise((done) => { resolve = done; });
    await flush();
    fixture.state.stopCamera();
    const pending = fixture.state.startCamera();
    fixture.unmount();
    resolve(fixture.browser.stream);
    await pending;
    assert.equal(fixture.browser.track.stop.mock.callCount(), 2);
});

test('result speech describes the whole phrase, its average score and server rewards', async (t) => {
    const result = detection({ success: true, spelling: spellingState('Apa Kabar', 8), spellingKind: 'frasa', confidence: 85, xp: 20 });
    const close = t.mock.fn();
    const fixture = mount(t, 'Layouts/PracticeResultModal.vue', { open: false, result, onClose: close });
    assert.match(fixture.state.resultSpeech, /Frasa APA KABAR berhasil dieja/);
    assert.match(fixture.state.resultSpeech, /Semua 8 huruf/);
    assert.match(fixture.state.resultSpeech, /Rata-rata akurasi 85 persen/);
    assert.match(fixture.state.resultSpeech, /20 XP/);
    assert.equal(fixture.state.wordSpeech, 'apa kabar');
    fixture.props.open = true;
    await flush();
    const buttons = fixture.nodes().filter((node) => node.type === 'button');
    assert.ok(buttons.length > 0);
    buttons.at(-1).focus();
    const tab = Object.assign(new Event('keydown', { cancelable: true }), { key: 'Tab', shiftKey: false });
    fixture.browser.window.dispatchEvent(tab);
    assert.equal(tab.defaultPrevented, true);
    assert.equal(document.activeElement, buttons[0]);
    fixture.browser.window.dispatchEvent(Object.assign(new Event('keydown', { cancelable: true }), { key: 'Escape' }));
    assert.equal(close.mock.callCount(), 1);
    fixture.props.result = detection({ recognized: 'H', letter: 'H', confidence: 65, passScore: 75 });
    await flush();
    assert.match(fixture.state.failure.reason, /minimal 75%/);
    assert.match(fixture.state.resultSpeech, /Minimal 75 persen/);
});

function loadSpeech(t, supported = true) {
    const browser = createBrowser(t);
    t.after(() => browser.restore());
    const synth = { cancel: t.mock.fn(), resume: t.mock.fn(), speak: t.mock.fn(), getVoices: () => [{ lang: 'id-ID' }], addEventListener() {} };
    if (supported) {
        browser.window.speechSynthesis = synth;
        browser.window.SpeechSynthesisUtterance = class { constructor(text) { this.text = text; } };
    }
    const router = { on: t.mock.fn() };
    const source = stripImports(readFileSync(new URL('../resources/js/speech.js', import.meta.url), 'utf8')).replace(/^export /gm, '');
    const speech = new Function('ref', 'router', `${source}\nreturn { speak, stopSpeech, setAutoSpeech, autoSpeechEnabled, speakingText, isSpeechSupported };`)(Vue.ref, router);
    return { browser, synth, speech, router };
}

test('TTS respects the saved auto preference while keeping manual Indonesian speech available', (t) => {
    const { browser, synth, speech, router } = loadSpeech(t);
    speech.setAutoSpeech(false);
    assert.equal(browser.storage.get('signtech_speech_auto'), 'off');
    assert.equal(speech.speak('Halo', { auto: true }), false);
    assert.equal(speech.speak('Halo'), true);
    const utterance = synth.speak.mock.calls[0].arguments[0];
    assert.equal(utterance.lang, 'id-ID');
    assert.equal(utterance.voice.lang, 'id-ID');
    const navigation = router.on.mock.calls[0].arguments[1];
    navigation({ detail: { visit: { url: { pathname: '/lesson/1' } } } });
    assert.equal(speech.speakingText.value, 'Halo');
    navigation({ detail: { visit: { url: { pathname: '/learn' } } } });
    assert.equal(speech.speakingText.value, null);
});

test('unavailable or failing browser TTS does not break the lesson', (t) => {
    const { browser, synth, speech } = loadSpeech(t);
    synth.speak = () => { throw new Error('Speech unavailable'); };
    assert.equal(speech.speak('Halo'), false);
    assert.equal(speech.speakingText.value, null);
    assert.doesNotThrow(() => browser.window.dispatchEvent(new Event('pointerdown')));
    synth.cancel = () => { throw new Error('Speech unavailable'); };
    assert.doesNotThrow(() => speech.stopSpeech());
    assert.equal(speech.speak('Halo'), false);
});

test('TTS voice discovery can fail without preventing manual speech', (t) => {
    const browser = createBrowser(t);
    t.after(() => browser.restore());
    browser.window.speechSynthesis = { getVoices() { throw new Error('Voices unavailable'); }, cancel() {}, resume() {}, speak() {}, addEventListener() {} };
    browser.window.SpeechSynthesisUtterance = class { constructor(text) { this.text = text; } };
    const source = stripImports(readFileSync(new URL('../resources/js/speech.js', import.meta.url), 'utf8')).replace(/^export /gm, '');
    const speech = new Function('ref', 'router', `${source}\nreturn { speak, hasIndonesianVoice };`)(Vue.ref, { on() {} });
    assert.equal(speech.hasIndonesianVoice.value, false);
    assert.equal(speech.speak('Halo'), true);
});

test('browsers without speech synthesis keep a working silent mode', (t) => {
    const { speech } = loadSpeech(t, false);
    assert.equal(speech.isSpeechSupported, false);
    assert.equal(speech.speak('Halo'), false);
    assert.doesNotThrow(() => speech.stopSpeech());
});

function leaderboardEntry(rank, name, xp, isMe = false) {
    return { rank, user_id: rank, name, initials: name.slice(0, 2).toUpperCase(), xp, level: 'Pemula', streak: 1, is_me: isMe };
}

test('dashboard "Semua Peringkat" opens the full leaderboard including my position outside the top list', async (t) => {
    t.mock.timers.enable({ apis: ['setInterval', 'setTimeout'] });
    const entries = Array.from({ length: 10 }, (_, index) => leaderboardEntry(index + 1, `Siswa ${index + 1}`, 500 - index * 20));
    const me = leaderboardEntry(12, 'Saya Sendiri', 250, true);
    const fixture = mount(t, 'Pages/Dashboard.vue', { leaderboard: { entries, me, total: 15 } });
    const names = () => fixture.nodes().map(textOf).join(' ');

    assert.equal(fixture.state.showAllRanks, false);
    assert.ok(names().includes('Siswa 3'));
    assert.equal(names().includes('Siswa 4'), false, 'tampilan ringkas hanya 3 besar');

    const toggle = fixture.nodes().find((node) => node.type === 'button' && textOf(node).includes('Semua Peringkat'));
    toggle.props.onClick();
    await flush();

    assert.equal(fixture.state.showAllRanks, true);
    assert.ok(names().includes('Siswa 10'));
    assert.ok(names().includes('Peringkatmu dari 15 siswa'));
    assert.equal(fixture.state.meOutsideTop, true);
    assert.deepEqual(fixture.state.xpToNextRank, { name: 'Siswa 10', xp: 71 });

    fixture.nodes().find((node) => node.type === 'button' && textOf(node).includes('Tampilkan 3 Besar')).props.onClick();
    await flush();
    assert.equal(names().includes('Siswa 4'), false);
});

test('in-progress material cards show self practice instead of a teacher name', (t) => {
    const fixture = mount(t, 'Components/CoursesInProgress.vue', {
        categories: [{ id: 1, slug: 'alphabet', name: 'Alphabet', total: 26, done: 5 }],
    });
    const text = fixture.nodes().map(textOf).join(' ');
    assert.ok(text.includes('Latihan Mandiri'));
    assert.equal(/Bu Siti|Pak Rahmat|Bu Ratna|Guru Pembimbing/.test(text), false);
});

test('mini calendar loads the visible week, marks practice and deadline days, and shows the clicked date', async (t) => {
    t.mock.timers.enable({ apis: ['Date'], now: new Date(2026, 10, 10, 10, 0, 0) });
    const requests = [];
    t.mock.method(globalThis, 'fetch', async (url) => {
        requests.push(url);
        return response({
            status: 'success',
            days: url.includes('start=2026-11-08') ? {
                '2026-11-09': { practice: { sessions: 2, passed: 1, xp: 10, lessons: ['Aku'] }, deadlines: [] },
                '2026-11-12': {
                    practice: null,
                    deadlines: [{ id: 7, title: 'Eja Aku', lesson_title: 'Aku', due_time: '15:30', completed: false, overdue: false, status_label: 'Belum dikerjakan', link: '/student/assignments/7' }],
                },
            } : {},
        });
    });

    const fixture = mount(t, 'Components/MiniCalendar.vue', { todayDate: new Date(2026, 10, 10, 10, 0, 0) });
    await flush();
    await flush();

    assert.equal(requests[0], '/api/calendar?start=2026-11-08&end=2026-11-14');
    const days = Object.fromEntries(fixture.state.weekDays.map((day) => [day.key, day]));
    assert.equal(days['2026-11-09'].hasPractice, true);
    assert.equal(days['2026-11-12'].hasDeadline, true);
    assert.equal(days['2026-11-12'].hasOpenDeadline, true);
    assert.equal(days['2026-11-10'].hasPractice, false);
    assert.equal(fixture.state.selectedDay.isToday, true);

    fixture.state.selectDay(days['2026-11-12']);
    await flush();
    const text = fixture.nodes().map(textOf).join(' ');
    assert.ok(text.includes('Kamis, 12 November'));
    assert.ok(text.includes('Eja Aku'));
    assert.ok(text.includes('Pukul 15:30'));

    fixture.state.selectDay(days['2026-11-09']);
    await flush();
    assert.ok(fixture.nodes().map(textOf).join(' ').includes('2 latihan · 1 berhasil'));

    fixture.state.nextWeek();
    await flush();
    assert.equal(requests.at(-1), '/api/calendar?start=2026-11-15&end=2026-11-21');
    fixture.state.prevWeek();
    await flush();
    assert.equal(requests.length, 2, 'minggu yang sudah dimuat tidak diminta ulang');
});

test('todo checkmarks follow server completion and cannot be toggled by hand', async (t) => {
    const fixture = mount(t, 'Components/TodoTasksCard.vue', {
        tasks: [
            { id: 'a', title: 'Tugas: Eja Aku', completed: false, link: '/student/assignments/1' },
            { id: 'b', title: 'Praktik 5 huruf', completed: true, progress: 5, target: 5, link: '/challenge' },
            { id: 'c', title: 'Eja 1 kata', completed: false, progress: 0, target: 1, link: '/challenge' },
        ],
    });

    assert.deepEqual(fixture.state.counts, { all: 3, open: 2, done: 1 });
    const buttons = fixture.nodes().filter((node) => node.type === 'button').map(textOf);
    assert.ok(buttons.every((label) => /Semua|Buka|Selesai/.test(label)), 'hanya tombol filter yang bisa diklik');
    assert.equal(fixture.nodes().filter((node) => node.props['aria-label'] === 'Sudah selesai').length, 1);

    // Server menandai tugas selesai → centang ikut berubah tanpa interaksi
    fixture.props.tasks = fixture.props.tasks.map((task) => (task.id === 'a' ? { ...task, completed: true } : task));
    await flush();
    assert.deepEqual(fixture.state.counts, { all: 3, open: 1, done: 2 });
    assert.equal(fixture.nodes().filter((node) => node.props['aria-label'] === 'Sudah selesai').length, 2);
});

test('student dashboard tasks combine teacher assignments, challenges and recommendations without dummy tasks', (t) => {
    t.mock.timers.enable({ apis: ['setInterval', 'setTimeout'] });
    const fixture = mount(t, 'Pages/Dashboard.vue', {
        assignments: [
            { id: 1, title: 'Eja Aku', lesson_title: 'Aku', classroom: 'Kelas A', due_at: '2026-11-12T15:00:00+07:00', overdue: false, completed: false, xp_reward: 35, link: '/student/assignments/1' },
            { id: 2, title: 'Huruf A', completed: true, overdue: false, xp_reward: 20, link: '/student/assignments/2' },
        ],
        challenges: [{ id: 9, title: 'Praktik 5 huruf', progress: 5, target: 5, completed: true, reward_xp: 20 }],
        recommended: [{ id: 63, title: 'Aku', material_title: 'Kosakata', xp_reward: 10 }],
    });

    const tasks = fixture.state.tasksList;
    assert.deepEqual(tasks.map((task) => [task.id, task.completed]), [
        ['assignment_1', false], ['assignment_2', true], ['challenge_9', true], ['rec_63', false],
    ]);
    assert.equal(tasks[0].link, '/student/assignments/1');
    assert.equal(tasks[2].target, 5);
    assert.equal(tasks[3].link, '/lesson/63');
});

test('student dashboard shows no fake tasks when there is nothing to do', (t) => {
    t.mock.timers.enable({ apis: ['setInterval', 'setTimeout'] });
    const fixture = mount(t, 'Pages/Dashboard.vue', {});
    assert.deepEqual(fixture.state.tasksList, []);
});

test('teacher dashboard error cards show the sign, language and the sign it is confused with', (t) => {
    t.mock.timers.enable({ apis: ['setInterval', 'setTimeout'] });
    const fixture = mount(t, 'Pages/Teacher/Dashboard.vue', {
        errorAnalysis: [
            { lesson_id: 1, lesson: 'K', gesture: 'k', language: 'SIBI', wrong: 4, total: 5, accuracy: 20, confused_with: 'V' },
            { lesson_id: 2, lesson: 'Aku', gesture: 'aku', language: 'BISINDO', wrong: 1, total: 4, accuracy: 75, confused_with: null },
        ],
    });
    const text = fixture.nodes().map(textOf).join(' ');

    assert.ok(text.includes('Huruf K'));
    assert.ok(text.includes('Kata Aku'));
    assert.ok(text.includes('Sering tertukar dengan'));
    assert.ok(text.includes('Perlu bimbingan'));
    assert.ok(text.includes('Hampir dikuasai'));
    assert.equal(fixture.state.errorSeverity({ accuracy: 55 }).label, 'Cukup sulit');
});

test('teacher report shows hardest signs and assignment status clearly', (t) => {
    t.mock.timers.enable({ apis: ['Date'], now: new Date(2026, 10, 10, 10, 0, 0) });
    const fixture = mount(t, 'Pages/Teacher/Reports.vue', {
        report: {
            period: { from: '2026-11-04', to: '2026-11-10', days: 7 },
            classroom: null,
            summary: { students: 2, active_students: 1, sessions: 3, pass_rate: 33, avg_score: 50, letters_mastered: 1, assignment_completion: 50 },
            daily: [],
            students: [],
            letters: [
                { letter: 'K', language: 'SIBI', attempts: 4, passed: 1, pass_rate: 25 },
                { letter: 'Terima kasih', language: 'BISINDO', attempts: 2, passed: 2, pass_rate: 100 },
            ],
            assignments: [
                { id: 1, title: 'Eja Aku', classroom: 'Kelas A', lesson: 'Aku', language: 'SIBI', due_at: '2026-11-12T10:00:00+07:00', total: 2, completed: 2, avg_score: 88 },
                { id: 2, title: 'Huruf B', classroom: 'Kelas A', lesson: 'B', language: 'SIBI', due_at: '2026-11-01T10:00:00+07:00', total: 2, completed: 1, avg_score: 70 },
                { id: 3, title: 'Huruf C', classroom: 'Kelas A', lesson: 'C', language: 'SIBI', due_at: null, total: 0, completed: 0, avg_score: null },
            ],
        },
        filters: { classroom_id: null, range: '7d', from: '2026-11-04', to: '2026-11-10' },
    });
    const text = fixture.nodes().map(textOf).join(' ');

    assert.ok(text.includes('Huruf K'));
    assert.ok(text.includes('Kata Terima kasih'));
    assert.ok(text.includes('1 dari 4 percobaan berhasil'));
    assert.deepEqual(
        fixture.props.report.assignments.map((task) => fixture.state.taskStatus(task).label),
        ['Tuntas', 'Terlewat', 'Belum dibagikan'],
    );
    assert.ok(text.includes('Tanpa tenggat'));
    assert.equal(fixture.state.difficulty(25).label, 'perlu bimbingan');
});

test('todo card shows open tasks first, hides the plus button for students and collapses long lists', async (t) => {
    const tasks = Array.from({ length: 6 }, (_, index) => ({ id: `t${index}`, title: `Tugas ${index}`, completed: index === 0 }));
    const fixture = mount(t, 'Components/TodoTasksCard.vue', { tasks });

    assert.deepEqual(fixture.state.visibleTasks.map((task) => task.id), ['t1', 't2', 't3', 't4']);
    assert.equal(fixture.state.hiddenCount, 2);
    assert.equal(fixture.nodes().filter((node) => node.type === 'stub-link' && node.props.title === 'Lihat Semua / Tambah').length, 0);

    fixture.state.isExpanded = true;
    await flush();
    assert.deepEqual(fixture.state.visibleTasks.map((task) => task.id), ['t1', 't2', 't3', 't4', 't5', 't0']);

    fixture.state.currentFilter = 'done';
    await flush();
    assert.equal(fixture.state.isExpanded, false);
    assert.equal(fixture.state.hiddenCount, 0);
});

test('level ladder marks reached, current and next levels from total XP', (t) => {
    const fixture = mount(t, 'Components/LevelLadderCard.vue', {
        levels: [
            { name: 'Beginner', min_xp: 0 },
            { name: 'Explorer', min_xp: 300 },
            { name: 'Communicator', min_xp: 900 },
            { name: 'Inclusive Champion', min_xp: 2000 },
        ],
        summary: { xp: 450, level: { name: 'Explorer', next_xp: 900, pct: 25 } },
    });

    const steps = fixture.state.levelSteps;
    assert.deepEqual(steps.map((step) => [step.isReached, step.isCurrent, step.isNext]), [
        [true, false, false], [true, true, false], [false, false, true], [false, false, false],
    ]);
    assert.equal(steps[2].status, 'Butuh 450 XP lagi');
    assert.equal(fixture.state.xpToNextLevel, 450);
    assert.ok(fixture.nodes().map(textOf).join(' ').includes('Tingkatan Level'));
});

test('word challenges lead to learning materials instead of self practice', (t) => {
    t.mock.timers.enable({ apis: ['setInterval', 'setTimeout'] });
    const dashboard = mount(t, 'Pages/Dashboard.vue', {
        challenges: [
            { id: 1, title: 'Praktik 5 huruf', category: 'alfabet', progress: 1, target: 5, completed: false },
            { id: 2, title: 'Eja 3 kata', category: 'kosakata', progress: 0, target: 3, completed: false },
            { id: 3, title: 'Eja 3 kata', category: 'kosakata', progress: 3, target: 3, completed: true },
        ],
    });
    assert.deepEqual(dashboard.state.tasksList.map((task) => task.link), ['/practice', '/learn?bab=kosakata', '/challenge']);

    const challenge = mount(t, 'Pages/Challenge.vue', { challenges: [] });
    assert.deepEqual(challenge.state.challengeDestination({ category: 'kosakata' }), { href: '/learn?bab=kosakata', label: 'Lanjutkan di Materi Belajar' });
    assert.equal(challenge.state.challengeDestination({ category: 'alfabet' }).href, '/practice');
});

test('letter lessons show the SIBI or BISINDO sign image instead of the hand icon', async (t) => {
    const images = (fixture) => fixture.nodes().filter((node) => node.type === 'img').map((node) => node.props.src);
    const letter = mount(t, 'Pages/Lesson.vue', {
        lesson: { id: 5, title: 'K', xp_reward: 10 },
        material: { title: 'Alfabet', language: 'bisindo' },
        category: { slug: 'alphabet' }, ai_supported: true, spelling: null, relatedLessons: [],
    });
    assert.deepEqual(images(letter), ['/images/isyarat/bisindo/K.png']);
    assert.equal(letter.nodes().filter((node) => node.type === 'stub-hand' && node.props.size === 80).length, 0);

    letter.state.advance();
    await flush();
    assert.deepEqual(images(letter), ['/images/isyarat/bisindo/K.png'], 'kuis memakai gambar huruf yang sama');

    const word = mount(t, 'Pages/Lesson.vue', lessonProps('AKU'));
    assert.deepEqual(images(word), ['A', 'K', 'U'].map((char) => `/images/isyarat/sibi/${char}.png`));
    word.state.advance();
    await flush();
    assert.deepEqual(images(word), ['/images/isyarat/sibi/A.png'], 'kuis ejaan menampilkan huruf yang ditanyakan');

    const number = mount(t, 'Pages/Lesson.vue', {
        lesson: { id: 9, title: '7', xp_reward: 10 },
        material: { title: 'Angka', language: 'sibi' },
        category: { slug: 'number' }, ai_supported: false, spelling: null, relatedLessons: [],
    });
    assert.equal(images(number).length, 0, 'materi selain huruf A–Z tetap memakai ikon tangan');
});

test('teacher dashboard keeps gesture analysis and classes short with a "lihat selengkapnya" toggle', async (t) => {
    t.mock.timers.enable({ apis: ['setInterval', 'setTimeout'] });
    const errorAnalysis = Array.from({ length: 5 }, (_, index) => ({
        lesson_id: index + 1, lesson: String.fromCharCode(65 + index), gesture: `g${index}`, language: 'SIBI', wrong: 2, total: 4, accuracy: 50,
    }));
    const classes = Array.from({ length: 4 }, (_, index) => ({ id: index + 1, name: `Kelas ${index + 1}`, code: `KODE${index}` }));
    const fixture = mount(t, 'Pages/Teacher/Dashboard.vue', { errorAnalysis, classes });

    assert.equal(fixture.state.visibleErrors.length, 3);
    assert.equal(fixture.state.hiddenErrorCount, 2);
    assert.equal(fixture.state.visibleClasses.length, 3);
    assert.ok(fixture.nodes().map(textOf).join(' ').includes('Lihat selengkapnya (2 lainnya)'));

    fixture.state.showAllErrors = true;
    fixture.state.showAllClasses = true;
    await flush();
    assert.equal(fixture.state.visibleErrors.length, 5);
    assert.equal(fixture.state.visibleClasses.length, 4);
    assert.ok(fixture.nodes().map(textOf).join(' ').includes('Tampilkan lebih sedikit'));
});

test('profile card shows student count for teachers but XP and streak for students', (t) => {
    const teacher = mount(t, 'Components/ProfileOverviewCard.vue', {
        user: { name: 'Budi Santoso' }, isTeacher: true, summary: { classes: 2, students: 14 },
    });
    const teacherText = teacher.nodes().map(textOf).join(' ');
    assert.deepEqual(teacher.state.statPills.map((pill) => pill.label), ['14 Siswa', 'Guru Aktif']);
    assert.ok(!teacherText.includes('XP'), 'guru tidak punya XP');
    assert.ok(!teacherText.includes('Beginner'), 'guru tidak punya level');

    const student = mount(t, 'Components/ProfileOverviewCard.vue', {
        user: { name: 'Rina Putri' }, summary: { xp: 1250, streak: 3, level: { name: 'Communicator', pct: 40 } },
    });
    assert.deepEqual(student.state.statPills.map((pill) => pill.label), ['1.250 XP', '3 Hari']);
    assert.equal(student.state.levelPct, 40);
    assert.ok(student.nodes().map(textOf).join(' ').includes('Communicator'));
});
