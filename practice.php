<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/learning-catalog.php';

$learningCourses = nisha_learning_catalog();
$learningById = array_column($learningCourses, null, 'id');
$learningLegacy = is_string($_GET['exam'] ?? null) ? strtolower($_GET['exam']) : '';
$learningRequested = is_string($_GET['course'] ?? null) ? $_GET['course'] : '';
$learningCourseId = isset($learningById[$learningRequested]) ? $learningRequested : ($learningLegacy === 'pte' ? 'pte-academic' : 'ielts-academic');
if (!isset($learningById[$learningCourseId])) $learningCourseId = array_key_first($learningById);
$learningActiveCourse = $learningById[$learningCourseId];
$learningSkill = is_string($_GET['skill'] ?? null) ? strtolower($_GET['skill']) : 'all';
if (!in_array($learningSkill, ['all', 'reading', 'listening', 'writing', 'speaking', 'speaking-writing'], true)) $learningSkill = 'all';
$learningQuery = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$learningSkillNames = ['all' => 'All skills', 'listening' => 'Listening', 'reading' => 'Reading', 'writing' => 'Writing', 'speaking' => 'Speaking', 'speaking-writing' => 'Speaking & writing'];

function learning_normalize(string $text): string
{
    return trim(strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text)));
}

function learning_module_description(array $module): string
{
    $title = strtolower($module['title']);
    if (str_contains($title, 'read aloud')) return 'Bring a written passage to life with clear, natural phrasing.';
    if (str_contains($title, 'repeat sentence')) return 'Listen for meaning, hold it in mind and repeat what you hear.';
    if (str_contains($title, 'describe image')) return 'Find the main patterns and put the visual details into words.';
    if (str_contains($title, 'reorder') || str_contains($title, 're-order')) return 'Follow the logic and put the paragraphs into a meaningful order.';
    if (str_contains($title, 'incorrect words')) return 'Compare what you hear with what you read. Spot the differences.';
    if (str_contains($title, 'dictation')) return 'Listen closely, then capture the sentence accurately in writing.';
    if (str_contains($title, 'headings')) return 'Find the main idea that holds each paragraph together.';
    if (str_contains($title, 'true') || str_contains($title, 'yes / no')) return 'Separate what the text tells you from what it leaves open.';
    if (str_contains($title, 'blank') || str_contains($title, 'completion')) return 'Use context and key details to find the missing information.';
    if (str_contains($title, 'summari') || str_contains($title, 'summary')) return 'Find the central ideas and focus on what matters most.';
    if (str_contains($title, 'essay')) return 'Give your ideas a clear direction, with reasons and examples.';
    if (str_contains($title, 'respond to') || str_contains($title, 'situation')) return 'Choose a helpful tone and communicate a practical response.';
    if (str_contains($title, 'matching')) return 'Connect the clues and match each detail with the right idea.';
    return match ($module['skill']) {
        'reading' => 'Read with purpose and find the evidence behind your answer.',
        'listening' => 'Tune in to the main ideas and the details that make a difference.',
        'writing' => 'Organise your thoughts and make your message easy to follow.',
        'speaking', 'speaking-writing' => 'Build a response that sounds clear, considered and your own.',
        default => 'Give one question type your full attention, one item at a time.',
    };
}

function learning_search_text(array $module): string
{
    $aliases = str_contains(strtolower($module['title']), 'blank') ? 'FIB gap fill missing words' : '';
    if (str_contains(strtolower($module['title']), 'choice')) $aliases .= ' MCQ multiple choice';
    return learning_normalize($module['title'] . ' ' . $module['skill'] . ' ' . learning_module_description($module) . ' ' . $aliases);
}

function learning_matches(array $module, string $skill, string $query): bool
{
    if ($skill !== 'all' && $module['skill'] !== $skill && !($skill === 'speaking-writing' && in_array($module['skill'], ['speaking', 'writing'], true))) return false;
    $search = learning_search_text($module);
    foreach (preg_split('/\s+/u', learning_normalize($query), -1, PREG_SPLIT_NO_EMPTY) as $term) if (!str_contains($search, $term)) return false;
    return true;
}

function learning_url(string $course, string $skill = 'all', string $query = ''): string
{
    return 'practice.php?' . http_build_query(array_filter(['course' => $course, 'skill' => $skill !== 'all' ? $skill : '', 'q' => $query], static fn($value) => $value !== ''));
}

function learning_launch(string $id): string
{
    return 'exams.php?' . http_build_query(['exam' => $id, 'start' => '1']);
}

$learningVisibleCount = count(array_filter($learningActiveCourse['modules'], static fn($module) => learning_matches($module, $learningSkill, $learningQuery)));
$pageTitle = 'Your practice library';
$pageDescription = 'Find your focus with the NISHA practice library. Explore IELTS Academic, IELTS General Training, PTE Academic and PTE Core question types, section tests and full mocks.';
$activePage = 'practice';
$extraStyles = ['assets/css/learning.css'];
$extraScripts = ['assets/js/learning.js'];
include __DIR__ . '/includes/header.php';
$learningIconMap = ['reading' => 'book', 'listening' => 'headphones', 'writing' => 'pen', 'speaking' => 'mic', 'speaking-writing' => 'mic'];
?>
<main id="main" class="learning-page">
    <div class="container">
        <div class="learning-intro">
            <div><p class="eyebrow"><span class="learning-live-dot" aria-hidden="true"></span> YOUR PRACTICE LIBRARY</p><h1>A little focus.<br><span>A world of progress.</span></h1><p>Find the practice that fits your next step. Choose your course, explore a skill and make a little more room for confidence.</p></div>
            <aside class="learning-intro-note" aria-label="A little encouragement"><span class="learning-note-spark" aria-hidden="true"><?= icon('spark', '', 29) ?></span><p>Big dreams.<br>Small, steady steps.</p><span>Let’s take the next one together.</span><div class="learning-note-leaves" aria-hidden="true"><svg viewBox="0 0 84 90" fill="none"><path d="M40 87V26M40 60C14 61 9 46 11 27c22-1 32 11 29 33Zm0-18C39 20 54 9 75 11c1 21-13 35-35 34Z" fill="currentColor"/><path d="m20 37 20 23m24-38L40 46" stroke="#edf1df" stroke-width="2"/></svg></div></aside>
        </div>

        <nav class="learning-course-tabs" id="learning-course-tabs" aria-label="Choose your course">
            <?php foreach ($learningCourses as $course): ?>
            <a href="<?= e(learning_url($course['id'])) ?>" data-course-id="<?= e($course['id']) ?>" class="learning-course-tab<?= $course['id'] === $learningCourseId ? ' is-active' : '' ?>" <?= $course['id'] === $learningCourseId ? 'aria-current="page"' : '' ?>><span class="learning-course-family"><?= e($course['exam']) ?></span><strong><?= e(str_replace($course['exam'] . ' ', '', $course['name'])) ?></strong><span class="learning-course-check" aria-hidden="true"><?= icon('check', '', 12) ?></span></a>
            <?php endforeach; ?>
        </nav>

        <section class="learning-library" aria-labelledby="learning-library-title">
            <div class="learning-library-heading"><div><p class="eyebrow">START SMALL. GO A LITTLE FURTHER.</p><h2 id="learning-library-title"><?= e($learningActiveCourse['name']) ?></h2><p id="learning-course-description"><?= e($learningActiveCourse['description']) ?></p></div><a href="#learning-sectionals" class="learning-subtle-link">Looking for a timed test? <?= icon('arrow', '', 16) ?></a></div>
            <form class="learning-search-form" id="learning-search-form" action="practice.php" method="get" role="search" aria-label="Find a practice question type"><input type="hidden" name="course" id="learning-course-input" value="<?= e($learningCourseId) ?>"><input type="hidden" name="skill" id="learning-skill-input" value="<?= e($learningSkill) ?>"><label for="learning-search" class="learning-sr-only">Search question types</label><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" width="19" height="19" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5" stroke-linecap="round"/></svg><input type="search" id="learning-search" name="q" value="<?= e($learningQuery) ?>" placeholder="Find your focus: read aloud, matching, fill in the blanks…" autocomplete="off" maxlength="160"><button type="submit">Search <?= icon('arrow', '', 15) ?></button></form>
            <nav class="learning-skill-filters" id="learning-skill-filters" aria-label="Filter by skill">
                <?php foreach ($learningSkillNames as $skillId => $skillName): ?>
                <?php $skillCount = count(array_filter($learningActiveCourse['modules'], static fn($module) => learning_matches($module, $skillId, ''))); ?>
                <a href="<?= e(learning_url($learningCourseId, $skillId, $learningQuery)) ?>" data-skill="<?= e($skillId) ?>" class="learning-filter<?= $learningSkill === $skillId ? ' is-active' : '' ?>" <?= $learningSkill === $skillId ? 'aria-current="true"' : '' ?> <?= $skillId !== 'all' && $skillCount === 0 ? 'hidden' : '' ?>><?php if ($skillId !== 'all'): ?><?= icon($learningIconMap[$skillId] ?? 'book', '', 14) ?><?php endif; ?><span><?= e($skillName) ?></span><small><?= $skillCount ?></small></a>
                <?php endforeach; ?>
            </nav>
            <div class="learning-grid-heading"><p><strong>Question type practice</strong><span>Untimed sessions, with room to learn.</span></p><span id="learning-result-count" role="status" aria-live="polite" aria-atomic="true"><?= $learningVisibleCount ?> <?= $learningVisibleCount === 1 ? 'question type' : 'question types' ?></span></div>
            <div id="learning-empty" class="learning-empty" <?= $learningVisibleCount > 0 ? 'hidden' : '' ?>><span aria-hidden="true"><?= icon('spark', '', 29) ?></span><h3>A fresh focus is only a search away.</h3><p>No question types match these filters. Try another word or explore all the skills in this course.</p><a href="<?= e(learning_url($learningCourseId)) ?>" id="learning-clear-filters" class="btn btn-outline">Clear filters <?= icon('arrow', '', 16) ?></a></div>

            <?php foreach ($learningCourses as $course): ?>
            <div class="learning-course-panel" data-course="<?= e($course['id']) ?>" id="learning-panel-<?= e($course['id']) ?>" <?= $course['id'] !== $learningCourseId ? 'hidden' : '' ?>>
                <div class="learning-module-grid">
                    <?php foreach ($course['modules'] as $module): ?>
                    <article class="learning-module learning-module-<?= e($module['skill']) ?>" data-skill="<?= e($module['skill']) ?>" data-search="<?= e(learning_search_text($module)) ?>" <?= !learning_matches($module, $learningSkill, $learningQuery) ? 'hidden' : '' ?>>
                        <div class="learning-module-top"><span class="learning-skill-icon" aria-hidden="true"><?= icon($learningIconMap[$module['skill']] ?? 'book', '', 22) ?></span><span class="learning-module-skill"><?= e($learningSkillNames[$module['skill']] ?? ucfirst($module['skill'])) ?></span><span class="learning-module-count"><?= (int) $module['item_count'] ?> <?= (int) $module['item_count'] === 1 ? 'item' : 'items' ?></span></div>
                        <h3><?= e($module['title']) ?></h3><p><?= e(learning_module_description($module)) ?></p><div class="learning-module-bottom"><span><?= in_array($module['skill'], ['writing', 'speaking', 'speaking-writing'], true) ? 'Reflect & improve' : 'Learn with feedback' ?></span><a href="<?= e(learning_launch($module['practice_id'])) ?>" class="learning-module-link" aria-label="Practise <?= e($module['title']) ?> in <?= e($course['name']) ?>">Start practice <?= icon('arrow', '', 17) ?></a></div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <div class="learning-course-sectionals" data-course-sectionals="<?= e($course['id']) ?>" <?= $course['id'] === $learningCourseId ? 'id="learning-sectionals"' : '' ?>>
                    <div class="learning-section-heading"><div><p class="eyebrow">BUILD YOUR FOCUS A LITTLE FURTHER</p><h2>One section. Your full attention.</h2><p>Bring the question types together in a timed session.</p></div><span class="learning-timed-label"><?= icon('clock', '', 16) ?> Section tests</span></div>
                    <div class="learning-section-grid">
                        <?php foreach ($course['sections'] as $section): ?>
                        <?php $sectionLaunchParts = explode('.', $section['exam_id']); $sectionSkill = $section['skill'] ?? end($sectionLaunchParts); ?>
                        <article class="learning-sectional"><span class="learning-sectional-icon" aria-hidden="true"><?= icon($learningIconMap[$sectionSkill] ?? 'book', '', 23) ?></span><h3><?= e($section['title']) ?></h3><p><span><?= (int) $section['item_count'] ?> <?= (int) $section['item_count'] === 1 ? 'task' : 'tasks' ?></span><span><?= (int) ceil($section['duration_seconds'] / 60) ?> min</span></p><a href="<?= e(learning_launch($section['exam_id'])) ?>" class="learning-sectional-link" aria-label="Start <?= e($section['title']) ?> section for <?= e($course['name']) ?>">Start section <?= icon('arrow', '', 16) ?></a></article>
                        <?php endforeach; ?>
                    </div>
                    <div class="learning-full-mock"><div><p class="eyebrow">WHEN YOU’RE READY FOR THE WHOLE EXPERIENCE</p><h2>Let your preparation come together.</h2><p>Step into the <?= e($course['name']) ?> full mock. Timed sections, original questions and a clear view of what to work on next.</p><div class="learning-full-facts"><span><?= icon('clock', '', 14) ?> <?= (int) ceil(array_sum(array_column($course['sections'], 'duration_seconds')) / 60) ?> minutes</span><span><?= count($course['sections']) ?> sections</span><span><?= array_sum(array_column($course['sections'], 'item_count')) ?> tasks</span></div></div><a class="btn btn-lime learning-full-link" href="<?= e(learning_launch($course['full_exam_id'])) ?>">Start full mock <?= icon('arrow', '', 19) ?></a><span class="learning-full-decoration" aria-hidden="true"><?= icon('spark', '', 100) ?></span></div>
                </div>
            </div>
            <?php endforeach; ?>
        </section>

        <section class="learning-good-to-know" aria-labelledby="learning-transparency-title"><div><span aria-hidden="true"><?= icon('heart', '', 22) ?></span><h2 id="learning-transparency-title">A thoughtful space to prepare.</h2></div><div><h3>Practice that tells you what it is.</h3><p>All questions are original, unofficial learning material. Some guided questions and all sectional tests share the full-mock bank. Course variants may share same-format questions; these are not independent unseen papers.</p></div><div><h3>Helpful feedback, honest limits.</h3><p>Objective questions earn practice points. Writing and speaking use self-review guidance. Listening uses browser speech. Recordings stay in the current tab: download them before leaving. No official band score or PTE score is generated.</p></div></section>
        <p class="learning-warmup-note">Just getting started? <a href="quick-practice.php">Try a short Academic warm-up <?= icon('arrow', '', 13) ?></a></p>
    </div>
</main>
<script id="learning-catalog-data" type="application/json"><?= json_encode(['courses' => array_map(static fn($course) => ['id' => $course['id'], 'name' => $course['name'], 'description' => $course['description']], $learningCourses), 'initial' => ['course' => $learningCourseId, 'skill' => $learningSkill, 'q' => $learningQuery]], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
