(() => {
    'use strict';
    const panels = new WeakMap();
    const blobKeys = new WeakMap();
    const wavCache = new WeakMap();
    const reviewError = (message, details = {}) => Object.assign(new Error(message), { userMessage: message, ...details });
    const sizeText = (bytes) => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(2)} MB` : `${Math.ceil(bytes / 1024)} KB`;
    async function boundedWav(blob) {
        if (wavCache.has(blob)) return wavCache.get(blob);
        const conversion = (async () => {
            if (blob.size < 100 || blob.size > 10 * 1024 * 1024) throw reviewError('Choose a recording between 100 bytes and 10 MB, with no more than 5 minutes of audio.');
            const Context = window.AudioContext || window.webkitAudioContext;
            const Offline = window.OfflineAudioContext || window.webkitOfflineAudioContext;
            if (!Context || !Offline) throw reviewError('This browser cannot prepare audio for AI review. Use a supported browser or enter a transcript of what you said.');
            let context;
            let decoded;
            try { context = new Context(); decoded = await context.decodeAudioData(await blob.arrayBuffer()); }
            catch (error) { throw reviewError('This browser could not decode the recording. Choose a compatible WAV, MP3, M4A or WebM clip, or enter a transcript.'); }
            finally { if (context) await context.close().catch(() => {}); }
            if (!Number.isFinite(decoded.duration) || decoded.duration < 0.5 || decoded.duration > 300) throw reviewError('AI review accepts recordings from half a second up to 5 minutes. Use a shorter clip or enter its transcript.');
            const length = Math.ceil(decoded.duration * 16000);
            const offline = new Offline(1, length, 16000);
            const source = offline.createBufferSource(); source.buffer = decoded; source.connect(offline.destination); source.start();
            const rendered = await offline.startRendering();
            const samples = rendered.getChannelData(0);
            const bytes = new ArrayBuffer(44 + samples.length * 2);
            const view = new DataView(bytes);
            const word = (offset, text) => [...text].forEach((letter, index) => view.setUint8(offset + index, letter.charCodeAt(0)));
            word(0, 'RIFF'); view.setUint32(4, 36 + samples.length * 2, true); word(8, 'WAVE'); word(12, 'fmt ');
            view.setUint32(16, 16, true); view.setUint16(20, 1, true); view.setUint16(22, 1, true); view.setUint32(24, 16000, true);
            view.setUint32(28, 32000, true); view.setUint16(32, 2, true); view.setUint16(34, 16, true); word(36, 'data'); view.setUint32(40, samples.length * 2, true);
            for (let index = 0; index < samples.length; index++) { const sample = Math.max(-1, Math.min(1, samples[index])); view.setInt16(44 + index * 2, sample < 0 ? sample * 32768 : sample * 32767, true); }
            return new Blob([bytes], { type: 'audio/wav' });
        })();
        wavCache.set(blob, conversion);
        try { return await conversion; } catch (error) { wavCache.delete(blob); throw error; }
    }
    const node = (tag, className, text) => {
        const value = document.createElement(tag);
        if (className) value.className = className;
        if (text !== undefined) value.textContent = text;
        return value;
    };
    const requestId = () => window.crypto?.randomUUID?.() || `ai-${Date.now()}-${Math.random().toString(36).slice(2, 18)}`;
    const scoreText = (score, max, unit) => score === null || score === undefined ? 'Not assessable' : unit === 'band' ? `${score} / ${max} bands` : `${score}%`;

    function renderFeedback(entry) {
        const review = entry.review;
        const fragment = document.createDocumentFragment();
        const heading = node('div', 'ai-review-score');
        const score = review.score || {};
        heading.append(node('span', 'ai-review-kicker', score.label || 'AI practice review'), node('strong', '', scoreText(score.value, score.max, score.unit)), node('span', 'ai-review-estimate', 'AI practice estimate · unofficial'));
        fragment.append(heading);
        if (typeof review.transcript === 'string') fragment.append(node('p', 'ai-review-source-note', 'Saved review of the transcript shown below. This feedback does not assess any new recording or transcript you select.'));
        fragment.append(node('p', 'ai-review-summary', review.summary));
        if (Array.isArray(review.criteria)) {
            const list = node('div', 'ai-review-criteria');
            review.criteria.forEach((criterion) => {
                const part = node('section', 'ai-review-criterion');
                const title = node('div', 'ai-review-criterion-title');
                title.append(node('h4', '', criterion.label), node('strong', '', scoreText(criterion.score, criterion.max, score.unit)));
                part.append(title, node('p', '', criterion.feedback));
                list.append(part);
            });
            fragment.append(list);
        }
        const stringList = (title, values, ordered = false) => {
            if (!Array.isArray(values) || !values.length) return;
            const block = node('section', 'ai-review-guidance');
            const list = node(ordered ? 'ol' : 'ul');
            values.forEach((value) => list.append(node('li', '', value)));
            block.append(node('h4', '', title), list); fragment.append(block);
        };
        stringList('What you did well', review.strengths);
        if (Array.isArray(review.improvements) && review.improvements.length) {
            const block = node('section', 'ai-review-guidance'); block.append(node('h4', '', 'How to improve this response'));
            const list = node('ol', 'ai-review-improvements');
            review.improvements.forEach((improvement) => {
                const item = node('li'); item.append(node('strong', '', improvement.issue));
                if (improvement.evidence) item.append(node('blockquote', '', improvement.evidence));
                item.append(node('p', '', improvement.suggestion));
                if (improvement.example) item.append(node('p', 'ai-review-example', `Try: ${improvement.example}`));
                list.append(item);
            });
            block.append(list); fragment.append(block);
        }
        stringList('Your next practice steps', review.next_steps, true);
        if (review.improved_sample) {
            const details = node('details', 'ai-review-sample');
            details.append(node('summary', '', 'Read a learning example'), node('p', '', review.improved_sample)); fragment.append(details);
        }
        if (typeof review.transcript === 'string') {
            const details = node('details', 'ai-review-transcript-result');
            details.append(node('summary', '', 'Check the transcript reviewed'), node('p', '', review.transcript)); fragment.append(details);
        }
        stringList('About this feedback', review.limitations);
        const date = typeof review.reviewed_at === 'string' ? new Date(review.reviewed_at) : null;
        fragment.append(node('p', 'ai-review-meta', `${Number.isFinite(review.word_count) ? review.word_count + ' words reviewed · ' : ''}${review.model || 'OpenAI'}${date && Number.isFinite(date.getTime()) ? ' · ' + date.toLocaleString() : ''}`));
        return fragment;
    }

    function mount(options) {
        const panel = node('section', 'ai-review-panel');
        panel.dataset.aiItem = options.item.id;
        panel.setAttribute('aria-label', 'AI writing and speaking coach');
        const draft = options.draft || {};
        const audioLimit = Number.isFinite(options.config?.maxAudioBytes) && options.config.maxAudioBytes >= 0 ? Math.min(options.config.maxAudioBytes, 10 * 1024 * 1024) : 10 * 1024 * 1024;
        const initialEntry = [...(options.entries || [])].reverse().find((entry) => entry?.review && entry.exam_id === options.examId && entry.exam_version === options.examVersion && entry.attempt_id === options.attemptId && entry.item_id === options.item.id && entry.response === options.getResponse());
        if (typeof draft.transcript !== 'string') draft.transcript = initialEntry?.review.mode === 'speaking-transcript' ? initialEntry.review.transcript || '' : '';
        if (!draft.sourceMode && initialEntry?.review.mode === 'speaking-audio') draft.sourceMode = 'recording';
        const spoken = options.item.type === 'spoken';
        const heading = node('div', 'ai-review-heading');
        heading.append(node('span', 'ai-review-kicker', 'YOUR AI PRACTICE COACH'), node('h3', '', spoken ? 'Learn from what you said.' : 'Make your next draft stronger.'));
        panel.append(heading, node('p', '', 'Get a practice estimate, feedback on each criterion and specific steps to improve. Objective question points stay separate.'));
        panel.append(node('p', 'ai-review-disclosure', spoken ? 'When you choose “Review with AI”, NISHA sends your transcript or selected recording and this task to OpenAI. Recordings are limited to 5 minutes and 10 MB and are converted to WAV in your browser before upload. Audio is transcribed before review. Content and language are assessed; pronunciation, fluency and delivery are not assessed. Your response, transcript and feedback are saved to your account; the uploaded audio is not retained by NISHA.' : 'When you choose “Review with AI”, NISHA sends your written answer and this task to OpenAI, then saves the answer and feedback to your account. This is learning guidance, not an official exam result.'));
        if (spoken && !options.guided) panel.append(node('p', 'ai-review-source-note', 'This coaching reviews the transcript or recording you provide after the test. NISHA cannot verify that it is the response spoken during your timed attempt.'));
        let mode;
        let transcript;
        let file;
        let recordStatus;
        let recordingOption;
        let selectedFile = null;
        let preparedSource = null;
        let preparedBytes = null;
        let uploadInfo;
        if (spoken) {
            const modeLabel = node('label', 'ai-review-label', 'Choose what the AI should review');
            mode = node('select', 'ai-review-source'); mode.dataset.aiSource = 'true';
            [['transcript', 'A transcript of what I actually said'], ['recording', 'My recording from this tab'], ['file', 'Upload a saved recording']].forEach(([value, text]) => {
                const option = node('option', '', text); option.value = value; mode.append(option);
                if (value === 'recording') recordingOption = option;
            });
            mode.value = ['transcript', 'recording', 'file'].includes(draft.sourceMode) ? draft.sourceMode : 'transcript';
            modeLabel.append(mode); panel.append(modeLabel);
            const transcriptLabel = node('label', 'ai-review-label ai-review-transcript-input', 'Transcript of your spoken response');
            transcript = node('textarea', 'ai-review-transcript'); transcript.dataset.aiTranscript = 'true'; transcript.rows = 5; transcript.maxLength = 40000; transcript.value = draft.transcript;
            transcript.placeholder = 'Type the words you actually said. Reflection notes are kept separately.';
            transcriptLabel.append(transcript); panel.append(transcriptLabel);
            const fileLabel = node('label', 'ai-review-label ai-review-file-input', 'Choose an audio recording (up to 5 minutes and 10 MB)');
            file = node('input', 'ai-review-file'); file.type = 'file'; file.dataset.aiFile = 'true'; file.accept = '.webm,.wav,.mp3,.m4a,.mp4,audio/webm,audio/wav,audio/mpeg,audio/mp4,video/mp4,video/webm';
            fileLabel.append(file); panel.append(fileLabel);
            recordStatus = node('p', 'ai-review-recording-status'); recordStatus.dataset.aiRecordingStatus = 'true'; panel.append(recordStatus);
            uploadInfo = node('p', 'ai-review-recording-status'); uploadInfo.dataset.aiUploadInfo = 'true'; uploadInfo.hidden = true; panel.append(uploadInfo);
            mode.addEventListener('change', () => { draft.sourceMode = mode.value; sourceEdited = true; sourceRevision++; changed(); refresh(); });
            transcript.addEventListener('input', () => { draft.transcript = transcript.value; sourceEdited = true; sourceRevision++; changed(); refresh(); });
            file.addEventListener('change', () => { selectedFile = file.files?.[0] || null; sourceEdited = true; sourceRevision++; changed(); refresh(); });
        }
        const setup = node('div', 'ai-review-availability'); setup.dataset.aiAvailability = 'true'; panel.append(setup);
        const action = node('button', 'btn btn-primary ai-review-action', 'Review with AI'); action.type = 'button'; action.dataset.aiAction = 'true';
        const status = node('p', 'ai-review-status'); status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite'); status.dataset.aiStatus = 'true';
        const feedback = node('div', 'ai-review-feedback'); feedback.dataset.aiFeedback = 'true'; feedback.hidden = true;
        panel.append(action, status, feedback);
        let busy = false;
        let lastRenderedEntry = null;
        let mountedEntries = Array.isArray(options.entries) ? [...options.entries] : [];
        let sourceEdited = false;
        let sourceRevision = 0;
        let observedRecording = recordingBlob();
        let observedResponse = response();
        let errorMessage = '';
        let submittedRequestId = null;
        let submittedSourceRevision = null;
        function changed() { errorMessage = ''; options.onChange?.(draft, mountedEntries); }
        function response() { const value = options.getResponse(); return typeof value === 'string' ? value : ''; }
        function recordingBlob() { const value = options.getRecording?.(); return value instanceof Blob ? value : value?.blob instanceof Blob ? value.blob : null; }
        function matchingEntry() {
            const value = response();
            return [...mountedEntries].reverse().find((entry) => entry?.review && entry.exam_id === options.examId && entry.exam_version === options.examVersion && entry.attempt_id === options.attemptId && entry.item_id === options.item.id && entry.response === value && (!spoken || (mode.value === 'transcript' && entry.review.mode === 'speaking-transcript' && entry.review.transcript === draft.transcript) || (mode.value !== 'transcript' && entry.review.mode === 'speaking-audio' && !sourceEdited))) || null;
        }
        function unavailable() {
            if (!options.signedIn) return 'Sign in to request and save AI coaching. Keep a copy of your work before leaving this page.';
            // Reading an already submitted review requires neither audio nor a
            // currently enabled provider connection or paid-request setup.
            if (draft.uncertainRequestId) return '';
            if (options.saved === false && !options.guided) return 'Save this completed test to your account before requesting AI coaching. Use “Retry saving to account” above.';
            if (!options.config?.configured) return 'AI coaching is awaiting the site owner’s OpenAI API setup. You can keep practising and use the self-review checklist.';
            if (!options.config?.transportAvailable) return 'AI coaching is temporarily unavailable. The site owner needs to enable PHP HTTPS requests. Your work stays available.';
            return '';
        }
        function refresh() {
            const currentResponse = response();
            if (currentResponse !== observedResponse) { observedResponse = currentResponse; sourceRevision++; errorMessage = ''; }
            const unavailableMessage = unavailable();
            setup.replaceChildren();
            if (unavailableMessage) {
                setup.append(node('p', '', unavailableMessage));
                if (!options.signedIn) { const link = node('a', 'exam-text-button', 'Sign in for AI coaching →'); link.href = 'login.php'; setup.append(link); }
            } else setup.append(node('p', '', `Up to ${options.config.studentDailyLimit} review requests per student each day. Previously saved feedback remains available.`));
            let validSource = response().trim().length > 0;
            if (spoken) {
                const blob = recordingBlob();
                if (blob !== observedRecording) { observedRecording = blob; if (mode.value === 'recording') { sourceEdited = true; sourceRevision++; } }
                recordingOption.disabled = !blob;
                panel.querySelector('.ai-review-transcript-input').hidden = mode.value !== 'transcript';
                panel.querySelector('.ai-review-file-input').hidden = mode.value !== 'file';
                recordStatus.hidden = mode.value !== 'recording';
                recordStatus.textContent = blob ? `A recording is ready in this tab (${(blob.size / 1048576).toFixed(2)} MB before WAV conversion). It will be uploaded only when you request an AI review.` : 'No recording is available in this tab. Record your response above, upload a saved recording, or enter its transcript.';
                const selectedAudio = mode.value === 'recording' ? blob : mode.value === 'file' ? selectedFile : null;
                uploadInfo.hidden = !selectedAudio || preparedSource !== selectedAudio || preparedBytes === null;
                if (!uploadInfo.hidden) uploadInfo.textContent = `Prepared WAV upload: ${sizeText(preparedBytes)}. This website’s upload limit: ${sizeText(audioLimit)}. Compressed recordings can grow during conversion.`;
                validSource = mode.value === 'transcript' ? draft.transcript.trim().length > 0 : mode.value === 'recording' ? Boolean(blob) : Boolean(selectedFile);
            }
            action.disabled = busy || Boolean(unavailableMessage) || (!validSource && !draft.uncertainRequestId);
            action.textContent = busy ? 'Reviewing your response…' : draft.uncertainRequestId ? 'Check AI review status' : errorMessage ? 'Retry AI review' : 'Review with AI';
            panel.setAttribute('aria-busy', String(busy));
            if (!busy && errorMessage) status.textContent = errorMessage;
            const entry = matchingEntry();
            if (entry !== lastRenderedEntry) {
                feedback.replaceChildren(); feedback.hidden = !entry;
                if (entry) feedback.append(renderFeedback(entry));
                lastRenderedEntry = entry;
            }
        }
        async function sourceKey(blob, text, responseText, selectedMode) {
            if (!blob) return JSON.stringify([responseText, text, selectedMode]);
            if (window.crypto?.subtle) {
                const bytes = await blob.arrayBuffer();
                const digest = await window.crypto.subtle.digest('SHA-256', bytes);
                return JSON.stringify([responseText, [...new Uint8Array(digest)].map((value) => value.toString(16).padStart(2, '0')).join(''), 'audio']);
            }
            if (!blobKeys.has(blob)) blobKeys.set(blob, requestId());
            return JSON.stringify([responseText, blobKeys.get(blob), 'audio']);
        }
        function clearUncertainRequest() {
            delete draft.uncertainRequestId;
            delete draft.uncertainResponse;
            submittedRequestId = null;
            submittedSourceRevision = null;
            options.onChange?.(draft, mountedEntries);
        }
        function acceptEntry(entry, expectedResponse, revision, data, recovered = false) {
            if (!entry?.review || entry.exam_id !== options.examId || entry.exam_version !== options.examVersion || entry.item_id !== options.item.id || entry.attempt_id !== options.attemptId || entry.response !== expectedResponse) throw reviewError('The feedback could not be matched to this response. Your work is retained; open your saved coaching history before retrying.', { uncertain: true });
            mountedEntries = mountedEntries.filter((saved) => saved.id !== entry.id); mountedEntries.push(entry);
            if (revision !== null && sourceRevision === revision) sourceEdited = false;
            clearUncertainRequest();
            status.textContent = `AI feedback saved to your account.${recovered ? ' Recovered the completed review without sending another provider request.' : ''}${data.cached ? ' A previous review was reused without another provider request.' : ''}${data.usage ? ` ${data.usage.remaining_today} of ${data.usage.limit} review requests remain today; reset ${data.usage.resets}.` : ''}`;
            if (response() !== expectedResponse || sourceEdited || (spoken && mode.value === 'transcript' && entry.review.mode === 'speaking-transcript' && draft.transcript !== entry.review.transcript)) status.textContent += ' Your response changed while this review was running. Review the updated response separately.';
            lastRenderedEntry = null;
        }
        function requestFailure(statusCode, audio) {
            if (statusCode === 413) return reviewError('The website rejected the recording upload as too large. Your recording is retained in this tab. Use a shorter clip or a transcript; the site owner can check PHP and hosting upload limits.');
            if (statusCode === 401) return reviewError('Sign in again before requesting AI coaching. Keep a copy of your response and recording first.');
            if (statusCode === 403) return reviewError('The website blocked this AI request. Your work is retained. Try signing in again; if it continues, the site owner needs to check the hosting request rules.');
            if ([408, 504].includes(statusCode)) return reviewError('The website did not return AI feedback in time. Your work is retained; checking whether a review was saved before retrying.', { uncertain: true });
            if ([502, 503].includes(statusCode)) return reviewError('The website is temporarily unable to return AI feedback. Your work is retained; checking whether a review was saved before retrying.', { uncertain: true });
            return reviewError(`The website returned an unreadable AI response${audio ? ' after the recording upload' : ''}. Your work is retained; checking for saved feedback.`, { uncertain: true });
        }
        async function savedReviewStatus(id) {
            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 15000);
            try {
                const result = await fetch(`api/ai-review.php?request_id=${encodeURIComponent(id)}`, { method: 'GET', credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal });
                let data;
                try { data = await result.json(); } catch (_) { return { state: 'unavailable' }; }
                if (result.status === 404 && data.state === 'not_found') return { state: 'not_found' };
                if (result.status === 401) return { state: 'unavailable', error: 'Sign in again to check for saved feedback. Keep a copy of your response and recording first.' };
                if (!result.ok || !['completed', 'pending', 'failed'].includes(data.state)) return { state: 'unavailable' };
                return data;
            } catch (_) { return { state: 'unavailable' }; }
            finally { window.clearTimeout(timeout); }
        }
        let waitingForReview = false;
        async function recoverReview(failureMessage, revision = null) {
            const id = draft.uncertainRequestId;
            if (revision === null && submittedRequestId === id) revision = submittedSourceRevision;
            const expectedResponse = typeof draft.uncertainResponse === 'string' ? draft.uncertainResponse : response();
            status.textContent = 'Checking your account for saved feedback. This check does not send another request to OpenAI.';
            const result = await savedReviewStatus(id);
            if (result.state === 'completed') { acceptEntry(result.entry, expectedResponse, revision, result, true); errorMessage = ''; return; }
            if (result.state === 'pending') {
                waitingForReview = true;
                errorMessage = 'Your AI review is still processing. Keep this page open and choose “Check AI review status” shortly. Checking does not send another recording or use another review request.';
            } else if (result.state === 'failed') {
                clearUncertainRequest();
                errorMessage = typeof result.error === 'string' ? result.error : 'The earlier AI review did not finish. Your work is retained. Choose “Retry AI review” to try again.';
            } else if (result.state === 'not_found') {
                clearUncertainRequest();
                errorMessage = failureMessage || 'No saved review was found for this request. Your work is retained. Choose “Retry AI review” to send it again.';
            } else errorMessage = result.error || `${failureMessage || 'The connection ended before AI feedback was confirmed.'} We could not check for saved feedback. Reconnect and choose “Check AI review status” before sending it again.`;
            status.textContent = errorMessage;
        }
        action.addEventListener('click', async () => {
            if (busy || action.disabled) return;
            busy = true; waitingForReview = false; errorMessage = ''; refresh(); options.onStatus?.('reviewing');
            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 115000);
            let reviewRevision = null;
            try {
                // A lost response may already have produced paid, saved feedback.
                // Explicit checks and automatic recovery never submit another POST.
                if (draft.uncertainRequestId) { await recoverReview(); return; }
                const responseText = response();
                reviewRevision = sourceRevision;
                const selectedMode = spoken ? mode.value : 'writing';
                const text = spoken ? draft.transcript : responseText;
                const originalAudio = spoken && selectedMode !== 'transcript' ? selectedMode === 'recording' ? recordingBlob() : selectedFile : null;
                if (spoken && selectedMode !== 'transcript' && !originalAudio) throw reviewError('Choose a recording first, or enter a transcript.');
                if (originalAudio) status.textContent = 'Preparing your recording as a bounded WAV file before upload…';
                const audio = originalAudio ? await boundedWav(originalAudio) : null;
                if (audio) {
                    preparedSource = originalAudio; preparedBytes = audio.size; refresh();
                    if (audio.size < 100 || audio.size > 10 * 1024 * 1024) throw reviewError('Use a recording between 100 bytes and 10 MB. Your response is retained.');
                    if (audio.size > audioLimit) throw reviewError(`The prepared WAV is ${sizeText(audio.size)}, above this website’s ${sizeText(audioLimit)} upload limit. Your recording is retained in this tab. Use a shorter recording or enter its transcript; the site owner can increase PHP upload limits.`);
                }
                status.textContent = audio ? `Uploading ${sizeText(audio.size)} of prepared WAV audio, then transcribing and preparing feedback. Keep this page open.` : 'Preparing your AI feedback. Your response is retained while you wait.';
                const key = await sourceKey(audio, text, responseText, selectedMode);
                if (draft.sourceKey !== key || !draft.requestId) { draft.sourceKey = key; draft.requestId = requestId(); changed(); }
                const payload = { request_id: draft.requestId, exam_id: options.examId, exam_version: options.examVersion, item_id: options.item.id, attempt_id: options.attemptId, response: responseText };
                const headers = { Accept: 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' };
                let body;
                if (audio) {
                    body = new FormData(); Object.entries(payload).forEach(([name, value]) => body.append(name, value));
                    body.append('audio', audio, 'response.wav');
                } else { if (spoken) payload.transcript = text; body = JSON.stringify(payload); headers['Content-Type'] = 'application/json'; }
                draft.uncertainRequestId = draft.requestId; draft.uncertainResponse = responseText; options.onChange?.(draft, mountedEntries);
                submittedRequestId = draft.requestId; submittedSourceRevision = reviewRevision;
                let result;
                try { result = await fetch('api/ai-review.php', { method: 'POST', credentials: 'same-origin', headers, body, signal: controller.signal }); }
                catch (error) {
                    const retained = audio ? 'Your recording is retained in this tab. Retry with the same recording or enter its transcript.' : 'Your written response is retained.';
                    const message = error.name === 'AbortError' ? `The AI review timed out before feedback was received. ${retained}` : navigator.onLine === false ? `You are offline. Reconnect before checking the review. ${retained}` : `The recording or review connection ended before feedback was received. ${retained}`;
                    throw reviewError(message, { uncertain: true });
                }
                let data;
                try { data = await result.json(); } catch (_) { throw requestFailure(result.status, Boolean(audio)); }
                if (!data || typeof data !== 'object' || Array.isArray(data)) throw requestFailure(result.status, Boolean(audio));
                if (data.code === 'ai_in_progress') throw reviewError('Your AI review is already processing. Checking for saved feedback.', { uncertain: true });
                if (!result.ok || !data.success) { clearUncertainRequest(); throw reviewError(typeof data.error === 'string' ? data.error : 'AI review is unavailable. Your work is retained; try again later.'); }
                if (!data.entry?.review) throw requestFailure(result.status, Boolean(audio));
                acceptEntry(data.entry, responseText, reviewRevision, data);
            } catch (error) {
                if (error.uncertain && draft.uncertainRequestId) {
                    try { await recoverReview(error.userMessage, reviewRevision); }
                    catch (_) { errorMessage = 'Saved feedback could not be confirmed. Your work is retained. Check your AI coaching history before retrying.'; }
                } else {
                    clearUncertainRequest();
                    errorMessage = error.userMessage || 'The browser could not prepare this AI review. Your work is retained. Try a compatible recording or enter its transcript.';
                }
                if (errorMessage) status.textContent = errorMessage;
            } finally { window.clearTimeout(timeout); busy = false; refresh(); options.onStatus?.(waitingForReview ? 'reviewing' : errorMessage ? 'error' : 'ready'); }
        });
        const controller = { refresh, addEntries: (entries) => { mountedEntries = [...mountedEntries, ...(entries || [])]; refresh(); } };
        panels.set(panel, controller); refresh(); return panel;
    }
    window.NishaAI = { mount, refresh: (panel) => panels.get(panel)?.refresh(), renderFeedback };
})();
