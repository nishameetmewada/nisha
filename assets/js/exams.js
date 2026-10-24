(() => {
    'use strict';
    const source = document.getElementById('exam-data');
    if (!source) return;
    const config = JSON.parse(source.textContent);
    const exams = config.exams;
    const $ = (id) => document.getElementById(id);
    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const writer = window.crypto?.randomUUID?.() || `tab-${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const lifetime = 48 * 60 * 60 * 1000;
    let exam = null;
    let state = null;
    let selectedExamId = null;
    let ticker = null;
    let saveTimeout = null;
    let inFlight = false;
    let starting = false;
    const pendingStarts = new Map();
    let confirmationOpen = false;
    let storageWarning = '';
    let currentEnvelope = null;
    let currentReview = [];
    const resultAIActivity = new Map();
    let audioResourceId = null;
    let audioGeneration = 0;
    let cueContext = null;
    let cueOscillator = null;
    let audioToneContext = null;
    let recordingRequest = 0;
    let recording = null;
    const recordings = new Map();

    const announce = (message) => { $('exam-announcer').textContent = message; };
    const formatTime = (seconds) => {
        const value = Math.max(0, Math.ceil(seconds));
        const hours = Math.floor(value / 3600);
        return `${hours ? hours + ':' : ''}${String(Math.floor(value / 60) % 60).padStart(2, '0')}:${String(value % 60).padStart(2, '0')}`;
    };
    const guided = (target = exam) => target?.session_type === 'practice';
    const timed = (target = exam) => target?.timing_mode !== 'untimed';
    const fullMock = (target) => (target?.session_type || 'full') === 'full';
    const hasAudio = (target) => typeof target?.has_audio === 'boolean' ? target.has_audio : items(target).some((question) => target.resources[question.resource_id]?.kind === 'audio');
    const feedbackCache = new Map();
    let feedbackRequest = 0;
    const budget = (target) => target.sections.reduce((sum, section) => sum + section.duration_seconds, 0);
    const items = (target) => target.sections.flatMap((section) => section.items || []);
    const taskCount = (target) => Number.isInteger(target.item_count) ? target.item_count : items(target).length;
    const sectionTaskCount = (part) => Number.isInteger(part.item_count) ? part.item_count : (part.items || []).length;
    const section = () => exam.sections[state.sectionIndex];
    const item = () => section().items[state.itemIndex];
    const storageKey = (target) => `nisha:fullmock:${config.userScope}:${target.id}:${target.version}`;
    const answered = (value) => Array.isArray(value) ? value.length > 0 : value && typeof value === 'object' ? Object.values(value).some((entry) => String(entry).trim()) : typeof value === 'string' && value.trim().length > 0;
    const wordCount = (value) => value.trim() ? value.trim().split(/\s+/u).length : 0;

    function view(name) {
        for (const id of ['catalog', 'runner', 'submission', 'result']) $('exam-' + id).hidden = name !== id;
    }

    function notice(id, message) {
        $(id).textContent = message;
        $(id).hidden = !message;
    }

    function contentSourceNotice(id, target) {
        if (!$(id)) return;
        const generated = target?.content_source === 'generated';
        notice(id, generated
            ? target.format_note || 'Generated practice · Not teacher-reviewed. Variants may reuse questions and scenarios; assembled mocks are not independent unseen papers.'
            : target?.teacher_reviewed === true ? 'Teacher-reviewed practice. Assembled mocks may reuse reviewed questions.' : '');
    }

    function loadState(target) {
        try {
            const raw = localStorage.getItem(storageKey(target));
            if (!raw) return null;
            const saved = JSON.parse(raw);
            if (saved.expiresAt <= Date.now()) {
                localStorage.removeItem(storageKey(target));
                return null;
            }
            if (saved.examId !== target.id || saved.version !== target.version || saved.userScope !== config.userScope || !saved.answers || typeof saved.answers !== 'object' || !Array.isArray(saved.completedSections) || !Number.isFinite(saved.startedAt) || !Number.isFinite(saved.sectionDeadline)) return null;
            if (!['running', 'submit-ready', 'results'].includes(saved.phase)) return null;
            if (saved.phase === 'running' && (!Number.isInteger(saved.sectionIndex) || saved.sectionIndex < 0 || saved.sectionIndex >= target.sections.length || !Number.isInteger(saved.itemIndex) || saved.itemIndex < 0 || saved.itemIndex >= sectionTaskCount(target.sections[saved.sectionIndex]))) return null;
            saved.flags = Array.isArray(saved.flags) ? saved.flags : [];
            saved.played = Array.isArray(saved.played) ? saved.played : [];
            saved.itemClocks = saved.itemClocks && typeof saved.itemClocks === 'object' ? saved.itemClocks : {};
            saved.audioEnded = saved.audioEnded && typeof saved.audioEnded === 'object' ? saved.audioEnded : {};
            saved.aiDrafts = saved.aiDrafts && typeof saved.aiDrafts === 'object' ? saved.aiDrafts : {};
            saved.aiReviews = Array.isArray(saved.aiReviews) ? saved.aiReviews : [];
            return saved;
        } catch (error) {
            storageWarning = 'Device autosave is unavailable. Keep this tab open and download your responses before leaving.';
            return null;
        }
    }

    function saveState() {
        window.clearTimeout(saveTimeout);
        if (!state || !exam) return;
        state.updatedAt = Date.now();
        state.writer = writer;
        try {
            localStorage.setItem(storageKey(exam), JSON.stringify(state));
            $('autosave-status').textContent = 'Answers saved on this device';
        } catch (error) {
            storageWarning = 'Device autosave is unavailable or full. Keep this tab open and download your responses before leaving.';
            $('autosave-status').textContent = 'Device autosave unavailable';
            notice('runner-notice', storageWarning);
        }
    }

    function scheduleSave() {
        window.clearTimeout(saveTimeout);
        saveTimeout = window.setTimeout(saveState, 200);
    }

    function renderCatalog() {
        $('exam-cards').replaceChildren(...(config.catalogIds || Object.keys(exams)).map((id) => exams[id]).filter(Boolean).map((target) => {
            const saved = loadState(target);
            const card = el('article', 'full-exam-card');
            card.dataset.examId = target.id;
            const top = el('div', 'exam-card-top');
            top.append(el('span', 'eyebrow', target.course_id ? target.course_id.replaceAll('-', ' ').toUpperCase() : target.exam), el('span', 'exam-card-badge', 'FULL MOCK'));
            const heading = el('h2', '', target.name);
            const description = el('p', 'exam-card-description', target.description);
            const stats = el('div', 'exam-card-stats');
            stats.append(el('span', '', `${Math.round(budget(target) / 60)} minutes`), el('span', '', `${taskCount(target)} tasks`), el('span', '', `${target.sections.length} sections`));
            const list = el('ul', 'exam-card-sections');
            target.sections.forEach((part) => {
                const li = el('li');
                li.append(el('strong', '', part.title), el('span', '', `${sectionTaskCount(part)} tasks · ${Math.round(part.duration_seconds / 60)} min`));
                list.append(li);
            });
            const formatCount = target.task_type_count || new Set(items(target).map((question) => question.task_type)).size;
            const note = el('p', 'exam-card-note', target.exam === 'IELTS' ? '40 listening + 40 reading questions, two writing tasks and three speaking parts. Free navigation within each section.' : `${taskCount(target)} tasks across ${formatCount} question types. Move forward through each item; previous items cannot be revisited.`);
            const buttons = el('div', 'exam-card-actions');
            const start = el('button', 'btn btn-primary', saved ? saved.phase === 'results' ? 'View device result →' : saved.phase === 'submit-ready' ? 'Retry final submission →' : 'Resume my mock →' : 'Prepare for this mock →');
            start.type = 'button';
            start.addEventListener('click', () => saved ? resume(target.id) : openSetup(target.id));
            buttons.append(start);
            if (saved) {
                const clear = el('button', 'exam-text-button', 'Clear device copy');
                clear.type = 'button';
                clear.addEventListener('click', async () => {
                    if (await confirm('Clear this device copy?', 'This removes saved text, progress and any device result for this mock. Saved account results remain in your dashboard.', 'Clear device copy', 'Keep copy')) {
                        try { localStorage.removeItem(storageKey(target)); } catch (error) { /* The memory copy can still be cleared. */ }
                        if (exam?.id === target.id) { stopTimer(); stopAudio(); stopRecording(true); clearRecordings(); state = null; exam = null; }
                        renderCatalog();
                        announce('Device copy cleared.');
                    }
                });
                buttons.append(clear);
                card.append(el('p', 'exam-resume-note', saved.phase === 'running' ? timed(target) ? 'A session is waiting on this device. Its timer has continued.' : 'Your guided practice is waiting on this device.' : 'A completed device copy is available for 48 hours from its start.'));
            }
            card.prepend(top, heading, description, stats, list, note);
            card.append(buttons);
            return card;
        }));
        if (storageWarning) notice('exam-catalog-message', storageWarning);
    }

    function speechAvailable() {
        return 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
    }

    function openSetup(id) {
        if (starting) return;
        selectedExamId = id;
        const target = exams[id];
        if (!target) return;
        if (fullMock(target) && !config.signedIn) {
            view('catalog');
            accessNotice('exam-catalog-message', 'Sign in to start your first free full mock and keep its result in your account.', 401);
            return;
        }
        $('setup-exam-name').textContent = target.name;
        $('setup-format-note').textContent = target.format_note || '';
        $('setup-duration').textContent = `${timed(target) ? Math.round(budget(target) / 60) + ' min' : 'Untimed'} · ${taskCount(target)} tasks`;
        notice('setup-access-message', fullMock(target) ? 'Your first full mock is free. Opening a paper reserves that free mock so you can resume it; after submitting, an access pass unlocks more papers.' : 'This practice session does not use your free full mock.');
        $('setup-readiness-note').textContent = timed(target) ? 'I’m ready for a timed session. Leaving or reloading will not pause the clock, and recordings cannot be restored.' : 'I’m ready to practise. I can check each answer, revisit questions and use transcripts. Recordings cannot be restored after a reload.';
        $('begin-exam').textContent = guided(target) ? 'Begin guided practice →' : target.session_type === 'section' ? 'Begin sectional mock →' : 'Begin full mock →';
        $('ready-confirmed').checked = false;
        $('audio-confirmed').checked = false;
        $('audio-confirmed').disabled = true;
        $('assisted-mode').checked = guided(target);
        $('assisted-mode').disabled = guided(target);
        $('test-exam-audio').disabled = !speechAvailable();
        $('equipment-status').textContent = speechAvailable() ? 'A microphone is optional. You can practise speaking aloud without recording.' : 'Browser speech is unavailable. Choose assisted mode to use transcripts instead.';
        updateSetupButton();
        $('exam-setup-dialog').showModal();
    }

    function updateSetupButton() {
        const target = exams[selectedExamId];
        $('begin-exam').disabled = starting || !target || !$('ready-confirmed').checked || (hasAudio(target) && !($('assisted-mode').checked || $('audio-confirmed').checked));
    }

    function accessNotice(id, message, statusCode = 0) {
        notice(id, message);
        if (statusCode === 401 || statusCode === 402) {
            const link = el('a', 'exam-text-button', statusCode === 401 ? 'Sign in for my free mock →' : 'View access plans →');
            link.href = statusCode === 401 ? 'login.php' : 'pricing.php';
            $(id).append(document.createTextNode(' '), link);
        }
    }

    const newAttemptId = () => window.crypto?.randomUUID?.() || `attempt-${Date.now()}-${Math.random().toString(36).slice(2, 14)}`;
    const startKey = (target) => `nisha:mock-start:${config.userScope}:${target.id}:${target.version}`;
    function pendingStartId(target) {
        if (pendingStarts.has(target.id)) return pendingStarts.get(target.id);
        let id = null;
        try {
            const saved = JSON.parse(localStorage.getItem(startKey(target)) || 'null');
            if (saved?.expiresAt > Date.now() && typeof saved.attemptId === 'string') id = saved.attemptId;
        } catch (_) { /* The memory reference still supports same-tab retries. */ }
        const access = config.mockAccess;
        if (!id && access?.trial_state === 'reserved' && access.trial_exam_id === target.id) id = access.trial_attempt_id;
        id ||= newAttemptId();
        pendingStarts.set(target.id, id);
        try { localStorage.setItem(startKey(target), JSON.stringify({ attemptId: id, expiresAt: Date.now() + lifetime })); } catch (_) { /* Keep the in-memory reference. */ }
        return id;
    }

    async function fetchFullMock(target, attemptId) {
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 25000);
        try {
            const response = await fetch('api/start-mock.php', {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ exam_id: target.id, attempt_id: attemptId }),
            });
            let data;
            try { data = await response.json(); } catch (_) { throw new Error('The website did not return this mock. Your start reference is retained; try again when the connection is available.'); }
            if (!response.ok || !data.success) { const error = new Error(data.error || 'This full mock could not be opened.'); error.httpStatus = response.status; throw error; }
            if (data.exam?.id !== target.id || data.grant?.exam_id !== target.id || data.grant?.attempt_id !== attemptId || !fullMock(data.exam) || !Array.isArray(data.exam.sections) || !data.exam.sections.every(part => Array.isArray(part.items) && part.items.length)) throw new Error('This full mock could not be loaded. Please try again.');
            if (data.access) config.mockAccess = data.access;
            exams[target.id] = data.exam;
            return data.exam;
        } catch (error) {
            if (error.name === 'AbortError' || error instanceof TypeError) throw new Error('The mock connection stopped before opening the paper. Your start reference is retained; retry to resume the same reservation.');
            throw error;
        } finally { window.clearTimeout(timeout); }
    }

    async function begin() {
        let target = exams[selectedExamId];
        if (!target || $('begin-exam').disabled || starting) return;
        starting = true;
        updateSetupButton();
        const attemptId = fullMock(target) ? pendingStartId(target) : newAttemptId();
        try {
            if (fullMock(target)) {
                notice('setup-access-message', 'Opening your full mock and checking account access…');
                target = await fetchFullMock(target, attemptId);
            }
        } catch (error) {
            accessNotice('setup-access-message', error.message, error.httpStatus);
            return;
        } finally { starting = false; updateSetupButton(); }
        stopAudio();
        stopRecording(true);
        clearRecordings();
        exam = target;
        feedbackRequest++;
        feedbackCache.clear();
        const now = Date.now();
        state = {
            examId: exam.id, version: exam.version, userScope: config.userScope,
            attemptId,
            startedAt: now, expiresAt: now + lifetime, updatedAt: now,
            sectionIndex: 0, itemIndex: 0, sectionDeadline: now + exam.sections[0].duration_seconds * 1000,
            answers: {}, flags: [], played: [], completedSections: [], itemClocks: {}, audioEnded: {}, aiDrafts: {}, aiReviews: [],
            assisted: $('assisted-mode').checked, phase: 'running', endedAt: null,
        };
        pendingStarts.delete(target.id);
        try { localStorage.removeItem(startKey(target)); } catch (_) { /* The running state is retained independently. */ }
        $('exam-setup-dialog').close();
        saveState();
        renderRunner();
        startTimer();
        $('runner-title').focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
        announce(timed() ? 'Your session started. The section timer is running.' : 'Guided practice started. Take your time and check each answer.');
    }

    async function resume(id) {
        if (starting) return;
        let target = exams[id];
        const saved = loadState(target);
        if (!saved) { openSetup(id); return; }
        if (fullMock(target) && saved.phase !== 'results') {
            starting = true;
            try { target = await fetchFullMock(target, saved.attemptId); }
            catch (error) { view('catalog'); accessNotice('exam-catalog-message', error.message, error.httpStatus); return; }
            finally { starting = false; }
            if (saved.version !== target.version) { view('catalog'); notice('exam-catalog-message', 'This paper changed since your attempt began. Keep a downloaded copy of your responses before starting the updated version.'); return; }
        }
        stopTimer();
        stopAudio();
        const sameAttempt = state?.attemptId === saved.attemptId && exam?.id === target.id;
        stopRecording(!sameAttempt);
        if (!sameAttempt) { clearRecordings(); feedbackRequest++; feedbackCache.clear(); }
        exam = target;
        state = saved;
        if (saved.phase === 'results' && saved.resultEnvelope) {
            showResults(saved.resultEnvelope);
        } else if (saved.phase === 'submit-ready') {
            showPending('Your finished answers are still here. Retry submission to get your review.');
        } else {
            const advanced = synchronize();
            if (state.phase === 'running') {
                renderRunner();
                notice('runner-notice', !timed() ? 'Your text answers are restored. Guided practice has no countdown. Audio recordings cannot be restored after a reload.' : advanced ? 'While you were away, one or more timers ended. You are now at the next available task. Recordings cannot be restored after a reload.' : 'Your text answers are restored. The timer continued while you were away. Audio recordings cannot be restored after a reload.');
                startTimer();
            } else {
                showPending('The mock timers ended while you were away. Your saved answers are ready to submit.');
                submitAttempt();
            }
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function confirm(title, message, action = 'Continue', cancel = 'Keep practising') {
        if (confirmationOpen) return false;
        confirmationOpen = true;
        const dialog = $('exam-confirm-dialog');
        $('exam-confirm-title').textContent = title;
        $('exam-confirm-message').textContent = message;
        $('exam-confirm-yes').textContent = action;
        $('exam-confirm-cancel').textContent = cancel;
        dialog.returnValue = 'cancel';
        const result = await new Promise((resolve) => {
            dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true });
            dialog.showModal();
        });
        confirmationOpen = false;
        return result;
    }

    function stopTimer() {
        if (ticker !== null) window.clearInterval(ticker);
        ticker = null;
    }

    function startTimer() {
        stopTimer();
        tick();
        if (state?.phase === 'running') ticker = window.setInterval(tick, 1000);
    }

    function ensureItemClock(at = Date.now()) {
        if (!timed()) return null;
        const question = item();
        const responseSeconds = question.response_seconds || (exam.exam === 'PTE' && question.type === 'essay' ? question.suggested_seconds : 0);
        if (!question.prep_seconds && !responseSeconds) return null;
        const resource = question.resource_id ? exam.resources[question.resource_id] : null;
        if (question.type === 'spoken' && resource?.kind === 'audio' && !state.audioEnded[question.resource_id]) return null;
        if (!state.itemClocks[question.id]) {
            const prep = Number(question.prep_seconds) || 0;
            const response = Number(responseSeconds) || 0;
            state.itemClocks[question.id] = { prepDeadline: at + prep * 1000, responseDeadline: at + (prep + response) * 1000 };
            saveState();
        }
        return state.itemClocks[question.id];
    }

    function advanceSection(at) {
        if ($('exam-confirm-dialog').open) $('exam-confirm-dialog').close('cancel');
        const old = section();
        if (!state.completedSections.includes(old.id)) state.completedSections.push(old.id);
        stopAudio();
        stopRecording(false);
        state.sectionIndex++;
        state.itemIndex = 0;
        if (state.sectionIndex >= exam.sections.length) {
            finishState(at);
        } else {
            state.sectionDeadline = at + section().duration_seconds * 1000;
            ensureItemClock(at);
        }
    }

    function finishState(at = Date.now()) {
        stopTimer();
        stopAudio();
        stopRecording(false);
        state.phase = 'submit-ready';
        state.endedAt = Math.min(Date.now(), at);
        if ($('exam-confirm-dialog').open) $('exam-confirm-dialog').close('cancel');
        saveState();
    }

    function synchronize() {
        if (!state || state.phase !== 'running' || !timed()) return false;
        const now = Date.now();
        let changed = false;
        let limit = 0;
        while (state.phase === 'running' && limit++ < 500) {
            if (now >= state.sectionDeadline) {
                advanceSection(state.sectionDeadline);
                changed = true;
                continue;
            }
            const clock = ensureItemClock();
            if (clock && now >= clock.responseDeadline) {
                stopRecording(false);
                if (state.itemIndex < section().items.length - 1) {
                    state.itemIndex++;
                    ensureItemClock(clock.responseDeadline);
                } else {
                    advanceSection(clock.responseDeadline);
                }
                changed = true;
                continue;
            }
            break;
        }
        if (changed) saveState();
        return changed;
    }

    function tick() {
        if (!state || state.phase !== 'running') return;
        const changed = synchronize();
        if (state.phase !== 'running') {
            showPending('Your mock is complete. Sending your responses for practice feedback…');
            submitAttempt();
            return;
        }
        if (changed) {
            renderRunner();
            notice('runner-notice', 'The previous timer ended. You have moved to the next available task.');
            announce('Time is up for the previous task or section. The next task is ready.');
        }
        updateClockUI();
    }

    function updateClockUI() {
        if (!state || state.phase !== 'running') return;
        const now = Date.now();
        const remaining = Math.max(0, (state.sectionDeadline - now) / 1000);
        $('section-clock-label').textContent = timed() ? 'SECTION TIME LEFT' : 'GUIDED PRACTICE';
        $('section-clock').textContent = timed() ? formatTime(remaining) : 'Untimed';
        $('section-clock').closest('.exam-section-clock').classList.toggle('is-low', timed() && remaining <= 60);
        const clock = timed() ? state.itemClocks[item().id] : null;
        const waitingForAudio = timed() && !clock && item().type === 'spoken' && (item().prep_seconds || item().response_seconds) && exam.resources[item().resource_id]?.kind === 'audio';
        $('item-time-panel').hidden = !clock && !waitingForAudio;
        if (waitingForAudio) {
            $('item-phase-label').textContent = 'LISTEN TO THE STIMULUS FIRST';
            $('item-clock').textContent = '—';
            const recordButton = document.querySelector('#item-response [data-record]');
            if (recordButton) { recordButton.disabled = true; recordButton.textContent = 'Ready after listening'; }
        }
        if (clock) {
            const preparation = now < clock.prepDeadline;
            $('item-phase-label').textContent = preparation ? 'PREPARATION TIME' : 'RESPONSE TIME';
            $('item-clock').textContent = formatTime(((preparation ? clock.prepDeadline : clock.responseDeadline) - now) / 1000);
            const recordButton = document.querySelector('#item-response [data-record]');
            if (recordButton && !recording?.pending) {
                recordButton.disabled = preparation || !navigator.mediaDevices?.getUserMedia || !window.MediaRecorder;
                if (preparation) recordButton.textContent = 'Ready after preparation';
                else if (!recording || recording.itemId !== item().id) recordButton.textContent = 'Record my response ●';
            }
        }
    }

    function renderRunner() {
        const part = section();
        const question = item();
        ensureItemClock();
        view('runner');
        $('runner-exam-name').textContent = exam.name;
        contentSourceNotice('runner-content-source', exam);
        $('runner-catalog').textContent = exam.session_type && exam.session_type !== 'full' ? '← Practice library' : '← My mock exams';
        $('runner-title').textContent = `${state.sectionIndex + 1}. ${part.title}`;
        $('runner-section-description').textContent = part.instructions || '';
        $('runner-mode').textContent = guided() ? 'GUIDED PRACTICE' : state.assisted ? 'ASSISTED MODE' : 'EXAM MODE';
        $('runner-session-note').textContent = guided() ? 'Take your time. Check an answer for feedback, replay audio or open a transcript, and revisit any question. Finish the session to save a complete review.' : 'The clock continues when you leave. Audio plays once in exam mode. Assisted mode adds transcripts and replays and is labelled on your result. Answers are revealed after submission.';
        $('practice-feedback-actions').hidden = !guided();
        renderPracticeFeedback();
        $('enable-assistance').hidden = state.assisted;
        $('runner-progress').textContent = `Section ${state.sectionIndex + 1} of ${exam.sections.length} · ${part.items.filter((entry) => answered(state.answers[entry.id])).length} of ${part.items.length} responses entered`;
        $('item-counter').textContent = `Task ${state.itemIndex + 1} of ${part.items.length}`;
        $('item-task-type').textContent = question.task_type || question.type;
        $('item-kicker').textContent = question.title || `${exam.exam} PRACTICE`;
        $('item-title').textContent = question.prompt || question.title || question.task_type;
        $('item-instructions').textContent = question.instructions || '';
        $('previous-item').hidden = part.navigation === 'forward';
        $('previous-item').disabled = state.itemIndex === 0;
        $('next-item').textContent = state.itemIndex === part.items.length - 1 ? state.sectionIndex === exam.sections.length - 1 ? 'Finish & submit →' : 'Next section →' : 'Next task →';
        $('navigation-note').textContent = part.navigation === 'forward' ? 'Forward only: after continuing, you cannot return to this item. For paragraph-order tasks, move a paragraph or explicitly confirm the order to enter a response.' : 'You can revisit and flag any task in this section until you finish it. You cannot return to an earlier section.';
        $('question-map-wrap').hidden = part.navigation === 'forward';
        $('flag-item').hidden = part.navigation === 'forward';
        $('finish-section').hidden = part.navigation === 'forward';
        renderFlag();
        renderResource(question);
        renderResponse(question);
        renderQuestionMap();
        updateClockUI();
        if (storageWarning) notice('runner-notice', storageWarning);
    }

    function renderFlag() {
        const flagged = state.flags.includes(item().id);
        $('flag-item').setAttribute('aria-pressed', String(flagged));
        $('flag-item').textContent = flagged ? '⚑ Flagged for review' : '⚑ Flag for review';
    }

    function renderQuestionMap() {
        $('question-map').replaceChildren(...section().items.map((question, index) => {
            const button = el('button', '', index + 1);
            button.type = 'button';
            const hasAnswer = answered(state.answers[question.id]);
            const flagged = state.flags.includes(question.id);
            button.classList.toggle('is-answered', Boolean(hasAnswer));
            button.classList.toggle('is-flagged', flagged);
            if (index === state.itemIndex) button.setAttribute('aria-current', 'step');
            button.setAttribute('aria-label', `Task ${index + 1}, ${hasAnswer ? 'response entered' : 'unanswered'}${flagged ? ', flagged' : ''}`);
            button.addEventListener('click', () => navigate(index));
            return button;
        }));
    }

    function renderPracticeFeedback() {
        const panel = $('practice-feedback');
        panel.replaceChildren();
        panel.hidden = true;
        $('check-practice-answer').disabled = false;
        if (!guided() || !state || state.phase !== 'running') return;
        const review = feedbackCache.get(item().id);
        if (!review) return;
        panel.hidden = false;
        panel.append(el('h3', '', review.graded ? `${review.score} / ${review.max_score} practice points` : 'Reflect on your response'));
        if (review.graded) panel.append(el('strong', '', 'Accepted answer'), el('p', 'exam-review-answer', answerText(review.item_snapshot || review, review.correct_answer)));
        if (review.correct_text) panel.append(el('p', '', review.correct_text));
        panel.append(el('p', '', review.explanation));
        if (review.rubric?.length) {
            const list = el('ul');
            review.rubric.forEach((point) => list.append(el('li', '', point)));
            panel.append(list);
        }
        panel.append(el('small', '', 'You can revise your response and check again. Full mocks reveal answers after submission.'));
    }

    async function checkPracticeAnswer() {
        if (!guided() || !state || state.phase !== 'running') return;
        const question = item();
        const request = ++feedbackRequest;
        const attemptId = state.attemptId;
        const value = state.answers[question.id] ?? (['gaps', 'multi', 'highlight', 'reorder'].includes(question.type) ? [] : '');
        const panel = $('practice-feedback');
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 15000);
        $('check-practice-answer').disabled = true;
        panel.replaceChildren(el('p', '', 'Checking your response…'));
        panel.hidden = false;
        try {
            const response = await fetch('api/practice-feedback.php', {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ exam_id: exam.id, exam_version: exam.version, item_id: question.id, response: value }),
            });
            const data = await response.json();
            if (!response.ok || !data.success || !data.review) throw new Error(data.error || 'Feedback is unavailable. Please try again.');
            if (request !== feedbackRequest || state?.attemptId !== attemptId || state.phase !== 'running' || item().id !== question.id) return;
            feedbackCache.set(question.id, data.review);
            renderPracticeFeedback();
            announce('Your answer explanation is ready. You can revise your response and check again.');
        } catch (error) {
            if (request === feedbackRequest && state?.attemptId === attemptId && state.phase === 'running' && item().id === question.id) {
                panel.replaceChildren(el('p', '', error.name === 'AbortError' ? 'Feedback took too long. Your response is kept; try again.' : error.message));
            }
        } finally {
            window.clearTimeout(timeout);
            if (request === feedbackRequest) $('check-practice-answer').disabled = false;
        }
    }

    function updateAnswer(id, value) {
        if (!state || state.phase !== 'running') return false;
        if (item().id !== id) return false;
        if (synchronize()) {
            if (state.phase === 'running') renderRunner();
            else { showPending(); submitAttempt(); }
            return false;
        }
        state.answers[id] = value;
        if (guided()) {
            feedbackCache.delete(id); feedbackRequest++; renderPracticeFeedback();
            document.querySelectorAll('#item-response .ai-review-panel').forEach((panel) => window.NishaAI?.refresh(panel));
        }
        scheduleSave();
        const part = section();
        $('runner-progress').textContent = `Section ${state.sectionIndex + 1} of ${exam.sections.length} · ${part.items.filter((entry) => answered(state.answers[entry.id])).length} of ${part.items.length} responses entered`;
        renderQuestionMap();
        return true;
    }

    function navigate(index) {
        if (!state || state.phase !== 'running') return;
        if (synchronize()) { if (state.phase === 'running') renderRunner(); else { showPending(); submitAttempt(); } return; }
        if (section().navigation === 'forward' && index <= state.itemIndex) return;
        stopRecording(false);
        state.itemIndex = index;
        ensureItemClock();
        saveState();
        renderRunner();
        $('item-title').focus({ preventScroll: true });
        $('exam-workspace').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function nextItem() {
        if (!state || state.phase !== 'running') return;
        if (state.itemIndex < section().items.length - 1) navigate(state.itemIndex + 1);
        else finishSection();
    }

    async function finishSection() {
        if (!state || state.phase !== 'running') return;
        const partId = section().id;
        const count = section().items.filter((entry) => !answered(state.answers[entry.id])).length;
        const last = state.sectionIndex === exam.sections.length - 1;
        if (!await confirm(last ? 'Finish and submit this session?' : 'Ready for the next section?', `${count ? `${count} task${count === 1 ? ' has' : 's have'} no text response. ` : ''}${last ? 'Submitting will end this session and show your complete practice review.' : 'Once you finish this section, you cannot return to it. The next section timer begins immediately.'}`, last ? 'Finish & submit' : 'Finish section')) return;
        if (state.phase !== 'running' || section().id !== partId) return;
        advanceSection(Date.now());
        saveState();
        if (state.phase === 'running') { renderRunner(); $('runner-title').focus(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
        else { showPending(); submitAttempt(); }
    }

    async function submitEarly() {
        if (!state || state.phase !== 'running') return;
        const count = items(exam).filter((entry) => !answered(state.answers[entry.id])).length;
        if (await confirm('Submit your mock for review?', `${count} tasks have no text response. This ends the attempt. Any unfinished sections will be labelled incomplete, and unanswered objective tasks receive zero points. You will not be able to change answers after submission.`, 'Submit exam')) {
            if (state.phase !== 'running') return;
            finishState();
            showPending();
            submitAttempt();
        }
    }

    function renderResource(question) {
        const panel = $('exam-resource-panel');
        // Shared IELTS recordings may continue between their questions. A new
        // stimulus must never inherit speech from the previous task.
        if (audioResourceId && audioResourceId !== question.resource_id) stopAudio();
        const resourceKey = `${state.attemptId}:${question.resource_id || question.id}:${question.secondary_resource_id || ''}:${state.assisted ? 'assisted' : 'exam'}`;
        // Keep a shared passage's scroll position and an open transcript while
        // moving between its questions. A new attempt/mode always renders anew.
        if (panel.dataset.resourceKey === resourceKey && panel.childNodes.length) return;
        panel.dataset.resourceKey = resourceKey;
        panel.replaceChildren();
        const resource = exam.resources[question.resource_id] || (typeof question.text === 'string' && ['choice', 'multi'].includes(question.type) ? { kind: 'passage', title: question.title || 'Reading passage', paragraphs: [question.text] } : null);
        if (!resource) {
            panel.classList.add('exam-resource-guidance');
            panel.append(el('span', 'exam-resource-spark', '✳'), el('p', 'eyebrow', 'ONE TASK. ONE STEP FORWARD.'), el('h2', '', question.type === 'spoken' ? 'Give your ideas a voice.' : 'A little focus goes a long way.'), el('p', '', question.type === 'spoken' ? 'Prepare your response, speak at a comfortable pace and reflect on the details. Recording is optional and is never marked automatically.' : 'Read the instructions carefully. Your answers are saved on this device when storage is available.'));
            return;
        }
        panel.classList.remove('exam-resource-guidance');
        panel.append(el('p', 'eyebrow', `${resource.kind === 'audio' ? 'LISTENING STIMULUS' : resource.kind === 'chart' ? 'VISUAL INFORMATION' : 'READING MATERIAL'} · NISHA ORIGINAL`), el('h2', '', resource.title));
        if (resource.kind === 'audio') {
            const player = el('div', 'exam-audio-player');
            const play = el('button', 'btn btn-primary', 'Play audio ▶');
            play.type = 'button';
            play.id = 'stimulus-play';
            play.disabled = !speechAvailable() || (!state.assisted && state.played.includes(question.resource_id));
            play.addEventListener('click', () => playResource(question.resource_id));
            const stop = el('button', 'btn btn-outline', 'Stop playback');
            stop.type = 'button';
            stop.addEventListener('click', () => { stopAudio(); setAudioStatus(state.assisted ? 'Playback stopped. Replay when you are ready.' : 'Playback stopped. In exam mode, an audio that has started cannot be replayed.'); });
            player.append(play, stop);
            const status = el('p', 'exam-small-copy');
            status.id = 'stimulus-status';
            status.setAttribute('role', 'status');
            status.textContent = audioResourceId === question.resource_id ? 'Audio is playing. The section timer continues.' : state.played.includes(question.resource_id) ? state.assisted ? 'This audio has played. You can replay it or open the transcript.' : state.audioEnded[question.resource_id] ? 'This audio has already played. Exam mode allows one playback.' : 'This audio was interrupted. Choose assisted mode to continue with a transcript.' : 'Audio uses your browser’s English speech voice. Play it when you are ready.';
            panel.append(player, status);
            if (!state.assisted) panel.append(el('p', 'exam-audio-once', 'One playback in exam mode. Do not reload during audio: playback cannot be restored. Transcripts and replays require assisted mode.'));
            if (state.assisted) {
                const transcript = el('details', 'exam-transcript');
                transcript.append(el('summary', '', 'Read the audio transcript'));
                const text = el('div', 'exam-transcript-text');
                for (const paragraph of String(resource.audio_script || '').split(/\n+/u)) if (paragraph.trim()) text.append(el('p', '', paragraph));
                transcript.append(text);
                panel.append(transcript);
                if ((question.prep_seconds || question.response_seconds) && !state.audioEnded[question.resource_id]) {
                    const continueButton = el('button', 'btn btn-outline', 'Use transcript & start response →');
                    continueButton.type = 'button';
                    continueButton.id = 'transcript-start-response';
                    continueButton.addEventListener('click', () => {
                        stopAudio();
                        markAudioComplete(question.resource_id);
                        continueButton.remove();
                        setAudioStatus(timed() ? 'Assisted transcript used. Preparation or response timing has now started.' : 'Transcript ready. Respond when you are ready.');
                    });
                    panel.append(continueButton);
                }
            }
        } else if (resource.kind === 'chart') {
            panel.append(renderChart(resource.chart, resource.title));
        } else if (resource.kind === 'table') {
            panel.append(renderDataTable(resource.columns, resource.rows, resource.title));
        } else if (resource.kind === 'diagram') {
            panel.append(renderDiagram(resource.diagram, resource.title));
        } else {
            const passage = el('div', 'exam-passage');
            (resource.paragraphs || [resource.text || '']).forEach((paragraph) => passage.append(el('p', '', paragraph)));
            panel.append(passage);
        }
        const secondary = exam.resources[question.secondary_resource_id];
        if (secondary) {
            panel.append(el('h3', 'exam-secondary-title', secondary.title));
            if (secondary.kind === 'diagram') panel.append(renderDiagram(secondary.diagram, secondary.title));
            else if (secondary.kind === 'table') panel.append(renderDataTable(secondary.columns, secondary.rows, secondary.title));
            else if (secondary.kind === 'chart') panel.append(renderChart(secondary.chart, secondary.title));
        }
    }

    function renderChart(chart, title) {
        const figure = el('figure', 'exam-chart');
        if (!chart || !Array.isArray(chart.categories) || !Array.isArray(chart.series)) return figure;
        const max = Math.max(1, ...chart.series.flatMap((series) => series.values.map(Number).filter(Number.isFinite)));
        const graphic = el('div', 'exam-chart-graphic');
        graphic.setAttribute('aria-hidden', 'true');
        chart.categories.forEach((category, index) => {
            const group = el('div', 'exam-chart-group');
            group.append(el('strong', '', category));
            chart.series.forEach((series, seriesIndex) => {
                const value = Number(series.values[index]) || 0;
                const row = el('div', `exam-chart-bar-row chart-series-${seriesIndex % 5}`);
                row.append(el('span', '', series.name));
                const track = el('div', 'exam-chart-track');
                const bar = el('span', 'exam-chart-bar');
                bar.style.width = `${Math.max(0, value) / max * 100}%`;
                track.append(bar);
                row.append(track, el('small', '', `${value}${chart.unit === '%' ? '%' : ''}`));
                group.append(row);
            });
            graphic.append(group);
        });
        const table = el('table', 'exam-chart-table');
        const caption = el('caption', '', `${title}${chart.unit ? ` (${chart.unit})` : ''}`);
        const head = el('thead');
        const header = el('tr');
        const first = el('th', '', 'Category'); first.scope = 'col'; header.append(first);
        chart.series.forEach((series) => { const th = el('th', '', series.name); th.scope = 'col'; header.append(th); });
        head.append(header);
        const body = el('tbody');
        chart.categories.forEach((category, index) => {
            const tr = el('tr'); const th = el('th', '', category); th.scope = 'row'; tr.append(th);
            chart.series.forEach((series) => tr.append(el('td', '', series.values[index])));
            body.append(tr);
        });
        table.append(caption, head, body);
        figure.append(['line', 'pie'].includes(chart.type) ? renderPlot(chart) : graphic, table);
        return figure;
    }

    function renderDataTable(columns = [], rows = [], title = '') {
        const wrap = el('div', 'exam-data-table-wrap');
        const table = el('table', 'exam-chart-table');
        table.append(el('caption', '', title));
        const head = el('thead'); const header = el('tr');
        columns.forEach((column) => { const cell = el('th', '', column); cell.scope = 'col'; header.append(cell); });
        head.append(header);
        const body = el('tbody');
        rows.forEach((row) => { const tr = el('tr'); row.forEach((value, index) => { const cell = el(index === 0 ? 'th' : 'td', '', value); if (!index) cell.scope = 'row'; tr.append(cell); }); body.append(tr); });
        table.append(head, body); wrap.append(table); return wrap;
    }

    const svgNode = (tag, attributes = {}, text) => {
        const node = document.createElementNS('http://www.w3.org/2000/svg', tag);
        Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, String(value)));
        if (text !== undefined) node.textContent = text;
        return node;
    };
    let diagramSerial = 0;
    const chartColors = ['#285e46', '#e6ac55', '#738bc3', '#b87e95', '#88b9a7', '#8b775b'];

    function renderPlot(chart) {
        const svg = svgNode('svg', { viewBox: '0 0 640 360', class: 'exam-plot', 'aria-hidden': 'true' });
        if (chart.type === 'pie') {
            const values = chart.series[0]?.values.map((value) => Math.max(0, Number(value) || 0)) || [];
            const total = values.reduce((sum, value) => sum + value, 0);
            let angle = -Math.PI / 2;
            values.forEach((value, index) => {
                const next = angle + (total ? value / total : 0) * Math.PI * 2;
                const x = 175 + 125 * Math.cos(angle); const y = 175 + 125 * Math.sin(angle);
                const endX = 175 + 125 * Math.cos(next); const endY = 175 + 125 * Math.sin(next);
                if (total && value === total) svg.append(svgNode('circle', { cx: 175, cy: 175, r: 125, fill: chartColors[index % chartColors.length] }));
                else if (value) svg.append(svgNode('path', { d: `M175 175 L${x} ${y} A125 125 0 ${next - angle > Math.PI ? 1 : 0} 1 ${endX} ${endY} Z`, fill: chartColors[index % chartColors.length], stroke: '#fff', 'stroke-width': 2 }));
                svg.append(svgNode('rect', { x: 340, y: 45 + index * 44, width: 18, height: 18, rx: 3, fill: chartColors[index % chartColors.length] }), svgNode('text', { x: 370, y: 60 + index * 44, 'font-size': 16, fill: '#263e32' }, `${chart.categories[index]}: ${value}${chart.unit === '%' ? '%' : ''}`));
                angle = next;
            });
        } else {
            const maximum = Math.max(1, ...chart.series.flatMap((series) => series.values.map(Number).filter(Number.isFinite)));
            for (let tick = 0; tick <= 4; tick++) {
                const y = 275 - tick * 55;
                svg.append(svgNode('line', { x1: 55, x2: 610, y1: y, y2: y, stroke: '#d9e2d9' }), svgNode('text', { x: 45, y: y + 5, 'text-anchor': 'end', 'font-size': 13, fill: '#56685b' }, Math.round(maximum * tick / 4 * 10) / 10));
            }
            const xFor = (index) => 65 + index / Math.max(1, chart.categories.length - 1) * 535;
            chart.categories.forEach((category, index) => svg.append(svgNode('text', { x: xFor(index), y: 300, 'text-anchor': 'middle', 'font-size': 13, fill: '#56685b' }, category)));
            chart.series.forEach((series, index) => {
                const points = series.values.map((value, position) => [xFor(position), 275 - Math.max(0, Number(value) || 0) / maximum * 220]);
                const color = chartColors[index % chartColors.length];
                svg.append(svgNode('polyline', { points: points.map((point) => point.join(',')).join(' '), fill: 'none', stroke: color, 'stroke-width': 3 }));
                points.forEach(([cx, cy]) => svg.append(svgNode('circle', { cx, cy, r: 4, fill: color })));
                const labelX = 65 + index * 175;
                svg.append(svgNode('line', { x1: labelX, x2: labelX + 22, y1: 335, y2: 335, stroke: color, 'stroke-width': 3 }), svgNode('text', { x: labelX + 28, y: 340, 'font-size': 13, fill: '#263e32' }, series.name));
            });
        }
        return svg;
    }

    function renderDiagram(diagram = {}, title) {
        const figure = el('figure', 'exam-diagram');
        if (diagram.description) figure.append(el('figcaption', '', diagram.description));
        const svg = svgNode('svg', { viewBox: '0 0 100 100', role: 'img', 'aria-label': title, class: 'exam-diagram-graphic' });
        const nodes = new Map((diagram.nodes || []).map((node) => [node.id, node]));
        const number = (value, fallback = 0) => Number.isFinite(Number(value)) ? Number(value) : fallback;
        const arrowId = `diagram-arrow-${++diagramSerial}`;
        const definitions = svgNode('defs'); const marker = svgNode('marker', { id: arrowId, viewBox: '0 0 10 10', refX: 9, refY: 5, markerWidth: 5, markerHeight: 5, orient: 'auto-start-reverse' });
        marker.append(svgNode('path', { d: 'M0 0 L10 5 L0 10 z', fill: '#7a927f' })); definitions.append(marker); svg.append(definitions);
        (diagram.edges || []).forEach((edge) => {
            const from = nodes.get(edge.from); const to = nodes.get(edge.to);
            if (!from || !to) return;
            const x1 = number(from.x) + number(from.width, 20) / 2; const y1 = number(from.y) + number(from.height, 12) / 2;
            const x2 = number(to.x) + number(to.width, 20) / 2; const y2 = number(to.y) + number(to.height, 12) / 2;
            const dx = x2 - x1; const dy = y2 - y1;
            if (!dx && !dy) return;
            const start = Math.min(dx ? number(from.width, 20) / 2 / Math.abs(dx) : Infinity, dy ? number(from.height, 12) / 2 / Math.abs(dy) : Infinity);
            const end = Math.min(dx ? number(to.width, 20) / 2 / Math.abs(dx) : Infinity, dy ? number(to.height, 12) / 2 / Math.abs(dy) : Infinity);
            svg.append(svgNode('line', { x1: x1 + dx * start, y1: y1 + dy * start, x2: x2 - dx * end, y2: y2 - dy * end, stroke: '#7a927f', 'stroke-width': 0.7, 'marker-end': `url(#${arrowId})` }));
        });
        nodes.forEach((node) => {
            const x = number(node.x); const y = number(node.y); const width = number(node.width, 20); const height = number(node.height, 12);
            svg.append(svgNode('rect', { x, y, width, height, rx: 1.6, fill: '#f0f5e9', stroke: '#53785d', 'stroke-width': 0.5 }));
            const label = svgNode('text', { x: x + width / 2, y: y + height / 2, 'text-anchor': 'middle', 'dominant-baseline': 'middle', 'font-size': 2.5, fill: '#263e32' });
            const lines = String(node.label || '').split(/\n/u);
            lines.forEach((line, index) => label.append(svgNode('tspan', { x: x + width / 2, dy: index ? 3.4 : -(lines.length - 1) * 1.7 }, line)));
            svg.append(label);
        });
        figure.append(svg);
        const alternative = el('details', 'exam-diagram-description');
        alternative.append(el('summary', '', 'Text description of the layout'));
        const list = el('ul');
        nodes.forEach((node) => list.append(el('li', '', `${String(node.label || '').replaceAll('\n', ' ')} — ${number(node.x)}% from the left, ${number(node.y)}% from the top.`)));
        (diagram.edges || []).forEach((edge) => { if (nodes.has(edge.from) && nodes.has(edge.to)) list.append(el('li', '', `Arrow: ${nodes.get(edge.from).label} → ${nodes.get(edge.to).label}`)); });
        alternative.append(list); figure.append(alternative);
        return figure;
    }

    function setAudioStatus(message) {
        const target = $('stimulus-status');
        if (target) target.textContent = message;
    }

    function stopAudio() {
        audioGeneration++;
        if (speechAvailable()) window.speechSynthesis.cancel();
        audioResourceId = null;
        if (cueOscillator) {
            try { cueOscillator.stop(); } catch (error) { /* A completed cue is already stopped. */ }
            cueOscillator = null;
        }
    }

    function englishUtterance(text) {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'en-GB';
        utterance.rate = 0.94;
        const voices = window.speechSynthesis.getVoices();
        const voice = voices.find((entry) => entry.lang === 'en-GB') || voices.find((entry) => entry.lang.startsWith('en'));
        if (voice) utterance.voice = voice;
        return utterance;
    }

    function markAudioComplete(id) {
        if (!state || state.phase !== 'running') return;
        state.audioEnded[id] = Date.now();
        if (item().resource_id === id) ensureItemClock(Date.now());
        saveState();
        updateClockUI();
    }

    function playResource(id) {
        if (!speechAvailable() || !state || state.phase !== 'running' || (!state.assisted && state.played.includes(id))) return;
        stopAudio();
        const generation = audioGeneration;
        audioResourceId = id;
        const script = exam.resources[id].audio_script || '';
        const hasTone = /\[tone\]/iu.test(script);
        if (hasTone) {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext && !cueContext) cueContext = new AudioContext();
                cueContext?.resume().catch(() => {});
            } catch (error) { /* The omitted word is still absent if a cue is unavailable. */ }
        }
        const utterance = englishUtterance(script.replace(/\[tone\]/giu, ''));
        if ($('stimulus-play')) $('stimulus-play').disabled = true;
        setAudioStatus('Preparing browser audio…');
        utterance.addEventListener('start', () => {
            if (generation !== audioGeneration || state.phase !== 'running') return;
            if (!state.played.includes(id)) state.played.push(id);
            saveState();
            setAudioStatus(timed() ? 'Audio is playing. Listen carefully; the section clock continues.' : 'Audio is playing. Listen carefully; you can replay it in guided practice.');
        });
        utterance.addEventListener('end', async () => {
            if (generation !== audioGeneration || state.phase !== 'running') return;
            let cuePlayed = !hasTone;
            if (hasTone) cuePlayed = await playEndCue();
            if (generation !== audioGeneration || state.phase !== 'running') return;
            audioResourceId = null;
            markAudioComplete(id);
            if (item().resource_id === id) {
                setAudioStatus(cuePlayed ? timed() ? 'Audio complete. Your preparation or response timer, when provided, now runs.' : 'Audio complete. Respond when you are ready; guided practice has no countdown.' : 'Audio complete. The final word was omitted; the browser could not play the end tone.');
                if ($('stimulus-play')) $('stimulus-play').disabled = !state.assisted;
                $('transcript-start-response')?.remove();
            }
        });
        utterance.addEventListener('error', (event) => {
            if (generation !== audioGeneration || ['canceled', 'interrupted'].includes(event.error)) return;
            audioResourceId = null;
            setAudioStatus('Browser audio could not play. Choose “Need transcripts?” above to continue in assisted mode. The section timer keeps running.');
            if ($('stimulus-play')) $('stimulus-play').disabled = !state.assisted && state.played.includes(id);
            announce('Audio failed. Assisted mode provides a transcript and a way to start your response timer.');
        });
        window.speechSynthesis.speak(utterance);
    }

    function playEndCue() {
        if (!cueContext || cueContext.state !== 'running') return Promise.resolve(false);
        return new Promise((resolve) => {
            try {
                const oscillator = cueContext.createOscillator();
                const gain = cueContext.createGain();
                const start = cueContext.currentTime;
                oscillator.type = 'sine'; oscillator.frequency.value = 880;
                gain.gain.setValueAtTime(0, start);
                gain.gain.linearRampToValueAtTime(0.06, start + 0.02);
                gain.gain.setValueAtTime(0.06, start + 0.25);
                gain.gain.linearRampToValueAtTime(0, start + 0.3);
                oscillator.connect(gain); gain.connect(cueContext.destination);
                cueOscillator = oscillator;
                oscillator.addEventListener('ended', () => {
                    oscillator.disconnect(); gain.disconnect();
                    if (cueOscillator === oscillator) cueOscillator = null;
                    resolve(true);
                }, { once: true });
                oscillator.start(start); oscillator.stop(start + 0.32);
            } catch (error) { cueOscillator = null; resolve(false); }
        });
    }

    function renderResponse(question) {
        const container = $('item-response');
        container.replaceChildren();
        const response = state.answers[question.id];
        if (question.type === 'choice' || question.type === 'multi') {
            const fieldset = el('fieldset', 'exam-choices');
            fieldset.append(el('legend', 'exam-sr-only', question.type === 'multi' ? 'Select your answers' : 'Select one answer'));
            (question.options || []).forEach((option, index) => {
                const label = el('label', 'exam-choice');
                const input = document.createElement('input');
                input.type = question.type === 'multi' ? 'checkbox' : 'radio';
                input.name = `response-${question.id}`;
                input.value = option.id;
                input.checked = question.type === 'multi' ? Array.isArray(response) && response.includes(option.id) : response === option.id;
                const letter = el('span', 'exam-option-letter', String.fromCharCode(65 + index));
                letter.setAttribute('aria-hidden', 'true');
                label.classList.toggle('is-selected', input.checked);
                input.addEventListener('change', () => {
                    const value = question.type === 'multi' ? [...fieldset.querySelectorAll('input:checked')].map((entry) => entry.value) : input.value;
                    if (question.type === 'multi' && question.select_count && value.length > question.select_count) {
                        input.checked = false;
                        announce(`Select at most ${question.select_count} answers. Deselect an option before choosing another.`);
                        return;
                    }
                    updateAnswer(question.id, value);
                    fieldset.querySelectorAll('label').forEach((entry) => entry.classList.toggle('is-selected', entry.querySelector('input').checked));
                });
                label.append(input, letter, el('span', '', option.text));
                fieldset.append(label);
            });
            container.append(fieldset);
            if (question.select_count) container.append(el('p', 'exam-small-copy', `Select ${question.select_count} answers.`));
        } else if (question.type === 'short') {
            const label = el('label', 'exam-input-label', 'Your answer');
            const input = document.createElement('input');
            input.type = 'text'; input.className = 'exam-short-input'; input.maxLength = 400;
            input.autocomplete = 'off'; input.spellcheck = false;
            input.value = typeof response === 'string' ? response : '';
            input.addEventListener('input', () => updateAnswer(question.id, input.value));
            label.append(input); container.append(label);
            if (question.max_words) container.append(el('p', 'exam-small-copy', `Maximum ${question.max_words} ${question.max_words === 1 ? 'word' : 'words'}${question.allow_numbers ? ' and/or a number' : ''}. Follow the task instructions.`));
        } else if (question.type === 'gaps') {
            const paragraph = el('div', 'exam-gap-passage');
            const blankMap = new Map(question.blanks.map((blank) => [blank.id, blank]));
            String(question.text || '').split(/(\{\{[^}]+\}\})/u).forEach((piece) => {
                const match = piece.match(/^\{\{([^}]+)\}\}$/u);
                if (!match) { paragraph.append(document.createTextNode(piece)); return; }
                const blank = blankMap.get(match[1]);
                if (!blank) { paragraph.append(document.createTextNode(piece)); return; }
                const input = document.createElement(blank.options ? 'select' : 'input');
                input.className = 'exam-gap-input';
                input.setAttribute('aria-label', `Blank ${blank.id}`);
                if (blank.options) {
                    input.append(Object.assign(document.createElement('option'), { value: '', textContent: 'Select…' }));
                    blank.options.forEach((option) => input.append(Object.assign(document.createElement('option'), { value: option, textContent: option })));
                } else { input.type = 'text'; input.maxLength = 200; input.autocomplete = 'off'; input.spellcheck = false; }
                input.value = response && typeof response === 'object' ? response[blank.id] || '' : '';
                input.addEventListener(blank.options ? 'change' : 'input', () => {
                    const values = state.answers[question.id] && typeof state.answers[question.id] === 'object' ? { ...state.answers[question.id] } : {};
                    values[blank.id] = input.value;
                    updateAnswer(question.id, values);
                });
                paragraph.append(input);
            });
            container.append(paragraph);
        } else if (question.type === 'reorder') {
            renderReorder(question, container);
        } else if (question.type === 'highlight') {
            const words = el('div', 'exam-highlight-words');
            const selected = new Set(Array.isArray(response) ? response : []);
            String(question.text || '').trim().split(/\s+/u).forEach((word, index) => {
                const button = el('button', selected.has(String(index)) ? 'is-selected' : '', word);
                button.type = 'button';
                button.setAttribute('aria-pressed', String(selected.has(String(index))));
                button.setAttribute('aria-label', `Word ${index + 1}: ${word}`);
                button.addEventListener('click', () => {
                    if (selected.has(String(index))) selected.delete(String(index)); else selected.add(String(index));
                    button.classList.toggle('is-selected', selected.has(String(index)));
                    button.setAttribute('aria-pressed', String(selected.has(String(index))));
                    updateAnswer(question.id, [...selected].sort((a, b) => Number(a) - Number(b)));
                });
                words.append(button, document.createTextNode(' '));
            });
            container.append(words, el('p', 'exam-small-copy', 'Select each word you believe differs from the audio. Select a highlighted word again to deselect it. Incorrect selections reduce the practice points for this task, down to zero.'));
        } else if (question.type === 'essay') {
            const label = el('label', 'exam-input-label', 'Your response');
            const textarea = document.createElement('textarea');
            textarea.className = 'exam-writing-area'; textarea.rows = 13; textarea.maxLength = 10000;
            textarea.spellcheck = false; textarea.value = typeof response === 'string' ? response : '';
            textarea.placeholder = 'Give your ideas a little room…';
            const meta = el('div', 'exam-writing-meta');
            const count = el('strong', '', `${wordCount(textarea.value)} words`);
            let target = '';
            if (question.min_words && question.max_words) target = `Aim for ${question.min_words}–${question.max_words} words`;
            else if (question.min_words) target = `At least ${question.min_words} words`;
            else if (question.max_words) target = `Up to ${question.max_words} words`;
            meta.append(count, el('span', '', target));
            textarea.addEventListener('input', () => { updateAnswer(question.id, textarea.value); count.textContent = `${wordCount(textarea.value)} words`; });
            label.append(textarea); container.append(label, meta);
            if (question.suggested_seconds) container.append(el('p', 'exam-small-copy', `${timed() && exam.exam === 'PTE' ? 'Task time limit' : 'Suggested task time'}: ${Math.round(question.suggested_seconds / 60)} minutes.${timed() ? ' Your section timer continues.' : ' Guided practice has no countdown.'}`));
            container.append(el('p', 'exam-small-copy', guided() ? 'Your response is saved on this device. Request optional AI coaching below, or use the self-review checklist.' : 'Your response is retained for review. Optional AI coaching is available after you finish and save this timed test.'));
        } else if (question.type === 'spoken') {
            if (Array.isArray(question.cues)) {
                const cues = el('ul', 'exam-speaking-cues');
                question.cues.forEach((cue) => cues.append(el('li', '', cue)));
                container.append(cues);
            }
            const recorder = el('div', 'exam-recorder');
            recorder.append(el('h3', '', 'Your voice. Your progress.'));
            const button = el('button', 'btn btn-outline', 'Record my response ●');
            button.type = 'button'; button.dataset.record = 'true';
            button.addEventListener('click', () => toggleRecording(question.id));
            const status = el('p', 'exam-small-copy'); status.dataset.recordStatus = 'true'; status.setAttribute('role', 'status');
            if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                button.disabled = true;
                status.textContent = 'Recording is unavailable. You can practise aloud; use a supported browser on HTTPS or localhost to record.';
            } else status.textContent = 'Optional microphone recording. Audio stays in this tab unless you explicitly send it to OpenAI for AI coaching.';
            recorder.append(button, status);
            const playback = el('div', 'exam-recording-playback'); playback.dataset.recordPlayback = 'true'; recorder.append(playback);
            container.append(recorder);
            const label = el('label', 'exam-input-label', 'Optional reflection notes (not a transcript or scored response)');
            const notes = document.createElement('textarea');
            notes.rows = 3; notes.maxLength = 2000; notes.className = 'exam-speaking-notes'; notes.value = typeof response === 'string' ? response : '';
            notes.addEventListener('input', () => updateAnswer(question.id, notes.value));
            label.append(notes); container.append(label);
            renderRecordingPlayback(question.id);
        }
        if (guided() && ['essay', 'spoken'].includes(question.type) && window.NishaAI) container.append(makeAIPanel(question));
    }

    function makeAIPanel(question, resultReview = null) {
        const result = resultReview ? currentEnvelope?.result : null;
        const attemptState = state;
        const examId = result?.exam_id || exam?.id;
        const version = result?.exam_version || exam?.version;
        const attemptId = result?.attempt_id || state?.attemptId;
        const matchingState = attemptState && attemptState.attemptId === attemptId && attemptState.examId === examId && attemptState.version === version;
        const memory = matchingState ? attemptState : currentEnvelope;
        memory.aiDrafts = memory.aiDrafts && typeof memory.aiDrafts === 'object' ? memory.aiDrafts : {};
        memory.aiReviews = Array.isArray(memory.aiReviews) ? memory.aiReviews : [];
        const draft = memory.aiDrafts[question.id] || (memory.aiDrafts[question.id] = {});
        const historyEntries = Array.isArray(currentEnvelope?.ai_reviews) && resultReview ? currentEnvelope.ai_reviews : [];
        const entries = [...memory.aiReviews, ...historyEntries];
        return window.NishaAI.mount({
            item: { ...question, type: resultReview?.type || question.type }, examId, examVersion: version, attemptId,
            signedIn: Boolean(config.signedIn), config: config.aiReview, guided: !resultReview && guided(), saved: resultReview ? Boolean(currentEnvelope.saved) : true,
            getResponse: () => resultReview ? resultReview.response : attemptState.answers[question.id] || '',
            getRecording: () => (state?.attemptId === attemptId && state?.examId === examId) ? recordings.get(question.id)?.blob : null,
            draft, entries, onChange: (updatedDraft, updatedEntries) => {
                memory.aiDrafts[question.id] = updatedDraft;
                const uniqueEntries = new Map([...(memory.aiReviews || []), ...updatedEntries].map((entry) => [entry.id, entry]));
                memory.aiReviews = [...uniqueEntries.values()];
                if (resultReview && currentEnvelope?.result.attempt_id === attemptId) currentEnvelope.ai_reviews = memory.aiReviews;
                if (matchingState && state === attemptState) saveState();
                if (resultReview && currentEnvelope?.result.attempt_id === attemptId) updateResultAIStatus();
            },
            onStatus: (status) => {
                if (!resultReview || currentEnvelope?.result.attempt_id !== attemptId) return;
                resultAIActivity.set(question.id, status);
                updateResultAIStatus();
            },
        });
    }

    function refreshAIPanels() {
        document.querySelectorAll('.ai-review-panel').forEach((panel) => window.NishaAI?.refresh(panel));
    }

    function renderReorder(question, container, focusId, direction) {
        const order = Array.isArray(state.answers[question.id]) && state.answers[question.id].length === question.options.length ? state.answers[question.id] : question.options.map((option) => option.id);
        const options = new Map(question.options.map((option) => [option.id, option]));
        const list = el('ol', 'exam-reorder');
        order.forEach((id, index) => {
            const li = el('li'); li.append(el('span', 'exam-reorder-index', index + 1), el('span', '', options.get(id)?.text || id));
            const controls = el('div');
            for (const move of [-1, 1]) {
                const button = el('button', '', move < 0 ? '↑' : '↓');
                button.type = 'button'; button.disabled = index + move < 0 || index + move >= order.length;
                button.dataset.optionId = id; button.dataset.move = move;
                button.setAttribute('aria-label', `Move paragraph ${index + 1} ${move < 0 ? 'up' : 'down'}`);
                button.addEventListener('click', () => {
                    const next = [...order]; [next[index], next[index + move]] = [next[index + move], next[index]];
                    if (!updateAnswer(question.id, next)) return;
                    container.replaceChildren();
                    renderReorder(question, container, id, move);
                });
                controls.append(button);
            }
            li.append(controls); list.append(li);
        });
        const accept = el('button', 'btn btn-outline', state.answers[question.id] ? 'Order entered ✓' : 'Use this order');
        accept.type = 'button';
        accept.addEventListener('click', () => { updateAnswer(question.id, [...order]); accept.textContent = 'Order entered ✓'; });
        container.append(list, accept, el('p', 'exam-small-copy', 'Move paragraphs with the up and down buttons, or choose “Use this order” if it is already correct. Moving on without either action leaves this task unanswered. Practice points reward correctly adjacent pairs.'));
        if (focusId) {
            const buttons = [...container.querySelectorAll('button')];
            const target = buttons.find((button) => button.dataset.optionId === focusId && Number(button.dataset.move) === direction && !button.disabled) || buttons.find((button) => button.dataset.optionId === focusId && !button.disabled);
            target?.focus();
        }
    }

    function recordingStatus(id, message) {
        if (state?.phase === 'running' && item().id === id) {
            const target = document.querySelector('[data-record-status]');
            if (target) target.textContent = message;
        }
    }

    function stopRecording(discard = false) {
        recordingRequest++;
        const active = recording;
        if (!active) return;
        active.discard = discard;
        if (active.recorder && active.recorder.state !== 'inactive') active.recorder.stop();
        if (active.stream) active.stream.getTracks().forEach((track) => track.stop());
        recording = null;
        const button = document.querySelector('[data-record]');
        if (button) { button.textContent = 'Record my response ●'; button.disabled = false; }
    }

    function clearRecordings() {
        recordings.forEach((value) => URL.revokeObjectURL(value.url));
        recordings.clear();
        document.querySelectorAll('.exam-local-recordings').forEach((node) => node.remove());
    }

    async function toggleRecording(id) {
        if (!state || state.phase !== 'running' || item().id !== id) return;
        if (recording?.itemId === id) { stopRecording(false); updateClockUI(); return; }
        if (synchronize()) { if (state.phase === 'running') renderRunner(); else { showPending(); submitAttempt(); } return; }
        const clock = timed() ? state.itemClocks[id] : null;
        if (clock && Date.now() < clock.prepDeadline) return;
        if (timed() && !clock && exam.resources[item().resource_id]?.kind === 'audio' && (item().prep_seconds || item().response_seconds)) return;
        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) return;
        stopRecording(false);
        const request = ++recordingRequest;
        const active = { itemId: id, pending: true, recorder: null, stream: null, discard: false };
        recording = active;
        const button = document.querySelector('[data-record]');
        if (button) button.disabled = true;
        recordingStatus(id, 'Waiting for microphone permission…');
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            if (request !== recordingRequest || !state || state.phase !== 'running' || item().id !== id) {
                stream.getTracks().forEach((track) => track.stop());
                return;
            }
            if (synchronize()) {
                stream.getTracks().forEach((track) => track.stop());
                if (state.phase === 'running') renderRunner();
                else { showPending(); submitAttempt(); }
                return;
            }
            active.stream = stream;
            active.recorder = new MediaRecorder(stream);
            active.pending = false;
            const chunks = [];
            active.recorder.addEventListener('dataavailable', (event) => { if (event.data.size > 0) chunks.push(event.data); });
            active.recorder.addEventListener('stop', () => {
                stream.getTracks().forEach((track) => track.stop());
                if (active.discard || chunks.length === 0) return;
                const mime = active.recorder.mimeType || chunks[0].type || 'audio/webm';
                const blob = new Blob(chunks, { type: mime });
                if (recordings.has(id)) URL.revokeObjectURL(recordings.get(id).url);
                recordings.set(id, { blob, mime, url: URL.createObjectURL(blob) });
                recordingStatus(id, 'Recording ready. Listen back or download it. It remains available during this tab’s session, including on your results.');
                renderRecordingPlayback(id);
                renderLocalRecordings();
                refreshAIPanels();
            });
            active.recorder.addEventListener('error', () => {
                stopRecording(true);
                recordingStatus(id, 'A browser recording error occurred. Your microphone has been released. You can continue speaking aloud.');
            });
            active.recorder.start();
            if (button) { button.disabled = false; button.textContent = 'Stop recording ■'; }
            recordingStatus(id, 'Recording now. Stop when you finish. Your microphone will also stop when you move on or the timer ends.');
        } catch (error) {
            if (active.stream) active.stream.getTracks().forEach((track) => track.stop());
            if (request === recordingRequest) {
                recording = null;
                recordingStatus(id, error.name === 'NotAllowedError' ? 'Microphone permission was not granted. Practise aloud, or enable access in your browser settings. This does not affect objective practice points.' : 'The microphone could not be accessed. Check your device, or continue speaking aloud without a recording.');
                if (button) button.disabled = false;
                updateClockUI();
            }
        }
    }

    function recordingDownload(id, value, text = 'Download recording ↓') {
        const link = el('a', 'exam-text-button', text);
        link.href = value.url;
        link.download = `nisha-${id}.${value.mime.includes('mp4') ? 'm4a' : value.mime.includes('ogg') ? 'ogg' : 'webm'}`;
        return link;
    }

    function renderRecordingPlayback(id) {
        if (!state || state.phase !== 'running' || item().id !== id) return;
        const container = document.querySelector('[data-record-playback]');
        if (!container) return;
        container.replaceChildren();
        const value = recordings.get(id);
        if (!value) return;
        const audio = document.createElement('audio'); audio.controls = true; audio.src = value.url;
        audio.setAttribute('aria-label', 'Your recorded response');
        const actions = el('div', 'exam-recording-actions');
        const remove = el('button', 'exam-text-button', 'Delete recording'); remove.type = 'button';
        remove.addEventListener('click', () => { audio.pause(); URL.revokeObjectURL(value.url); recordings.delete(id); renderRecordingPlayback(id); renderLocalRecordings(); recordingStatus(id, 'Local recording deleted.'); refreshAIPanels(); });
        actions.append(recordingDownload(id, value), remove);
        container.append(audio, actions);
    }

    function renderLocalRecordings() {
        document.querySelectorAll('.exam-local-recordings').forEach((node) => node.remove());
        if (!recordings.size || !exam) return;
        const make = () => {
            const block = el('section', 'exam-local-recordings');
            block.append(el('h2', '', 'Keep a little record of your progress.'), el('p', 'exam-small-copy', 'These recordings exist only in this tab. Download them before reloading or closing it. They are uploaded to OpenAI only when you choose AI coaching. The audio files are not included in the saved result or JSON download.'));
            const list = el('ul');
            const questions = new Map(items(exam).map((question) => [question.id, question]));
            recordings.forEach((value, id) => { const li = el('li'); li.append(recordingDownload(id, value, `${questions.get(id)?.title || id} · download ↓`)); list.append(li); });
            block.append(list);
            return block;
        };
        $('exam-submission').append(make());
        const heading = document.querySelector('.exam-review-heading');
        heading.before(make());
    }

    function submissionPayload() {
        return {
            exam_id: state.examId, exam_version: state.version, attempt_id: state.attemptId,
            answers: state.answers,
            elapsed_seconds: Math.min(timed() ? budget(exam) : 604800, Math.max(0, Math.round(((state.endedAt || Date.now()) - state.startedAt) / 1000))),
            completed_sections: state.completedSections, assisted: Boolean(state.assisted),
        };
    }

    function showPending(message = 'Your answers are ready. Submitting will reveal your practice feedback.') {
        stopTimer();
        view('submission');
        $('submission-message').textContent = message;
        $('retry-submission').hidden = false;
        $('retry-submission').disabled = inFlight;
        $('download-pending').disabled = !state;
        renderLocalRecordings();
        $('submission-title').focus();
    }

    async function submitAttempt() {
        if (!state || state.phase !== 'submit-ready' || inFlight) return;
        inFlight = true;
        $('retry-submission').disabled = true;
        $('submission-message').textContent = 'Sending your responses for practice feedback… Keep this page open for a moment.';
        saveState();
        const attemptId = state.attemptId;
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 30000);
        try {
            const response = await fetch('api/complete-exam.php', {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify(submissionPayload()),
            });
            const envelope = await response.json();
            if (!response.ok || !envelope.success || !envelope.result) {
                const message = response.status === 409 ? 'This mock has been updated since your attempt began. Download your responses below, then start a new attempt; your old answers cannot be marked against changed questions.' : envelope.error || 'The server could not complete your review. Please try again.';
                const error = new Error(message); error.httpStatus = response.status; throw error;
            }
            if (state?.attemptId !== attemptId) return;
            state.phase = 'results';
            state.resultEnvelope = envelope;
            saveState();
            showResults(envelope);
            announce('Your answer review is ready. Objective points and AI review options are available.');
        } catch (error) {
            if (state?.attemptId === attemptId) {
                accessNotice('submission-message', `${error.name === 'AbortError' ? 'The connection timed out. The server may still have received your attempt; retrying uses the same attempt ID.' : error.message} Your finished responses are retained. Retry submission or download a copy below.`, error.httpStatus);
                $('retry-submission').hidden = false;
            }
        } finally {
            window.clearTimeout(timeout);
            inFlight = false;
            $('retry-submission').disabled = false;
        }
    }

    async function retryAccountSave() {
        if (inFlight || !config.signedIn || !state || !currentEnvelope || currentEnvelope.saved || state.attemptId !== currentEnvelope.result.attempt_id) return;
        inFlight = true;
        $('retry-save-result').disabled = true;
        $('exam-result-save').textContent = 'Retrying account saving. Your review and responses remain available.';
        const attemptId = state.attemptId;
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 30000);
        try {
            const response = await fetch('api/complete-exam.php', {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify(submissionPayload()),
            });
            const envelope = await response.json();
            if (!response.ok || !envelope.success || !envelope.result) throw new Error(envelope.error || 'Account saving is unavailable.');
            if (state?.attemptId !== attemptId) return;
            state.phase = 'results'; state.resultEnvelope = envelope; saveState(); showResults(envelope);
            announce(envelope.saved ? 'Your full mock result is now saved to your account.' : 'Your review is ready, but account saving is still unavailable.');
        } catch (error) {
            $('exam-result-save').textContent = `${error.name === 'AbortError' ? 'Account saving timed out.' : error.message} Your existing review is retained. Download a copy or retry saving later.`;
        } finally {
            window.clearTimeout(timeout); inFlight = false; $('retry-save-result').disabled = false;
        }
    }

    function downloadJSON(value, filename) {
        const url = URL.createObjectURL(new Blob([JSON.stringify(value, null, 2)], { type: 'application/json;charset=utf-8' }));
        const link = document.createElement('a'); link.href = url; link.download = filename;
        document.body.append(link); link.click(); link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1500);
    }

    function showResults(envelope) {
        stopTimer();
        stopAudio();
        stopRecording(false);
        const result = envelope.result;
        if (envelope.mock_access) config.mockAccess = envelope.mock_access;
        if (currentEnvelope?.result.attempt_id !== result.attempt_id) resultAIActivity.clear();
        currentEnvelope = envelope;
        currentReview = result.review || [];
        exam = exams[result.exam_id] || null;
        view('result');
        $('result-catalog').textContent = result.session_type && result.session_type !== 'full' ? '← Practice library' : '← All mock exams';
        $('result-mode').textContent = `${result.session_type === 'practice' ? 'GUIDED PRACTICE' : result.assisted ? 'ASSISTED PRACTICE' : 'EXAM MODE'}${result.incomplete ? ' · INCOMPLETE' : ''}`;
        const score = el('strong', '', result.objective_total ? result.objective_score : 'Writing & speaking');
        if (result.objective_total) score.append(el('span', '', `/${result.objective_total}`));
        $('exam-result-points').closest('.exam-result-score').classList.toggle('is-self-review', !result.objective_total);
        $('exam-result-points').nextElementSibling.textContent = result.objective_total ? 'OBJECTIVE PRACTICE POINTS' : 'WRITING & SPEAKING PRACTICE';
        $('exam-result-points').replaceChildren(...score.childNodes);
        $('exam-result-name').textContent = result.name;
        contentSourceNotice('result-content-source', result);
        $('exam-result-summary').textContent = `${result.answered_items} of ${result.total_items} tasks have a text response · ${formatTime(result.elapsed_seconds)} elapsed. ${result.incomplete ? 'This attempt is incomplete: not all sections were finished. ' : ''}Writing and speaking use separate AI practice estimates and improvement guidance. Open the AI review below to request your score.`;
        $('retry-save-result').hidden = !(config.signedIn && !envelope.saved && state && state.attemptId === result.attempt_id);
        $('exam-result-save').replaceChildren();
        $('exam-result-save').append(el('strong', '', envelope.saved ? 'Your account result is saved.' : 'This result is not saved to an account.'), el('p', '', envelope.save_message || (envelope.saved ? 'Find it again from your dashboard.' : 'Download a copy to keep your review.')));
        if (!config.signedIn) { const link = el('a', 'exam-text-button', 'Create an account for future saved results →'); link.href = 'register.php'; $('exam-result-save').append(link); }
        $('exam-result-sections').replaceChildren(...result.sections.map((part) => {
            const card = el('article'); card.append(el('h2', '', part.title));
            card.dataset.sectionId = part.id;
            const points = el('strong', '', part.total > 0 ? `${part.score} / ${part.total}` : 'AI review pending');
            points.dataset.sectionPoints = 'true'; card.append(points);
            const aiStatus = el('p', 'exam-section-ai-status'); aiStatus.dataset.sectionAiStatus = 'true'; aiStatus.hidden = true; card.append(aiStatus);
            card.append(el('p', '', `${part.answered} of ${part.total_items} text responses${part.ungraded_items ? ` · ${part.ungraded_items} writing/speaking tasks` : ''}`));
            if (!(result.completed_sections || []).includes(part.id)) card.append(el('span', 'exam-incomplete-label', 'Section not completed'));
            return card;
        }));
        // Older saved reports can contain outdated self-review-only wording.
        // Keep their original JSON, while explaining the current review options.
        $('exam-grading-note').textContent = 'Reading/listening points and AI writing/speaking estimates are shown separately. Request an AI review to receive a practice estimate and improvement advice. These are not official IELTS/PTE results.';
        const select = $('review-section'); select.replaceChildren(Object.assign(el('option', '', 'All sections'), { value: '' }));
        result.sections.forEach((part) => select.append(Object.assign(el('option', '', part.title), { value: part.id })));
        renderReview();
        updateResultAIStatus();
        renderLocalRecordings();
        $('clear-result-device').hidden = !state || state.attemptId !== result.attempt_id;
        $('start-new-attempt').hidden = !exam;
        $('result-title').focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resultAIEntry(review) {
        if (!currentEnvelope?.result) return null;
        const result = currentEnvelope.result;
        const candidates = [
            ...(Array.isArray(state?.aiReviews) ? state.aiReviews : []),
            ...(Array.isArray(currentEnvelope.aiReviews) ? currentEnvelope.aiReviews : []),
            ...(Array.isArray(currentEnvelope.ai_reviews) ? currentEnvelope.ai_reviews : []),
        ];
        return candidates.filter((entry) => entry?.review && entry.exam_id === result.exam_id && entry.exam_version === result.exam_version
            && entry.attempt_id === result.attempt_id && entry.item_id === review.id && entry.response === review.response)
            .sort((a, b) => Number(b.id) - Number(a.id))[0] || null;
    }

    function aiResultLabel(review) {
        const entry = resultAIEntry(review);
        if (entry) {
            const score = entry.review.score;
            return score && typeof score.value === 'number' && Number.isFinite(score.value)
                ? `AI ${score.value}${score.unit === '%' ? '%' : ` / ${score.max}`}` : 'AI feedback ready';
        }
        if (resultAIActivity.get(review.id) === 'reviewing') return 'Reviewing with AI…';
        if (resultAIActivity.get(review.id) === 'error') return 'AI review needs retry';
        if (!config.aiReview?.configured) return 'AI setup needed';
        if (!config.signedIn) return 'Sign in for AI review';
        if (!config.aiReview?.transportAvailable) return 'AI review unavailable';
        if (!currentEnvelope.saved && currentEnvelope.result.session_type !== 'practice') return 'Save for AI review';
        return 'AI review available';
    }

    function updateResultAIStatus() {
        if (!currentEnvelope?.result) return;
        const productive = currentReview.filter((review) => ['essay', 'spoken'].includes(review.type));
        const banner = $('result-ai-status'); banner.hidden = !productive.length;
        if (!productive.length) return;
        const reviewed = productive.filter((review) => resultAIEntry(review));
        const waiting = productive.filter((review) => !resultAIEntry(review));
        const reviewing = waiting.some((review) => resultAIActivity.get(review.id) === 'reviewing');
        const count = `${reviewed.length} of ${productive.length} answers have AI feedback.`;
        let status = 'ready'; let heading = 'Your answers are ready for AI scoring.';
        let message = `${count} Open an answer below and choose “Review with AI” for a practice estimate and improvement advice. Submitting this test did not call OpenAI.`;
        if (!waiting.length) { status = 'reviewed'; heading = 'Your AI feedback is ready.'; message = `${count} Open each answer to see its estimate, criterion feedback and improvement plan. Estimates use the scale shown for that task.`; }
        else if (reviewing) { status = 'reviewing'; heading = 'Your AI review is in progress.'; message = `${count} Keep this page open. Your answers are retained while feedback is prepared.`; }
        else if (waiting.some((review) => resultAIActivity.get(review.id) === 'error')) { status = 'error'; heading = 'Your AI review could not finish.'; message = `${count} Your answers are retained. Open the review to see the error and retry; no score has been invented.`; }
        else if (!config.aiReview?.configured) { status = 'setup'; heading = 'AI scoring needs to be enabled.'; message = `${count} This website’s OpenAI API setup is not complete. Your answers are retained. The site owner needs to enable AI reviews${config.signedIn ? '.' : '; you will also need to sign in to request one.'}`; }
        else if (!config.signedIn) { status = 'guest'; heading = 'Sign in to get an AI score.'; message = `${count} This was a guest attempt. Sign in before taking an activity to request and save AI feedback. Download your responses before leaving this page.`; }
        else if (!config.aiReview.transportAvailable) { status = 'unavailable'; heading = 'AI scoring is temporarily unavailable.'; message = `${count} Your answers are retained. The site owner needs to restore the AI connection.`; }
        else if (!currentEnvelope.saved && currentEnvelope.result.session_type !== 'practice') { status = 'save'; heading = 'Save this result to request AI scoring.'; message = `${count} Choose “Retry saving to account”, then open a writing or speaking answer for AI feedback.`; }
        else if (reviewed.length) { status = 'partial'; heading = 'Continue your AI reviews.'; }
        banner.dataset.aiState = status;
        if ($('result-ai-heading').textContent !== heading) $('result-ai-heading').textContent = heading;
        if ($('result-ai-message').textContent !== message) $('result-ai-message').textContent = message;
        $('result-ai-action').textContent = !waiting.length ? 'View AI feedback →' : 'Open AI review →';
        $('result-ai-sign-in').hidden = Boolean(config.signedIn);
        document.querySelectorAll('.exam-review-item[data-review-item]').forEach((details) => {
            const review = productive.find((entry) => entry.id === details.dataset.reviewItem);
            if (review) details.querySelector('.exam-review-points').textContent = aiResultLabel(review);
        });
        document.querySelectorAll('[data-section-id]').forEach((card) => {
            const sectionItems = productive.filter((review) => review.section_id === card.dataset.sectionId);
            if (!sectionItems.length) return;
            const complete = sectionItems.filter((review) => resultAIEntry(review)).length;
            const part = currentEnvelope.result.sections.find((entry) => entry.id === card.dataset.sectionId);
            const summary = complete === sectionItems.length ? 'AI reviewed' : complete ? `${complete} / ${sectionItems.length} AI reviewed` : aiResultLabel(sectionItems[0]);
            if (!part?.total) card.querySelector('[data-section-points]').textContent = summary;
            const note = card.querySelector('[data-section-ai-status]'); note.hidden = false; note.textContent = `${complete} of ${sectionItems.length} AI reviews completed`;
        });
    }

    function openResultAIReview() {
        const productive = currentReview.filter((review) => ['essay', 'spoken'].includes(review.type));
        const target = productive.find((review) => !resultAIEntry(review)) || productive[0];
        if (!target) return;
        let details = [...document.querySelectorAll('.exam-review-item')].find((entry) => entry.dataset.reviewItem === target.id);
        if (!details) { $('review-section').value = ''; renderReview(); details = [...document.querySelectorAll('.exam-review-item')].find((entry) => entry.dataset.reviewItem === target.id); }
        if (!details) return;
        details.open = true;
        const panel = details.querySelector('.ai-review-panel') || details;
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const focus = panel.querySelector('[data-ai-action]:not(:disabled), a') || panel;
        if (!focus.hasAttribute('tabindex') && !focus.matches('button,a')) focus.tabIndex = -1;
        focus.focus({ preventScroll: true });
    }

    function answerText(question, response) {
        if (response === null || response === undefined || response === '' || (Array.isArray(response) && !response.length) || (response && typeof response === 'object' && !Array.isArray(response) && !Object.values(response).some((value) => String(value).trim()))) return 'No text response';
        if (!question) return typeof response === 'string' ? response : JSON.stringify(response, null, 2);
        if (question.type === 'choice' || question.type === 'multi' || question.type === 'reorder') {
            const options = new Map((question.options || []).map((option) => [option.id, option.text]));
            return Array.isArray(response) ? response.map((id, index) => `${question.type === 'reorder' ? index + 1 + '. ' : ''}${options.get(id) || id}`).join('\n') : options.get(response) || response;
        }
        if (question.type === 'highlight' && Array.isArray(response)) {
            const tokens = String(question.text || '').trim().split(/\s+/u);
            return response.map((index) => `Word ${Number(index) + 1}: ${tokens[Number(index)] || index}`).join(', ');
        }
        if (question.type === 'gaps' && typeof response === 'object') return Object.entries(response).map(([key, value]) => `${key}: ${Array.isArray(value) ? value.join(' / ') : value || '(blank)'}`).join('\n');
        return Array.isArray(response) ? response.join(' / ') : String(response);
    }

    function renderReview() {
        const filter = $('review-section').value;
        const questionMap = new Map(exam ? items(exam).map((question) => [question.id, question]) : []);
        $('exam-answer-review').replaceChildren(...currentReview.filter((review) => !filter || review.section_id === filter).map((review) => {
            const question = review.item_snapshot || (review.options || review.text ? review : questionMap.get(review.id));
            const details = el('details', `exam-review-item ${review.graded && review.score < review.max_score ? 'needs-review' : ''}`);
            details.dataset.reviewItem = review.id;
            const summary = el('summary');
            const title = el('span'); title.append(el('small', '', `${review.id} · ${review.task_type}`), el('strong', '', review.prompt));
            summary.append(title, el('span', 'exam-review-points', review.graded ? `${review.score} / ${review.max_score}` : aiResultLabel(review)));
            const body = el('div', 'exam-review-body');
            body.append(el('h3', '', review.type === 'spoken' ? 'Your reflection notes' : 'Your response'), el('p', 'exam-review-response', answerText(question, review.response)));
            if (review.graded) body.append(el('h3', '', 'Accepted answer / correct order'), el('p', 'exam-review-answer', answerText(question, review.correct_answer)));
            if (review.correct_text) body.append(el('h3', '', 'Correct transcript'), el('p', '', review.correct_text));
            body.append(el('p', 'exam-review-explanation', review.graded ? review.explanation : 'Open the AI coach below for a practice estimate and improvement advice. The checklist is also available to help you prepare your next response.'));
            if (Array.isArray(review.rubric) && review.rubric.length) {
                body.append(el('h3', '', 'Your self-review checklist'));
                const list = el('ul'); review.rubric.forEach((point) => list.append(el('li', '', point))); body.append(list);
            }
            if (review.type === 'spoken') body.append(el('p', 'exam-small-copy', 'AI speaking coaching reviews content and language from a transcript. Pronunciation, fluency and delivery are not assessed. A recording is uploaded to OpenAI only if you choose AI review; its audio is not kept with this saved result.'));
            if (['essay', 'spoken'].includes(review.type) && window.NishaAI) body.append(makeAIPanel({ ...(question || review), id: review.id, type: review.type }, review));
            const resources = currentEnvelope?.result.resources || exam?.resources || {};
            const inlinePassage = ['choice', 'multi'].includes(question?.type) ? question.text : null;
            const stimulusIds = [question?.resource_id || review.resource_id, question?.secondary_resource_id || review.secondary_resource_id].filter((id) => resources[id]);
            if (stimulusIds.length || inlinePassage) {
                const source = el('details', 'exam-review-resource');
                source.append(el('summary', '', 'Review the original stimulus'));
                let loaded = false;
                source.addEventListener('toggle', () => {
                    if (!source.open || loaded) return;
                    loaded = true;
                    if (!stimulusIds.length && inlinePassage) source.append(el('p', '', inlinePassage));
                    stimulusIds.forEach((id) => {
                        const resource = resources[id];
                        source.append(el('h3', '', resource.title));
                        if (resource.kind === 'chart') source.append(renderChart(resource.chart, resource.title));
                        else if (resource.kind === 'table') source.append(renderDataTable(resource.columns, resource.rows, resource.title));
                        else if (resource.kind === 'diagram') source.append(renderDiagram(resource.diagram, resource.title));
                        else (resource.paragraphs || [resource.audio_script || resource.text || '']).forEach((paragraph) => source.append(el('p', '', paragraph)));
                    });
                });
                body.append(source);
            }
            details.append(summary, body);
            return details;
        }));
    }

    async function loadSavedResult(id) {
        if (!/^\d+$/u.test(id)) { notice('exam-catalog-message', 'That result reference is not valid.'); return; }
        view('submission');
        $('submission-message').textContent = 'Loading your saved result…';
        $('retry-submission').hidden = true; $('download-pending').disabled = true;
        try {
            const response = await fetch(`api/exam-result.php?id=${encodeURIComponent(id)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const envelope = await response.json();
            if (!response.ok || !envelope.success || !envelope.result) throw new Error(envelope.error || 'This result could not be loaded.');
            showResults(envelope);
        } catch (error) {
            view('catalog');
            notice('exam-catalog-message', `${error.message} Sign in to the account that completed this attempt and open it from your dashboard.`);
        }
    }

    function goCatalog() {
        stopTimer();
        stopAudio();
        stopRecording(false);
        saveState();
        const sessionType = exam?.session_type || currentEnvelope?.result.session_type;
        const courseId = exam?.course_id || currentEnvelope?.result.course_id;
        if (sessionType && sessionType !== 'full') { window.location.href = `practice.php?course=${encodeURIComponent(courseId || 'ielts-academic')}`; return; }
        renderCatalog();
        view('catalog');
        $('catalog-title').focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    $('check-practice-answer').addEventListener('click', checkPracticeAnswer);
    $('close-setup').addEventListener('click', () => $('exam-setup-dialog').close());
    $('exam-setup-dialog').addEventListener('close', stopAudio);
    for (const id of ['audio-confirmed', 'assisted-mode', 'ready-confirmed']) $(id).addEventListener('change', updateSetupButton);
    $('begin-exam').addEventListener('click', begin);
    $('test-exam-audio').addEventListener('click', () => {
        if (!speechAvailable()) return;
        stopAudio();
        const utterance = englishUtterance('Welcome to NISHA. A little practice, a world of possibilities.');
        $('equipment-status').textContent = 'Playing a sample. If you hear it clearly, tick the audio confirmation below.';
        utterance.addEventListener('start', () => { $('audio-confirmed').disabled = false; });
        utterance.addEventListener('error', (event) => {
            if (!['canceled', 'interrupted'].includes(event.error)) {
                $('equipment-status').textContent = 'The browser could not play speech audio. Choose assisted mode for transcripts, or try a browser with an installed English speech voice.';
                $('audio-confirmed').checked = false; updateSetupButton();
            }
        });
        window.speechSynthesis.speak(utterance);
    });
    $('test-exam-mic').addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getUserMedia) { $('equipment-status').textContent = 'Microphone access is unavailable. Use HTTPS and a current browser, or practise aloud without recording.'; return; }
        $('test-exam-mic').disabled = true;
        $('equipment-status').textContent = 'Waiting for optional microphone permission…';
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            stream.getTracks().forEach((track) => track.stop());
            $('equipment-status').textContent = 'Microphone available. It has been released. You can choose to record during speaking tasks.';
        } catch (error) {
            $('equipment-status').textContent = 'The microphone is unavailable or permission was declined. You can still practise aloud; recording is optional.';
        } finally { $('test-exam-mic').disabled = false; }
    });
    $('runner-catalog').addEventListener('click', goCatalog);
    $('pending-catalog').addEventListener('click', goCatalog);
    $('result-catalog').addEventListener('click', goCatalog);
    $('previous-item').addEventListener('click', () => { if (state?.phase === 'running' && section().navigation !== 'forward' && state.itemIndex > 0) navigate(state.itemIndex - 1); });
    $('next-item').addEventListener('click', nextItem);
    $('finish-section').addEventListener('click', finishSection);
    $('submit-exam-early').addEventListener('click', submitEarly);
    $('flag-item').addEventListener('click', () => {
        const id = item().id;
        state.flags = state.flags.includes(id) ? state.flags.filter((flag) => flag !== id) : [...state.flags, id];
        saveState(); renderFlag(); renderQuestionMap();
    });
    $('enable-assistance').addEventListener('click', async () => {
        if (await confirm('Switch to assisted practice?', 'Assisted mode reveals audio transcripts and enables replay. Your result will be labelled assisted. Existing section and response timers continue without restarting.', 'Use assisted mode')) {
            if (!state || state.phase !== 'running') return;
            state.assisted = true; saveState(); renderRunner();
            announce('Assisted mode enabled. Audio transcripts and replays are available.');
        }
    });
    $('retry-submission').addEventListener('click', submitAttempt);
    $('download-pending').addEventListener('click', () => { if (state) downloadJSON(submissionPayload(), `nisha-${state.examId}-responses.json`); });
    $('download-exam-result').addEventListener('click', () => { if (currentEnvelope) downloadJSON(currentEnvelope, `nisha-${currentEnvelope.result.exam_id}-result.json`); });
    $('print-exam-result').addEventListener('click', () => window.print());
    $('retry-save-result').addEventListener('click', retryAccountSave);
    $('review-section').addEventListener('change', renderReview);
    $('result-ai-action').addEventListener('click', openResultAIReview);
    $('start-new-attempt').addEventListener('click', async () => {
        if (!exam) return;
        if (await confirm('Make space for a fresh attempt?', 'A new attempt replaces this mock’s saved device copy and removes recordings from this tab. Download your result and recordings first. Account results remain saved.', 'Prepare new attempt', 'Keep result')) {
            try { localStorage.removeItem(storageKey(exam)); } catch (error) { /* A new memory attempt remains possible. */ }
            stopRecording(true); clearRecordings(); state = null;
            openSetup(exam.id);
        }
    });
    $('clear-result-device').addEventListener('click', async () => {
        if (!exam || !state) return;
        if (await confirm('Clear this device copy?', 'This removes saved responses and recordings from this device/tab. Download what you need first. Account results remain in your dashboard.', 'Clear device copy', 'Keep copy')) {
            try { localStorage.removeItem(storageKey(exam)); } catch (error) { /* Clear the memory copy too. */ }
            stopRecording(true); clearRecordings(); state = null;
            $('clear-result-device').hidden = true;
            announce('Device copy cleared. The displayed review remains available until you leave this page.');
        }
    });
    window.addEventListener('pagehide', () => { saveState(); stopTimer(); stopAudio(); stopRecording(true); });
    window.addEventListener('pageshow', (event) => { if (event.persisted && state?.phase === 'running' && !$('exam-runner').hidden) startTimer(); });
    window.addEventListener('storage', (event) => {
        if (exam && state && event.key === storageKey(exam) && event.newValue) {
            try {
                const other = JSON.parse(event.newValue);
                if (other.writer !== writer && other.updatedAt > state.updatedAt) notice('runner-notice', 'This mock was updated in another tab. Use one tab at a time to avoid overwriting responses. Reload to restore the latest device copy.');
            } catch (error) { /* Ignore unrelated invalid storage events. */ }
        }
    });

    renderCatalog();
    const query = new URLSearchParams(window.location.search);
    if (config.requestedId && !exams[config.requestedId] && !['ielts', 'pte'].includes(config.requestedId.toLowerCase())) notice('exam-catalog-message', 'This practice session is unavailable. Choose a question type from the practice library.');
    if (query.has('result')) loadSavedResult(query.get('result'));
    else {
        const requested = query.get('exam') || '';
        const target = exams[requested] || Object.values(exams).find((entry) => entry.exam.toLowerCase() === requested.toLowerCase());
        if (target) {
            const card = [...document.querySelectorAll('[data-exam-id]')].find((node) => node.dataset.examId === target.id);
            card?.classList.add('is-recommended');
            if (query.get('start') === '1') loadState(target) ? resume(target.id) : openSetup(target.id);
        }
    }
})();
