<?php
declare(strict_types=1);

// This exports a deliberately small free-practice pack, never the full mock bank.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/generated-public-library.php';

$sessions = []; $ids = []; $courseCounts = []; $skillCounts = []; $structures = [];
foreach (nisha_generated_courses() as $courseIndex => $definition) {
    $courseNumber = array_search($courseIndex, array_keys(nisha_generated_courses()), true);
    for ($position = 0; $position < 20; ++$position) {
        // Spread the pack across topics and evaluation perspectives while
        // exercising each of the ten source slots twice per course.
        $scenario = ($courseNumber * 149 + $position * 37) % 750;
        $number = $scenario * 10 + ($position % 10) + 1;
        $id = 'xp.' . $courseIndex . '.' . str_pad((string)$number, 5, '0', STR_PAD_LEFT);
        $session = nisha_generated_public_resolve_exam($id);
        if (!$session || $session['session_type'] !== 'practice' || $session['timing_mode'] !== 'untimed'
            || $session['teacher_reviewed'] !== false || isset($ids[$id])) {
            throw new RuntimeException('Only distinct, generated free-practice sessions can enter the mobile pack.');
        }
        nisha_content_validate_session($session);
        $item = array_values(nisha_exam_items($session))[0];
        if (count(nisha_exam_items($session)) !== 1) throw new RuntimeException('An offline session must contain exactly one task.');
        $skill = substr($session['module_id'], strlen('generated-'));
        $session['offline_available'] = true;
        $session['offline_skill'] = $skill;
        $session['offline_disclosure'] = 'Offline generated practice, not teacher-reviewed. Marking runs on this device. These are learning points, not official IELTS/PTE scores. Writing and speaking are ungraded offline.';
        $sessions[] = $session; $ids[$id] = true;
        $courseCounts[$courseIndex] = ($courseCounts[$courseIndex] ?? 0) + 1;
        $skillCounts[$courseIndex][$skill] = ($skillCounts[$courseIndex][$skill] ?? 0) + 1;
        $structures[nisha_content_semantic_hash($session)] = true;
    }
}
if (count($sessions) !== 80 || count($ids) !== 80 || count($courseCounts) !== 4) throw new RuntimeException('The mobile pack must contain exactly 80 free-practice sessions.');
foreach ($courseCounts as $course => $count) {
    if ($count !== 20 || ($skillCounts[$course] ?? []) !== ['reading'=>6, 'listening'=>6, 'writing'=>4, 'speaking'=>4]) throw new RuntimeException('Each course must include all four skills.');
}
$payload = [
    'schema_version' => 1, 'session_count' => 80, 'course_count' => 4,
    'source_version' => nisha_generated_public_version(),
    'normalisation' => ['unicode_nfkc' => class_exists('Normalizer'), 'unicode_lowercase' => function_exists('mb_strtolower')],
    'content_sha256' => hash('sha256', json_encode([class_exists('Normalizer'), function_exists('mb_strtolower'), $sessions], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
    'disclosure' => 'Generated free practice for use offline. These variants have not been teacher-reviewed. Topics and question structures may recur. This pack includes local marking keys for these practice tasks only; full mock exams remain online and access-controlled.',
    'courses' => $courseCounts, 'skills' => $skillCounts, 'sessions' => $sessions,
];
$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
$target = dirname(__DIR__) . '/mobile/src/data/offline-sessions.json';
if (in_array('--check', $argv, true)) {
    if (!is_file($target) || !hash_equals(hash('sha256', $json), hash_file('sha256', $target))) {
        fwrite(STDERR, "Offline content is missing or stale. Run php scripts/build-mobile-content.php.\n"); exit(1);
    }
} else {
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) throw new RuntimeException('The mobile content directory could not be created.');
    if (file_put_contents($target, $json) !== strlen($json)) throw new RuntimeException('The mobile content pack could not be saved.');
}
echo 'PASS: 80 offline practice tasks across four courses and all skills; ' . count($structures) . ' distinct normalised structures. No full mocks, student data, API calls or teacher certification.' . PHP_EOL;
