<?php
declare(strict_types=1);
require_once __DIR__ . '/generated-library.php';

/**
 * Public, unreviewed learning variations. These IDs deliberately do not share
 * the gp./gm. publication namespace used by the frozen teacher-review ledger.
 * The source templates are local: browsing never calls a paid generation API.
 */
function nisha_generated_public_disclosure(): string
{
    return 'Generated practice, not teacher-reviewed. These original NISHA template variations reuse scenarios and questions; 30,000 question slots and 3,000 assembled mock versions do not mean that many independently written or unseen papers. The generated task mix is for extra practice, not a replica of an official exam. Listening uses browser speech. Scores are practice guidance only.';
}

function nisha_generated_public_stats(): array
{
    $courses = [];
    foreach (nisha_generated_courses() as $id => $course) {
        $courses[] = ['id' => $id, 'name' => $course['name'], 'question_count' => 7500, 'mock_count' => 750];
    }
    return ['question_count' => 30000, 'mock_count' => 3000, 'courses' => $courses,
        'generated' => true, 'teacher_reviewed' => false, 'content_source' => 'generated',
        'disclosure' => nisha_generated_public_disclosure()];
}

function nisha_generated_public_parse_id(string $id): ?array
{
    if (!preg_match('/\A(xp|xm)\.(ielts-academic|ielts-general|pte-academic|pte-core)\.([0-9]{4,5})\z/D', $id, $m)) return null;
    $number = (int) $m[3];
    if (($m[1] === 'xp' && strlen($m[3]) === 5 && $number >= 1 && $number <= 7500)
        || ($m[1] === 'xm' && strlen($m[3]) === 4 && $number >= 1 && $number <= 750)) {
        return ['id' => $id, 'kind' => $m[1] === 'xp' ? 'question' : 'mock', 'course' => $m[2], 'number' => $number];
    }
    return null;
}

/** Stable content versions invalidate device attempts after either template changes. */
function nisha_generated_public_version(): string
{
    static $version;
    return $version ??= substr(hash('sha256', hash_file('sha256', __FILE__) . nisha_generated_content_hash()), 0, 24);
}

function nisha_generated_public_resolve_exam(string $id): ?array
{
    $parsed = nisha_generated_public_parse_id($id);
    if ($parsed === null) return null;
    $course = nisha_generated_courses()[$parsed['course']];
    $isMock = $parsed['kind'] === 'mock';
    $session = $isMock ? nisha_generated_mock($parsed['course'], $parsed['number'], false)
        : nisha_generated_question_session($parsed['course'], $parsed['number']);
    $session['id'] = $id;
    $session['name'] = $course['name'] . ' · Generated ' . ($isMock ? 'assembled mock ' : 'practice ') . $parsed['number'];
    $session['description'] = $isMock ? 'A timed paper assembled from generated, unreviewed question variations. Scenarios and questions recur across versions.'
        : 'A generated, unreviewed question variation with a supplied scenario. Related practice and mocks reuse this material.';
    $session['format_note'] = nisha_generated_public_disclosure();
    $session['version'] = 'generated-' . nisha_generated_public_version() . '-' . ($isMock ? 'm' : 'q') . $parsed['number'];
    $session['generated'] = true;
    $session['teacher_reviewed'] = false;
    $session['content_source'] = 'generated';
    unset($session['review_dependencies'], $session['reviewed_at'], $session['reviewer_id']);

    // Separate item/resource identifiers as well as session IDs. This keeps
    // saved generated work distinct from a subsequently reviewed publication.
    $resources = [];
    foreach ($session['resources'] as $resourceId => $resource) {
        $resources['x-' . $resourceId] = $resource;
    }
    $session['resources'] = $resources;
    foreach ($session['sections'] as &$section) {
        $section['instructions'] = $isMock
            ? 'Generated, unreviewed practice. Complete this timed assembled section. Scenarios and questions recur, and this task mix is not an official exam replica.'
            : 'Generated, unreviewed practice. Work at your own pace and check the supplied explanation. Related questions and assembled mocks reuse scenarios.';
        foreach ($section['items'] as &$item) {
            $item['id'] = 'x-' . $item['id'];
            if (isset($item['resource_id'])) $item['resource_id'] = 'x-' . $item['resource_id'];
            if (isset($item['secondary_resource_id'])) $item['secondary_resource_id'] = 'x-' . $item['secondary_resource_id'];
            $item['generated'] = true;
            $item['teacher_reviewed'] = false;
        }
        unset($item);
    }
    unset($section);
    return $session;
}

function nisha_generated_public_filters(string $courseId): array
{
    if (!isset(nisha_generated_courses()[$courseId])) return ['skills' => [], 'task_types' => []];
    $types = [];
    for ($slot = 0; $slot < 10; ++$slot) {
        $question = nisha_generated_question($courseId, $slot + 1);
        $type = $question['item']['task_type'];
        $types[$type] = ['id' => $type, 'name' => $type, 'skill' => $question['skill']];
    }
    return ['skills' => ['reading' => 'Reading', 'listening' => 'Listening', 'writing' => 'Writing', 'speaking' => 'Speaking'],
        'task_types' => array_values($types)];
}

/** Page metadata only; at most one requested paper is resolved by the runner. */
function nisha_generated_public_page(string $kind, string $courseId, int $page = 1, int $perPage = 24, string $skill = 'all', string $taskType = 'all'): array
{
    $empty = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 0, 'per_page' => max(1, min(48, $perPage))];
    if (!isset(nisha_generated_courses()[$courseId]) || !in_array($kind, ['questions', 'mocks'], true)) return $empty;
    if (!in_array($skill, ['all', 'reading', 'listening', 'writing', 'speaking'], true)) return $empty;
    $perPage = max(1, min(48, $perPage));
    $course = nisha_generated_courses()[$courseId];
    $slots = [];
    if ($kind === 'questions') {
        for ($slot = 0; $slot < 10; ++$slot) {
            $question = nisha_generated_question($courseId, $slot + 1);
            if (($skill === 'all' || $question['skill'] === $skill) && ($taskType === 'all' || $question['item']['task_type'] === $taskType)) $slots[] = $slot;
        }
        if (!$slots) return $empty;
    }
    $total = $kind === 'mocks' ? 750 : 750 * count($slots);
    $pages = (int) ceil($total / $perPage);
    $page = max(1, min($pages, $page));
    $items = [];
    $base = nisha_exams()[$course['base']];
    $mockCount = array_sum(array_map(static fn (array $section): int => count($section['items']), $base['sections']));
    $mockDuration = array_sum(array_column($base['sections'], 'duration_seconds'));
    for ($offset = ($page - 1) * $perPage; $offset < min($total, $page * $perPage); ++$offset) {
        if ($kind === 'questions') {
            $number = intdiv($offset, count($slots)) * 10 + $slots[$offset % count($slots)] + 1;
            $question = nisha_generated_question($courseId, $number);
            $id = 'xp.' . $courseId . '.' . str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $name = $course['name'] . ' · Generated practice ' . $number;
            $entrySkill = $question['skill']; $type = $question['item']['task_type'];
            $count = 1; $duration = $entrySkill === 'writing' ? 1200 : 180;
        } else {
            $number = $offset + 1;
            $id = 'xm.' . $courseId . '.' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $name = $course['name'] . ' · Generated assembled mock ' . $number;
            $entrySkill = 'all'; $type = 'Assembled mixed-task practice';
            $count = $mockCount; $duration = $mockDuration;
        }
        $items[] = ['id' => $id, 'name' => $name, 'course_id' => $courseId,
            'session_type' => $kind === 'questions' ? 'practice' : 'full', 'skill' => $entrySkill, 'task_type' => $type,
            'item_count' => $count, 'duration_seconds' => $duration, 'generated' => true, 'teacher_reviewed' => false,
            'launch_url' => 'exams.php?' . http_build_query(['exam' => $id, 'start' => '1'])];
    }
    return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
}
