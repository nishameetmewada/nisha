<?php
declare(strict_types=1);
// Disposable local database only. No live accounts, secrets or email delivery.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__); $adminBin = getenv('NISHA_TEST_MYSQL_ADMIN_BIN') ?: '/workspace/.tools/bin/mariadb';
if (!is_executable($adminBin)) { fwrite(STDERR, "A local MariaDB admin wrapper is required.\n"); exit(1); }
$suffix = bin2hex(random_bytes(7)); $database = 'nisha_admin_test_' . $suffix; $databaseUser = 'admin_test_' . $suffix; $fixtureSecret = bin2hex(random_bytes(24));
$scratch = sys_get_temp_dir() . '/nisha-admin-test-' . $suffix; $checks = 0; $failed = false;
function admin_test_sql(string $sql): void {
    global $adminBin; $process = proc_open([$adminBin], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start the local fixture.');
    fwrite($pipes[0], $sql); fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException('Disposable administrator database setup or cleanup failed.');
}
function admin_ok(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException($message); ++$checks; }
function admin_reject(callable $callback, string $message): void { try { $callback(); } catch (InvalidArgumentException $exception) { admin_ok(true, $message); return; } throw new RuntimeException($message); }
function admin_fixture_user(string $name, string $email): int {
    $q = db()->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)'); $q->execute([$name,$email,password_hash('Owner-fixture-password-123', PASSWORD_DEFAULT)]); return (int) db()->lastInsertId();
}
try {
    mkdir($scratch,0700); mkdir($scratch.'/config',0700);
    if (!preg_match('/CREATE TABLE IF NOT EXISTS users \(.*?\) ENGINE=.*?;/s', (string) file_get_contents($root.'/database/schema.sql'), $users)) throw new RuntimeException('Canonical users schema is unavailable.');
    if (!preg_match('/CREATE TABLE IF NOT EXISTS user_roles \(.*?\) ENGINE=.*?;/s', (string) file_get_contents($root.'/database/migrations/005_teachers.sql'), $roles)) throw new RuntimeException('Canonical role schema is unavailable.');
    admin_test_sql("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nCREATE USER '$databaseUser'@'127.0.0.1' IDENTIFIED BY '$fixtureSecret';\nGRANT SELECT,INSERT,UPDATE,DELETE ON `$database`.* TO '$databaseUser'@'127.0.0.1';\nUSE `$database`;\n".$users[0]."\n".$roles[0]);
    file_put_contents($scratch.'/config/config.php', '<?php return '.var_export(['db_host'=>'127.0.0.1','db_port'=>3306,'db_name'=>$database,'db_user'=>$databaseUser,'db_password'=>$fixtureSecret,'admin_user_ids'=>[3]], true).';'); chmod($scratch.'/config/config.php',0600);
    define('NISHA_ROOT',$scratch); require $root.'/includes/admin-accounts.php';
    $owner = admin_fixture_user('Fixture owner','owner@example.test'); $student = admin_fixture_user('100% Real_User','student@example.test'); $configured = admin_fixture_user('Configured owner','configured@example.test'); $teacher = admin_fixture_user('Fixture teacher','teacher@example.test');
    admin_ok($owner===1 && $configured===3, 'Configured numeric account fixture is deterministic.');
    $q=db()->prepare("INSERT INTO user_roles(user_id,role) VALUES(?,'admin'),(?,'teacher')"); $q->execute([$owner,$teacher]);
    admin_ok(nisha_account_security(db(),$student)===['status'=>'active','auth_version'=>0,'available'=>false], 'Missing optional migration preserves existing login at version zero.');
    admin_ok(nisha_account_login_version(db(),$student)===0 && nisha_account_session_valid(db(),$student,0), 'Legacy account session remains valid before additive migration.');
    try { nisha_admin_accounts_ready(db()); throw new LogicException('Missing migration accepted admin writes.'); } catch (RuntimeException $exception) { admin_ok(str_contains($exception->getMessage(),'updated database schema'), 'Admin writes fail closed before the migration.'); }
    admin_test_sql("USE `$database`;\n".file_get_contents($root.'/database/migrations/008_admin_accounts.sql'));
    $input=['current_password'=>'Owner-fixture-password-123','reason'=>'Verified local fixture correction.'];
    admin_ok(nisha_account_security(db(),$student)===['status'=>'active','auth_version'=>0,'available'=>true], 'Missing security row uses active version-zero defaults.');
    admin_ok(nisha_admin_accounts_is_admin(db(),$owner) && nisha_admin_accounts_is_admin(db(),$configured) && !nisha_admin_accounts_is_admin(db(),$student), 'Assigned and private numeric administrators are recognized.');
    $search=nisha_admin_accounts_search(db(),'%'); admin_ok($search['total']===1 && $search['items'][0]['id']===$student, 'Account search escapes literal SQL wildcard characters.');
    $search=nisha_admin_accounts_search(db(),'',$page=99,$perPage=2); admin_ok($search['page']===2 && count($search['items'])===2 && $search['total']===4, 'Search pages clamp bounds and return only bounded rows.');
    $detail=nisha_admin_accounts_detail(db(),$owner); admin_ok(!array_key_exists('password_hash',$detail) && $detail['audit']===[], 'Account DTOs never expose password hashes.');
    admin_ok(nisha_admin_accounts_detail(db(),99999)===null, 'Unknown account detail does not invent records.');
    admin_reject(fn()=>nisha_admin_accounts_change(db(),$student,$teacher,'suspend',$input), 'Students cannot mutate accounts.');
    admin_reject(fn()=>nisha_admin_accounts_change(db(),$owner,$student,'delete',$input), 'Hard deletion is not an administrative action.');
    admin_reject(fn()=>nisha_admin_accounts_change(db(),$owner,$student,'suspend',array_replace($input,['reason'=>''])), 'A reason is mandatory.');
    admin_reject(fn()=>nisha_admin_accounts_change(db(),$owner,$student,'suspend',array_replace($input,['current_password'=>'wrong'])), 'Incorrect current password cannot suspend an account.');
    admin_ok(nisha_account_login_version(db(),$student)===0 && nisha_admin_accounts_detail(db(),$student)['audit']===[], 'Rejected reauthentication changes no target records or audit events.');
    for ($i=0;$i<4;$i++) admin_reject(fn()=>nisha_admin_accounts_change(db(),$owner,$student,'suspend',array_replace($input,['current_password'=>'wrong'])), 'Repeated reauthentication failure is rejected.');
    admin_reject(fn()=>nisha_admin_accounts_change(db(),$owner,$student,'suspend',$input), 'Persistent reauthentication throttle blocks even a correct password until expiry.');
    db()->exec("UPDATE account_security SET reauth_blocked_until=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE user_id=$owner");
    $updated=nisha_admin_accounts_change(db(),$owner,$student,'edit_profile',$input+['name'=>'Updated student','email'=>'updated@example.test']);
    admin_ok($updated['name']==='Updated student' && $updated['email']==='updated@example.test' && $updated['auth_version']===1, 'Profile email changes atomically update details and invalidate prior sessions.');
    admin_ok(!nisha_account_session_valid(db(),$student,0) && nisha_account_session_valid(db(),$student,1), 'Only the current integer authentication version remains valid.');
    admin_ok(!nisha_account_session_valid(db(),$student,'1'), 'Forged string authentication versions are rejected.');
    $updated=nisha_admin_accounts_change(db(),$owner,$student,'edit_profile',$input+['name'=>'Name only','email'=>'updated@example.test']);
    admin_ok($updated['auth_version']===1 && count($updated['audit'])===2, 'Name-only changes preserve the session version and append an audit entry.');
    $auditCount=(int)db()->query('SELECT COUNT(*) FROM admin_account_audit')->fetchColumn();
    admin_reject(fn()=>nisha_admin_accounts_change(db(),$owner,$student,'edit_profile',$input+['name'=>'Duplicate email','email'=>'owner@example.test']), 'Duplicate email updates are rejected.');
    admin_ok(nisha_admin_accounts_detail(db(),$student)['email']==='updated@example.test' && (int)db()->query('SELECT COUNT(*) FROM admin_account_audit')->fetchColumn()===$auditCount, 'A duplicate email rolls back the mutation and its audit.');
    foreach ([['short','short'],[str_repeat('x',73),str_repeat('x',73)],['New-student-password-123','different']] as [$pwd,$confirmation]) admin_reject(fn()=>nisha_admin_accounts_change(db(),$owner,$student,'reset_password',$input+['new_password'=>$pwd,'password_confirmation'=>$confirmation]), 'Invalid new password is rejected.');
    $newPassword='New-student-password-123'; $updated=nisha_admin_accounts_change(db(),$owner,$student,'reset_password',$input+['new_password'=>$newPassword,'password_confirmation'=>$newPassword]);
    $q=db()->prepare('SELECT password_hash FROM users WHERE id=?'); $q->execute([$student]); $hash=(string)$q->fetchColumn();
    admin_ok(password_verify($newPassword,$hash) && !password_verify('Owner-fixture-password-123',$hash) && $updated['auth_version']===2, 'A password reset replaces the hash and invalidates all prior sessions.');
    $safe=json_encode($updated,JSON_THROW_ON_ERROR); admin_ok(!str_contains($safe,$newPassword) && !str_contains($safe,$hash) && !str_contains($safe,'password_hash') && !str_contains($safe,'Owner-fixture-password-123'), 'Password material is absent from DTOs and audit snapshots.');
    admin_ok(str_contains($safe,'password_changed'), 'Audit records password replacement as a boolean event only.');
    $updated=nisha_admin_accounts_change(db(),$owner,$student,'suspend',$input); admin_ok($updated['status']==='suspended' && $updated['auth_version']===3 && nisha_account_login_version(db(),$student)===null, 'Suspension blocks new sign-ins and invalidates old sessions.');
    admin_reject(fn()=>nisha_admin_accounts_change(db(),$owner,$student,'grant_admin',$input), 'Suspended accounts cannot receive administrator access.');
    $updated=nisha_admin_accounts_change(db(),$owner,$student,'restore',$input); admin_ok($updated['status']==='active' && $updated['auth_version']===4 && !nisha_account_session_valid(db(),$student,2), 'Restoring access requires fresh sign-in rather than reviving an old session.');
    admin_reject(fn()=>nisha_admin_accounts_change(db(),$owner,$owner,'suspend',$input), 'Administrators cannot suspend their own account.');
    admin_reject(fn()=>nisha_admin_accounts_change(db(),$owner,$owner,'revoke_admin',$input), 'Administrators cannot remove their own administrator access.');
    admin_reject(fn()=>nisha_admin_accounts_change(db(),$owner,$configured,'revoke_admin',$input), 'Privately configured administrators cannot be demoted through a database-only change.');
    $updated=nisha_admin_accounts_change(db(),$owner,$student,'grant_admin',$input); admin_ok($updated['is_admin'] && $updated['auth_version']===5, 'Explicit reauthenticated promotion assigns a role and revokes old sessions.');
    $updated=nisha_admin_accounts_change(db(),$owner,$student,'revoke_admin',$input); admin_ok(!$updated['is_admin'] && $updated['auth_version']===6, 'Explicit demotion removes the role and invalidates elevated sessions.');
    $updated=nisha_admin_accounts_change(db(),$configured,$teacher,'grant_admin',$input); admin_ok($updated['is_admin'] && $updated['role']==='admin', 'Configured numeric owner may explicitly promote another active account.');
    admin_reject(fn()=>nisha_admin_accounts_bootstrap(db(),'create',['name'=>'Existing email','email'=>'owner@example.test','password'=>$newPassword,'password_confirmation'=>$newPassword]), 'CLI creation never promotes an existing email implicitly.');
    admin_reject(fn()=>nisha_admin_accounts_bootstrap(db(),'promote',['user_id'=>'owner@example.test']), 'CLI promotion requires an explicit existing numeric account ID.');
    $newId=nisha_admin_accounts_bootstrap(db(),'create',['name'=>'Created owner','email'=>'created@example.test','password'=>$newPassword,'password_confirmation'=>$newPassword]);
    $created=nisha_admin_accounts_detail(db(),$newId); admin_ok($created['is_admin'] && $created['auth_version']===1 && $created['audit'][0]['actor_id']===null, 'Private CLI creation records its source and sets a nondefault password.');
    $oldVersion=nisha_account_login_version(db(),$student); nisha_admin_accounts_bootstrap(db(),'promote',['user_id'=>$student]);
    admin_ok(nisha_account_login_version(db(),$student)===$oldVersion+1 && nisha_admin_accounts_detail(db(),$student)['is_admin'], 'Numeric CLI promotion also revokes all existing sessions.');
    $beforeSessions=nisha_account_security(db(),$owner)['auth_version']; $updated=nisha_admin_accounts_change(db(),$owner,$owner,'reset_password',$input+['new_password'=>$newPassword,'password_confirmation'=>$newPassword]);
    admin_ok($updated['auth_version']===$beforeSessions+1 && !nisha_account_session_valid(db(),$owner,$beforeSessions), 'Self password replacement revokes the administrator’s own session too.');
    // Isolate the last active owner, including the privately configured account,
    // to exercise the recovery CLI's guard against leaving nobody in control.
    foreach ([$student,$configured,$teacher,$newId] as $otherId) {
        $q=db()->prepare("INSERT INTO account_security(user_id,status) VALUES(?,'suspended') ON DUPLICATE KEY UPDATE status='suspended'"); $q->execute([$otherId]);
    }
    $ownerVersion=nisha_account_security(db(),$owner)['auth_version'];
    admin_ok(nisha_admin_accounts_active_admin_count(db(),$owner)===0, 'Suspended and absent configured owners cannot count as active recovery administrators.');
    admin_reject(fn()=>nisha_admin_accounts_bootstrap(db(),'revoke',['user_id'=>$owner]), 'The private CLI cannot revoke the only remaining active administrator.');
    admin_ok(nisha_admin_accounts_is_admin(db(),$owner) && nisha_account_security(db(),$owner)['auth_version']===$ownerVersion, 'Rejected last-owner removal preserves the owner role and authenticated version.');
    db()->exec("UPDATE account_security SET status='active'");
    admin_test_sql("RENAME TABLE `$database`.admin_account_audit TO `$database`.admin_account_audit_fixture;");
    try { nisha_admin_accounts_change(db(),$configured,$teacher,'suspend',$input); throw new LogicException('Missing audit accepted a mutation.'); } catch (RuntimeException $exception) { admin_ok(str_contains($exception->getMessage(),'updated database schema'), 'Unavailable audit storage fails closed before account mutation.'); }
    admin_ok(nisha_account_login_version(db(),$teacher)!==null, 'Unavailable admin storage does not suspend unrelated users.');
    admin_test_sql("RENAME TABLE `$database`.account_security TO `$database`.account_security_fixture;\nCREATE TABLE `$database`.account_security(user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY);");
    try { nisha_account_security(db(),$student); throw new LogicException('Malformed security state accepted as legacy.'); } catch (PDOException $exception) { admin_ok((string)$exception->getCode()==='42S22', 'Malformed installed security schema fails closed instead of bypassing suspension.'); }
    echo 'PASS: '.$checks." administrator account integrity checks.\n";
} catch (Throwable $exception) { $failed=true; fwrite(STDERR, 'FAIL: '.$exception->getMessage()."\n"); }
finally {
    try { admin_test_sql("DROP DATABASE IF EXISTS `$database`;\nDROP USER IF EXISTS '$databaseUser'@'127.0.0.1';"); } catch (Throwable $exception) { $failed=true; fwrite(STDERR,"Fixture cleanup failed.\n"); }
    if (is_file($scratch.'/config/config.php')) unlink($scratch.'/config/config.php'); if (is_dir($scratch.'/config')) rmdir($scratch.'/config'); if (is_dir($scratch)) rmdir($scratch);
}
exit($failed?1:0);
