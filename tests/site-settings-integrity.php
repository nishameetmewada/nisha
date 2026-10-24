<?php
declare(strict_types=1);
// Isolated local database; never changes the website's singleton settings.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$adminBin = getenv('NISHA_TEST_MYSQL_ADMIN_BIN') ?: '/workspace/.tools/bin/mariadb';
$suffix = bin2hex(random_bytes(7));
$database = 'nisha_site_test_' . $suffix;
$databaseUser = 'site_test_' . $suffix;
$fixtureSecret = bin2hex(random_bytes(24));
$scratch = sys_get_temp_dir() . '/nisha-site-test-' . $suffix;
$checks = 0; $failed = false; $created = false;
function site_test_sql(string $sql): void
{
    global $adminBin;
    $process = proc_open([$adminBin], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start local database fixture.');
    fwrite($pipes[0], $sql); fclose($pipes[0]);
    stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException('Isolated site-settings database operation failed.');
}
function site_ok(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new LogicException($message);
    ++$checks;
}
function site_reject(callable $callback, string $message): void
{
    try { $callback(); } catch (InvalidArgumentException $exception) { site_ok(true, $message); return; }
    throw new LogicException($message);
}
try {
    if (!is_executable($adminBin)) throw new RuntimeException('The local MariaDB admin wrapper is required.');
    mkdir($scratch, 0700); mkdir($scratch . '/config', 0700); mkdir($scratch . '/sessions', 0700);
    site_test_sql("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '$databaseUser'@'127.0.0.1' IDENTIFIED BY '$fixtureSecret'; GRANT SELECT,INSERT,UPDATE,DELETE ON `$database`.* TO '$databaseUser'@'127.0.0.1';");
    $created = true;
    site_test_sql("USE `$database`;\n" . file_get_contents($root . '/database/schema.sql'));
    file_put_contents($scratch . '/config/config.php', '<?php return ' . var_export([
        'db_host'=>'127.0.0.1', 'db_port'=>3306, 'db_name'=>$database, 'db_user'=>$databaseUser,
        'db_password'=>$fixtureSecret, 'admin_user_ids'=>[], 'openai_api_key'=>'',
    ], true) . ';');
    chmod($scratch . '/config/config.php', 0600);
    define('NISHA_ROOT', $scratch); ini_set('session.save_path', $scratch . '/sessions');
    require $root . '/includes/site-settings.php';
    require $root . '/includes/admin-accounts.php';
    require $root . '/includes/teachers.php';
    $pdo = db(); $accounts = [];
    foreach (['owner','student'] as $role) {
        $q = $pdo->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');
        $q->execute(['Site fixture ' . $role, $role . '@example.test', password_hash('Site-settings-fixture-123', PASSWORD_DEFAULT)]);
        $accounts[$role] = ['id'=>(int)$pdo->lastInsertId(), 'name'=>'Site fixture ' . $role, 'email'=>$role . '@example.test'];
    }
    $q = $pdo->prepare("INSERT INTO user_roles(user_id,role) VALUES(?,'admin')"); $q->execute([$accounts['owner']['id']]);
    $defaults = ['support_email'=>'','home_tagline'=>'Your next chapter starts here.','announcement_text'=>'','announcement_enabled'=>false];
    site_ok(nisha_site_settings($pdo) === $defaults, 'A fresh install has stable, public-only defaults.');
    $input = ['support_email'=>' SUPPORT@EXAMPLE.TEST ', 'home_tagline'=>'Build & learn <strong>today</strong>',
        'announcement_text'=>'<img src=x onerror="alert(1)"> Save & practise.', 'announcement_enabled'=>'1'];
    site_reject(fn()=>nisha_site_save_settings($pdo, $accounts['student'], $input), 'An ordinary student cannot change website settings.');
    site_ok((int)$pdo->query('SELECT COUNT(*) FROM site_settings')->fetchColumn() === 0
        && (int)$pdo->query('SELECT COUNT(*) FROM site_settings_audit')->fetchColumn() === 0, 'An unauthorized write creates neither settings nor audit records.');
    foreach ([
        ['support_email'=>'not an email'], ['support_email'=>'javascript:alert(1)'], ['home_tagline'=>'x'],
        ['home_tagline'=>str_repeat('x',181)], ['home_tagline'=>"Line\nbreak"],
        ['announcement_text'=>str_repeat('x',281)], ['announcement_text'=>''],
        ['announcement_text'=>['invalid shape']],
    ] as $invalid) site_reject(fn()=>nisha_site_save_settings($pdo, $accounts['owner'], array_replace($input,$invalid)), 'Invalid public settings are rejected before persistence.');
    site_ok((int)$pdo->query('SELECT COUNT(*) FROM site_settings_audit')->fetchColumn() === 0, 'Rejected validation creates no misleading audit events.');
    nisha_site_save_settings($pdo, $accounts['owner'], $input);
    $saved = nisha_site_settings($pdo);
    site_ok($saved['support_email'] === 'support@example.test' && $saved['home_tagline'] === $input['home_tagline']
        && $saved['announcement_text'] === $input['announcement_text'] && $saved['announcement_enabled'] === true, 'Valid public settings persist with normalized contact email.');
    $audit = $pdo->query('SELECT owner_id,settings_json FROM site_settings_audit')->fetchAll();
    site_ok(count($audit) === 1 && (int)$audit[0]['owner_id'] === $accounts['owner']['id']
        && json_decode($audit[0]['settings_json'],true,8,JSON_THROW_ON_ERROR) === $saved, 'A successful save atomically records its owner and exact public settings.');
    ob_start(); require $root . '/index.php'; $home = (string)ob_get_clean();
    site_ok(str_contains($home, 'aria-label="Website announcement"') && str_contains($home,e($input['announcement_text']))
        && !str_contains($home,$input['announcement_text']), 'The real shared header escapes announcement markup instead of executing it.');
    site_ok(str_contains($home,e($input['home_tagline'])) && !str_contains($home,$input['home_tagline']), 'The real homepage renders the configured tagline as escaped text.');
    site_ok(str_contains($home,'href="mailto:support@example.test"') && str_contains($home,'Contact student support'), 'The real footer exposes only the configured support contact link.');
    site_ok(!str_contains($home,$fixtureSecret) && !str_contains($home,'password_hash'), 'Public page output contains neither configuration secrets nor password hashes.');
    $updated = array_replace($input, ['support_email'=>'','announcement_enabled'=>'0','announcement_text'=>'']);
    nisha_site_save_settings($pdo, $accounts['owner'], $updated);
    site_ok(nisha_site_settings($pdo)['announcement_enabled'] === false && nisha_site_settings($pdo)['support_email'] === '', 'An administrator can hide the announcement and support contact.');
    site_ok((int)$pdo->query('SELECT COUNT(*) FROM site_settings')->fetchColumn() === 1
        && (int)$pdo->query('SELECT COUNT(*) FROM site_settings_audit')->fetchColumn() === 2, 'Updates keep one settings row and append history rather than replacing it.');
    $before = nisha_site_settings($pdo);
    $q=$pdo->prepare("INSERT INTO account_security(user_id,status) VALUES(?,'suspended')"); $q->execute([$accounts['owner']['id']]);
    site_reject(fn()=>nisha_site_save_settings($pdo,$accounts['owner'],$input), 'A suspended owner cannot write even with a previously resolved owner object.');
    $pdo->prepare('DELETE FROM account_security WHERE user_id=?')->execute([$accounts['owner']['id']]);
    $pdo->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$accounts['owner']['id']]);
    site_reject(fn()=>nisha_site_save_settings($pdo,$accounts['owner'],$input), 'A revoked owner cannot use an old actor object to save settings.');
    site_ok(nisha_site_settings($pdo) === $before && (int)$pdo->query('SELECT COUNT(*) FROM site_settings_audit')->fetchColumn() === 2, 'Rejected stale or suspended owner writes preserve settings and history.');
    $pdo->prepare("INSERT INTO user_roles(user_id,role) VALUES(?,'admin')")->execute([$accounts['owner']['id']]);
    site_test_sql("RENAME TABLE `$database`.site_settings_audit TO `$database`.site_settings_audit_fixture;");
    try { nisha_site_save_settings($pdo,$accounts['owner'],$input); throw new LogicException('Missing audit storage accepted a write.'); }
    catch (PDOException $exception) { site_ok((string)$exception->getCode()==='42S02','Unavailable audit storage fails the transaction.'); }
    site_ok(nisha_site_settings($pdo) === $before && !$pdo->inTransaction(), 'An audit failure rolls back the public settings update.');
    site_test_sql("RENAME TABLE `$database`.site_settings TO `$database`.site_settings_fixture;");
    site_ok(nisha_site_settings($pdo) === $defaults, 'Public pages retain safe defaults when the optional settings table is absent.');
    echo 'PASS: ' . $checks . " website-settings integrity checks. Isolated local database; no production settings changed.\n";
} catch (Throwable $exception) { $failed=true; fwrite(STDERR,'FAIL: '.$exception->getMessage()."\n"); }
finally {
    if ($created) try { site_test_sql("DROP DATABASE IF EXISTS `$database`; DROP USER IF EXISTS '$databaseUser'@'127.0.0.1';"); }
    catch (Throwable $exception) { $failed=true; fwrite(STDERR,"Site-settings fixture cleanup failed.\n"); }
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    foreach(glob($scratch.'/sessions/*')?:[] as $file) unlink($file);
    if(is_file($scratch.'/config/config.php')) unlink($scratch.'/config/config.php');
    foreach (['sessions','config',''] as $directory) if(is_dir($scratch.'/'.$directory)) rmdir($scratch.'/'.$directory);
}
exit($failed?1:0);
