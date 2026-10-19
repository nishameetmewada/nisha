<?php
declare(strict_types=1);
require_once __DIR__ . '/ai-review.php';
require_once __DIR__ . '/learning-catalog.php';

function nisha_ai_usage(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare('SELECT used_count FROM ai_review_usage WHERE usage_day = UTC_DATE() AND scope_key = ?');
    $statement->execute(['user-' . $userId]);
    $limit = nisha_ai_settings()['daily_user_limit'];
    return ['remaining_today' => max(0, $limit - (int) ($statement->fetchColumn() ?: 0)), 'limit' => $limit, 'resets' => '00:00 UTC'];
}

function nisha_ai_entry(array $row): array
{
    return ['id' => (int) $row['id'], 'item_id' => $row['item_id'], 'exam_id' => $row['exam_id'], 'exam_version' => $row['exam_version'],
        'attempt_id' => $row['attempt_id'], 'response_hash' => $row['response_hash'], 'response' => $row['response_text'],
        'review' => json_decode($row['review_json'], true, 32, JSON_THROW_ON_ERROR)];
}

/** Atomically reserve a provider attempt, or attach an already-completed cache. */
function nisha_ai_claim(PDO $pdo, int $userId, array $request): array
{
    $settings = nisha_ai_settings();
    $usageDay = gmdate('Y-m-d');
    try {
        $pdo->beginTransaction();
        $statement = $pdo->prepare("INSERT IGNORE INTO ai_review_usage (usage_day,scope_key,used_count) VALUES (?,'global',0)"); $statement->execute([$usageDay]);
        $statement = $pdo->prepare("SELECT used_count FROM ai_review_usage WHERE usage_day = ? AND scope_key = 'global' FOR UPDATE"); $statement->execute([$usageDay]); $global = (int) $statement->fetchColumn();
        $statement = $pdo->prepare('SELECT * FROM ai_reviews WHERE user_id = ? AND request_id = ? LIMIT 1 FOR UPDATE');
        $statement->execute([$userId, $request['request_id']]); $existing = $statement->fetch();
        if ($existing) {
            foreach (['cache_key', 'exam_id', 'exam_version', 'item_id', 'attempt_id', 'response_hash'] as $field) {
                if ($existing[$field] !== $request[$field]) throw new NishaAiException('This review request refers to an earlier response. Start a new review for the changed work.', 'ai_request_conflict', 409, false);
            }
            if ($existing['status'] === 'completed') { $pdo->commit(); return ['cached' => true, 'entry' => nisha_ai_entry($existing)]; }
        }
        $statement = $pdo->prepare('SELECT * FROM ai_reviews WHERE user_id = ? AND cache_key = ? AND status = \'completed\' ORDER BY id DESC LIMIT 1');
        $statement->execute([$userId, $request['cache_key']]); $cached = $statement->fetch();
        if ($cached) {
            // A cache hit still gets an attempt-specific row so saved history can find it.
            $token = bin2hex(random_bytes(16));
            $statement = $pdo->prepare('INSERT INTO ai_reviews (user_id,request_id,exam_id,exam_version,item_id,attempt_id,exam_name,task_type,mode,response_hash,cache_key,claim_token,status,response_text,review_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,\'completed\',?,?) ON DUPLICATE KEY UPDATE status=\'completed\',review_json=VALUES(review_json),error_code=NULL');
            $statement->execute([$userId, $request['request_id'], $request['exam_id'], $request['exam_version'], $request['item_id'], $request['attempt_id'], $request['exam_name'], $request['task_type'], $request['mode'], $request['response_hash'], $request['cache_key'], $token, $request['response'], $cached['review_json']]);
            $statement = $pdo->prepare('SELECT * FROM ai_reviews WHERE user_id = ? AND request_id = ?'); $statement->execute([$userId, $request['request_id']]);
            $entry = nisha_ai_entry($statement->fetch()); $pdo->commit(); return ['cached' => true, 'entry' => $entry];
        }
        $statement = $pdo->prepare('SELECT id FROM ai_reviews WHERE user_id = ? AND cache_key = ? AND status = \'pending\' AND claimed_at > UTC_TIMESTAMP() - INTERVAL 10 MINUTE LIMIT 1');
        $statement->execute([$userId, $request['cache_key']]);
        if ($statement->fetchColumn()) throw new NishaAiException('This response is already being reviewed. Keep your work and retry the same request shortly.', 'ai_in_progress', 409);
        $statement = $pdo->prepare('INSERT IGNORE INTO ai_review_usage (usage_day,scope_key,used_count) VALUES (?,?,0)'); $statement->execute([$usageDay, 'user-' . $userId]);
        $statement = $pdo->prepare('SELECT used_count FROM ai_review_usage WHERE usage_day = ? AND scope_key = ? FOR UPDATE'); $statement->execute([$usageDay, 'user-' . $userId]); $used = (int) $statement->fetchColumn();
        if ($used >= $settings['daily_user_limit']) throw new NishaAiException('Your daily AI review request limit is reached. Saved feedback stays available. New requests reset at 00:00 UTC.', 'ai_student_limit', 429);
        if ($global >= $settings['daily_global_limit']) throw new NishaAiException('The site’s daily AI review request limit is reached. Your work is retained. Try again after 00:00 UTC.', 'ai_site_limit', 429);
        $token = bin2hex(random_bytes(16));
        $statement = $pdo->prepare('INSERT INTO ai_reviews (user_id,request_id,exam_id,exam_version,item_id,attempt_id,exam_name,task_type,mode,response_hash,cache_key,claim_token,status,response_text) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,\'pending\',?) ON DUPLICATE KEY UPDATE status=\'pending\',claim_token=VALUES(claim_token),claimed_at=UTC_TIMESTAMP(),error_code=NULL');
        $statement->execute([$userId, $request['request_id'], $request['exam_id'], $request['exam_version'], $request['item_id'], $request['attempt_id'], $request['exam_name'], $request['task_type'], $request['mode'], $request['response_hash'], $request['cache_key'], $token, $request['response']]);
        $statement = $pdo->prepare('SELECT id FROM ai_reviews WHERE user_id=? AND request_id=?'); $statement->execute([$userId, $request['request_id']]); $id = (int) $statement->fetchColumn();
        $statement = $pdo->prepare('UPDATE ai_review_usage SET used_count=used_count+1 WHERE usage_day=? AND scope_key IN (\'global\',?)'); $statement->execute([$usageDay, 'user-' . $userId]);
        $pdo->commit(); return ['cached' => false, 'id' => $id, 'token' => $token];
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
}

/** Only explicit CLI callers may inject a deterministic provider for tests. */
function nisha_ai_submit(array $user, array $payload, ?array $audio = null, ?callable $transport = null): array
{
    if ($transport !== null && PHP_SAPI !== 'cli') throw new LogicException('Test transports are CLI-only.');
    $required = ['request_id', 'exam_id', 'exam_version', 'item_id', 'attempt_id', 'response'];
    if (array_diff($required, array_keys($payload)) || array_diff(array_keys($payload), [...$required, 'transcript'])) throw new NishaAiException('Send the task, attempt and current response for this review.', 'ai_invalid_request', 422, false);
    foreach (['request_id', 'attempt_id'] as $field) if (!is_string($payload[$field]) || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{15,79}\z/D', $payload[$field])) throw new NishaAiException('Use a valid unique review and attempt reference.', 'ai_invalid_request', 422, false);
    if (!nisha_valid_text($payload['exam_id'], 64) || !nisha_valid_text($payload['exam_version'], 100) || !nisha_valid_text($payload['item_id'], 80) || !nisha_valid_text($payload['response'], 40000)) throw new NishaAiException('This response or task reference is invalid or too long.', 'ai_invalid_request', 422, false);
    $exam = nisha_resolve_exam($payload['exam_id']);
    if (!$exam) throw new NishaAiException('Choose an available writing or speaking task.', 'ai_invalid_task', 422, false);
    if ($exam['version'] !== $payload['exam_version']) throw new NishaAiException('This task changed since your attempt began. Keep your response and start the updated task before requesting AI feedback.', 'exam_version_changed', 409, false);
    $item = nisha_exam_items($exam)[$payload['item_id']] ?? null;
    if (!$item || !in_array($item['type'], ['essay', 'spoken'], true)) throw new NishaAiException('AI coaching is available for writing and speaking tasks.', 'ai_invalid_task', 422, false);
    if (isset($payload['transcript']) && !nisha_valid_text($payload['transcript'], 40000)) throw new NishaAiException('The transcript is invalid or too long.', 'ai_invalid_transcript', 422, false);
    $transcript = trim($payload['transcript'] ?? '');
    if ($item['type'] === 'essay' && ($audio !== null || $transcript !== '')) throw new NishaAiException('Send your written response for this task.', 'ai_invalid_request', 422, false);
    if ($audio !== null && $transcript !== '') throw new NishaAiException('Choose either a recording or a transcript for this review.', 'ai_invalid_request', 422, false);
    $mode = $item['type'] === 'essay' ? 'writing' : ($audio !== null ? 'speaking-audio' : 'speaking-transcript');
    $text = $item['type'] === 'essay' ? $payload['response'] : $transcript;
    if ($audio === null && nisha_word_count($text) < 1) throw new NishaAiException('Enter your response or a transcript of what you actually said. Reflection notes are not a spoken response.', 'ai_empty_response', 422, false);
    if ($audio !== null) {
        if ($item['type'] !== 'spoken' || !is_string($audio['path'] ?? null)) throw new NishaAiException('Choose a supported speaking recording.', 'ai_invalid_audio', 422, false);
        nisha_ai_audio_info($audio['path']);
        $audio['mime'] = 'audio/wav';
    }
    if ($transport === null && !nisha_ai_configured()) throw new NishaAiException('AI reviews are awaiting the site owner’s OpenAI API setup.', 'ai_not_configured', 503, false);
    if ($transport === null && !nisha_ai_transport_available()) throw new NishaAiException('The site owner needs to enable PHP HTTPS requests for AI reviews.', 'ai_transport_unavailable', 503, false);
    $pdo = db();
    if ($exam['session_type'] !== 'practice') {
        $statement = $pdo->prepare('SELECT exam_id,result_json FROM exam_runs WHERE user_id=? AND attempt_id=? LIMIT 1'); $statement->execute([$user['id'], $payload['attempt_id']]); $stored = $statement->fetch();
        if (!$stored || $stored['exam_id'] !== $exam['id']) throw new NishaAiException('Finish and save this timed test before requesting AI coaching.', 'ai_finish_mock_first', 403, false);
        $result = json_decode($stored['result_json'], true, 32, JSON_THROW_ON_ERROR);
        if (($result['exam_version'] ?? '') !== $exam['version'] || ($result['responses'][$item['id']] ?? '') !== $payload['response']) throw new NishaAiException('Review the original submitted response. Start guided practice to revise it.', 'ai_response_changed', 409, false);
    }
    $source = $audio !== null ? hash_file('sha256', $audio['path']) : $text;
    $request = $payload + ['exam_name' => $exam['name'], 'task_type' => $item['task_type'], 'mode' => $mode, 'response_hash' => nisha_ai_response_hash($payload['response'])];
    $request['cache_key'] = hash('sha256', nisha_ai_review_fingerprint($exam, $item, $mode, $source) . $request['response_hash']);
    $claim = nisha_ai_claim($pdo, (int) $user['id'], $request);
    if ($claim['cached']) return ['success' => true, 'saved' => true, 'cached' => true, 'review_id' => $claim['entry']['id'], 'entry' => $claim['entry'], 'review' => $claim['entry']['review'], 'usage' => nisha_ai_usage($pdo, (int) $user['id'])];
    try {
        if ($audio !== null) $text = nisha_ai_transcribe($audio['path'], $audio['mime'], $transport);
        $review = nisha_ai_review($exam, $item, $text, $mode, $transport);
        $entry = ['id' => $claim['id'], 'item_id' => $item['id'], 'exam_id' => $exam['id'], 'exam_version' => $exam['version'], 'attempt_id' => $payload['attempt_id'], 'response_hash' => $request['response_hash'], 'response' => $payload['response'], 'review' => $review];
        $statement = $pdo->prepare('UPDATE ai_reviews SET status=\'completed\',review_json=?,error_code=NULL WHERE id=? AND claim_token=? AND status=\'pending\'');
        $statement->execute([json_encode($review, JSON_THROW_ON_ERROR), $claim['id'], $claim['token']]);
        if ($statement->rowCount() !== 1) throw new NishaAiException('A newer request replaced this review. Open your saved feedback before retrying.', 'ai_request_conflict', 409);
        return ['success' => true, 'saved' => true, 'cached' => false, 'review_id' => $claim['id'], 'entry' => $entry, 'review' => $review, 'usage' => nisha_ai_usage($pdo, (int) $user['id'])];
    } catch (Throwable $exception) {
        $statement = $pdo->prepare('UPDATE ai_reviews SET status=\'failed\',error_code=? WHERE id=? AND claim_token=? AND status=\'pending\'');
        $statement->execute([$exception instanceof NishaAiException ? $exception->errorCode : 'ai_internal', $claim['id'], $claim['token']]);
        throw $exception;
    }
}

function nisha_ai_saved_reviews(PDO $pdo, int $userId, array $result): array
{
    $statement = $pdo->prepare('SELECT * FROM ai_reviews WHERE user_id=? AND attempt_id=? AND exam_id=? AND exam_version=? AND status=\'completed\' ORDER BY id');
    $statement->execute([$userId, $result['attempt_id'], $result['exam_id'], $result['exam_version']]); $entries = [];
    foreach ($statement->fetchAll() as $row) {
        $response = $result['responses'][$row['item_id']] ?? '';
        if (is_string($response) && hash_equals($row['response_hash'], nisha_ai_response_hash($response))) $entries[] = nisha_ai_entry($row);
    }
    return $entries;
}
