<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/generated-public-library.php';
require_once dirname(__DIR__) . '/includes/learning-catalog.php';
$checks = 0;
function generated_check(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new LogicException('Generated library: ' . $label);
    ++$checks;
}
function generated_no_answers(array $value): bool
{
    foreach ($value as $key => $part) {
        if (in_array($key, ['answer', 'explanation', 'correct_text'], true)) return false;
        if (is_array($part) && !generated_no_answers($part)) return false;
    }
    return true;
}
function generated_correct_answers(array $session): array
{
    $answers = [];
    foreach (nisha_exam_items($session) as $id => $item) {
        $answers[$id] = match ($item['type']) {
            'essay' => 'A fictional response used only to verify this local grading fixture.',
            'spoken' => 'A spoken-response transcript used only for this local grading fixture.',
            'short' => $item['answer'][0],
            'gaps' => array_column(array_map(static fn (array $blank): array => ['id' => $blank['id'], 'value' => $blank['answer'][0]], $item['blanks']), 'value', 'id'),
            default => $item['answer'],
        };
    }
    return $answers;
}
function generated_submission(array $session, array $answers): array
{
    return ['exam_id' => $session['id'], 'exam_version' => $session['version'], 'attempt_id' => 'generated-fixture-00001',
        'answers' => $answers, 'elapsed_seconds' => 0, 'completed_sections' => array_column($session['sections'], 'id'), 'assisted' => true];
}
try {
    $stats = nisha_generated_public_stats();
    generated_check($stats['question_count'] === 30000 && $stats['mock_count'] === 3000 && count($stats['courses']) === 4, 'Count slots across all four courses.');
    generated_check($stats['teacher_reviewed'] === false && $stats['content_source'] === 'generated', 'Generated totals do not impersonate reviewed inventory.');
    generated_check(str_contains($stats['disclosure'], 'not teacher-reviewed') && str_contains($stats['disclosure'], 'reuse') && str_contains($stats['disclosure'], 'not a replica'), 'Disclosure explains review status, reuse and exam limits.');
    foreach (['gp.ielts-academic.00001', 'gm.ielts-academic.0001', 'xp.ielts-academic.00000', 'xp.ielts-academic.07501', 'xp.ielts-academic.0001', 'xm.ielts-academic.00001', 'xm.ielts-academic.0751', 'xp.bad-course.00001', 'xp.ielts-academic.00001/../'] as $id) {
        generated_check(nisha_generated_public_resolve_exam($id) === null, 'Reject malformed, unavailable and reviewed namespace IDs.');
    }
    // Independent reference checks pin actual source meaning and expected task
    // controls instead of accepting merely self-consistent generated answer keys.
    $first = nisha_resolve_exam('xp.ielts-academic.00001');
    $firstItem = array_values(nisha_exam_items($first))[0];
    $correctText = array_column($firstItem['options'], 'text', 'id')[$firstItem['answer']];
    generated_check($correctText === 'understand barriers faced by people who do not participate', 'Known first reading answer follows the stated evaluation purpose.');
    $gap = nisha_resolve_exam('xp.pte-core.00002');
    generated_check($gap['sections'][0]['items'][0]['type'] === 'gaps' && $gap['sections'][0]['items'][0]['blanks'][0]['answer'] === ['non-users'], 'PTE Core reading note has the known supported term.');
    $highlight = nisha_resolve_exam('xp.pte-academic.00005');
    $highlightItem = $highlight['sections'][0]['items'][0];
    generated_check($highlightItem['type'] === 'highlight' && $highlightItem['answer'] === ['3'] && preg_split('/\s+/', $highlightItem['text'])[3] === 'ignore', 'Highlight task marks the displayed contradiction.');
    generated_check(str_contains($highlight['resources'][$highlightItem['resource_id']]['audio_script'], 'should examine evidence'), 'Highlight comparison is supported by the actual audio script.');

    foreach (nisha_generated_courses() as $course => $definition) {
        $page = nisha_generated_public_page('questions', $course);
        generated_check($page['total'] === 7500 && count($page['items']) === 24 && $page['pages'] === 313, 'Question metadata is paginated per course.');
        generated_check(!isset($page['items'][0]['sections'], $page['items'][0]['resources']), 'Page cards contain metadata only.');
        $last = nisha_generated_public_page('questions', $course, 99999, 48);
        generated_check($last['page'] === 157 && count($last['items']) === 12 && str_ends_with(end($last['items'])['id'], '.07500'), 'Last question page clamps bounds and reaches final slot.');
        $filteredTotal = 0;
        foreach (['reading' => 2250, 'listening' => 2250, 'writing' => 1500, 'speaking' => 1500] as $skill => $count) {
            $filtered = nisha_generated_public_page('questions', $course, 1, 48, $skill);
            generated_check($filtered['total'] === $count && count(array_unique(array_column($filtered['items'], 'skill'))) === 1 && $filtered['items'][0]['skill'] === $skill, 'Skill filters contain only the selected skill.');
            $filteredTotal += $filtered['total'];
        }
        generated_check($filteredTotal === 7500, 'Skill totals partition the course without inflated counts.');
        foreach (nisha_generated_public_filters($course)['task_types'] as $type) {
            $filtered = nisha_generated_public_page('questions', $course, 1, 3, 'all', $type['id']);
            generated_check($filtered['total'] > 0 && count(array_unique(array_column($filtered['items'], 'task_type'))) === 1 && $filtered['items'][0]['task_type'] === $type['id'], 'Task filter maps to real matching question types.');
        }
        generated_check(nisha_generated_public_page('questions', $course, 1, 24, 'speaking', 'Write Essay')['total'] === 0, 'Incompatible filters return no results.');
        generated_check(nisha_generated_public_page('mocks', $course, 9999, 48)['total'] === 750 && count(nisha_generated_public_page('mocks', $course, 9999, 48)['items']) === 30, 'Mock pages clamp and expose only metadata.');

        $questionNumbers = in_array('--all', $argv, true) ? range(1, 7500) : array_merge(range(1, 20), [7491, 7492, 7493, 7494, 7495, 7496, 7497, 7498, 7499, 7500]);
        foreach ($questionNumbers as $number) {
            $id = 'xp.' . $course . '.' . str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $session = nisha_resolve_exam($id);
            nisha_content_validate_session($session);
            generated_check($session['id'] === $id && $session['content_source'] === 'generated' && $session['teacher_reviewed'] === false && $session['session_type'] === 'practice', 'Public question resolves with distinct source identity.');
            generated_check(generated_no_answers(exam_public_data($session)), 'Question public data recursively strips all answer keys.');
            $payload = nisha_validate_exam_submission($session, generated_submission($session, generated_correct_answers($session)));
            $result = nisha_grade_exam($session, $payload);
            generated_check($result['objective_score'] === $result['objective_total'] && $result['total_items'] === 1, 'Accepted objective answers grade fully; writing and speaking remain separate.');
        }
        $mockNumbers = in_array('--all', $argv, true) ? range(1, 750) : [1, 2, 375, 749, 750];
        foreach ($mockNumbers as $number) {
            $id = 'xm.' . $course . '.' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $session = nisha_resolve_exam($id);
            nisha_content_validate_session($session);
            $base = nisha_exams()[$definition['base']];
            generated_check($session['session_type'] === 'full' && $session['timing_mode'] === 'timed' && count(nisha_exam_items($session)) === count(nisha_exam_items($base)), 'Generated full paper retains the configured full-mock count and access category.');
            generated_check(array_column($session['sections'], 'duration_seconds') === array_column($base['sections'], 'duration_seconds'), 'Generated paper retains each configured section budget.');
            generated_check(!isset($session['review_dependencies']) && $session['content_source'] === 'generated' && $session['teacher_reviewed'] === false, 'Unreviewed papers never carry a teacher publication dependency.');
            foreach (nisha_exam_items($session) as $item) generated_check(str_starts_with($item['id'], 'x-') && isset($session['resources'][$item['resource_id']]), 'Each isolated generated item has a valid source.');
            generated_check(generated_no_answers(exam_public_data($session)), 'Full paper public data strips nested answer keys.');
            $payload = nisha_validate_exam_submission($session, generated_submission($session, generated_correct_answers($session)));
            $result = nisha_grade_exam($session, $payload);
            generated_check($result['objective_score'] === $result['objective_total'], 'Full paper accepted objective answers grade correctly.');
        }
    }
    generated_check(nisha_generated_public_page('questions', 'unknown')['total'] === 0 && nisha_generated_public_page('other', 'ielts-academic')['total'] === 0, 'Reject invalid catalogue kinds and courses.');
    generated_check(nisha_generated_public_page('questions', 'ielts-academic', 1, 9999)['per_page'] === 48, 'Bound page sizes independently of client input.');
    generated_check(nisha_resolve_exam('xp.ielts-academic.00001') === $first, 'Regeneration is deterministic for the same content version.');
    generated_check(nisha_generated_parse_id('xp.ielts-academic.00001') === null && nisha_generated_parse_id('xm.ielts-academic.0001') === null, 'Public generation IDs cannot enter the teacher publication workflow.');
    echo 'PASS: ' . $checks . ' public generated-content checks. Local deterministic variations; no model call, publication or database mutation.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
