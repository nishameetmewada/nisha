<?php
declare(strict_types=1);

/** Canonical answer-bearing exam data. These files must remain server-only. */
function nisha_exams(): array
{
    static $exams;
    if ($exams !== null) {
        return $exams;
    }
    $exams = [];
    foreach (['ielts-academic-01', 'pte-academic-01'] as $id) {
        $path = __DIR__ . '/exams/' . $id . '.php';
        if (!is_file($path)) {
            continue;
        }
        try {
            $exam = require $path;
            if (is_array($exam) && ($exam['id'] ?? null) === $id
                && isset($exam['sections']) && is_array($exam['sections'])) {
                $exams[$id] = $exam;
            }
        } catch (Throwable $exception) {
            // A temporarily missing/incomplete content file must not break the catalogue.
            error_log('NISHA could not load exam content: ' . $id);
        }
    }
    return $exams;
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
