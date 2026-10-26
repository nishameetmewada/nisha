<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (is_file(__DIR__ . '/commerce.php')) require_once __DIR__ . '/commerce.php';

function nisha_teacher_settings(): array
{
    $hosts = config('teacher_video_hosts', []);
    if (is_string($hosts)) $hosts = explode(',', $hosts);
    $hosts = is_array($hosts) ? array_values(array_unique(array_filter(array_map(static fn ($host): string => strtolower(trim((string) $host)), $hosts)))) : [];
    $share = max(0, min(100, (float) config('teacher_reward_share_percent', 90)));
    $currency = strtoupper((string) config('teacher_reward_currency', 'AUD'));
    if (!in_array($currency, ['USD','NZD','AUD','EUR','GBP','INR','CAD','SGD'], true)) $currency = 'AUD';
    $settings = ['enabled' => filter_var(config('teacher_rewards_enabled', false), FILTER_VALIDATE_BOOLEAN) && $share > 0,
        'share_percent' => $share, 'share_basis_points' => (int) round($share * 100),
        'watch_percent' => max(10, min(100, (int) config('teacher_qualified_watch_percent', 70))),
        'paid_students_only' => filter_var(config('teacher_paid_views_only', true), FILTER_VALIDATE_BOOLEAN),
        'video_hosts' => $hosts, 'currency' => $currency];
    try {
        $row = db()->query('SELECT * FROM teacher_policy_settings WHERE id=1')->fetch();
        if ($row) {
            $savedHosts = json_decode((string) $row['video_hosts_json'], true, 8, JSON_THROW_ON_ERROR);
            $settings = array_replace($settings, ['enabled' => (bool) $row['enabled'], 'share_percent' => 90.0,
                'share_basis_points' => 9000, 'watch_percent' => (int) $row['watch_percent'],
                'paid_students_only' => (bool) $row['paid_students_only'], 'currency' => $row['currency'],
                'video_hosts' => is_array($savedHosts) ? $savedHosts : []]);
        }
    } catch (Throwable $exception) { /* Optional setup must not break account pages. */ }
    return $settings;
}

/** Only an existing numeric account ID or an assigned role can bootstrap owners. */
function nisha_is_admin(array $user): bool
{
    $ids = config('admin_user_ids', []);
    if (is_string($ids)) $ids = explode(',', $ids);
    if (is_array($ids) && in_array((int) ($user['id'] ?? 0), array_map('intval', $ids), true) && (int) ($user['id'] ?? 0) > 0) return true;
    try {
        $q = db()->prepare("SELECT 1 FROM user_roles WHERE user_id=? AND role='admin'");
        $q->execute([(int) ($user['id'] ?? 0)]);
        return (bool) $q->fetchColumn();
    } catch (Throwable $exception) { return false; }
}

function nisha_teacher_profile(PDO $pdo, int $userId): ?array
{
    $q = $pdo->prepare('SELECT * FROM teacher_profiles WHERE user_id=?');
    $q->execute([$userId]);
    return $q->fetch() ?: null;
}

function nisha_teacher_profile_review_hash(array $profile): string
{
    return hash('sha256', json_encode([(int) $profile['user_id'], (string) $profile['display_name'], (string) $profile['bio']], JSON_THROW_ON_ERROR));
}

function nisha_teacher_approved(array $user): bool
{
    try {
        $profile = nisha_teacher_profile(db(), (int) $user['id']);
        return $profile !== null && $profile['status'] === 'approved';
    } catch (Throwable $exception) { return false; }
}

function nisha_teacher_text(mixed $text, string $label, int $max, int $min = 0): string
{
    if (!is_string($text) || !preg_match('//u', $text) || strlen($text) > $max || strlen(trim($text)) < $min || str_contains($text, "\0")) throw new InvalidArgumentException('Enter a valid ' . $label . '.');
    return trim($text);
}

function nisha_teacher_positive_id(mixed $value, string $label = 'record'): int
{
    if (!is_int($value) && (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,17}\z/D', $value))) throw new InvalidArgumentException('Choose a valid ' . $label . '.');
    $id = (int) $value;
    if ($id < 1) throw new InvalidArgumentException('Choose a valid ' . $label . '.');
    return $id;
}

function nisha_teacher_video_url(mixed $value): string
{
    $url = nisha_teacher_text($value, 'video link', 2000, 12);
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
        || (isset($parts['port']) && $parts['port'] !== 443) || !preg_match('/\A[a-z0-9][a-z0-9.-]*\.[a-z]{2,63}\z/D', $host)
        || !in_array($host, nisha_teacher_settings()['video_hosts'], true)
        || !preg_match('/\.(?:mp4|webm)\z/iD', (string) ($parts['path'] ?? ''))
        || preg_match('/(?:^|\.)(?:localhost|local|internal|invalid|test)\z/D', $host)) throw new InvalidArgumentException('Use a direct HTTPS MP4 or WebM link from a video host approved by the site owner.');
    return $url;
}

function nisha_teacher_apply(array $user, array $input): void
{
    $name = nisha_teacher_text($input['display_name'] ?? null, 'display name', 100, 2);
    $bio = nisha_teacher_text($input['bio'] ?? null, 'teacher introduction', 2000, 20);
    $q = db()->prepare("INSERT INTO teacher_profiles(user_id,display_name,bio,status) VALUES (?,?,?,'pending') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),bio=VALUES(bio),status='pending',review_note='',reviewed_by=NULL");
    $q->execute([(int) $user['id'], $name, $bio]);
}

function nisha_teacher_submit_video(array $user, array $input): int
{
    if (!nisha_teacher_approved($user)) throw new InvalidArgumentException('Your teacher application must be approved before you can submit videos.');
    $title = nisha_teacher_text($input['title'] ?? null, 'video title', 160, 3);
    $description = nisha_teacher_text($input['description'] ?? null, 'video description', 4000, 10);
    $url = nisha_teacher_video_url($input['video_url'] ?? null);
    $duration = filter_var($input['duration_seconds'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 15, 'max_range' => 7200]]);
    if ($duration === false) throw new InvalidArgumentException('Enter a video duration between 15 seconds and two hours.');
    $course = $input['course_id'] ?? null; $skill = $input['skill'] ?? null;
    if (!in_array($course, ['ielts-academic','ielts-general','pte-academic','pte-core'], true) || !in_array($skill, ['reading','listening','writing','speaking'], true)) throw new InvalidArgumentException('Choose a valid course and skill.');
    if (!empty($input['video_id'])) {
        $id = nisha_teacher_positive_id($input['video_id'], 'video');
        $q = db()->prepare("UPDATE coaching_videos SET title=?,description=?,video_url=?,duration_seconds=?,course_id=?,skill=?,status='pending',review_note='',reviewed_by=NULL,revision=revision+1 WHERE id=? AND teacher_id=?");
        $q->execute([$title,$description,$url,$duration,$course,$skill,$id,(int) $user['id']]);
        if ($q->rowCount() !== 1) throw new InvalidArgumentException('This video is not available in your teacher account.');
        return $id;
    }
    $q = db()->prepare('INSERT INTO coaching_videos(teacher_id,title,description,video_url,duration_seconds,course_id,skill) VALUES (?,?,?,?,?,?,?)');
    $q->execute([(int) $user['id'],$title,$description,$url,$duration,$course,$skill]);
    return (int) db()->lastInsertId();
}

function nisha_teacher_money(int $amount, string $currency): string
{ return strtoupper($currency) . ' ' . number_format($amount / 100, 2); }

function nisha_teacher_page_data(array $user): array
{
    $q = db()->prepare('SELECT v.*, (SELECT COUNT(*) FROM coaching_qualified_views w WHERE w.video_id=v.id) AS qualified_views FROM coaching_videos v WHERE v.teacher_id=? ORDER BY v.created_at DESC,v.id DESC LIMIT 100');
    $q->execute([(int) $user['id']]); $videos = $q->fetchAll();
    $q = db()->prepare('SELECT a.*,p.period FROM teacher_reward_allocations a JOIN teacher_profit_periods p ON p.id=a.period_id WHERE a.teacher_id=? ORDER BY p.period DESC,a.id DESC LIMIT 100');
    $q->execute([(int) $user['id']]); $rewards = $q->fetchAll();
    $totals = ['allocated' => 0, 'paid' => 0];
    foreach ($rewards as $reward) $totals[$reward['status'] === 'paid' ? 'paid' : 'allocated'] += (int) $reward['amount_cents'];
    return ['profile' => nisha_teacher_profile(db(), (int) $user['id']), 'videos' => $videos, 'rewards' => $rewards, 'totals' => $totals, 'settings' => nisha_teacher_settings()];
}

/** Shared filters keep the result count and displayed lessons consistent. */
function nisha_teacher_catalog_filters(?string $course = null, ?string $skill = null, ?string $search = null): array
{
    $where = ["v.status='published'", "p.status='approved'"]; $parameters = [];
    if ($course !== null && $course !== '') { $where[] = 'v.course_id=?'; $parameters[] = $course; }
    if ($skill !== null && $skill !== '') { $where[] = 'v.skill=?'; $parameters[] = $skill; }
    if ($search !== null && trim($search) !== '') {
        $term = '%' . strtr(trim($search), ['=' => '==', '%' => '=%', '_' => '=_']) . '%';
        $where[] = "(v.title LIKE ? ESCAPE '=' OR v.description LIKE ? ESCAPE '=' OR p.display_name LIKE ? ESCAPE '=')";
        array_push($parameters, $term, $term, $term);
    }
    return [implode(' AND ', $where), $parameters];
}

function nisha_teacher_catalog_count(?string $course = null, ?string $skill = null, ?string $search = null): int
{
    [$where, $parameters] = nisha_teacher_catalog_filters($course, $skill, $search);
    $q = db()->prepare('SELECT COUNT(*) FROM coaching_videos v JOIN teacher_profiles p ON p.user_id=v.teacher_id WHERE ' . $where);
    $q->execute($parameters);
    return (int) $q->fetchColumn();
}

function nisha_teacher_catalog(?string $course = null, ?string $skill = null, ?string $search = null): array
{
    [$where, $parameters] = nisha_teacher_catalog_filters($course, $skill, $search);
    $q = db()->prepare('SELECT v.*,p.display_name AS teacher_name FROM coaching_videos v JOIN teacher_profiles p ON p.user_id=v.teacher_id WHERE ' . $where . ' ORDER BY v.created_at DESC,v.id DESC LIMIT 100');
    $q->execute($parameters);
    return $q->fetchAll();
}

function nisha_teacher_video(int $id): ?array
{
    $q = db()->prepare("SELECT v.*,p.display_name AS teacher_name FROM coaching_videos v JOIN teacher_profiles p ON p.user_id=v.teacher_id WHERE v.id=? AND v.status='published' AND p.status='approved'");
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

function nisha_teacher_minor(mixed $value): int
{
    if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,8})(?:\.([0-9]{1,2}))?\z/D', $value)) throw new InvalidArgumentException('Enter money as a non-negative amount with up to two decimal places.');
    $parts = explode('.', $value);
    return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
}

/** Integer allocation conserves the pool, including rounding remainder cents. */
function nisha_teacher_allocate(int $pool, array $views): array
{
    if ($pool < 0 || $pool > 100000000000) throw new InvalidArgumentException('The reward pool is outside the supported range.');
    $valid = [];
    foreach ($views as $teacherId => $count) {
        if ((int) $teacherId < 1 || !is_int($count) || $count < 0 || $count > 100000000) throw new InvalidArgumentException('Qualified view totals are invalid.');
        if ($count > 0) $valid[(int) $teacherId] = $count;
    }
    ksort($valid);
    $total = array_sum($valid);
    if ($pool === 0 || $total === 0) return array_fill_keys(array_keys($valid), 0);
    $allocations = []; $remainders = []; $distributed = 0;
    // Avoid an overflowing pool*views product by separating quotient/remainder.
    $whole = intdiv($pool, $total); $rest = $pool % $total;
    foreach ($valid as $teacherId => $count) {
        $fractionProduct = $rest * $count;
        if (!is_int($fractionProduct)) throw new InvalidArgumentException('The view distribution exceeds the supported range.');
        $amount = $whole * $count + intdiv($fractionProduct, $total);
        $allocations[$teacherId] = $amount; $distributed += $amount;
        $remainders[$teacherId] = $fractionProduct % $total;
    }
    $ids = array_keys($valid);
    usort($ids, static fn (int $a, int $b): int => ($remainders[$b] <=> $remainders[$a]) ?: ($a <=> $b));
    for ($i = 0; $i < $pool - $distributed; $i++) $allocations[$ids[$i]]++;
    return $allocations;
}

function nisha_teacher_admin_data(): array
{
    $profiles = db()->query('SELECT p.*,u.email FROM teacher_profiles p JOIN users u ON u.id=p.user_id ORDER BY p.created_at DESC LIMIT 100')->fetchAll();
    $videos = db()->query('SELECT v.*,p.display_name AS teacher_name,u.email FROM coaching_videos v JOIN teacher_profiles p ON p.user_id=v.teacher_id JOIN users u ON u.id=v.teacher_id ORDER BY v.created_at DESC LIMIT 100')->fetchAll();
    $periods = db()->query('SELECT * FROM teacher_profit_periods ORDER BY period DESC,id DESC LIMIT 100')->fetchAll();
    $allocations = db()->query('SELECT a.*,p.period,t.display_name AS teacher_name FROM teacher_reward_allocations a JOIN teacher_profit_periods p ON p.id=a.period_id JOIN teacher_profiles t ON t.user_id=a.teacher_id ORDER BY p.period DESC,a.id DESC LIMIT 200')->fetchAll();
    return compact('profiles','videos','periods','allocations');
}

function nisha_teacher_admin_action(array $admin, array $input): string
{
    if (!nisha_is_admin($admin)) throw new InvalidArgumentException('Owner access is required.');
    $action = $input['action'] ?? '';
    if ($action === 'save_policy') {
        $watch = filter_var($input['watch_percent'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 10, 'max_range' => 100]]);
        $currency = strtoupper((string) ($input['currency'] ?? ''));
        if ($watch === false || !in_array($currency, ['AUD','USD','NZD','EUR','GBP','INR','CAD','SGD'], true)) throw new InvalidArgumentException('Choose a valid watch threshold and reward currency.');
        $hostsText = nisha_teacher_text($input['video_hosts'] ?? '', 'video hosts', 4000);
        $hosts = array_values(array_unique(array_filter(array_map('strtolower', preg_split('/[\s,]+/', $hostsText) ?: []))));
        if (count($hosts) > 30) throw new InvalidArgumentException('Use no more than 30 approved video hosts.');
        foreach ($hosts as $host) if (!preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.[a-z]{2,63}\z/D', $host) || preg_match('/(?:^|\.)(?:localhost|local|internal|invalid|test)\z/D', $host)) throw new InvalidArgumentException('Enter plain public video hostnames, without a URL scheme, wildcard or path.');
        $q = db()->prepare('INSERT INTO teacher_policy_settings(id,enabled,watch_percent,paid_students_only,currency,video_hosts_json,updated_by) VALUES (1,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),watch_percent=VALUES(watch_percent),paid_students_only=VALUES(paid_students_only),currency=VALUES(currency),video_hosts_json=VALUES(video_hosts_json),updated_by=VALUES(updated_by)');
        $q->execute([($input['enabled'] ?? '') === '1' ? 1 : 0,$watch,($input['paid_students_only'] ?? '') === '1' ? 1 : 0,$currency,json_encode($hosts, JSON_THROW_ON_ERROR),(int) $admin['id']]);
        return 'Teacher policy saved. The reward pool remains 90% of verified profit after expenses and carried losses.';
    }
    if (in_array($action, ['approve_teacher','reject_teacher'], true)) {
        $id = nisha_teacher_positive_id($input['user_id'] ?? null, 'teacher');
        $note = nisha_teacher_text($input['review_note'] ?? '', 'review note', 1000);
        $status = $action === 'approve_teacher' ? 'approved' : 'rejected';
        $pdo = db(); $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT * FROM teacher_profiles WHERE user_id=? FOR UPDATE'); $q->execute([$id]); $profile = $q->fetch();
            $expected = $input['expected_profile_hash'] ?? null;
            if (!$profile || !is_string($expected) || !hash_equals(nisha_teacher_profile_review_hash($profile), $expected)) throw new InvalidArgumentException('This teacher application changed since you opened it. Reload and review the current application.');
            $q = $pdo->prepare('UPDATE teacher_profiles SET status=?,review_note=?,reviewed_by=? WHERE user_id=?');
            $q->execute([$status,$note,(int) $admin['id'],$id]);
            if ($q->rowCount() !== 1) throw new InvalidArgumentException('Choose an available teacher application.');
            if ($status === 'approved') {
                $q = $pdo->prepare("INSERT INTO user_roles(user_id,role,assigned_by) VALUES (?,'teacher',?) ON DUPLICATE KEY UPDATE role=IF(role='admin','admin','teacher'),assigned_by=VALUES(assigned_by)");
                $q->execute([$id,(int) $admin['id']]);
            }
            $pdo->commit();
        } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
        return 'Teacher application ' . $status . '.';
    }
    if (in_array($action, ['approve_video','reject_video'], true)) {
        $id = nisha_teacher_positive_id($input['video_id'] ?? null, 'video');
        $note = nisha_teacher_text($input['review_note'] ?? '', 'review note', 1000);
        if ($action === 'approve_video' && ($input['verified_duration'] ?? '') !== '1') throw new InvalidArgumentException('Check the video content, declared duration and teaching rights before publishing.');
        $expectedRevision = nisha_teacher_positive_id($input['expected_revision'] ?? null, 'lesson version');
        $pdo = db(); $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT v.*,p.status AS teacher_status FROM coaching_videos v JOIN teacher_profiles p ON p.user_id=v.teacher_id WHERE v.id=? FOR UPDATE');
            $q->execute([$id]); $video = $q->fetch();
            if (!$video || ($action === 'approve_video' && $video['teacher_status'] !== 'approved')) throw new InvalidArgumentException('Choose a video from an approved teacher.');
            if ((int) $video['revision'] !== $expectedRevision) throw new InvalidArgumentException('This lesson changed after your preview. Reload and review the current version before deciding.');
            if ($action === 'approve_video') nisha_teacher_video_url($video['video_url']);
            $q = $pdo->prepare('UPDATE coaching_videos SET status=?,review_note=?,reviewed_by=? WHERE id=? AND revision=?');
            $q->execute([$action === 'approve_video' ? 'published' : 'rejected',$note,(int) $admin['id'],$id,$expectedRevision]);
            $pdo->commit();
        } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
        return $action === 'approve_video' ? 'Video published.' : 'Video rejected.';
    }
    if ($action === 'approve_profit') return nisha_teacher_approve_profit($admin, $input);
    if ($action === 'record_payout') {
        if (($input['confirmation'] ?? '') !== '1') throw new InvalidArgumentException('Confirm that the payout has already been made outside this website.');
        $id = nisha_teacher_positive_id($input['allocation_id'] ?? null, 'reward allocation');
        $reference = nisha_teacher_text($input['reference'] ?? null, 'payment reference', 200, 3);
        $q = db()->prepare("UPDATE teacher_reward_allocations SET status='paid',payout_reference=?,paid_by=?,paid_at=UTC_TIMESTAMP() WHERE id=? AND status='allocated' AND amount_cents>0");
        $q->execute([$reference,(int) $admin['id'],$id]);
        if ($q->rowCount() !== 1) throw new InvalidArgumentException('This reward is unavailable, already recorded as paid, or has no payable balance.');
        return 'External payout recorded. This website has not transferred money.';
    }
    throw new InvalidArgumentException('Choose a valid owner action.');
}

function nisha_teacher_approve_profit(array $admin, array $input): string
{
    if (!nisha_is_admin($admin)) throw new InvalidArgumentException('Owner access is required.');
    $settings = nisha_teacher_settings();
    if (!$settings['enabled']) throw new InvalidArgumentException('Configure and enable the teacher reward policy before approving a reward period.');
    $period = $input['period'] ?? null;
    if (!is_string($period) || !preg_match('/\A[0-9]{4}-(?:0[1-9]|1[0-2])\z/D', $period) || $period >= gmdate('Y-m') || $period < '2020-01') throw new InvalidArgumentException('Choose a completed calendar month in UTC.');
    $currency = strtoupper((string) ($input['currency'] ?? ''));
    if ($currency !== $settings['currency']) throw new InvalidArgumentException('Use the currency configured for teacher rewards.');
    if (($input['confirmation'] ?? '') !== '1') throw new InvalidArgumentException('Confirm that revenue, refunds and all business costs have been verified.');
    $note = nisha_teacher_text($input['verification_note'] ?? null, 'profit verification note', 2000, 10);
    $revenue = nisha_teacher_minor($input['revenue'] ?? null);
    $refunds = nisha_teacher_minor($input['refunds'] ?? null);
    $expenses = nisha_teacher_minor($input['expenses'] ?? null);
    $pdo = db(); $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('INSERT IGNORE INTO teacher_reward_locks(currency) VALUES (?)'); $q->execute([$currency]);
        $q = $pdo->prepare('SELECT currency FROM teacher_reward_locks WHERE currency=? FOR UPDATE'); $q->execute([$currency]);
        $q = $pdo->prepare('SELECT period,remaining_loss_cents FROM teacher_profit_periods WHERE currency=? ORDER BY period DESC LIMIT 1 FOR UPDATE');
        $q->execute([$currency]); $previous = $q->fetch();
        if ($previous && $period <= $previous['period']) throw new InvalidArgumentException('Approve months in chronological order. An approved period cannot be changed or approved twice.');
        $carriedLoss = $previous ? (int) $previous['remaining_loss_cents'] : 0;
        $profit = $revenue - $refunds - $expenses - $carriedLoss;
        $remainingLoss = max(0, -$profit);
        $pool = intdiv(max(0, $profit) * $settings['share_basis_points'], 10000);
        // Unique period/currency freezes this ledger and prevents double awards.
        $q = $pdo->prepare('INSERT INTO teacher_profit_periods(period,currency,revenue_cents,refunds_cents,expenses_cents,carried_loss_cents,remaining_loss_cents,net_profit_cents,pool_cents,share_basis_points,verification_note,approved_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$period,$currency,$revenue,$refunds,$expenses,$carriedLoss,$remainingLoss,$profit,$pool,$settings['share_basis_points'],$note,(int) $admin['id']]);
        $periodId = (int) $pdo->lastInsertId();
        $q = $pdo->prepare("SELECT w.teacher_id,COUNT(*) AS qualified_views FROM coaching_qualified_views w JOIN teacher_profiles p ON p.user_id=w.teacher_id WHERE w.period=? AND p.status='approved' GROUP BY w.teacher_id ORDER BY w.teacher_id");
        $q->execute([$period]); $views = [];
        foreach ($q->fetchAll() as $row) $views[(int) $row['teacher_id']] = (int) $row['qualified_views'];
        $allocations = nisha_teacher_allocate($pool, $views);
        $q = $pdo->prepare('INSERT INTO teacher_reward_allocations(period_id,teacher_id,qualified_views,currency,amount_cents) VALUES (?,?,?,?,?)');
        foreach ($allocations as $teacherId => $amount) $q->execute([$periodId,$teacherId,$views[$teacherId],$currency,$amount]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof PDOException && $exception->getCode() === '23000') throw new InvalidArgumentException('This month and currency have already been approved. Approved reward ledgers cannot be changed.');
        throw $exception;
    }
    return $profit > 0 ? 'Profit period approved and rewards allocated within its capped 90% pool.' : 'Period recorded with no distributable profit. Remaining losses carry forward before future rewards.';
}

function nisha_teacher_student_eligible(array $user, array $video): bool
{
    if ((int) $user['id'] === (int) $video['teacher_id'] || nisha_is_admin($user)) return false;
    $profile = nisha_teacher_profile(db(), (int) $user['id']);
    if ($profile && $profile['status'] === 'approved') return false;
    if (!nisha_teacher_settings()['paid_students_only']) return true;
    return function_exists('nisha_commerce_has_paid') && nisha_commerce_has_paid(db(), (int) $user['id']);
}

/** Client position is bounded by elapsed server time; seeking cannot grant time. */
function nisha_teacher_watch_credit(float $elapsed, float $delta, bool $playing, bool $visible): float
{
    if (!$playing || !$visible || $elapsed <= 0 || $elapsed > 45 || $delta < 0 || $delta > $elapsed * 2.25 + 2) return 0;
    return max(0, min($delta, $elapsed * 2));
}

/** Store the union of watched intervals, so replaying a section adds no time. */
function nisha_teacher_watch_ranges(array $ranges, float $start, float $end, float $duration): array
{
    if (count($ranges) > 256 || !is_finite($start) || !is_finite($end) || $start < 0 || $end < $start || $duration <= 0) throw new InvalidArgumentException('Video progress could not be verified. Play the lesson again to reconnect.');
    $all = [];
    foreach ($ranges as $range) {
        if (!is_array($range) || count($range) !== 2 || !is_numeric($range[0]) || !is_numeric($range[1]) || !is_finite((float) $range[0]) || !is_finite((float) $range[1]) || $range[0] < 0 || $range[1] < $range[0] || $range[1] > $duration) throw new InvalidArgumentException('Saved video progress is invalid.');
        $all[] = [(float) $range[0], (float) $range[1]];
    }
    if ($end > $start) $all[] = [min($duration, $start), min($duration, $end)];
    usort($all, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
    $merged = [];
    foreach ($all as $range) {
        $last = count($merged) - 1;
        if ($last >= 0 && $range[0] <= $merged[$last][1] + .001) $merged[$last][1] = max($merged[$last][1], $range[1]);
        else $merged[] = $range;
    }
    if (count($merged) > 256) throw new InvalidArgumentException('Watch the lesson in longer connected sections to reconnect verified progress.');
    return $merged;
}

function nisha_teacher_watch(array $user, array $input): array
{
    $id = nisha_teacher_positive_id($input['video_id'] ?? null, 'video');
    $video = nisha_teacher_video($id);
    if (!$video) throw new InvalidArgumentException('This coaching video is not available.');
    $action = $input['action'] ?? null;
    if (!in_array($action, ['start','heartbeat'], true)) throw new InvalidArgumentException('Choose a valid video progress action.');
    $allowed = $action === 'start' ? ['action','video_id','position'] : ['action','video_id','token','position','playing','visible'];
    if (array_diff(array_keys($input), $allowed)) throw new InvalidArgumentException('Send only the requested video progress fields.');
    $settings = nisha_teacher_settings();
    $required = (int) ceil((int) $video['duration_seconds'] * $settings['watch_percent'] / 100);
    $eligible = nisha_teacher_student_eligible($user, $video);
    $pdo = db(); $pdo->beginTransaction();
    try {
        // One student may contribute verified time to one active lesson only.
        nisha_commerce_lock_user($pdo, (int)$user['id']);
        $period = (string) $pdo->query("SELECT DATE_FORMAT(UTC_TIMESTAMP(),'%Y-%m')")->fetchColumn();
        $revision = (int) $video['revision'];
        $q = $pdo->prepare('SELECT *,UTC_TIMESTAMP(6) AS server_now FROM coaching_watch_sessions WHERE user_id=? AND video_id=? AND period=? AND video_revision=? FOR UPDATE');
        $q->execute([(int) $user['id'],$id,$period,$revision]); $row = $q->fetch();
        if ($action === 'start') {
            $position = $input['position'] ?? 0;
            if ((!is_int($position) && !is_float($position)) || !is_finite((float) $position) || $position < 0 || $position > (int) $video['duration_seconds']) throw new InvalidArgumentException('Choose a valid starting video position.');
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256',$token);
            $q = $pdo->prepare('INSERT INTO coaching_watch_sessions(user_id,video_id,period,video_revision,token_hash,last_position,last_seen) VALUES (?,?,?,?,?,?,UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),last_position=VALUES(last_position),last_seen=UTC_TIMESTAMP(6)');
            $q->execute([(int) $user['id'],$id,$period,$revision,$hash,$position]);
            $q = $pdo->prepare('INSERT INTO coaching_watch_presence(user_id,video_id,video_revision,token_hash) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE video_id=VALUES(video_id),video_revision=VALUES(video_revision),token_hash=VALUES(token_hash)');
            $q->execute([(int) $user['id'],$id,$revision,$hash]);
            $watched = $row ? (float) $row['watched_seconds'] : 0;
            $qualified = $row && $row['qualified_at'] !== null;
        } else {
            $token = $input['token'] ?? null;
            if (!$row || !is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/D', $token) || !hash_equals($row['token_hash'], hash('sha256',$token))) throw new InvalidArgumentException('The watch session changed. Play this video again to reconnect progress.');
            $q = $pdo->prepare('SELECT * FROM coaching_watch_presence WHERE user_id=? FOR UPDATE'); $q->execute([(int) $user['id']]); $presence = $q->fetch();
            if (!$presence || (int) $presence['video_id'] !== $id || (int) $presence['video_revision'] !== $revision || !hash_equals($presence['token_hash'], hash('sha256',$token))) throw new InvalidArgumentException('Another lesson became active. Play this lesson again to reconnect verified progress.');
            $position = $input['position'] ?? null;
            if ((!is_int($position) && !is_float($position)) || !is_finite((float) $position) || $position < 0 || $position > (int) $video['duration_seconds'] + 5 || !is_bool($input['playing'] ?? null) || !is_bool($input['visible'] ?? null)) throw new InvalidArgumentException('Video progress is invalid.');
            $before = (float) (new DateTimeImmutable($row['last_seen'], new DateTimeZone('UTC')))->format('U.u');
            $now = (float) (new DateTimeImmutable($row['server_now'], new DateTimeZone('UTC')))->format('U.u');
            $credit = $eligible ? nisha_teacher_watch_credit($now - $before, (float) $position - (float) $row['last_position'], $input['playing'], $input['visible']) : 0;
            $ranges = json_decode((string) ($row['watched_ranges_json'] ?: '[]'), true, 16, JSON_THROW_ON_ERROR);
            $ranges = nisha_teacher_watch_ranges($ranges, max(0, (float) $position - $credit), (float) $position, (float) $video['duration_seconds']);
            $watched = array_sum(array_map(static fn (array $range): float => $range[1] - $range[0], $ranges));
            $qualified = $row['qualified_at'] !== null || ($eligible && $watched >= $required);
            $q = $pdo->prepare('UPDATE coaching_watch_sessions SET watched_seconds=?,watched_ranges_json=?,last_position=?,last_seen=UTC_TIMESTAMP(6),qualified_at=IF(qualified_at IS NULL AND ?=1,UTC_TIMESTAMP(),qualified_at) WHERE id=?');
            $q->execute([$watched,json_encode($ranges,JSON_THROW_ON_ERROR),$position,$qualified ? 1 : 0,(int) $row['id']]);
            if ($qualified && $eligible) {
                $q = $pdo->prepare('INSERT IGNORE INTO coaching_qualified_views(user_id,video_id,period,teacher_id,video_revision) VALUES (?,?,?,?,?)');
                $q->execute([(int) $user['id'],$id,$period,(int) $video['teacher_id'],$revision]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
    return ['success' => true,'token' => $token,'watched_seconds' => (int) floor($watched),'required_seconds' => $required,'qualified' => (bool) $qualified,'reward_eligible' => $eligible,
        'message' => !$eligible ? 'This viewing is available for learning but does not earn a qualified student view.' : ($qualified ? 'Qualified student view recorded once for this video this month.' : 'Watch progress recorded. Qualified views require active viewing; skipping does not add time.')];
}
