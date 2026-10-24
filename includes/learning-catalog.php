<?php
declare(strict_types=1);
require_once __DIR__ . '/exam-data.php';

/** Stable course identifiers are independent of display labels. */
function nisha_course_definitions(): array
{
    return [
        ['id' => 'ielts-academic', 'name' => 'IELTS Academic', 'exam' => 'IELTS', 'description' => 'Build academic English skills with focused practice and timed mock exams.', 'full_exam_id' => 'ielts-academic-01'],
        ['id' => 'ielts-general', 'name' => 'IELTS General Training', 'exam' => 'IELTS', 'description' => 'Practise everyday, workplace and general English for your next chapter.', 'full_exam_id' => 'ielts-general-01'],
        ['id' => 'pte-academic', 'name' => 'PTE Academic', 'exam' => 'PTE', 'description' => 'Explore integrated academic English tasks, then bring them together in a mock.', 'full_exam_id' => 'pte-academic-01'],
        ['id' => 'pte-core', 'name' => 'PTE Core', 'exam' => 'PTE', 'description' => 'Develop practical English for everyday situations with focused PTE Core preparation.', 'full_exam_id' => 'pte-core-01'],
    ];
}

function nisha_learning_slug(string $text): string
{
    $text = strtolower($text);
    $text = str_replace('&', ' and ', $text);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $text) ?? '', '-');
}

function nisha_module_id(string $skill, string $title): string
{
    $slug = $skill . '-' . nisha_learning_slug($title);
    if (strlen($slug) > 39) {
        $slug = rtrim(substr($slug, 0, 30), '-') . '-' . substr(hash('sha256', $slug), 0, 8);
    }
    return $slug;
}

function nisha_section_skill(array $section): string
{
    $text = strtolower(($section['id'] ?? '') . ' ' . ($section['title'] ?? ''));
    if (str_contains($text, 'speaking') && str_contains($text, 'writing')) {
        return 'speaking-writing';
    }
    foreach (['listening', 'reading', 'writing', 'speaking'] as $skill) {
        if (str_contains($text, $skill)) {
            return $skill;
        }
    }
    throw new UnexpectedValueException('An exam section has no recognised skill.');
}

function nisha_item_skill(array $section, array $item): string
{
    $skill = nisha_section_skill($section);
    return $skill === 'speaking-writing' ? ($item['type'] === 'essay' ? 'writing' : 'speaking') : $skill;
}

/** Keep every referenced stimulus; missing resources indicate a content error. */
function nisha_learning_resources(array $items, array $resources): array
{
    $selected = [];
    foreach ($items as $item) {
        foreach (['resource_id', 'secondary_resource_id'] as $field) {
            if (!isset($item[$field])) {
                continue;
            }
            $id = $item[$field];
            if (!isset($resources[$id])) {
                throw new UnexpectedValueException('A learning item references a missing resource: ' . $id);
            }
            $selected[$id] = $resources[$id];
        }
    }
    ksort($selected);
    return $selected;
}

function nisha_learning_version(array $session): string
{
    unset($session['version']);
    return 'content-' . substr(hash('sha256', json_encode($session, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), 0, 24);
}

function nisha_practice_suggested_seconds(array $items): int
{
    $seconds = 0;
    foreach ($items as $item) {
        $seconds += isset($item['suggested_seconds']) ? (int) $item['suggested_seconds']
            : ($item['type'] === 'spoken' ? max(60, (int) ($item['prep_seconds'] ?? 0) + (int) ($item['response_seconds'] ?? 60))
            : ($item['type'] === 'essay' ? 1200 : 90));
    }
    return max(60, min(21600, $seconds));
}

/** Add a canonical item once, rejecting accidental ID collisions. */
function nisha_append_learning_item(array &$group, array $item, array $resources): void
{
    $id = $item['id'] ?? null;
    if (!is_string($id) || $id === '') {
        throw new UnexpectedValueException('A practice item needs a stable ID.');
    }
    if (isset($group['items'][$id]) && $group['items'][$id] !== $item) {
        throw new UnexpectedValueException('Conflicting practice item ID: ' . $id);
    }
    $group['items'][$id] = $item;
    foreach (nisha_learning_resources([$item], $resources) as $resourceId => $resource) {
        if (isset($group['resources'][$resourceId]) && $group['resources'][$resourceId] !== $resource) {
            throw new UnexpectedValueException('Conflicting practice resource ID: ' . $resourceId);
        }
        $group['resources'][$resourceId] = $resource;
    }
}

/** Build public catalogue metadata and canonical generated sessions together. */
function nisha_learning_library(): array
{
    static $library;
    if ($library !== null) {
        return $library;
    }
    $fullExams = nisha_exams();
    $supplements = [];
    $supplementFile = __DIR__ . '/practice/ielts-modules.php';
    if (is_file($supplementFile)) {
        $supplements = (static function (string $file): mixed { return require $file; })($supplementFile);
        if (!is_array($supplements) || !array_is_list($supplements)) {
            throw new UnexpectedValueException('Supplementary practice must return a module list.');
        }
    }
    $catalog = [];
    $sessions = [];
    foreach (nisha_course_definitions() as $course) {
        $full = $fullExams[$course['full_exam_id']] ?? null;
        $groups = [];
        $aliases = [];
        $itemGroups = [];
        $sectionMetadata = [];
        if ($full !== null) {
            foreach ($full['sections'] as $section) {
                $skill = nisha_section_skill($section);
                if ($section['items'] !== []) {
                    $sectionId = 'section.' . $course['id'] . '.' . $skill;
                    if (isset($sessions[$sectionId])) {
                        throw new UnexpectedValueException('A course contains duplicate sectional skills.');
                    }
                    $sectionSession = [
                        'id' => $sectionId, 'name' => $course['name'] . ' · ' . $section['title'] . ' sectional mock',
                        'exam' => $course['exam'], 'description' => 'A timed ' . strtolower($section['title']) . ' section using the original full-mock bank.',
                        'format_note' => $full['format_note'], 'course_id' => $course['id'], 'module_id' => null,
                        'session_type' => 'section', 'timing_mode' => 'timed',
                        'resources' => nisha_learning_resources($section['items'], $full['resources']), 'sections' => [$section],
                    ];
                    $sectionSession['version'] = nisha_learning_version($sectionSession);
                    $sessions[$sectionId] = $sectionSession;
                    $sectionMetadata[] = ['id' => $section['id'], 'title' => $section['title'], 'item_count' => count($section['items']), 'duration_seconds' => $section['duration_seconds'], 'exam_id' => $sectionId];
                }
                foreach ($section['items'] as $item) {
                    $itemSkill = nisha_item_skill($section, $item);
                    $title = trim((string) ($item['task_type'] ?? ''));
                    if ($title === '' || nisha_learning_slug($title) === '') {
                        throw new UnexpectedValueException('A practice-bank item is missing its task type.');
                    }
                    $key = $itemSkill . ':' . nisha_learning_slug($title);
                    if (!isset($groups[$key])) {
                        $groups[$key] = ['id' => nisha_module_id($itemSkill, $title), 'title' => $title, 'skill' => $itemSkill, 'items' => [], 'resources' => []];
                    }
                    $aliases[$key] = $key;
                    if (isset($itemGroups[$item['id']]) && $itemGroups[$item['id']] !== $key) {
                        throw new UnexpectedValueException('A question was assigned to two practice categories.');
                    }
                    $itemGroups[$item['id']] = $key;
                    nisha_append_learning_item($groups[$key], $item, $full['resources']);
                }
            }
        }
        foreach ($supplements as $module) {
            if (!is_array($module) || !is_array($module['course_ids'] ?? null)
                || !array_is_list($module['course_ids']) || $module['course_ids'] === []) {
                throw new UnexpectedValueException('A supplementary module has invalid course assignments.');
            }
            foreach ($module['course_ids'] as $assignedCourse) {
                if (!is_string($assignedCourse) || !in_array($assignedCourse, array_column(nisha_course_definitions(), 'id'), true)) {
                    throw new UnexpectedValueException('A supplementary module names an unknown course.');
                }
            }
            if (!in_array($course['id'], $module['course_ids'], true)) {
                continue;
            }
            $skill = $module['skill'] ?? null;
            if (!in_array($skill, ['listening', 'reading', 'writing', 'speaking'], true)
                || !is_string($module['title'] ?? null) || trim($module['title']) === ''
                || nisha_learning_slug($module['title']) === ''
                || !is_string($module['id'] ?? null) || !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $module['id'])
                || !is_array($module['items'] ?? null) || !array_is_list($module['items'])
                || !is_array($module['resources'] ?? null)) {
                throw new UnexpectedValueException('A supplementary module has invalid metadata.');
            }
            if ($module['items'] === []) {
                continue;
            }
            $titleKey = $skill . ':' . nisha_learning_slug($module['title']);
            $idKey = $skill . ':' . nisha_learning_slug($module['id']);
            if (isset($aliases[$titleKey], $aliases[$idKey]) && $aliases[$titleKey] !== $aliases[$idKey]) {
                throw new UnexpectedValueException('A supplementary module has ambiguous category aliases.');
            }
            $key = $aliases[$titleKey] ?? $aliases[$idKey] ?? $titleKey;
            if (!isset($groups[$key])) {
                $groups[$key] = ['id' => nisha_module_id($skill, $module['title']), 'title' => trim($module['title']), 'skill' => $skill, 'items' => [], 'resources' => []];
            }
            $aliases[$titleKey] = $key;
            $aliases[$idKey] = $key;
            foreach ($module['items'] as $item) {
                if (isset($itemGroups[$item['id']]) && $itemGroups[$item['id']] !== $key) {
                    throw new UnexpectedValueException('A supplementary question duplicates another practice category.');
                }
                $itemGroups[$item['id']] = $key;
                nisha_append_learning_item($groups[$key], $item, $module['resources']);
            }
        }
        $moduleMetadata = [];
        foreach ($groups as $group) {
            if ($group['items'] === []) {
                continue;
            }
            $items = array_values($group['items']);
            $practiceId = 'practice.' . $course['id'] . '.' . $group['id'];
            if (strlen($practiceId) > 64 || isset($sessions[$practiceId])) {
                throw new UnexpectedValueException('A practice launch ID is too long or duplicated.');
            }
            $resources = $group['resources'];
            ksort($resources);
            $sectionId = 'practice-' . $group['skill'];
            $session = [
                'id' => $practiceId, 'name' => $course['name'] . ' · ' . $group['title'], 'exam' => $course['exam'],
                'description' => 'Focused ' . strtolower($group['title']) . ' practice. Take your time and check each response when you are ready.',
                'format_note' => 'The practice library combines full-mock items with additional original question-type exercises; some questions are shared across courses and modes. This session is untimed and the time shown is a suggestion. Objective checks give practice points. Writing and speaking offer optional AI coaching when configured, with self-review also available.',
                'session_type' => 'practice', 'timing_mode' => 'untimed', 'course_id' => $course['id'], 'module_id' => $group['id'],
                'resources' => $resources,
                'sections' => [[
                    'id' => $sectionId, 'title' => $group['title'], 'navigation' => 'free',
                    'duration_seconds' => nisha_practice_suggested_seconds($items),
                    'instructions' => 'Work at your own pace. Check a response for feedback, or move freely between questions. Writing and speaking offer optional AI coaching. Recordings stay in this tab unless you choose to send one to OpenAI for review.',
                    'items' => $items,
                ]],
            ];
            $session['version'] = nisha_learning_version($session);
            $sessions[$practiceId] = $session;
            $moduleMetadata[] = ['id' => $group['id'], 'title' => $group['title'], 'skill' => $group['skill'], 'item_count' => count($items), 'practice_id' => $practiceId, 'section_id' => $sectionId];
        }
        usort($moduleMetadata, static fn (array $a, array $b): int => strcmp($a['skill'] . ':' . strtolower($a['title']), $b['skill'] . ':' . strtolower($b['title'])));
        $course['modules'] = $moduleMetadata;
        $course['sections'] = $sectionMetadata;
        $catalog[] = $course;
    }
    return $library = ['courses' => $catalog, 'sessions' => $sessions];
}

function nisha_learning_catalog(): array
{
    return nisha_learning_library()['courses'];
}

/** Resolve only known server-built content; no client question definitions. */
function nisha_resolve_exam(string $id): ?array
{
    if (strlen($id) > 64 || !preg_match('/\A[a-z0-9][a-z0-9.-]*\z/D', $id)) {
        return null;
    }
    $full = nisha_exams();
    if (isset($full[$id])) {
        return $full[$id];
    }
    if (str_starts_with($id, 'xp.') || str_starts_with($id, 'xm.')) {
        require_once __DIR__ . '/generated-public-library.php';
        return nisha_generated_public_resolve_exam($id);
    }
    if (str_starts_with($id, 'gp.') || str_starts_with($id, 'gm.')) {
        require_once __DIR__ . '/generated-library.php';
        return nisha_generated_resolve_exam($id);
    }
    if (!str_starts_with($id, 'practice.') && !str_starts_with($id, 'section.')) {
        return null;
    }
    return nisha_learning_library()['sessions'][$id] ?? null;
}
