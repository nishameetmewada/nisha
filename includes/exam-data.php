<?php
declare(strict_types=1);

/** Canonical answer-bearing exam data. These files must remain server-only. */
function nisha_exams(): array
{
    static $exams;
    if ($exams !== null) {
        return $exams;
    }
    $loaded = [];
    $courses = [
        'ielts-academic-01' => 'ielts-academic',
        'ielts-general-01' => 'ielts-general',
        'pte-academic-01' => 'pte-academic',
        'pte-core-01' => 'pte-core',
    ];
    foreach ($courses as $id => $courseId) {
        $path = __DIR__ . '/exams/' . $id . '.php';
        if (!is_file($path)) {
            continue;
        }
        $exam = (static function (string $file): mixed { return require $file; })($path);
        if (!is_array($exam) || ($exam['id'] ?? null) !== $id
            || !isset($exam['sections']) || !is_array($exam['sections'])
            || !isset($exam['resources']) || !is_array($exam['resources'])
            || !is_string($exam['name'] ?? null) || $exam['name'] === ''
            || !is_string($exam['version'] ?? null) || $exam['version'] === ''
            || !in_array($exam['exam'] ?? null, ['IELTS', 'PTE'], true)) {
            throw new UnexpectedValueException('Invalid canonical exam data: ' . $id);
        }
        $exam['session_type'] = 'full';
        $exam['timing_mode'] = 'timed';
        $exam['course_id'] = $courseId;
        $exam['module_id'] = null;
        $loaded[$id] = $exam;
    }
    return $exams = $loaded;
}

/** Remove answer keys at every nesting level before embedding data in a page. */
function nisha_public_exam(array $exam): array
{
    $public = [];
    foreach ($exam as $key => $value) {
        if (in_array($key, ['answer', 'explanation', 'correct_text'], true)) {
            continue;
        }
        $public[$key] = is_array($value) ? nisha_public_exam($value) : $value;
    }
    return $public;
}

function exam_public_data(array $exam): array
{
    return nisha_public_exam($exam);
}

return nisha_exams();
