<?php
declare(strict_types=1);

// Run with /workspace/.tools/bin/php tests/exam-integrity.php (or PHP 8.2+).
// These checks need neither a running web server nor a database.
require_once dirname(__DIR__) . '/includes/exam-data.php';
require_once dirname(__DIR__) . '/includes/exam-grading.php';

$checks = 0;
$failures = [];

function check(bool $condition, string $message): void
{
    global $checks, $failures;
    ++$checks;
    if (!$condition) {
        $failures[] = $message;
    }
}

function words(string $text): int
{
    return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
}

function nonempty_string(mixed $value): bool
{
    return is_string($value) && trim($value) !== '';
}

function no_review_keys(mixed $value, string $path = 'public exam'): void
{
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $key => $child) {
        check(!in_array((string) $key, ['answer', 'explanation', 'correct_text'], true), "$path leaks $key");
        no_review_keys($child, "$path.$key");
    }
}

function valid_answers(array $accepted, string $path, ?int $maxWords = null): void
{
    check($accepted !== [], "$path has no accepted answers");
    foreach ($accepted as $answer) {
        check(nonempty_string($answer), "$path has a blank/non-string accepted answer");
        if ($maxWords !== null && is_string($answer)) {
            check(words($answer) <= $maxWords, "$path accepted answer exceeds its own word limit");
        }
    }
}

function validate_item(array $item, array $resources, string $path): void
{
    $type = $item['type'] ?? '';
    check(in_array($type, ['choice', 'multi', 'short', 'gaps', 'reorder', 'highlight', 'essay', 'spoken'], true), "$path has an unsupported interaction");
    check(nonempty_string($item['prompt'] ?? null), "$path needs a prompt");
    check(nonempty_string($item['task_type'] ?? null), "$path needs a task type");
    if (isset($item['resource_id'])) {
        check(isset($resources[$item['resource_id']]), "$path references a missing resource");
    }
    $graded = !in_array($type, ['essay', 'spoken'], true);
    if ($graded) {
        check(nonempty_string($item['explanation'] ?? null), "$path needs a useful answer explanation");
    } else {
        check(!array_key_exists('answer', $item), "$path pretends an ungraded response has a canonical answer");
        check(is_array($item['rubric'] ?? null) && count($item['rubric']) > 0, "$path needs a self-review rubric");
    }
    if (isset($item['min_words'], $item['max_words'])) {
        check($item['min_words'] <= $item['max_words'], "$path has conflicting word limits");
    }
    $maxWords = isset($item['max_words']) ? (int) $item['max_words'] : null;
    if ($maxWords !== null) {
        check($maxWords > 0, "$path has a nonpositive word limit");
    }
    $optionIds = [];
    if (in_array($type, ['choice', 'multi', 'reorder'], true)) {
        check(is_array($item['options'] ?? null) && count($item['options']) >= 2, "$path needs at least two options");
        foreach ($item['options'] ?? [] as $option) {
            check(nonempty_string($option['id'] ?? null), "$path has an option without an ID");
            check(nonempty_string($option['text'] ?? null), "$path has an empty option");
            $optionIds[] = $option['id'] ?? '';
        }
        check(count(array_unique($optionIds)) === count($optionIds), "$path repeats option IDs");
    }
    if ($type === 'choice') {
        check(is_string($item['answer'] ?? null) && in_array($item['answer'], $optionIds, true), "$path answer is not one of its options");
    } elseif ($type === 'multi' || $type === 'reorder') {
        $answer = $item['answer'] ?? [];
        check(is_array($answer) && count($answer) > 0, "$path needs answer IDs");
        if (is_array($answer)) {
            check(count(array_unique($answer)) === count($answer), "$path repeats answer IDs");
            foreach ($answer as $id) {
                check(in_array($id, $optionIds, true), "$path answer references a missing option");
            }
            if ($type === 'reorder') {
                check(count($answer) === count($optionIds), "$path order omits an option");
                check(count($answer) >= 3, "$path needs enough paragraphs for meaningful adjacent-pair scoring");
            } elseif (isset($item['select_count'])) {
                check($item['select_count'] === count($answer), "$path select_count differs from its answer count");
            }
        }
    } elseif ($type === 'short') {
        check(is_array($item['answer'] ?? null), "$path needs accepted answer strings");
        valid_answers(is_array($item['answer'] ?? null) ? $item['answer'] : [], $path, $maxWords);
    } elseif ($type === 'gaps') {
        $text = $item['text'] ?? '';
        preg_match_all('/\{\{([^{}]+)\}\}/u', is_string($text) ? $text : '', $matches);
        $placeholders = $matches[1];
        check(count($placeholders) > 0, "$path has no gap placeholders");
        check(count(array_unique($placeholders)) === count($placeholders), "$path repeats gap placeholders");
        $blankIds = [];
        foreach ($item['blanks'] ?? [] as $blank) {
            $blankId = $blank['id'] ?? '';
            $blankIds[] = $blankId;
            check(nonempty_string($blankId), "$path has an unnamed blank");
            $accepted = $blank['answer'] ?? [];
            check(is_array($accepted), "$path.$blankId needs accepted answers");
            valid_answers(is_array($accepted) ? $accepted : [], "$path.$blankId", isset($blank['max_words']) ? (int) $blank['max_words'] : $maxWords);
            if (isset($blank['options'])) {
                check(is_array($blank['options']) && count($blank['options']) >= 2, "$path.$blankId needs dropdown alternatives");
                foreach (is_array($accepted) ? $accepted : [] as $answer) {
                    check(in_array($answer, $blank['options'], true), "$path.$blankId answer missing from dropdown");
                }
            }
        }
        sort($blankIds);
        sort($placeholders);
        check($blankIds === $placeholders, "$path blank IDs do not exactly match its placeholders");
    } elseif ($type === 'highlight') {
        $tokens = preg_split('/\s+/u', trim((string) ($item['text'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $answer = $item['answer'] ?? [];
        check(is_array($answer) && count($answer) > 0, "$path needs incorrect-word indices");
        if (is_array($answer)) {
            check(count(array_unique($answer)) === count($answer), "$path repeats incorrect-word indices");
            foreach ($answer as $index) {
                check(is_string($index) && ctype_digit($index) && (int) $index < count($tokens), "$path has an invalid word index");
            }
        }
    }
}

function grade_canonical_answers(array $exam): array
{
    $answers = [];
    foreach ($exam['sections'] as $section) {
        foreach ($section['items'] as $item) {
            if ($item['type'] === 'short') {
                $answers[$item['id']] = $item['answer'][0];
            } elseif ($item['type'] === 'gaps') {
                $answers[$item['id']] = [];
                foreach ($item['blanks'] as $blank) {
                    $answers[$item['id']][$blank['id']] = $blank['answer'][0];
                }
            } elseif (!in_array($item['type'], ['essay', 'spoken'], true)) {
                $answers[$item['id']] = $item['answer'];
            }
        }
    }
    $payload = [
        'exam_id' => $exam['id'], 'exam_version' => $exam['version'],
        'attempt_id' => 'canonical-answer-check-0001', 'answers' => $answers,
        'elapsed_seconds' => 0, 'completed_sections' => array_column($exam['sections'], 'id'),
        'assisted' => false,
    ];
    return nisha_grade_exam($exam, nisha_validate_exam_submission($exam, $payload));
}

function validate_full_exam_data(): void
{
    $exams = nisha_exams();
    check(is_array($exams) && isset($exams['ielts-academic-01'], $exams['pte-academic-01']), 'Both promised full exams must be available');
    if ($exams === []) {
        return;
    }

    foreach ($exams as $examId => $exam) {
        check(($exam['id'] ?? null) === $examId, "$examId has an inconsistent ID");
        check(nonempty_string($exam['version'] ?? null), "$examId needs a version to invalidate stale saved answers");
        check(nonempty_string($exam['format_note'] ?? null), "$examId needs its unofficial-format caveat");
        $resources = $exam['resources'] ?? [];
        $ids = [];
        $sectionIds = [];
        $questionFingerprints = [];
        foreach ($exam['sections'] ?? [] as $section) {
            $sectionId = $section['id'] ?? '';
            check(nonempty_string($sectionId), "$examId has an unnamed section");
            $sectionIds[] = $sectionId;
            check(is_int($section['duration_seconds'] ?? null) && $section['duration_seconds'] > 0, "$examId.$sectionId needs a positive section budget");
            foreach ($section['items'] ?? [] as $item) {
                $id = $item['id'] ?? '';
                check(nonempty_string($id), "$examId has an unnamed question");
                $ids[] = $id;
                validate_item($item, $resources, "$examId.$id");
                // Repeated interaction instructions are fine; repeating the same
                // stimulus, question and options is not another original item.
                $resource = $resources[$item['resource_id'] ?? ''] ?? [];
                unset($resource['title']);
                $fingerprint = hash('sha256', serialize([
                    $item['type'], $item['task_type'] ?? '', $item['prompt'] ?? '',
                    $item['text'] ?? '', $item['options'] ?? [], $resource,
                ]));
                check(!isset($questionFingerprints[$fingerprint]), "$examId.$id duplicates question " . ($questionFingerprints[$fingerprint] ?? ''));
                $questionFingerprints[$fingerprint] = $id;
            }
        }
        check(count(array_unique($ids)) === count($ids), "$examId repeats question IDs");
        check(count(array_unique($sectionIds)) === count($sectionIds), "$examId repeats section IDs");
        foreach ($resources as $resourceId => $resource) {
            check(nonempty_string($resource['title'] ?? null), "$examId resource $resourceId has no title");
            $kind = $resource['kind'] ?? '';
            check(in_array($kind, ['passage', 'audio', 'chart'], true), "$examId resource $resourceId has an unsupported kind");
            if ($kind === 'passage') {
                check(is_array($resource['paragraphs'] ?? null) && count($resource['paragraphs']) > 0, "$resourceId has no passage");
            } elseif ($kind === 'audio') {
                check(nonempty_string($resource['audio_script'] ?? null), "$resourceId has no audio script");
            } elseif ($kind === 'chart') {
                $chart = $resource['chart'] ?? [];
                $categories = $chart['categories'] ?? [];
                check(is_array($categories) && count($categories) > 0, "$resourceId chart has no categories");
                check(is_array($chart['series'] ?? null) && count($chart['series']) > 0, "$resourceId chart has no values");
                foreach ($chart['series'] ?? [] as $series) {
                    check(nonempty_string($series['name'] ?? null), "$resourceId chart has an unnamed series");
                    check(count($series['values'] ?? []) === count($categories), "$resourceId chart labels and values differ in length");
                    foreach ($series['values'] ?? [] as $value) {
                        check(is_int($value) || is_float($value), "$resourceId chart has a non-numeric value");
                    }
                }
            }
        }
        no_review_keys(nisha_public_exam($exam));
        try {
            $result = grade_canonical_answers($exam);
            check($result['objective_total'] > 0 && $result['objective_score'] === $result['objective_total'], "$examId canonical accepted answers must validate and earn all available objective points");
            if ($examId === 'ielts-academic-01') {
                check(array_column($result['sections'], 'total') === [40, 40, 0, 0], 'IELTS must report exactly /40 listening, /40 reading, and ungraded writing/speaking');
                check(array_column($result['sections'], 'ungraded_items') === [0, 0, 2, 3], 'IELTS must preserve all five ungraded tasks in results');
            } elseif ($examId === 'pte-academic-01') {
                check(array_column($result['sections'], 'ungraded_items') === [35, 0, 1], 'PTE writing/speaking and spoken-summary essay require self-review');
            }
        } catch (Throwable $exception) {
            check(false, "$examId rejected its own accepted answers: " . $exception->getMessage());
        }
    }

    if (!isset($exams['ielts-academic-01'])) {
        return;
    }
    $ielts = $exams['ielts-academic-01'];
    $sections = $ielts['sections'];
    check(array_column($sections, 'duration_seconds') === [1800, 3600, 3600, 840], 'IELTS section order/budgets must be listening, reading, writing, speaking');
    check(array_map(static fn (array $section): int => count($section['items']), $sections) === [40, 40, 2, 3], 'IELTS needs exactly 40 listening, 40 reading, 2 writing, and 3 speaking items');
    if (count($sections) === 4) {
        $listeningIds = array_values(array_unique(array_column($sections[0]['items'], 'resource_id')));
        $readingIds = array_values(array_unique(array_column($sections[1]['items'], 'resource_id')));
        check(count($listeningIds) === 4, 'IELTS listening must use four distinct audio parts');
        check(count($readingIds) === 3, 'IELTS reading must use three distinct academic passages');
        $listeningWords = 0;
        foreach ($listeningIds as $id) {
            $resource = $ielts['resources'][$id] ?? [];
            check(($resource['kind'] ?? '') === 'audio', "IELTS listening $id must reference audio");
            $count = words((string) ($resource['audio_script'] ?? ''));
            check($count >= 500, "IELTS listening $id is too short for a full mock ($count words; minimum 500)");
            $listeningWords += $count;
        }
        check($listeningWords >= 2200, "IELTS listening scripts total only $listeningWords words (minimum 2200)");
        $readingWords = 0;
        foreach ($readingIds as $id) {
            $resource = $ielts['resources'][$id] ?? [];
            check(($resource['kind'] ?? '') === 'passage', "IELTS reading $id must reference a passage");
            $readingWords += words(implode(' ', $resource['paragraphs'] ?? []));
        }
        check($readingWords >= 2150 && $readingWords <= 2750, "IELTS passages total $readingWords words (expected 2150–2750)");
        check(array_column($sections[2]['items'], 'min_words') === [150, 250], 'IELTS writing requires 150/250-word targets');
        check(count(array_filter($sections[2]['items'], static fn (array $item): bool => $item['type'] === 'essay')) === 2, 'IELTS writing must be ungraded essay responses');
        check(count(array_filter($sections[3]['items'], static fn (array $item): bool => $item['type'] === 'spoken')) === 3, 'IELTS speaking must have three ungraded parts');
        check(($sections[3]['items'][1]['prep_seconds'] ?? null) === 60 && ($sections[3]['items'][1]['response_seconds'] ?? null) === 120, 'IELTS speaking Part 2 requires one minute preparation and two minutes speaking');
    }

    if (!isset($exams['pte-academic-01'])) {
        return;
    }
    $pte = $exams['pte-academic-01'];
    check(array_column($pte['sections'], 'duration_seconds') === [4800, 1500, 2100], 'PTE configured section budgets must total 140 minutes');
    check(array_map(static fn (array $section): int => count($section['items']), $pte['sections']) === [35, 15, 18], 'PTE needs 35 speaking/writing, 15 reading, and 18 listening items');
    $pteItems = array_merge(...array_column($pte['sections'], 'items'));
    check(count($pteItems) === 68, 'PTE full mock needs 68 original items');
    $typeCounts = array_count_values(array_column($pteItems, 'task_type'));
    check(count($typeCounts) === 22, 'PTE full mock must cover all 22 distinct task formats');
    $pteFormats = [
        [
            'Read Aloud' => [6, 'spoken'], 'Repeat Sentence' => [10, 'spoken'],
            'Describe Image' => [5, 'spoken'], 'Retell Lecture' => [2, 'spoken'],
            'Answer Short Question' => [5, 'spoken'], 'Summarize Group Discussion' => [2, 'spoken'],
            'Respond to a Situation' => [2, 'spoken'], 'Summarize Written Text' => [2, 'essay'],
            'Write Essay' => [1, 'essay'],
        ],
        [
            'Reading & Writing: Fill in the Blanks' => [5, 'gaps'],
            'Reading: Multiple Choice, Multiple Answers' => [2, 'multi'],
            'Reorder Paragraphs' => [2, 'reorder'], 'Reading: Fill in the Blanks' => [4, 'gaps'],
            'Reading: Multiple Choice, Single Answer' => [2, 'choice'],
        ],
        [
            'Summarize Spoken Text' => [1, 'essay'],
            'Listening: Multiple Choice, Multiple Answers' => [2, 'multi'],
            'Listening: Fill in the Blanks' => [3, 'gaps'], 'Highlight Correct Summary' => [2, 'choice'],
            'Listening: Multiple Choice, Single Answer' => [2, 'choice'],
            'Select Missing Word' => [2, 'choice'], 'Highlight Incorrect Words' => [2, 'highlight'],
            'Write from Dictation' => [4, 'short'],
        ],
    ];
    foreach ($pteFormats as $sectionIndex => $formats) {
        $items = $pte['sections'][$sectionIndex]['items'] ?? [];
        $actual = array_count_values(array_column($items, 'task_type'));
        check(count($actual) === count($formats), "PTE section $sectionIndex must contain its specified formats");
        foreach ($formats as $format => [$count, $interaction]) {
            check(($actual[$format] ?? 0) === $count, "PTE needs exactly $count instances of $format in its correct section");
            foreach ($items as $item) {
                if ($item['task_type'] !== $format) {
                    continue;
                }
                check($item['type'] === $interaction, "PTE {$item['id']} must use $interaction for $format");
                if ($sectionIndex === 2 || in_array($format, ['Repeat Sentence', 'Retell Lecture', 'Summarize Group Discussion'], true)) {
                    check(($pte['resources'][$item['resource_id'] ?? '']['kind'] ?? null) === 'audio', "PTE {$item['id']} needs an audio stimulus");
                }
                if ($format === 'Describe Image') {
                    check(($pte['resources'][$item['resource_id'] ?? '']['kind'] ?? null) === 'chart', "PTE {$item['id']} needs its own chart stimulus");
                } elseif ($format === 'Summarize Written Text') {
                    check(($item['min_words'] ?? null) === 5 && ($item['max_words'] ?? null) === 75, "PTE {$item['id']} needs its 5–75-word summary limits");
                }
            }
        }
    }

}
validate_full_exam_data();

// Small, deliberately different fixtures make the scoring expectations explicit.
// They do not derive expected scores from the implementation under test.
$options = [
    ['id' => 'a', 'text' => 'North'], ['id' => 'b', 'text' => 'South'],
    ['id' => 'c', 'text' => 'East'], ['id' => 'd', 'text' => 'West'],
];
$fixture = [
    'id' => 'fixture-exam', 'name' => 'Regression fixture', 'exam' => 'PTE', 'version' => 'fixture-1',
    'sections' => [[
        'id' => 'fixture-section', 'title' => 'Fixture section', 'items' => [
            ['id' => 'choice', 'type' => 'choice', 'options' => $options, 'answer' => 'b'],
            ['id' => 'multi', 'type' => 'multi', 'options' => $options, 'answer' => ['a', 'c']],
            ['id' => 'exact', 'type' => 'multi', 'options' => $options, 'answer' => ['a', 'c'], 'all_or_nothing' => true, 'select_count' => 2],
            ['id' => 'short', 'type' => 'short', 'answer' => ['Mill Road', 'Mill Rd'], 'max_words' => 2],
            ['id' => 'gaps', 'type' => 'gaps', 'blanks' => [
                ['id' => 'g1', 'answer' => ['trees']],
                ['id' => 'g2', 'answer' => ['summer'], 'options' => ['winter', 'summer']],
                ['id' => 'g3', 'answer' => ['five', '5']],
            ]],
            ['id' => 'order', 'type' => 'reorder', 'options' => $options, 'answer' => ['c', 'a', 'd', 'b']],
            ['id' => 'highlight', 'type' => 'highlight', 'text' => 'The narrow bridge closes before noon.', 'answer' => ['1', '4'], 'correct_text' => 'The wide bridge closes after noon.'],
            ['id' => 'essay', 'type' => 'essay', 'rubric' => ['Check evidence and organisation.']],
            ['id' => 'spoken', 'type' => 'spoken', 'rubric' => ['Reflect on clarity.']],
        ],
    ]],
];

function fixture_payload(array $answers = [], array $changes = []): array
{
    return array_replace([
        'exam_id' => 'fixture-exam', 'exam_version' => 'fixture-1', 'attempt_id' => 'regression-attempt-0001',
        'answers' => $answers, 'elapsed_seconds' => 120,
        'completed_sections' => ['fixture-section'], 'assisted' => false,
    ], $changes);
}

function grade_fixture(array $answers = [], array $changes = []): array
{
    global $fixture;
    return nisha_grade_exam($fixture, nisha_validate_exam_submission($fixture, fixture_payload($answers, $changes)));
}

function review_item(array $result, string $id): array
{
    foreach ($result['review'] as $item) {
        if ($item['id'] === $id) {
            return $item;
        }
    }
    throw new RuntimeException("Missing review item $id");
}

function rejects(array $payload, string $reason): void
{
    global $fixture;
    try {
        nisha_validate_exam_submission($fixture, $payload);
        check(false, "Submission accepted $reason");
    } catch (InvalidArgumentException $exception) {
        check(true, $reason);
    }
}

$blank = grade_fixture([], ['completed_sections' => []]);
check($blank['objective_score'] === 0 && $blank['objective_total'] === 13, 'Skipping all questions must retain all 13 available objective points');
check($blank['answered_items'] === 0 && $blank['total_items'] === 9, 'Skipped questions must not count as answered');
check($blank['incomplete'] === true, 'A missing completed section must be labelled incomplete');
check(count($blank['review']) === 9, 'Skipped and ungraded questions must still appear in review');
check($blank['sections'][0]['ungraded_items'] === 2, 'Essay and speech must be counted as ungraded');
foreach (['essay', 'spoken'] as $id) {
    $review = review_item($blank, $id);
    check($review['graded'] === false && $review['score'] === null && $review['max_score'] === null && $review['correct_answer'] === null, "$id must never receive a fabricated score or answer");
}

$correct = grade_fixture([
    'choice' => 'b', 'multi' => ['a', 'c'], 'exact' => ['c', 'a'],
    'short' => "  MILL \n ROAD.  ",
    'gaps' => ['g1' => 'Trees', 'g2' => 'summer', 'g3' => '5'],
    'order' => ['c', 'a', 'd', 'b'], 'highlight' => ['1', '4'],
    'essay' => 'My own written response.', 'spoken' => 'A private self-review note.',
]);
check($correct['objective_score'] === 13 && $correct['objective_total'] === 13, 'Correct answers across all objective interactions must earn 13/13');
check($correct['answered_items'] === 9, 'Nonempty writing and spoken notes count as responses without inventing marks');
check($correct['incomplete'] === false, 'All completed section IDs must produce a complete result');
check(review_item($correct, 'short')['score'] === 1, 'Short answers must tolerate case, surrounding whitespace, repeated spaces, and punctuation');
check(grade_fixture(['short' => 'mill rd'])['objective_score'] === 1, 'An explicit alternate accepted answer must earn credit');
check(grade_fixture(['choice' => 'a', 'short' => 'Elsewhere'])['objective_score'] === 0, 'Wrong choice and text answers must earn no credit');
check(grade_fixture(['multi' => ['a', 'c', 'b']])['objective_score'] === 1, 'Multiple-choice selections deduct one point for an incorrect option');
check(grade_fixture(['multi' => ['b', 'd']])['objective_score'] === 0, 'Multiple-choice deductions must stop at zero');
check(grade_fixture(['exact' => ['c', 'a']])['objective_score'] === 1, 'IELTS exact selections must ignore selection order');
check(grade_fixture(['exact' => ['a']])['objective_score'] === 0, 'IELTS all-or-nothing selections must not award partial credit');
check(grade_fixture(['gaps' => ['g1' => 'trees', 'g2' => 'winter', 'g3' => 'five']])['objective_score'] === 2, 'Gap marking must score each blank independently');
check(grade_fixture(['gaps' => ['g1' => '', 'g2' => '']])['answered_items'] === 0, 'Empty gap strings must not count as an answered item');
check(grade_fixture(['order' => ['d', 'b', 'c', 'a']])['objective_score'] === 2, 'Reordering must credit two correctly adjacent pairs even when neither pair is in its original position');
check(grade_fixture(['order' => ['b', 'd', 'a', 'c']])['objective_score'] === 0, 'Reversed paragraph pairs must not receive adjacency credit');
check(grade_fixture(['highlight' => ['1', '4', '0']])['objective_score'] === 1, 'Highlighting an extra word must deduct from correct selections');
check(grade_fixture(['highlight' => ['0', '2', '5']])['objective_score'] === 0, 'Wrong highlights must not create negative points');
check(review_item($correct, 'highlight')['correct_text'] === 'The wide bridge closes after noon.', 'Completed highlight review must include the corrected transcript');

// A hyphenated place name counts as one word, while a space-separated version
// contains two. Both normalise to the same letters, so this isolates the limit.
$wordLimitFixture = $fixture;
$wordLimitFixture['sections'][0]['items'] = [[
    'id' => 'oneword', 'type' => 'short', 'answer' => ['north-east'], 'max_words' => 1,
]];
foreach (['north-east' => 1, 'north east' => 0] as $response => $expected) {
    $payload = fixture_payload(['oneword' => $response]);
    $result = nisha_grade_exam($wordLimitFixture, nisha_validate_exam_submission($wordLimitFixture, $payload));
    check($result['objective_score'] === $expected, "Word limit must distinguish '$response' despite answer normalisation");
}

check(array_intersect(['band', 'overall_band', 'pte_score'], array_keys($correct)) === [], 'Practice results must not fabricate official IELTS/PTE scores');
check(str_contains(strtolower($correct['grading_note']), 'practice') && str_contains(strtolower($correct['grading_note']), 'self-review'), 'Results must explain practice points and ungraded writing/speech');
check(grade_fixture([], ['assisted' => true])['assisted'] === true, 'Assisted mode must remain visible in the result');

rejects(fixture_payload(['unknown' => 'x']), 'unknown question IDs');
rejects(fixture_payload(['choice' => 'not-an-option']), 'unavailable choice IDs');
rejects(fixture_payload(['choice' => ['b']]), 'an array supplied for a single choice');
rejects(fixture_payload(['short' => ['Mill Road']]), 'an array supplied for a written answer');
rejects(fixture_payload(['multi' => ['a', 'a']]), 'duplicate multi-selection IDs');
rejects(fixture_payload(['exact' => ['a', 'b', 'c']]), 'more than the configured exact selections');
rejects(fixture_payload(['order' => ['c', 'a']]), 'an incomplete reordered paragraph list');
rejects(fixture_payload(['gaps' => ['missing' => 'trees']]), 'unknown blank IDs');
rejects(fixture_payload(['gaps' => ['g2' => 'autumn']]), 'an unavailable dropdown answer');
rejects(fixture_payload(['highlight' => ['6']]), 'an out-of-range highlight token');
rejects(fixture_payload(['highlight' => [1]]), 'non-string highlight indices');
rejects(fixture_payload([], ['completed_sections' => ['missing']]), 'unknown completed sections');
rejects(fixture_payload([], ['completed_sections' => ['fixture-section', 'fixture-section']]), 'duplicate completed sections');
rejects(fixture_payload([], ['elapsed_seconds' => -1]), 'negative elapsed seconds');
rejects(fixture_payload([], ['assisted' => 'false']), 'a string instead of the assisted boolean');
rejects(fixture_payload([], ['exam_version' => 'stale-version']), 'a saved response from a different exam version');

// Ensure the public serializer strips nested answer/review fields, including
// within gap arrays, while retaining the question structure used by the runner.
$publicFixture = nisha_public_exam([
    'answer' => 'root-key', 'sections' => [['items' => [[
        'id' => 'public-gap', 'type' => 'gaps', 'explanation' => 'private',
        'blanks' => [['id' => 'g1', 'answer' => ['private'], 'options' => ['north', 'south'], 'explanation' => 'private']],
        'correct_text' => 'private',
    ]]]],
]);
no_review_keys($publicFixture, 'nested fixture');
check(($publicFixture['sections'][0]['items'][0]['blanks'][0]['options'] ?? null) === ['north', 'south'], 'Public stripping must preserve dropdown options');

function grade_real_case(array $exam, array $answers): array
{
    $payload = [
        'exam_id' => $exam['id'], 'exam_version' => $exam['version'],
        'attempt_id' => 'known-content-regression-0001', 'answers' => $answers,
        'elapsed_seconds' => 120, 'completed_sections' => [], 'assisted' => false,
    ];
    return nisha_grade_exam($exam, nisha_validate_exam_submission($exam, $payload));
}

// These expectations come from reading the original stimuli, rather than
// copying each item's current answer key. They catch accidental key changes.
$canonical = nisha_exams();
if (isset($canonical['ielts-academic-01'])) {
    $result = grade_real_case($canonical['ielts-academic-01'], [
        'il08' => '45', 'il11' => 'b', 'ir03' => 'not_given',
    ]);
    check($result['objective_score'] === 3 && $result['objective_total'] === 80, 'IELTS known-content answers: community room hire is £45, the park was industrial, and nighttime noise is not stated');
    $result = grade_real_case($canonical['ielts-academic-01'], [
        'il08' => '60', 'il11' => 'a', 'ir03' => 'false',
    ]);
    check($result['objective_score'] === 0, 'IELTS distractors must not score: normal price, private estate, or contradiction inferred from missing evidence');
    $result = grade_real_case($canonical['ielts-academic-01'], [
        'il08' => 'forty-five', 'il09' => 'eight forty',
    ]);
    check($result['objective_score'] === 2, 'IELTS accepted written-number/time variants must satisfy their stated word limits');
}
if (isset($canonical['pte-academic-01'])) {
    $result = grade_real_case($canonical['pte-academic-01'], [
        'pte-r01' => ['g1' => 'distort', 'g2' => 'establish', 'g3' => 'competitive'],
        'pte-r06' => ['a', 'c', 'b'], 'pte-r08' => ['d', 'b', 'c', 'e', 'a'],
        'pte-l13' => ['6', '30', '0'],
    ]);
    check(review_item($result, 'pte-r01')['score'] === 2, 'PTE research communication gaps require distort/establish; competitive is a distractor and a skipped gap earns zero');
    check(review_item($result, 'pte-r06')['score'] === 1, 'PTE cooling passage supports shade timing and waiting places; selecting only-temperature evidence deducts a point');
    check(review_item($result, 'pte-r08')['score'] === 3, 'PTE stream paragraphs award the three adjacent pairs d→b, c→e, and e→a');
    check(review_item($result, 'pte-l13')['score'] === 1, 'PTE archive transcript changes paintings/certain at indices6/30; selecting The at index0 deducts one point');
    check($result['objective_score'] === 7 && $result['answered_items'] === 4, 'PTE mixed real responses must total seven practice points across four answered items');
    $dictation = grade_real_case($canonical['pte-academic-01'], [
        'pte-l15' => 'THE REVISED PROPOSAL includes a more detailed explanation of the costs!',
    ]);
    check(review_item($dictation, 'pte-l15')['score'] === 1, 'PTE dictation accepts case/punctuation changes in the complete original sentence');
    $dictation = grade_real_case($canonical['pte-academic-01'], [
        'pte-l15' => 'The revised proposal includes a detailed explanation of the costs.',
    ]);
    check(review_item($dictation, 'pte-l15')['score'] === 0, 'PTE simplified exact-sentence dictation must reject a missing word');
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " of $checks checks failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: $checks full-exam integrity and grading checks.\n";
