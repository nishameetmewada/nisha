<?php
declare(strict_types=1);

// Pure catalogue/content/grading checks. No database or web server is needed.
$root = dirname(__DIR__);
require_once $root . '/includes/exam-data.php';
require_once $root . '/includes/exam-grading.php';
if (!is_file($root . '/includes/learning-catalog.php')) {
    fwrite(STDERR, "FAIL: includes/learning-catalog.php is not available.\n");
    exit(1);
}
require_once $root . '/includes/learning-catalog.php';

$checks = 0;
$failures = [];
function learning_check(bool $condition, string $message): void
{
    global $checks, $failures;
    ++$checks;
    if (!$condition) {
        $failures[] = $message;
    }
}
function learning_has_text(mixed $value): bool
{
    return is_string($value) && trim($value) !== '';
}
function learning_private_keys(mixed $value): array
{
    $leaks = [];
    if (is_array($value)) {
        foreach ($value as $key => $child) {
            if (in_array((string) $key, ['answer', 'explanation', 'correct_text'], true)) {
                $leaks[] = (string) $key;
            }
            array_push($leaks, ...learning_private_keys($child));
        }
    }
    return $leaks;
}
function learning_items(array $exam): array
{
    return array_merge(...array_map(static fn (array $section): array => $section['items'], $exam['sections']));
}
function learning_correct_response(array $item): string|array
{
    if ($item['type'] === 'short') {
        return $item['answer'][0];
    }
    if ($item['type'] === 'gaps') {
        $response = [];
        foreach ($item['blanks'] as $blank) {
            $response[$blank['id']] = $blank['answer'][0];
        }
        return $response;
    }
    return in_array($item['type'], ['essay', 'spoken'], true) ? 'A response for ungraded self-review.' : $item['answer'];
}
function learning_wrong_response(array $item): string|array
{
    if ($item['type'] === 'choice') {
        foreach ($item['options'] as $option) {
            if ($option['id'] !== $item['answer']) {
                return $option['id'];
            }
        }
        throw new RuntimeException('A choice item has no distractor: ' . $item['id']);
    }
    if ($item['type'] === 'multi') {
        foreach ($item['options'] as $option) {
            if (!in_array($option['id'], $item['answer'], true)) {
                return [$option['id']];
            }
        }
        return [];
    }
    if ($item['type'] === 'reorder') {
        return array_reverse($item['answer']);
    }
    if ($item['type'] === 'highlight') {
        $tokens = preg_split('/\s+/u', trim($item['text']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach (array_keys($tokens) as $index) {
            if (!in_array((string) $index, $item['answer'], true)) {
                return [(string) $index];
            }
        }
        return [];
    }
    if ($item['type'] === 'gaps') {
        $response = [];
        foreach ($item['blanks'] as $blank) {
            $response[$blank['id']] = 'nisha-regression-incorrect';
            if (isset($blank['options'])) {
                $response[$blank['id']] = '';
                foreach ($blank['options'] as $option) {
                    if (!in_array($option, $blank['answer'], true)) {
                        $response[$blank['id']] = $option;
                        break;
                    }
                }
            }
        }
        return $response;
    }
    return 'nisha-regression-incorrect';
}
function learning_grade(array $exam, array $answers): array
{
    return nisha_grade_exam($exam, nisha_validate_exam_submission($exam, [
        'exam_id' => $exam['id'], 'exam_version' => $exam['version'],
        'attempt_id' => 'learning-regression-0001', 'answers' => $answers,
        'elapsed_seconds' => 1, 'completed_sections' => array_column($exam['sections'], 'id'),
        'assisted' => ($exam['session_type'] ?? '') === 'practice',
    ]));
}
function learning_validate_session(array $exam, string $path): void
{
    learning_check(learning_has_text($exam['id'] ?? null) && learning_has_text($exam['version'] ?? null), "$path needs a stable ID and version");
    learning_check(learning_private_keys(nisha_public_exam($exam)) === [], "$path leaks answer/review data to candidates");
    $ids = [];
    $correct = [];
    $wrong = [];
    foreach (learning_items($exam) as $item) {
        $id = $item['id'];
        $ids[] = $id;
        learning_check(learning_has_text($item['prompt'] ?? null), "$path.$id has no question");
        learning_check(learning_has_text($item['task_type'] ?? null), "$path.$id has no format label");
        foreach (['resource_id', 'secondary_resource_id'] as $resourceField) {
            if (isset($item[$resourceField])) {
                learning_check(isset($exam['resources'][$item[$resourceField]]), "$path.$id references a missing $resourceField");
            }
        }
        if (in_array($item['type'], ['essay', 'spoken'], true)) {
            learning_check(is_array($item['rubric'] ?? null) && count($item['rubric']) > 0, "$path.$id needs meaningful self-review guidance");
            foreach ($item['rubric'] ?? [] as $criterion) {
                learning_check(learning_has_text($criterion), "$path.$id has an empty self-review criterion");
            }
        } else {
            learning_check(learning_has_text($item['explanation'] ?? null), "$path.$id needs an answer explanation");
        }
        $correct[$id] = learning_correct_response($item);
        $wrong[$id] = learning_wrong_response($item);
    }
    learning_check($ids !== [] && count(array_unique($ids)) === count($ids), "$path needs nonempty unique question IDs");
    foreach ($exam['resources'] ?? [] as $resourceId => $resource) {
        learning_check(learning_has_text($resource['title'] ?? null), "$path.$resourceId has no resource title");
        $kind = $resource['kind'] ?? '';
        learning_check(in_array($kind, ['passage', 'audio', 'chart', 'table', 'diagram'], true), "$path.$resourceId has an unsupported resource type");
        if ($kind === 'diagram') {
            $diagram = $resource['diagram'] ?? [];
            $nodeIds = [];
            learning_check(count($diagram['nodes'] ?? []) >= 2, "$path.$resourceId needs a meaningful diagram");
            foreach ($diagram['nodes'] ?? [] as $node) {
                $nodeIds[] = $node['id'];
                learning_check(learning_has_text($node['label'] ?? null), "$path.$resourceId contains an unlabeled diagram node");
                foreach (['x', 'y', 'width', 'height'] as $dimension) {
                    learning_check(isset($node[$dimension]) && is_numeric($node[$dimension]) && $node[$dimension] >= 0 && $node[$dimension] <= 100, "$path.$resourceId diagram has invalid $dimension");
                }
            }
            learning_check(count($nodeIds) === count(array_unique($nodeIds)), "$path.$resourceId repeats diagram node IDs");
            foreach ($diagram['edges'] ?? [] as $edge) {
                learning_check(in_array($edge['from'] ?? null, $nodeIds, true) && in_array($edge['to'] ?? null, $nodeIds, true), "$path.$resourceId diagram edge points to a missing node");
            }
        } elseif ($kind === 'table') {
            $columns = $resource['columns'] ?? [];
            $rows = $resource['rows'] ?? [];
            learning_check(count($columns) >= 2 && count($rows) >= 2, "$path.$resourceId needs a real table with headers and data");
            foreach ($rows as $row) {
                learning_check(count($row) === count($columns), "$path.$resourceId table has misaligned cells");
            }
        } elseif ($kind === 'chart') {
            $chart = $resource['chart'] ?? [];
            $categories = $chart['categories'] ?? [];
            learning_check(count($categories) >= 2 && count($chart['series'] ?? []) > 0, "$path.$resourceId needs meaningful chart data");
            foreach ($chart['series'] ?? [] as $series) {
                learning_check(count($series['values'] ?? []) === count($categories), "$path.$resourceId chart values do not match category labels");
            }
        } elseif ($kind === 'audio') {
            learning_check(learning_has_text($resource['audio_script'] ?? null), "$path.$resourceId audio has no original script");
        } elseif ($kind === 'passage') {
            learning_check(count($resource['paragraphs'] ?? []) > 0, "$path.$resourceId has no readable passage");
        }
    }
    try {
        $allCorrect = learning_grade($exam, $correct);
        $allWrong = learning_grade($exam, $wrong);
        $allSkipped = learning_grade($exam, []);
        learning_check($allCorrect['objective_score'] === $allCorrect['objective_total'], "$path rejects or fails to credit one of its canonical answers");
        learning_check($allWrong['objective_score'] === 0, "$path incorrectly credits a distractor/incorrect response");
        learning_check($allSkipped['objective_score'] === 0 && $allSkipped['answered_items'] === 0, "$path invents responses or points for skipped items");
        learning_check($allSkipped['objective_total'] === $allCorrect['objective_total'], "$path removes skipped items from the points denominator");
        foreach ($allCorrect['review'] as $review) {
            if (in_array($review['type'], ['essay', 'spoken'], true)) {
                learning_check($review['graded'] === false && $review['score'] === null && $review['max_score'] === null, "$path fabricates a writing/speaking score");
            }
        }
    } catch (Throwable $exception) {
        learning_check(false, "$path cannot validate/grade its real questions: " . $exception->getMessage());
    }
}

$catalog = nisha_learning_catalog();
learning_check(is_array($catalog) && count($catalog) === 4, 'The learning catalogue must offer all four courses');
$courseIds = [];
$fullIds = [];
$resolved = [];
$skillsByCourse = [];
$moduleLabels = [];
foreach ($catalog as $course) {
    $courseId = $course['id'];
    $courseIds[] = $courseId;
    $fullIds[] = $course['full_exam_id'];
    learning_check(learning_has_text($course['name'] ?? null), "$courseId has no learner-facing name");
    $full = nisha_resolve_exam($course['full_exam_id']);
    learning_check(is_array($full), "$courseId does not resolve to a full mock");
    if (!is_array($full)) {
        continue;
    }
    $resolved[$full['id']] = $full;
    learning_validate_session($full, $full['id']);
    if (str_contains($courseId, 'ielts')) {
        $result = learning_grade($full, []);
        learning_check(array_column($result['sections'], 'total') === [40, 40, 0, 0], "$courseId full mock needs exactly /40 listening, /40 reading, ungraded writing and speaking");
    }
    $moduleIds = [];
    foreach ($course['modules'] as $module) {
        $moduleIds[] = $module['id'];
        $practice = nisha_resolve_exam($module['practice_id']);
        learning_check(is_array($practice), "$courseId module {$module['id']} has a broken practice link");
        if (!is_array($practice)) {
            continue;
        }
        $resolved[$practice['id']] = $practice;
        learning_check(($practice['session_type'] ?? null) === 'practice' && ($practice['timing_mode'] ?? null) === 'untimed', "{$practice['id']} must resolve to untimed practice");
        learning_check(count($practice['sections']) === 1 && ($practice['sections'][0]['navigation'] ?? null) === 'free', "{$practice['id']} must allow free practice navigation");
        learning_check(count(learning_items($practice)) === $module['item_count'] && $module['item_count'] > 0, "{$practice['id']} card count does not match real questions");
        $skillsByCourse[$courseId][$module['skill']] = true;
        $moduleLabels[$courseId][$module['skill']][] = $module['title'];
        learning_validate_session($practice, $practice['id']);
    }
    learning_check(count($moduleIds) === count(array_unique($moduleIds)), "$courseId repeats practice module IDs");
    $sectionIds = [];
    foreach ($course['sections'] as $section) {
        $sectionIds[] = $section['id'];
        $session = nisha_resolve_exam($section['exam_id']);
        learning_check(is_array($session), "$courseId section {$section['id']} does not resolve");
        if (!is_array($session)) {
            continue;
        }
        $resolved[$session['id']] = $session;
        learning_check(($session['session_type'] ?? null) === 'section' && ($session['timing_mode'] ?? null) !== 'untimed', "{$session['id']} must stay a timed sectional test");
        learning_check(count($session['sections']) === 1 && count(learning_items($session)) === $section['item_count'], "{$session['id']} sectional card count is inaccurate");
        learning_check($session['sections'][0]['duration_seconds'] === $section['duration_seconds'], "{$session['id']} section timer differs from its card");
        learning_validate_session($session, $session['id']);
    }
    learning_check(count($sectionIds) === count(array_unique($sectionIds)), "$courseId repeats section IDs");
    learning_check(count($course['sections']) === count($full['sections']), "$courseId must expose every full-mock section for separate testing");
    if (str_contains($courseId, 'pte')) {
        $expected = str_contains($courseId, 'core') ? 19 : 22;
        learning_check(count($course['modules']) >= $expected, "$courseId needs at least $expected distinct task modules");
        $types = array_unique(array_column(learning_items($full), 'task_type'));
        $practiceTypes = [];
        foreach ($course['modules'] as $module) {
            foreach (learning_items($resolved[$module['practice_id']]) as $item) {
                $practiceTypes[] = $item['task_type'];
            }
        }
        learning_check(array_diff($types, $practiceTypes) === [], "$courseId practice leaves a full-mock format unrepresented");
    }
    if ($courseId === 'pte-core') {
        learning_check(array_map(static fn (array $section): int => count($section['items']), $full['sections']) === [30, 15, 15], 'PTE Core must retain its 60-item configured mock');
        learning_check(array_column($full['sections'], 'duration_seconds') === [3600, 1800, 1800], 'PTE Core must retain its configured 120-minute section budgets');
        $types = array_unique(array_column(learning_items($full), 'task_type'));
        learning_check(count($types) === 19 && array_intersect($types, ['Retell Lecture', 'Summarize Group Discussion', 'Write Essay', 'Highlight Correct Summary']) === [], 'PTE Core must use its own 19-format route');
        foreach (learning_items($full) as $item) {
            $limits = match ($item['task_type']) {
                'Summarize Written Text' => [25, 50], 'Summarize Spoken Text' => [20, 30],
                'Write Email' => [50, 120], default => null,
            };
            if ($limits !== null) {
                learning_check([$item['min_words'] ?? null, $item['max_words'] ?? null] === $limits, "PTE Core {$item['task_type']} needs Core-specific word limits");
            }
        }
    }
}
learning_check(count(array_unique($courseIds)) === 4 && count(array_unique($fullIds)) === 4, 'Four distinct courses must resolve to four distinct full mocks');
foreach (['ielts-academic-01', 'ielts-general-01', 'pte-academic-01', 'pte-core-01'] as $id) {
    learning_check(in_array($id, $fullIds, true), "$id must be linked from its learning course");
}
foreach (['../config/config.php', 'practice.ielts-academic.missing-module', 'section.ielts-academic.invalid-skill', 'practice.unknown.short', 'pte-core-02', '', "practice.ielts-academic.\0bad", str_repeat('a', 300)] as $invalidId) {
    try {
        learning_check(nisha_resolve_exam($invalidId) === null, 'Resolver accepted a non-whitelisted or malformed ID');
    } catch (InvalidArgumentException $exception) {
        learning_check(true, 'Malformed ID rejected');
    }
}

$ieltsCoverage = [
    'listening' => [
        'single-answer multiple choice' => '/^multiple choice$/i',
        'multiple-answer choice' => '/multiple.answer/i', 'matching' => '/matching/i',
        'form completion' => '/form completion/i', 'note completion' => '/note completion/i',
        'map/plan/diagram labelling' => '/map.*plan.*diagram/i',
        'table completion' => '/table completion/i', 'flowchart completion' => '/flow.?chart completion/i',
        'summary completion' => '/summary completion/i', 'sentence completion' => '/sentence completion/i',
        'short answers' => '/short.answer/i',
    ],
    'reading' => [
        'True/False/Not Given' => '/true.*false.*not given/i',
        'Yes/No/Not Given' => '/yes.*no.*not given/i',
        'matching information' => '/matching information/i', 'matching headings' => '/matching headings/i',
        'matching features' => '/matching features/i', 'matching sentence endings' => '/matching sentence endings/i',
        'sentence completion' => '/sentence completion/i', 'summary completion' => '/summary completion/i',
        'note completion' => '/note completion/i', 'table completion' => '/table completion/i',
        'flowchart completion' => '/flow.?chart completion/i', 'diagram labels' => '/diagram label/i',
        'multiple choice' => '/multiple choice/i', 'short answers' => '/short.answer/i',
    ],
    'writing' => [
        'opinion essay' => '/task 2.*opinion/i', 'discussion essay' => '/task 2.*discussion/i',
        'advantages/disadvantages essay' => '/task 2.*advantages.*disadvantages/i',
        'problem/solution essay' => '/task 2.*problem.*solution/i', 'two-part essay' => '/task 2.*two.part/i',
    ],
    'speaking' => ['Part 1' => '/part 1/i', 'Part 2' => '/part 2/i', 'Part 3' => '/part 3/i'],
];
foreach (['ielts-academic', 'ielts-general'] as $courseId) {
    foreach ($ieltsCoverage as $skill => $formats) {
        $labels = $moduleLabels[$courseId][$skill] ?? [];
        foreach ($formats as $name => $pattern) {
            learning_check(count(array_filter($labels, static fn (string $label): bool => preg_match($pattern, $label) === 1)) > 0, "$courseId lacks a real $skill module for $name");
        }
    }
}
foreach (['Line graph', 'Pie chart', 'Table', 'Map', 'Process', 'Mixed charts'] as $format) {
    $pattern = '/task 1.*' . preg_quote($format, '/') . '/i';
    learning_check(count(array_filter($moduleLabels['ielts-academic']['writing'] ?? [], static fn (string $label): bool => preg_match($pattern, $label) === 1)) > 0, "IELTS Academic lacks Writing Task 1 $format practice");
}
foreach (['Formal letter', 'Semi-formal letter', 'Informal letter'] as $format) {
    learning_check(count(array_filter($moduleLabels['ielts-general']['writing'] ?? [], static fn (string $label): bool => str_ends_with(strtolower($label), strtolower('· ' . $format)))) > 0, "IELTS General lacks an explicit $format module");
}

// Supplemental visual tasks must provide structured resources rather than a
// label-only module. Schema is checked within each resolved session above.
$resourceKinds = [];
$chartTypes = [];
foreach ($resolved as $exam) {
    foreach ($exam['resources'] ?? [] as $resource) {
        $resourceKinds[$resource['kind'] ?? ''] = true;
        if (($resource['kind'] ?? '') === 'chart') {
            $chartTypes[$resource['chart']['type'] ?? 'bar'] = true;
        }
    }
}
foreach (['diagram', 'table'] as $kind) {
    learning_check(isset($resourceKinds[$kind]), "The learning library must include real $kind resources");
}
foreach (['bar', 'line', 'pie'] as $type) {
    learning_check(isset($chartTypes[$type]), "The learning library must include real $type chart data");
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " of $checks learning checks failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: $checks learning catalogue/content/grading checks across " . count($resolved) . " real sessions.\n";
