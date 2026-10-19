<?php
declare(strict_types=1);

class ExamVersionConflictException extends InvalidArgumentException
{
}

function nisha_exam_items(array $exam): array
{
    $items = [];
    foreach ($exam['sections'] as $section) {
        foreach ($section['items'] as $item) {
            if (isset($items[$item['id']])) {
                throw new RuntimeException('The exam contains a duplicate item ID.');
            }
            $items[$item['id']] = $item;
        }
    }
    return $items;
}

function nisha_valid_text(mixed $value, int $maxBytes): bool
{
    return is_string($value) && strlen($value) <= $maxBytes
        && preg_match('//u', $value) === 1 && !str_contains($value, "\0");
}

function nisha_option_ids(array $item): array
{
    return array_map(static fn (array $option): string => (string) $option['id'], $item['options'] ?? []);
}

/** Validate every client field before marking or persisting it. */
function nisha_validate_exam_submission(array $exam, array $payload): array
{
    $allowed = ['exam_id', 'exam_version', 'attempt_id', 'answers', 'elapsed_seconds', 'completed_sections', 'assisted'];
    if (array_diff(array_keys($payload), $allowed) !== [] || array_diff($allowed, array_keys($payload)) !== []) {
        throw new InvalidArgumentException('Send the exam ID and version, attempt ID, answers, elapsed time, completed sections, and assisted setting.');
    }
    if (($payload['exam_id'] ?? null) !== $exam['id']) {
        throw new InvalidArgumentException('Choose a known practice session or mock exam.');
    }
    if (!nisha_valid_text($payload['exam_version'], 100) || $payload['exam_version'] === '') {
        throw new InvalidArgumentException('Send the version of the session you started.');
    }
    if ($payload['exam_version'] !== ($exam['version'] ?? null)) {
        throw new ExamVersionConflictException('This session has been updated since you started. Download your local answers, then start a new attempt with the updated content.');
    }
    if (!is_string($payload['attempt_id']) || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{15,79}\z/D', $payload['attempt_id'])) {
        throw new InvalidArgumentException('Use a unique attempt ID containing 16–80 letters, numbers, hyphens or underscores.');
    }
    if (!is_int($payload['elapsed_seconds']) || $payload['elapsed_seconds'] < 0 || $payload['elapsed_seconds'] > 604800) {
        throw new InvalidArgumentException('Elapsed time must be a whole number between zero and seven days.');
    }
    if (!is_bool($payload['assisted'])) {
        throw new InvalidArgumentException('The assisted setting must be true or false.');
    }
    $sectionIds = array_column($exam['sections'], 'id');
    $completed = $payload['completed_sections'];
    if (!is_array($completed) || !array_is_list($completed) || count($completed) > count($sectionIds)) {
        throw new InvalidArgumentException('Completed sections must be a list of section IDs.');
    }
    $seen = [];
    foreach ($completed as $id) {
        if (!is_string($id) || !in_array($id, $sectionIds, true) || isset($seen[$id])) {
            throw new InvalidArgumentException('Completed sections must contain known, unique section IDs.');
        }
        $seen[$id] = true;
    }
    $items = nisha_exam_items($exam);
    $answers = $payload['answers'];
    if (!is_array($answers) || ($answers !== [] && array_is_list($answers)) || count($answers) > count($items)) {
        throw new InvalidArgumentException('Answers must be an object keyed by question ID.');
    }
    foreach ($answers as $id => $response) {
        if (!is_string($id) || !isset($items[$id])) {
            throw new InvalidArgumentException('An answer refers to an unknown question.');
        }
        $item = $items[$id];
        $type = $item['type'];
        if (in_array($type, ['short', 'essay', 'spoken'], true)) {
            $limit = ['short' => 2000, 'essay' => 50000, 'spoken' => 10000][$type];
            if (!nisha_valid_text($response, $limit)) {
                throw new InvalidArgumentException('A written response is invalid or too long. Audio uploads are not accepted.');
            }
        } elseif ($type === 'choice') {
            if (!nisha_valid_text($response, 80) || ($response !== '' && !in_array($response, nisha_option_ids($item), true))) {
                throw new InvalidArgumentException('Choose an available answer option.');
            }
        } elseif (in_array($type, ['multi', 'reorder', 'highlight'], true)) {
            $ids = $type === 'highlight'
                ? array_map('strval', array_keys(preg_split('/\s+/u', trim($item['text']), -1, PREG_SPLIT_NO_EMPTY)))
                : nisha_option_ids($item);
            if (!is_array($response) || !array_is_list($response) || count($response) > count($ids)) {
                throw new InvalidArgumentException('Selected answers must be a bounded list.');
            }
            $selected = [];
            foreach ($response as $selectedId) {
                if (!is_string($selectedId) || !in_array($selectedId, $ids, true) || isset($selected[$selectedId])) {
                    throw new InvalidArgumentException('Selected answers must contain known, unique option IDs.');
                }
                $selected[$selectedId] = true;
            }
            if ($type === 'reorder' && $response !== [] && count($response) !== count($ids)) {
                throw new InvalidArgumentException('An ordered answer must include every paragraph exactly once.');
            }
            if ($type === 'multi' && isset($item['select_count']) && count($response) > (int) $item['select_count']) {
                throw new InvalidArgumentException('Too many answer options were selected.');
            }
        } elseif ($type === 'gaps') {
            if (!is_array($response) || ($response !== [] && array_is_list($response)) || count($response) > count($item['blanks'])) {
                throw new InvalidArgumentException('Gap answers must be an object keyed by blank ID.');
            }
            $blanks = array_column($item['blanks'], null, 'id');
            foreach ($response as $blankId => $text) {
                if (!is_string($blankId) || !isset($blanks[$blankId]) || !nisha_valid_text($text, 1000)) {
                    throw new InvalidArgumentException('A gap answer is invalid or refers to an unknown blank.');
                }
                $options = $blanks[$blankId]['options'] ?? [];
                if ($text !== '' && $options !== [] && !in_array($text, $options, true)) {
                    throw new InvalidArgumentException('Choose an available answer for each dropdown gap.');
                }
            }
        } else {
            throw new RuntimeException('An exam item uses an unsupported interaction.');
        }
    }
    // Use canonical section order regardless of the order received from a browser.
    $payload['completed_sections'] = array_values(array_filter($sectionIds, static fn (string $id): bool => in_array($id, $completed, true)));
    return $payload;
}

function nisha_normalize_answer(string $text): string
{
    if (class_exists('Normalizer')) {
        $text = Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text;
    }
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
}

function nisha_word_count(string $text): int
{
    // Decimal numbers, contractions and hyphenated words each count as one word.
    return preg_match_all('/[\p{L}\p{N}]+(?:[\x{2019}\x{0027}.\-][\p{L}\p{N}]+)*/u', $text);
}

function nisha_text_is_correct(string $response, array $accepted, ?int $maxWords = null): bool
{
    if ($maxWords !== null && nisha_word_count($response) > $maxWords) {
        return false;
    }
    $normalized = nisha_normalize_answer($response);
    if ($normalized === '') {
        return false;
    }
    foreach ($accepted as $answer) {
        if ($normalized === nisha_normalize_answer((string) $answer)) {
            return true;
        }
    }
    return false;
}

function nisha_has_response(mixed $response): bool
{
    if (is_array($response)) {
        foreach ($response as $value) {
            if (nisha_has_response($value)) {
                return true;
            }
        }
        return false;
    }
    return is_string($response) && trim(preg_replace('/\s+/u', ' ', $response) ?? '') !== '';
}

/** Answer-free item context for reviewing saved sessions after content changes. */
function nisha_review_item_snapshot(array $item): array
{
    $snapshot = [];
    foreach ($item as $key => $value) {
        if (in_array($key, ['answer', 'explanation', 'correct_text'], true)) {
            continue;
        }
        $snapshot[$key] = is_array($value) ? nisha_review_item_snapshot($value) : $value;
    }
    return $snapshot;
}

/** Return only original practice points, never official bands or PTE scores. */
function nisha_grade_exam(array $exam, array $submission): array
{
    $result = [
        'exam_id' => $exam['id'], 'name' => $exam['name'], 'exam' => $exam['exam'],
        'session_type' => $exam['session_type'] ?? 'full', 'course_id' => $exam['course_id'] ?? null,
        'module_id' => $exam['module_id'] ?? null, 'timing_mode' => $exam['timing_mode'] ?? 'timed',
        'exam_version' => $exam['version'] ?? '', 'attempt_id' => $submission['attempt_id'],
        'elapsed_seconds' => $submission['elapsed_seconds'], 'assisted' => $submission['assisted'],
        'completed_sections' => $submission['completed_sections'],
        'objective_score' => 0, 'objective_total' => 0, 'answered_items' => 0, 'total_items' => 0,
        'sections' => [], 'review' => [], 'responses' => $submission['answers'],
        'resources' => $exam['resources'] ?? [],
        'grading_note' => 'Objective raw practice points only. Writing/speaking offer optional AI coaching or self-review, separately from these points. These are not official IELTS band scores or PTE scores.',
    ];
    foreach ($exam['sections'] as $section) {
        $sectionResult = [
            'id' => $section['id'], 'title' => $section['title'], 'score' => 0, 'total' => 0,
            'answered' => 0, 'total_items' => count($section['items']), 'ungraded_items' => 0,
        ];
        foreach ($section['items'] as $item) {
            $type = $item['type'];
            $graded = !in_array($type, ['essay', 'spoken'], true);
            $defaultResponse = in_array($type, ['multi', 'reorder', 'highlight', 'gaps'], true) ? [] : '';
            $response = $submission['answers'][$item['id']] ?? $defaultResponse;
            $score = $graded ? 0 : null;
            $maximum = $graded ? 1 : null;
            $correct = $graded ? ($item['answer'] ?? null) : null;
            if ($type === 'choice') {
                $score = $response !== '' && $response === $correct ? 1 : 0;
            } elseif ($type === 'short') {
                $score = nisha_text_is_correct($response, (array) $correct, isset($item['max_words']) ? (int) $item['max_words'] : null) ? 1 : 0;
            } elseif ($type === 'multi' || $type === 'highlight') {
                $correct = array_map('strval', (array) $correct);
                if ($type === 'multi' && !empty($item['all_or_nothing'])) {
                    $maximum = 1;
                    $actual = $response;
                    sort($actual);
                    $expected = $correct;
                    sort($expected);
                    $score = $actual === $expected ? 1 : 0;
                } else {
                    $maximum = count($correct);
                    $right = count(array_intersect($response, $correct));
                    $wrong = count(array_diff($response, $correct));
                    $score = max(0, $right - $wrong);
                }
            } elseif ($type === 'reorder') {
                $correct = array_map('strval', (array) $correct);
                $maximum = max(0, count($correct) - 1);
                $score = 0;
                for ($i = 0; $i < count($response) - 1; $i++) {
                    for ($j = 0; $j < count($correct) - 1; $j++) {
                        if ($response[$i] === $correct[$j] && $response[$i + 1] === $correct[$j + 1]) {
                            $score++;
                            break;
                        }
                    }
                }
            } elseif ($type === 'gaps') {
                $score = 0;
                $maximum = count($item['blanks']);
                $correct = [];
                foreach ($item['blanks'] as $blank) {
                    $correct[$blank['id']] = $blank['answer'];
                    $maxWords = $blank['max_words'] ?? $item['max_words'] ?? null;
                    if (nisha_text_is_correct($response[$blank['id']] ?? '', (array) $blank['answer'], $maxWords === null ? null : (int) $maxWords)) {
                        $score++;
                    }
                }
            } elseif (!$graded) {
                $sectionResult['ungraded_items']++;
            } else {
                throw new RuntimeException('An exam item cannot be marked.');
            }
            if ($graded) {
                $sectionResult['score'] += $score;
                $sectionResult['total'] += $maximum;
            }
            if (nisha_has_response($response)) {
                $sectionResult['answered']++;
            }
            $review = [
                'id' => $item['id'], 'section_id' => $section['id'], 'type' => $type,
                'task_type' => $item['task_type'] ?? $type, 'prompt' => $item['prompt'] ?? $item['text'] ?? '',
                'response' => $response, 'correct_answer' => $correct, 'score' => $score, 'max_score' => $maximum,
                'explanation' => $item['explanation'] ?? 'This response is ungraded. Use the self-review checklist to reflect on your response.',
                'graded' => $graded, 'rubric' => $item['rubric'] ?? [],
                'title' => $item['title'] ?? '', 'instructions' => $item['instructions'] ?? '',
                'options' => $item['options'] ?? [], 'text' => $item['text'] ?? '',
                'resource_id' => $item['resource_id'] ?? null,
                'secondary_resource_id' => $item['secondary_resource_id'] ?? null,
                'item_snapshot' => nisha_review_item_snapshot($item),
            ];
            if ($type === 'highlight' && isset($item['correct_text'])) {
                $review['correct_text'] = $item['correct_text'];
            }
            $result['review'][] = $review;
        }
        $result['sections'][] = $sectionResult;
        $result['objective_score'] += $sectionResult['score'];
        $result['objective_total'] += $sectionResult['total'];
        $result['answered_items'] += $sectionResult['answered'];
        $result['total_items'] += $sectionResult['total_items'];
    }
    $result['incomplete'] = count($submission['completed_sections']) < count($exam['sections']);
    return $result;
}
