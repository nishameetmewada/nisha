<?php
declare(strict_types=1);
require_once __DIR__ . '/commerce.php';

function nisha_promotions_require_owner(array $owner): void
{
    require_once __DIR__ . '/teachers.php';
    if (!nisha_is_admin($owner)) throw new NishaCommerceException('Owner access is required.', 'owner_required', 403);
}

function nisha_promotions_code(string $code): string
{
    $code = strtoupper(trim($code));
    if ($code !== '' && !preg_match('/\A[A-Z0-9][A-Z0-9_-]{2,39}\z/D', $code)) throw new NishaCommerceException('Enter a promotion code containing 3–40 letters, numbers, hyphens or underscores.', 'promotion_invalid', 422);
    return $code;
}

function nisha_promotions_limit(mixed $value): ?int
{
    if ($value === '' || $value === null) return null;
    if ((!is_int($value) && (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,8}\z/D', $value))) || (int) $value < 1 || (int) $value > 999999999) throw new InvalidArgumentException('Enter a usage limit from 1 to 999999999, or leave it empty for unlimited.');
    return (int) $value;
}

function nisha_promotions_date(mixed $value): ?string
{
    if ($value === '' || $value === null) return null;
    if (!is_string($value)) throw new InvalidArgumentException('Enter a valid UTC start or end date.');
    $value = str_replace('T', ' ', $value);
    if (preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}\z/D', $value)) $value .= ':00';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d H:i:s') !== $value || $date->format('Y') < '2000' || $date->format('Y') > '2099') throw new InvalidArgumentException('Enter a valid UTC start or end date from 2000 to 2099.');
    return $value;
}

function nisha_promotions_list(PDO $pdo, array $owner): array
{
    nisha_promotions_require_owner($owner);
    return $pdo->query("SELECT p.*,COALESCE(u.reserved_count,0) AS reserved_count,COALESCE(u.redeemed_count,0) AS redeemed_count FROM commerce_promotions p LEFT JOIN (SELECT promotion_id,SUM(status='reserved') AS reserved_count,SUM(status='redeemed') AS redeemed_count FROM commerce_promotion_orders GROUP BY promotion_id) u ON u.promotion_id=p.id ORDER BY p.created_at DESC,p.id DESC")->fetchAll();
}

function nisha_promotions_audit(PDO $pdo, int $promotionId, array $owner, string $action, array $settings): void
{
    $q = $pdo->prepare('INSERT INTO commerce_promotions_audit(promotion_id,owner_id,action,settings_json) VALUES(?,?,?,?)');
    $q->execute([$promotionId,(int) $owner['id'],$action,json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
}

function nisha_promotions_save(PDO $pdo, array $owner, array $input): int
{
    nisha_promotions_require_owner($owner);
    $id = empty($input['id']) ? 0 : filter_var($input['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id < 0) throw new InvalidArgumentException('Choose an existing promotion.');
    if (!is_string($input['code'] ?? null)) throw new InvalidArgumentException('Enter a promotion code.');
    $code = nisha_promotions_code($input['code']);
    if ($code === '') throw new InvalidArgumentException('Enter a promotion code.');
    $label = $input['label'] ?? $input['public_label'] ?? '';
    if (!is_string($label) || strlen($label) > 120 || !preg_match('//u', $label) || preg_match('/[\x00-\x1F\x7F]/', $label)) throw new InvalidArgumentException('Enter a public label of at most 120 characters.');
    $type = $input['type'] ?? '';
    if (!in_array($type, ['percent', 'fixed'], true)) throw new InvalidArgumentException('Choose a percentage or fixed amount discount.');
    if ($type === 'percent') {
        $value = filter_var($input['percent'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($value) || $value < 1 || $value > 99) throw new InvalidArgumentException('Enter a whole-number discount from 1% to 99%.');
    } else {
        $text = $input['fixed_amount'] ?? '';
        if (!is_string($text) || !preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?\z/D', $text)) throw new InvalidArgumentException('Enter a fixed discount using at most two decimal places.');
        $pieces = explode('.', $text, 2); $value = (int) $pieces[0] * 100 + (int) str_pad($pieces[1] ?? '', 2, '0');
        if ($value < 1) throw new InvalidArgumentException('The fixed discount must be greater than zero.');
    }
    $currency = is_string($input['currency'] ?? null) ? strtolower($input['currency']) : '';
    if (!in_array($currency, ['usd','nzd','aud','gbp','eur','inr','cad','sgd'], true)) throw new InvalidArgumentException('Choose a supported promotion currency.');
    $start = nisha_promotions_date($input['starts_at'] ?? null); $end = nisha_promotions_date($input['ends_at'] ?? null);
    if ($start !== null && $end !== null && $end <= $start) throw new InvalidArgumentException('The end must be later than the start, in UTC.');
    $settings = ['code'=>$code,'public_label'=>trim($label),'discount_type'=>$type,'discount_value'=>$value,'currency'=>$currency,'starts_at'=>$start,'ends_at'=>$end,
        'max_redemptions'=>nisha_promotions_limit($input['max_redemptions'] ?? null),'max_per_student'=>nisha_promotions_limit($input['max_per_student'] ?? null),
        'enabled'=>(int) (($input['enabled'] ?? '') === '1'),'featured'=>(int) (($input['featured'] ?? '') === '1')];
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $q = $pdo->prepare('SELECT id FROM commerce_promotions WHERE id=? FOR UPDATE'); $q->execute([$id]);
            if (!$q->fetchColumn()) throw new InvalidArgumentException('The promotion was not found.');
            $q = $pdo->prepare('UPDATE commerce_promotions SET code=?,public_label=?,discount_type=?,discount_value=?,currency=?,starts_at=?,ends_at=?,max_redemptions=?,max_per_student=?,enabled=?,featured=?,updated_by=? WHERE id=?');
            $q->execute([...array_values($settings),(int) $owner['id'],$id]);
        } else {
            $q = $pdo->prepare('INSERT INTO commerce_promotions(code,public_label,discount_type,discount_value,currency,starts_at,ends_at,max_redemptions,max_per_student,enabled,featured,updated_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
            $q->execute([...array_values($settings),(int) $owner['id']]); $id = (int) $pdo->lastInsertId();
        }
        nisha_promotions_audit($pdo, $id, $owner, empty($input['id']) ? 'created' : 'edited', $settings);
        $pdo->commit(); return $id;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($exception instanceof PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062) throw new InvalidArgumentException('That promotion code already exists.');
        throw $exception;
    }
}

function nisha_promotions_set_enabled(PDO $pdo, array $owner, int $id, bool $enabled): void
{
    nisha_promotions_require_owner($owner);
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT * FROM commerce_promotions WHERE id=? FOR UPDATE'); $q->execute([$id]); $row = $q->fetch();
        if (!$row) throw new InvalidArgumentException('The promotion was not found.');
        $q = $pdo->prepare('UPDATE commerce_promotions SET enabled=?,updated_by=? WHERE id=?'); $q->execute([(int) $enabled,(int) $owner['id'],$id]);
        nisha_promotions_audit($pdo, $id, $owner, $enabled ? 'enabled' : 'disabled', ['enabled'=>$enabled]);
        $pdo->commit();
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
}

/** Public banners expose active featured offers, never private account data. */
function nisha_promotions_featured(?PDO $pdo = null): ?array
{
    try {
        $plan = nisha_commerce_plan($pdo);
        $q = ($pdo ?? db())->prepare("SELECT id,code,public_label,discount_type,discount_value,currency,ends_at FROM commerce_promotions p WHERE enabled=1 AND featured=1 AND currency=? AND (starts_at IS NULL OR starts_at<=UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at>UTC_TIMESTAMP()) AND (max_redemptions IS NULL OR max_redemptions>(SELECT COUNT(*) FROM commerce_promotion_orders o WHERE o.promotion_id=p.id AND o.status IN ('reserved','redeemed'))) ORDER BY id DESC LIMIT 1");
        $q->execute([strtolower((string) $plan['currency'])]); $row = $q->fetch();
        if (!$row) return null;
        $base = (int) $plan['amount_minor']; $discount = $row['discount_type'] === 'percent' ? intdiv($base * (int) $row['discount_value'], 100) : (int) $row['discount_value'];
        if ($discount < 1 || $base - $discount < 100) return null;
        return $row + ['base_amount_minor'=>$base,'discount_minor'=>$discount,'amount_minor'=>$base-$discount,'label'=>$row['public_label']];
    } catch (Throwable $exception) { return null; }
}

/** Caller must hold a transaction and user lock when reserving a quotation. */
function nisha_promotions_quote(PDO $pdo, int $userId, string $code, array $plan, bool $reserve = false): array
{
    $code = nisha_promotions_code($code);
    $base = (int) $plan['amount_minor'];
    $quote = ['promotion_id'=>null,'code'=>'','public_label'=>'','base_amount_minor'=>$base,'discount_minor'=>0,'amount_minor'=>$base,'currency'=>$plan['currency'],'access_days'=>(int) $plan['access_days']];
    if ($code === '') return $quote;
    if ($reserve && !$pdo->inTransaction()) throw new LogicException('Promotion reservations require a transaction.');
    $q = $pdo->prepare('SELECT *,(starts_at IS NULL OR starts_at<=UTC_TIMESTAMP()) AS started,(ends_at IS NULL OR ends_at>UTC_TIMESTAMP()) AS unexpired FROM commerce_promotions WHERE code=?' . ($reserve ? ' FOR UPDATE' : '')); $q->execute([$code]); $promotion = $q->fetch();
    if (!$promotion || !$promotion['enabled'] || !$promotion['started'] || !$promotion['unexpired'] || $promotion['currency'] !== $plan['currency']) throw new NishaCommerceException('This promotion is unavailable, expired or does not apply to the current currency.', 'promotion_unavailable', 422);
    // A locking/current read matters here: an earlier entitlement read may
    // already have established a repeatable-read snapshot before we waited
    // for another student's promotion lock.
    $q = $pdo->prepare("SELECT user_id FROM commerce_promotion_orders WHERE promotion_id=? AND status IN ('reserved','redeemed')" . ($reserve ? ' FOR UPDATE' : '')); $q->execute([$promotion['id']]); $rows = $q->fetchAll();
    $usage = ['total'=>count($rows),'student'=>count(array_filter($rows,static fn(array $row):bool=>(int)$row['user_id']===$userId))];
    if (($promotion['max_redemptions'] !== null && (int) $usage['total'] >= (int) $promotion['max_redemptions']) || ($promotion['max_per_student'] !== null && (int) $usage['student'] >= (int) $promotion['max_per_student'])) throw new NishaCommerceException('This promotion has reached its usage limit. An existing checkout may still be reserved; resume it or wait for its confirmed expiry.', 'promotion_limit', 409);
    $discount = $promotion['discount_type'] === 'percent' ? intdiv($base * (int) $promotion['discount_value'], 100) : (int) $promotion['discount_value'];
    if ($discount < 1 || $base - $discount < 100) throw new NishaCommerceException('This promotion does not apply to the current price. Checkout must remain at least 1.00 in the plan currency.', 'promotion_price', 422);
    return array_replace($quote, ['promotion_id'=>(int) $promotion['id'],'code'=>$promotion['code'],'public_label'=>$promotion['public_label'],'discount_minor'=>$discount,'amount_minor'=>$base-$discount]);
}

function nisha_promotions_order_snapshot(PDO $pdo, string $orderId, bool $lock = false): ?array
{
    try { $q = $pdo->prepare('SELECT * FROM commerce_promotion_orders WHERE order_id=?' . ($lock ? ' FOR UPDATE' : '')); $q->execute([$orderId]); return $q->fetch() ?: null; }
    catch (PDOException $exception) { if ((int) ($exception->errorInfo[1] ?? 0) === 1146) return null; throw $exception; }
}

function nisha_promotions_snapshot(PDO $pdo, array $order, array $quote): void
{
    try {
        $q = $pdo->prepare('INSERT INTO commerce_promotion_orders(order_id,promotion_id,user_id,code,public_label,base_amount_minor,discount_minor,amount_minor,currency,access_days) VALUES(?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$order['id'],$quote['promotion_id'],$order['user_id'],$quote['code'],$quote['public_label'],$quote['base_amount_minor'],$quote['discount_minor'],$quote['amount_minor'],$quote['currency'],$quote['access_days']]);
    } catch (PDOException $exception) { if ((int) ($exception->errorInfo[1] ?? 0) !== 1146 || $quote['promotion_id'] !== null) throw $exception; }
}

/** Usage survives refunds; a redeemed code cannot be recycled by refunding. */
function nisha_promotions_redeem(PDO $pdo, array $order): void
{
    $snapshot = nisha_promotions_order_snapshot($pdo, $order['id'], true);
    if ($snapshot === null) return;
    if ((int) $snapshot['amount_minor'] !== (int) $order['amount_minor'] || $snapshot['currency'] !== $order['currency'] || (int) $snapshot['access_days'] !== (int) $order['access_days'] || (int) $snapshot['user_id'] !== (int) $order['user_id'] || (int) $snapshot['base_amount_minor'] - (int) $snapshot['discount_minor'] !== (int) $order['amount_minor']) throw new NishaCommerceException('The order price snapshot does not match this payment.', 'webhook_binding', 400);
    if ($snapshot['status'] === 'released') throw new NishaCommerceException('A terminally expired checkout cannot be settled. Contact the owner if payment was taken.', 'webhook_binding', 409);
    $q = $pdo->prepare("UPDATE commerce_promotion_orders SET status='redeemed',redeemed_at=COALESCE(redeemed_at,UTC_TIMESTAMP()) WHERE order_id=? AND status='reserved'"); $q->execute([$order['id']]);
}

/** Only a signed, session-bound terminal event releases a checkout reservation. */
function nisha_promotions_release(PDO $pdo, array $order, string $reason): void
{
    if ($order['status'] !== 'pending') return;
    $snapshot = nisha_promotions_order_snapshot($pdo, $order['id'], true);
    if ($snapshot === null) return;
    $q = $pdo->prepare("UPDATE commerce_promotion_orders SET status='released',release_reason=?,released_at=COALESCE(released_at,UTC_TIMESTAMP()) WHERE order_id=? AND status='reserved'"); $q->execute([$reason,$order['id']]);
}
