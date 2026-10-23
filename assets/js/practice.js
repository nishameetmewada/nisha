(() => {
    'use strict';

    const dataElement = document.getElementById('practice-data');
    if (!dataElement) return;
    const data = JSON.parse(dataElement.textContent);
    const $ = (id) => document.getElementById(id);
    const state = {
        exam: 'IELTS', skill: 'Reading', module: null, question: 0, answers: [],
        startedAt: 0, deadline: 0, timer: null, active: false, finished: false,
        resultVersion: 0, confirmPending: false,
    };
    let mediaRecorder = null;
    let mediaStream = null;
    let recordingUrl = null;
    let recordingVersion = 0;
    let isRequestingMicrophone = false;

    function announce(message) {
        $('practice-announcer').textContent = message;
    }

    function formatTime(seconds) {
        const safe = Math.max(0, Math.floor(seconds));
        return `${String(Math.floor(safe / 60)).padStart(2, '0')}:${String(safe % 60).padStart(2, '0')}`;
    }

    function stopSpeech() {
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        $('play-audio').innerHTML = '<span aria-hidden="true">▶</span>';
        $('play-audio').setAttribute('aria-label', 'Play listening passage');
    }

    function stopTimer() {
        if (state.timer !== null) window.clearInterval(state.timer);
        state.timer = null;
    }

    function changeView(view) {
        for (const name of ['setup', 'session', 'results']) $('practice-' + name).hidden = name !== view;
    }

    function choose(exam, skill) {
        state.exam = exam;
        state.skill = skill;
        const module = data.modules[exam][skill];
        document.querySelectorAll('[data-exam]').forEach((button) => {
            const selected = button.dataset.exam === exam;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        document.querySelectorAll('[data-skill]').forEach((button) => {
            const selected = button.dataset.skill === skill;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        $('preview-title').textContent = module.title;
        $('preview-description').textContent = module.description;
        $('preview-duration').textContent = module.guidedUrl ? 'Untimed practice' : `${module.duration / 60} min`;
        $('guided-practice-notice').hidden = !module.guidedUrl;
        $('start-practice').textContent = module.guidedUrl ? 'Open guided practice →' : 'Let’s practise →';
    }

    function updateTimer() {
        const remaining = Math.max(0, Math.ceil((state.deadline - Date.now()) / 1000));
        $('practice-timer').textContent = formatTime(remaining);
        $('practice-timer').closest('.session-timer').classList.toggle('timer-low', remaining <= 60);
        if (remaining <= 0) {
            stopTimer();
            if (state.module.questions) {
                finishQuiz(true);
            } else {
                stopRecording(false);
                $('creative-completion').textContent = 'Time is up. You can still review and download your work, then finish when you are ready.';
                announce('The practice timer has finished. You can still review your work.');
            }
        }
    }

    function startPractice() {
        const selected = data.modules[state.exam][state.skill];
        if (selected.guidedUrl) {
            window.location.assign(selected.guidedUrl);
            return;
        }
        stopTimer();
        stopSpeech();
        stopRecording(true);
        clearRecording();
        state.module = data.modules[state.exam][state.skill];
        state.answers = state.module.questions ? Array(state.module.questions.length).fill(null) : [];
        state.question = 0;
        state.startedAt = Date.now();
        state.deadline = state.startedAt + state.module.duration * 1000;
        state.active = true;
        state.finished = false;
        state.resultVersion++;
        $('session-tag').textContent = `${state.exam} · ${state.skill}`;
        $('session-title').textContent = state.module.title;
        $('objective-workspace').hidden = !state.module.questions;
        $('creative-workspace').hidden = Boolean(state.module.questions);
        $('quiz-progress').hidden = !state.module.questions;
        $('creative-completion').textContent = '';
        $('finish-creative').disabled = false;
        if (state.module.questions) {
            prepareObjective();
        } else {
            prepareCreative();
        }
        changeView('session');
        updateTimer();
        state.timer = window.setInterval(updateTimer, 1000);
        $('session-title').focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
        announce(`${state.exam} ${state.skill} practice started. ${state.module.duration / 60} minutes available.`);
    }

    function prepareObjective() {
        const listening = state.skill === 'Listening';
        $('resource-title').textContent = state.module.resourceTitle;
        $('resource-label-text').textContent = listening ? 'LISTENING PASSAGE' : 'READING PASSAGE';
        $('listening-controls').hidden = !listening;
        $('resource-passage').replaceChildren(...state.module.paragraphs.map((paragraph) => {
            const p = document.createElement('p');
            p.textContent = paragraph;
            return p;
        }));
        $('resource-passage').hidden = listening;
        $('toggle-transcript').setAttribute('aria-expanded', 'false');
        $('toggle-transcript').innerHTML = 'Show transcript <span aria-hidden="true">↓</span>';
        const audioSupported = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
        $('play-audio').disabled = !audioSupported;
        $('audio-status').textContent = audioSupported ? 'Browser-generated audio · replay any time' : 'Audio is unavailable here. Use the transcript below.';
        if (listening && !audioSupported) {
            $('resource-passage').hidden = false;
            $('toggle-transcript').setAttribute('aria-expanded', 'true');
            $('toggle-transcript').innerHTML = 'Hide transcript <span aria-hidden="true">↑</span>';
        }
        renderQuestion();
    }

    function updateProgress() {
        const answered = state.answers.filter((answer) => answer !== null).length;
        const total = state.module.questions.length;
        $('question-progress-text').textContent = `Question ${state.question + 1} of ${total}`;
        $('answered-progress-text').textContent = `${answered} of ${total} answered`;
        $('answer-progress').max = total;
        $('answer-progress').value = answered;
        const buttons = state.module.questions.map((question, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = index + 1;
            const selected = index === state.question;
            const hasAnswer = state.answers[index] !== null;
            button.className = `${selected ? 'is-current ' : ''}${hasAnswer ? 'is-answered' : ''}`;
            button.setAttribute('aria-label', `Question ${index + 1}, ${hasAnswer ? 'answered' : 'unanswered'}`);
            if (selected) button.setAttribute('aria-current', 'step');
            button.addEventListener('click', () => {
                state.question = index;
                renderQuestion(true);
            });
            return button;
        });
        $('question-jump').replaceChildren(...buttons);
    }

    function renderQuestion(focus = false) {
        const question = state.module.questions[state.question];
        $('question-number').textContent = `QUESTION ${String(state.question + 1).padStart(2, '0')}`;
        $('question-prompt').textContent = question.prompt;
        $('question-prompt').tabIndex = -1;
        $('question-options').replaceChildren(...question.options.map((text, index) => {
            const label = document.createElement('label');
            label.className = 'question-option';
            label.classList.toggle('is-selected', state.answers[state.question] === index);
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'practice-answer';
            input.value = index;
            input.checked = state.answers[state.question] === index;
            const letter = document.createElement('span');
            letter.className = 'option-letter';
            letter.textContent = String.fromCharCode(65 + index);
            letter.setAttribute('aria-hidden', 'true');
            const content = document.createElement('span');
            content.textContent = text;
            input.addEventListener('change', () => {
                state.answers[state.question] = index;
                $('question-options').querySelectorAll('.question-option').forEach((option) => {
                    option.classList.toggle('is-selected', option.querySelector('input').checked);
                });
                updateProgress();
            });
            label.append(input, letter, content);
            return label;
        }));
        $('previous-question').disabled = state.question === 0;
        $('next-question').textContent = state.question === state.module.questions.length - 1 ? 'Finish & review →' : 'Next question →';
        updateProgress();
        if (focus) $('question-prompt').focus({ preventScroll: true });
    }

    async function confirmAction(title, description, action) {
        if (state.confirmPending) return false;
        state.confirmPending = true;
        const dialog = $('practice-confirm-dialog');
        $('confirm-title').textContent = title;
        $('confirm-description').textContent = description;
        $('confirm-action').textContent = action;
        let confirmed = false;
        if (typeof dialog.showModal === 'function') {
            dialog.returnValue = 'cancel';
            confirmed = await new Promise((resolve) => {
                dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true });
                dialog.showModal();
            });
        } else {
            confirmed = window.confirm(`${title}\n\n${description}`);
        }
        state.confirmPending = false;
        return confirmed;
    }

    async function requestFinish() {
        if (!state.active || state.finished) return;
        const unanswered = state.answers.filter((answer) => answer === null).length;
        const description = unanswered ? `You have ${unanswered} unanswered question${unanswered === 1 ? '' : 's'}. You can return to them, or finish now. Unanswered questions count as incorrect.` : 'You have answered every question. Finish to see your result and explanations.';
        if (await confirmAction('Ready to review?', description, 'Finish & review')) finishQuiz(false);
    }

    function finishQuiz(timedOut) {
        if (!state.active || state.finished) return;
        state.finished = true;
        state.active = false;
        if ($('practice-confirm-dialog').open) $('practice-confirm-dialog').close('cancel');
        stopTimer();
        stopSpeech();
        const total = state.module.questions.length;
        const score = state.module.questions.reduce((sum, question, index) => sum + Number(state.answers[index] === question.answer), 0);
        const duration = Math.min(state.module.duration, Math.max(1, Math.round((Date.now() - state.startedAt) / 1000)));
        const percent = Math.round(score / total * 100);
        $('result-score').replaceChildren(document.createTextNode(score), Object.assign(document.createElement('span'), { textContent: `/${total}` }));
        $('results-tag').textContent = `${state.exam} · ${state.skill}`;
        $('results-title').textContent = score === total ? 'Look at you making progress.' : score >= total / 2 ? 'You’re building something good.' : 'Every attempt teaches you something.';
        $('result-description').textContent = `${timedOut ? 'Time is up. ' : ''}You got ${score} of ${total} questions right. ${score === total ? 'A lovely result. Read the explanations to reinforce what you learned.' : 'Take a moment with the explanations below. That’s where your next improvement begins.'}`;
        $('result-percent').textContent = `${percent}% correct`;
        $('result-duration').textContent = `${formatTime(duration)} spent practising`;
        $('answer-review').replaceChildren(...state.module.questions.map((question, index) => createReview(question, index)));
        const version = state.resultVersion;
        if (data.signedIn) {
            $('save-result-status').textContent = 'Saving your result to your dashboard…';
            saveAttempt({ exam: state.exam, skill: state.skill, score, total, duration_seconds: duration }, version);
        } else {
            $('save-result-status').textContent = 'This guest result is not saved. Create a free account to save future practice results.';
        }
        changeView('results');
        $('results-title').focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
        announce(`Practice complete. ${score} of ${total} answers correct. Explanations are available below.`);
    }

    function createReview(question, index) {
        const selected = state.answers[index];
        const correct = selected === question.answer;
        const details = document.createElement('details');
        details.className = `review-item ${correct ? 'review-correct' : 'review-incorrect'}`;
        if (!correct) details.open = true;
        const summary = document.createElement('summary');
        const number = document.createElement('span');
        number.className = 'review-number';
        number.textContent = String(index + 1).padStart(2, '0');
        const title = document.createElement('span');
        title.textContent = question.prompt;
        const badge = document.createElement('span');
        badge.className = 'review-badge';
        badge.textContent = correct ? '✓ Correct' : selected === null ? '— Unanswered' : '→ Review';
        summary.append(number, title, badge);
        const body = document.createElement('div');
        body.className = 'review-body';
        const yourAnswer = document.createElement('p');
        const yourLabel = document.createElement('strong');
        yourLabel.textContent = 'Your answer: ';
        yourAnswer.append(yourLabel, document.createTextNode(selected === null ? 'Not answered' : question.options[selected]));
        const rightAnswer = document.createElement('p');
        const rightLabel = document.createElement('strong');
        rightLabel.textContent = 'Correct answer: ';
        rightAnswer.append(rightLabel, document.createTextNode(question.options[question.answer]));
        const explanation = document.createElement('p');
        explanation.className = 'review-explanation';
        explanation.textContent = question.explanation;
        body.append(yourAnswer, rightAnswer, explanation);
        details.append(summary, body);
        return details;
    }

    async function saveAttempt(attempt, version) {
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch('api/save-attempt.php', {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify(attempt),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error('Result not saved');
            if (version === state.resultVersion) $('save-result-status').textContent = 'Your result is saved. Find it in your dashboard whenever you need it.';
        } catch (error) {
            if (version === state.resultVersion) $('save-result-status').textContent = 'Your result could not be saved. You can still review it here. Check your connection and sign-in before your next session.';
        } finally {
            window.clearTimeout(timeout);
        }
    }

    function prepareCreative() {
        const writing = state.skill === 'Writing';
        $('creative-label').textContent = writing ? 'YOUR WRITING PROMPT' : 'YOUR SPEAKING PROMPT';
        $('creative-prompt').textContent = state.module.prompt;
        $('creative-instructions').textContent = state.module.instructions;
        $('speaking-cues').hidden = writing;
        $('speaking-cues').replaceChildren(...(state.module.cues || []).map((cue) => Object.assign(document.createElement('li'), { textContent: cue })));
        $('creative-checklist').replaceChildren(...state.module.checklist.map((text) => {
            const li = document.createElement('li');
            const label = document.createElement('label');
            const input = document.createElement('input');
            input.type = 'checkbox';
            label.append(input, document.createTextNode(text));
            li.append(label);
            return li;
        }));
        $('writing-tools').hidden = !writing;
        $('speaking-tools').hidden = writing;
        $('writing-answer').value = '';
        $('word-target').textContent = state.exam === 'IELTS' ? 'Aim for at least 250 words' : 'Aim for 200–300 words';
        updateWordCount();
        $('record-status').textContent = 'Your microphone is only used when you choose to record.';
        $('record-speaking').disabled = false;
        if (!writing && (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder)) {
            $('record-speaking').disabled = true;
            $('record-status').textContent = 'Recording is unavailable in this browser. You can still practise aloud. Try a current browser on HTTPS or localhost to record.';
        }
    }

    function updateWordCount() {
        const trimmed = $('writing-answer').value.trim();
        const words = trimmed ? trimmed.split(/\s+/u).length : 0;
        $('word-count').textContent = `${words} ${words === 1 ? 'word' : 'words'}`;
        $('word-count').classList.toggle('word-target-met', Boolean(state.module?.targetWords && words >= state.module.targetWords && (state.exam !== 'PTE' || words <= 300)));
        $('download-writing').disabled = words === 0;
    }

    function downloadWriting() {
        if (!$('writing-answer').value.trim()) return;
        const text = `NISHA · ${state.exam} writing practice\n\nPrompt: ${state.module.prompt}\n\n${$('writing-answer').value}\n\nSelf-guided practice. No official score.\n`;
        const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `nisha-${state.exam.toLowerCase()}-writing.txt`;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    function clearRecording() {
        if (recordingUrl) URL.revokeObjectURL(recordingUrl);
        recordingUrl = null;
        $('speaking-playback').pause();
        $('speaking-playback').removeAttribute('src');
        $('speaking-playback').hidden = true;
        $('recording-actions').hidden = true;
        $('download-recording').removeAttribute('href');
    }

    function stopRecording(discard) {
        if (discard) {
            recordingVersion++;
            isRequestingMicrophone = false;
        }
        if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
        if (mediaStream) mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
        $('record-speaking').innerHTML = 'Start recording <span aria-hidden="true">●</span>';
        $('record-speaking').disabled = false;
        document.querySelector('.recorder-visual').classList.remove('is-recording');
    }

    async function toggleRecording() {
        if (isRequestingMicrophone) return;
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            stopRecording(false);
            return;
        }
        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) return;
        const version = ++recordingVersion;
        isRequestingMicrophone = true;
        $('record-speaking').disabled = true;
        $('record-status').textContent = 'Waiting for microphone permission…';
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            if (version !== recordingVersion || !state.active || state.skill !== 'Speaking') {
                stream.getTracks().forEach((track) => track.stop());
                return;
            }
            mediaStream = stream;
            mediaRecorder = new MediaRecorder(stream);
            const recorder = mediaRecorder;
            const chunks = [];
            recorder.addEventListener('dataavailable', (event) => {
                if (event.data.size > 0) chunks.push(event.data);
            });
            recorder.addEventListener('stop', () => {
                stream.getTracks().forEach((track) => track.stop());
                if (version !== recordingVersion) return;
                clearRecording();
                if (chunks.length === 0) {
                    $('record-status').textContent = 'No audio was captured. You can try recording again.';
                    return;
                }
                const type = recorder.mimeType || chunks[0].type || 'audio/webm';
                recordingUrl = URL.createObjectURL(new Blob(chunks, { type }));
                $('speaking-playback').src = recordingUrl;
                $('speaking-playback').hidden = false;
                $('download-recording').href = recordingUrl;
                $('download-recording').download = `nisha-speaking-practice.${type.includes('mp4') ? 'm4a' : type.includes('ogg') ? 'ogg' : 'webm'}`;
                $('recording-actions').hidden = false;
                $('record-status').textContent = 'Recording ready. Listen back, reflect, or download a copy.';
            });
            recorder.addEventListener('error', () => {
                stopRecording(true);
                $('record-status').textContent = 'Recording stopped because of a browser error. Your microphone has been released. Please try again.';
            });
            clearRecording();
            recorder.start();
            $('record-speaking').innerHTML = 'Stop recording <span aria-hidden="true">■</span>';
            $('record-status').textContent = 'Recording now. Select “Stop recording” when you are finished.';
            document.querySelector('.recorder-visual').classList.add('is-recording');
        } catch (error) {
            if (mediaStream) mediaStream.getTracks().forEach((track) => track.stop());
            mediaStream = null;
            if (version === recordingVersion) $('record-status').textContent = error.name === 'NotAllowedError' ? 'Microphone permission was not granted. You can practise aloud or enable microphone access in your browser settings.' : 'We could not access your microphone. Check that one is connected and available, then try again.';
        } finally {
            if (version === recordingVersion) {
                isRequestingMicrophone = false;
                $('record-speaking').disabled = false;
            }
        }
    }

    async function returnToSetup() {
        if (state.active) {
            const creative = !state.module.questions;
            const message = creative ? 'Your text or recording stays only in this page. Download a copy before leaving if you want to keep it.' : 'This session will end and your current answers will be cleared.';
            if (!await confirmAction('Leave this session?', message, 'Leave session')) return;
        }
        state.active = false;
        stopTimer();
        stopSpeech();
        stopRecording(true);
        clearRecording();
        changeView('setup');
        $('practice-title').tabIndex = -1;
        $('practice-title').focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    document.querySelectorAll('[data-exam]').forEach((button) => button.addEventListener('click', () => choose(button.dataset.exam, state.skill)));
    document.querySelectorAll('[data-skill]').forEach((button) => button.addEventListener('click', () => choose(state.exam, button.dataset.skill)));
    $('start-practice').addEventListener('click', startPractice);
    $('retry-practice').addEventListener('click', startPractice);
    $('leave-session').addEventListener('click', returnToSetup);
    $('results-all-practice').addEventListener('click', returnToSetup);
    $('finish-quiz').addEventListener('click', requestFinish);
    $('previous-question').addEventListener('click', () => {
        if (state.question > 0) { state.question--; renderQuestion(true); }
    });
    $('next-question').addEventListener('click', () => {
        if (state.question < state.module.questions.length - 1) { state.question++; renderQuestion(true); }
        else requestFinish();
    });
    $('toggle-transcript').addEventListener('click', () => {
        const show = $('resource-passage').hidden;
        $('resource-passage').hidden = !show;
        $('toggle-transcript').setAttribute('aria-expanded', String(show));
        $('toggle-transcript').innerHTML = show ? 'Hide transcript <span aria-hidden="true">↑</span>' : 'Show transcript <span aria-hidden="true">↓</span>';
    });
    $('play-audio').addEventListener('click', () => {
        if (!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) return;
        stopSpeech();
        const utterance = new SpeechSynthesisUtterance(state.module.paragraphs.join('\n\n'));
        utterance.lang = 'en-GB';
        utterance.rate = 0.92;
        const voice = window.speechSynthesis.getVoices().find((candidate) => candidate.lang === 'en-GB') || window.speechSynthesis.getVoices().find((candidate) => candidate.lang.startsWith('en'));
        if (voice) utterance.voice = voice;
        $('audio-status').textContent = 'Playing passage · select Stop to end playback';
        utterance.addEventListener('end', () => { $('audio-status').textContent = 'Passage complete · replay any time'; });
        utterance.addEventListener('error', (event) => {
            if (event.error !== 'canceled' && event.error !== 'interrupted') $('audio-status').textContent = 'Audio could not play. Use “Show transcript” to continue.';
        });
        window.speechSynthesis.speak(utterance);
    });
    $('stop-audio').addEventListener('click', () => { stopSpeech(); $('audio-status').textContent = 'Playback stopped · replay any time'; });
    $('writing-answer').addEventListener('input', updateWordCount);
    $('download-writing').addEventListener('click', downloadWriting);
    $('record-speaking').addEventListener('click', toggleRecording);
    $('delete-recording').addEventListener('click', () => { clearRecording(); $('record-status').textContent = 'Recording deleted. You can record another response.'; });
    $('finish-creative').addEventListener('click', () => {
        stopTimer();
        stopRecording(isRequestingMicrophone);
        state.active = false;
        state.finished = true;
        $('finish-creative').disabled = true;
        if (state.skill === 'Speaking') $('record-speaking').disabled = true;
        $('creative-completion').textContent = 'Session complete. Well done for showing up. Use the checklist to reflect, and download your work before leaving. This self-guided session has no score and is not saved to your dashboard.';
        announce('Session complete. Reflect using the checklist and download your work before leaving.');
    });
    window.addEventListener('pagehide', () => { stopTimer(); stopSpeech(); stopRecording(true); });

    const query = new URLSearchParams(window.location.search);
    const requestedExam = (query.get('exam') || '').toUpperCase();
    const requestedSkill = Object.keys(data.modules.IELTS).find((skill) => skill.toLowerCase() === (query.get('skill') || '').toLowerCase());
    choose(Object.hasOwn(data.modules, requestedExam) ? requestedExam : 'IELTS', requestedSkill || 'Reading');
    if (query.get('start') === '1') startPractice();
})();
