<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Quick warm-ups';
$activePage = 'practice';
$extraStyles = ['assets/css/practice.css'];
$extraScripts = ['assets/js/practice.js'];
$practiceData = require __DIR__ . '/includes/practice-data.php';
$practiceSignedIn = current_user() !== null;
include __DIR__ . '/includes/header.php';
?>
<main class="practice-page" id="main">
    <div class="container practice-container">
        <section id="practice-setup" aria-labelledby="practice-title">
            <div class="practice-intro">
                <div>
                    <p class="eyebrow"><span class="practice-spark" aria-hidden="true">✳</span> QUICK WARM-UPS · IELTS & PTE ACADEMIC</p>
                    <h1 id="practice-title">A little practice.<br><span>A lot more possibility.</span></h1>
                    <p class="practice-lead">A few original introductory exercises for IELTS Academic and PTE Academic. These six-question warm-ups are a small starting point. <a href="practice.php" style="text-decoration:underline;text-underline-offset:3px">Explore the full practice library →</a></p>
                </div>
                <div class="practice-intro-note">
                    <span class="practice-note-icon" aria-hidden="true">→</span>
                    <strong>Progress starts<br>with showing up.</strong>
                    <span>You’ve already taken the first step.</span>
                </div>
            </div>

            <aside class="studio-fullmock-banner" aria-label="Full-length exam practice"><div><span class="eyebrow">READY FOR THE WHOLE EXPERIENCE?</span><h2>Step into the full exam room.</h2><p>Longer passages, realistic task formats, and timed sections for IELTS Academic and PTE Academic.</p></div><a class="btn btn-primary" href="exams.php">Explore full mock exams <span aria-hidden="true">→</span></a></aside>
            <div class="practice-studio">
                <div class="practice-studio-heading">
                    <div><p class="eyebrow">THE PRACTICE STUDIO</p><h2>Make this session yours.</h2></div>
                    <div class="exam-switch" role="group" aria-label="Choose your exam">
                        <button type="button" data-exam="IELTS" aria-pressed="true" class="is-selected">IELTS</button>
                        <button type="button" data-exam="PTE" aria-pressed="false">PTE</button>
                    </div>
                </div>
                <div class="practice-skill-grid" role="group" aria-label="Choose a skill">
                    <button type="button" class="practice-skill is-selected" data-skill="Reading" aria-pressed="true">
                        <span class="skill-icon skill-icon-reading" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 5c-3-2-7-2-9-1v15c3-1 6-1 9 1 3-2 6-2 9-1V4c-2-1-6-1-9 1Zm0 0v15M6 8h3M6 11h3m6-3h3m-3 3h3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="skill-selected-mark" aria-hidden="true">✓</span><strong>Reading</strong><span>Find meaning.<br>Connect the dots.</span><small>6 questions · instant feedback</small>
                    </button>
                    <button type="button" class="practice-skill" data-skill="Listening" aria-pressed="false">
                        <span class="skill-icon skill-icon-listening" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 14v-3a8 8 0 0 1 16 0v3M4 12H3v7h4v-7H4Zm16 0h1v7h-4v-7h3Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="skill-selected-mark" aria-hidden="true">✓</span><strong>Listening</strong><span>Tune in.<br>Pick up the details.</span><small>6 questions · audio & transcript</small>
                    </button>
                    <button type="button" class="practice-skill" data-skill="Writing" aria-pressed="false">
                        <span class="skill-icon skill-icon-writing" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m15 4 5 5M4 20l5-1L21 7a2 2 0 0 0-5-4L4 15l-1 6 6-2M4 15l5 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="skill-selected-mark" aria-hidden="true">✓</span><strong>Writing</strong><span>Shape your ideas.<br>Make your point.</span><small>Guided prompt · word counter</small>
                    </button>
                    <button type="button" class="practice-skill" data-skill="Speaking" aria-pressed="false">
                        <span class="skill-icon skill-icon-speaking" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><rect x="9" y="2" width="6" height="13" rx="3" stroke="currentColor" stroke-width="1.6"/><path d="M5 11a7 7 0 0 0 14 0m-7 7v4m-4 0h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
                        <span class="skill-selected-mark" aria-hidden="true">✓</span><strong>Speaking</strong><span>Find your rhythm.<br>Speak with confidence.</span><small>Cue card · record & reflect</small>
                    </button>
                </div>
                <div class="practice-session-preview">
                    <div class="session-preview-info"><span class="session-preview-dot" aria-hidden="true"></span><div><strong id="preview-title">A greener kind of city</strong><p id="preview-description">Read a short article, then find the evidence behind each answer.</p></div></div>
                    <div class="session-preview-actions"><span id="preview-duration">10 min</span><button type="button" class="btn btn-primary" id="start-practice">Let’s practise <span aria-hidden="true">→</span></button></div>
                </div>
            </div>
            <div class="practice-reassurance"><span>✓ Original practice material</span><span>✓ Learn at your own pace</span><span>✓ No pressure, just progress</span></div>
            <p class="practice-disclaimer">These short exercises build individual skills. They are not full mock exams or official IELTS/PTE assessments. Reading and listening results show correct answers, not band scores.</p>
        </section>

        <section id="practice-session" hidden aria-labelledby="session-title">
            <div class="session-topline"><button type="button" class="practice-text-button" id="leave-session">← All practice</button><span class="practice-session-tag" id="session-tag">IELTS · Reading</span></div>
            <div class="session-heading"><div><p class="eyebrow">ONE SESSION. ONE STEP FORWARD.</p><h1 id="session-title" tabindex="-1">A greener kind of city</h1></div><div class="session-timer"><span aria-hidden="true">◷</span><div><small>TIME REMAINING</small><strong id="practice-timer" role="timer" aria-live="off">10:00</strong></div></div></div>
            <div class="quiz-progress" id="quiz-progress"><div><span id="question-progress-text">Question 1 of 6</span><span id="answered-progress-text">0 answered</span></div><progress id="answer-progress" max="6" value="0" aria-label="Questions answered"></progress></div>

            <div class="practice-workspace" id="objective-workspace">
                <article class="practice-resource">
                    <div class="resource-label"><span id="resource-label-text">READING PASSAGE</span><span class="resource-original">NISHA ORIGINAL</span></div>
                    <h2 id="resource-title"></h2>
                    <div id="listening-controls" hidden>
                        <div class="listening-player"><button type="button" class="audio-play-button" id="play-audio" aria-label="Play listening passage"><span aria-hidden="true">▶</span></button><div><strong>Listen closely. You’ve got this.</strong><p id="audio-status">Browser-generated audio · replay any time</p></div><button type="button" class="practice-text-button" id="stop-audio">Stop</button></div>
                        <p class="audio-help">Uses your browser’s speech voice. Voice quality and availability vary by browser. Replay as often as you need.</p>
                        <button type="button" class="practice-text-button transcript-toggle" id="toggle-transcript" aria-expanded="false" aria-controls="resource-passage">Show transcript <span aria-hidden="true">↓</span></button>
                    </div>
                    <div class="resource-passage" id="resource-passage"></div>
                </article>
                <div class="question-panel"><p class="eyebrow" id="question-number">QUESTION 01</p><fieldset id="question-fieldset"><legend id="question-prompt"></legend><div class="question-options" id="question-options"></div></fieldset><p class="question-hint">Choose one answer. You can change it before submitting.</p><div class="question-navigation"><button type="button" class="btn btn-outline" id="previous-question">← Previous</button><button type="button" class="btn btn-primary" id="next-question">Next question →</button></div><div class="question-jump" id="question-jump" role="group" aria-label="Jump to a question"></div><div class="quiz-finish-row"><span>Ready when you are.</span><button type="button" class="practice-text-button" id="finish-quiz">Finish & review →</button></div></div>
            </div>

            <div class="practice-workspace" id="creative-workspace" hidden>
                <article class="practice-resource creative-prompt"><div class="resource-label"><span id="creative-label">YOUR WRITING PROMPT</span><span class="resource-original">NISHA ORIGINAL</span></div><h2 id="creative-prompt"></h2><p id="creative-instructions"></p><ul id="speaking-cues" hidden></ul><div class="creative-reflection"><span class="eyebrow">A MOMENT TO REFLECT</span><h3>Small checks. Stronger skills.</h3><ul id="creative-checklist"></ul></div></article>
                <div class="creative-work-panel">
                    <div id="writing-tools"><label for="writing-answer">Your ideas belong here.</label><textarea id="writing-answer" placeholder="Start with your main idea…" spellcheck="true"></textarea><div class="writing-meta"><span id="word-count" aria-live="polite">0 words</span><span id="word-target">Aim for at least 250 words</span></div><p class="creative-privacy">Your text stays in this page and is not submitted for marking. Copy or download it before leaving.</p><button type="button" class="btn btn-outline" id="download-writing">Download my writing ↓</button></div>
                    <div id="speaking-tools" hidden><div class="recorder-visual" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div><h3>Your voice. Your progress.</h3><p>Record a response, then listen back with the reflection checklist.</p><button type="button" class="btn btn-primary" id="record-speaking">Start recording <span aria-hidden="true">●</span></button><p id="record-status" role="status">Your microphone is only used when you choose to record.</p><audio id="speaking-playback" controls hidden></audio><div class="recording-actions" id="recording-actions" hidden><a id="download-recording" class="practice-text-button" download="nisha-speaking-practice.webm">Download recording ↓</a><button type="button" class="practice-text-button" id="delete-recording">Delete recording</button></div><p class="creative-privacy">Audio stays in your browser and is never uploaded. Recording requires microphone permission and a supported browser on HTTPS or localhost.</p></div>
                    <div class="creative-finish"><button type="button" class="btn btn-primary" id="finish-creative">Finish & reflect <span aria-hidden="true">→</span></button><p id="creative-completion" role="status"></p></div>
                </div>
            </div>
            <p class="practice-disclaimer">Original practice material. Writing and speaking are self-guided exercises with no automated marking. This session does not provide an official exam score.</p>
        </section>

        <section id="practice-results" hidden aria-labelledby="results-title">
            <div class="session-topline"><button type="button" class="practice-text-button" id="results-all-practice">← All practice</button><span class="practice-session-tag" id="results-tag">IELTS · Reading</span></div>
            <div class="results-summary"><div class="results-score"><strong id="result-score">0<span>/6</span></strong><span>ANSWERS CORRECT</span></div><div class="results-message"><p class="eyebrow">THAT’S ANOTHER STEP FORWARD</p><h1 id="results-title" tabindex="-1">Every attempt teaches you something.</h1><p id="result-description"></p><div class="results-meta"><span id="result-percent"></span><span id="result-duration"></span></div></div></div>
            <div class="results-next-step"><div><strong>Keep the learning going.</strong><p id="save-result-status"><?php echo $practiceSignedIn ? 'Your result will be saved to your dashboard when you finish.' : 'Create a free account to save results from future practice sessions.'; ?></p></div><?php if (!$practiceSignedIn): ?><a href="register.php" class="btn btn-primary">Join NISHA <span aria-hidden="true">→</span></a><?php endif; ?><button type="button" class="btn btn-outline" id="retry-practice">Try this set again ↻</button></div>
            <div class="review-heading"><div><p class="eyebrow">UNDERSTANDING IS THE REAL WIN</p><h2>Let’s learn from your answers.</h2></div><p>Open each question to see the explanation.</p></div><div id="answer-review" class="answer-review"></div>
            <p class="practice-disclaimer">This is a short practice result, not an IELTS band score or a PTE score. The same questions appear when you retry this set.</p>
        </section>
        <noscript><p class="practice-noscript">Please enable JavaScript to use the interactive practice studio. You can still browse the rest of NISHA.</p></noscript>
        <div id="practice-announcer" class="practice-sr-only" aria-live="polite" aria-atomic="true"></div>
    </div>
</main>
<dialog id="practice-confirm-dialog" class="practice-dialog"><form method="dialog"><span class="eyebrow">YOUR PRACTICE, YOUR CALL</span><h2 id="confirm-title">Ready to review?</h2><p id="confirm-description"></p><div><button value="cancel" class="btn btn-outline">Keep practising</button><button value="confirm" class="btn btn-primary" id="confirm-action">Finish & review</button></div></form></dialog>
<script id="practice-data" type="application/json"><?php echo json_encode(['modules' => $practiceData, 'signedIn' => $practiceSignedIn], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); ?></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
