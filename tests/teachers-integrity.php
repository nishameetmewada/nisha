<?php
declare(strict_types=1);
// Disposable local database only. No real students, views, payouts or provider calls.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__); $adminBin = getenv('NISHA_TEST_MYSQL_ADMIN_BIN') ?: '/workspace/.tools/bin/mariadb';
if (!is_executable($adminBin)) { fwrite(STDERR,"A local MariaDB admin wrapper is required.\n"); exit(1); }
$suffix = bin2hex(random_bytes(7)); $database = 'nisha_teacher_test_' . $suffix; $databaseUser = 'teacher_test_' . $suffix; $password = bin2hex(random_bytes(24));
$scratch = sys_get_temp_dir() . '/nisha-teacher-test-' . $suffix; $checks = 0; $failed = false;
function teacher_test_sql(string $sql): void {
    global $adminBin;
    $process = proc_open([$adminBin], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start the local database fixture.');
    fwrite($pipes[0], $sql); fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException('Disposable teacher database setup or cleanup failed.');
}
function teacher_ok(bool $value, string $message): void { global $checks; if (!$value) throw new RuntimeException($message); $checks++; }
function teacher_reject(callable $callback, string $message): void { try { $callback(); } catch (InvalidArgumentException $exception) { teacher_ok(true,$message); return; } throw new RuntimeException($message); }
function teacher_fixture_user(string $label): array {
    $q=db()->prepare('INSERT INTO users(name,email,password_hash) VALUES (?,?,?)'); $q->execute([$label,$label.'@example.test',password_hash('disposable-only-password',PASSWORD_DEFAULT)]);
    return ['id'=>(int)db()->lastInsertId(),'name'=>$label];
}
function teacher_fixture_paid(array $user): void {
    $id=bin2hex(random_bytes(16));
    $q=db()->prepare("INSERT INTO payment_orders(id,user_id,amount_minor,currency,access_days,status,payment_intent) VALUES (?,?,2900,'aud',30,'paid',?)");$q->execute([$id,$user['id'],'pi_fixture_'.bin2hex(random_bytes(10))]);
    $q=db()->prepare('INSERT INTO student_entitlements(user_id,order_id,starts_at,expires_at) VALUES (?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL 30 DAY)');$q->execute([$user['id'],$id]);
}
function teacher_fixture_elapsed(array $user,int $video,int $seconds=10): void {
    $q=db()->prepare('UPDATE coaching_watch_sessions SET last_seen=UTC_TIMESTAMP(6)-INTERVAL ? SECOND WHERE user_id=? AND video_id=?');$q->execute([$seconds,$user['id'],$video]);
}
function teacher_fixture_beat(array $user,int $video,string $token,int $position,bool $playing=true,bool $visible=true): array {
    teacher_fixture_elapsed($user,$video);
    return nisha_teacher_watch($user,['action'=>'heartbeat','video_id'=>$video,'token'=>$token,'position'=>$position,'playing'=>$playing,'visible'=>$visible]);
}
try {
    mkdir($scratch,0700); mkdir($scratch.'/config',0700);
    $schema=(string)file_get_contents($root.'/database/schema.sql');
    if (!preg_match('/CREATE TABLE IF NOT EXISTS users \(.*?\) ENGINE=.*?;/s',$schema,$m)) throw new RuntimeException('Canonical users schema is unavailable.');
    teacher_test_sql("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nCREATE USER '$databaseUser'@'127.0.0.1' IDENTIFIED BY '$password';\nGRANT SELECT,INSERT,UPDATE,DELETE ON `$database`.* TO '$databaseUser'@'127.0.0.1';\nUSE `$database`;\n".$m[0]."\n".file_get_contents($root.'/database/migrations/004_commerce.sql')."\n".file_get_contents($root.'/database/migrations/005_teachers.sql'));
    file_put_contents($scratch.'/config/config.php','<?php return '.var_export(['db_host'=>'127.0.0.1','db_port'=>3306,'db_name'=>$database,'db_user'=>$databaseUser,'db_password'=>$password,'admin_user_ids'=>[],'teacher_rewards_enabled'=>false,'teacher_reward_share_percent'=>90,'teacher_reward_currency'=>'AUD','teacher_video_hosts'=>[]],true).';'); chmod($scratch.'/config/config.php',0600);
    define('NISHA_ROOT',$scratch); require $root.'/includes/teachers.php';
    teacher_ok(nisha_teacher_settings()['share_percent']===90.0 && !nisha_teacher_settings()['enabled'],'The requested 90% policy is the default and needs owner activation.');
    foreach ([[100,[1=>1,2=>1,3=>1]],[1,[1=>1,2=>1]],[9000,[1=>1,2=>2]],[0,[1=>2]],[500,[]]] as [$pool,$views]) {
        $a=nisha_teacher_allocate($pool,$views); teacher_ok(array_sum($a)===($views?$pool:0),'Integer allocations conserve the available pool.');
    }
    teacher_reject(fn()=>nisha_teacher_allocate(-1,[1=>1]),'Negative pools are rejected.');
    teacher_reject(fn()=>nisha_teacher_allocate(100,[1=>-1]),'Invalid view totals are rejected.');
    teacher_ok(nisha_teacher_watch_credit(10,100,true,true)===0.0 && nisha_teacher_watch_credit(10,10,false,true)===0.0 && nisha_teacher_watch_credit(10,10,true,false)===0.0,'Seeking, paused and hidden playback do not grant time.');
    $ranges=nisha_teacher_watch_ranges([],0,10,100); $ranges=nisha_teacher_watch_ranges($ranges,0,10,100);
    teacher_ok($ranges===[[0.0,10.0]],'Replayed intervals are counted once.');
    $ranges=nisha_teacher_watch_ranges($ranges,5,20,100);teacher_ok($ranges===[[0.0,20.0]],'Overlapping intervals merge instead of inflating time.');
    $owner=teacher_fixture_user('owner');$teacher=teacher_fixture_user('teacher');$secondTeacher=teacher_fixture_user('teacher-two');$student=teacher_fixture_user('student');$secondStudent=teacher_fixture_user('student-two');$free=teacher_fixture_user('free-student');
    $q=db()->prepare("INSERT INTO user_roles(user_id,role) VALUES (?,'admin')");$q->execute([$owner['id']]);
    teacher_ok(nisha_is_admin($owner) && !nisha_is_admin($student),'Only an assigned owner can administer the business.');
    nisha_teacher_apply($teacher,['display_name'=>'First teacher','bio'=>'Original IELTS and PTE teaching experience for test students.','role'=>'admin']);
    teacher_ok(!nisha_teacher_approved($teacher) && !nisha_is_admin($teacher),'Applications cannot self-approve or self-assign owner access.');
    teacher_reject(fn()=>nisha_teacher_admin_action($teacher,['action'=>'approve_teacher','user_id'=>$teacher['id'],'expected_profile_hash'=>nisha_teacher_profile_review_hash(nisha_teacher_profile(db(),$teacher['id']))]),'Teachers cannot approve themselves.');
    teacher_reject(fn()=>nisha_teacher_submit_video($teacher,[]),'Pending teachers cannot submit lessons.');
    nisha_teacher_admin_action($owner,['action'=>'approve_teacher','user_id'=>$teacher['id'],'expected_profile_hash'=>nisha_teacher_profile_review_hash(nisha_teacher_profile(db(),$teacher['id']))]);
    teacher_ok(nisha_teacher_approved($teacher),'Owner approval enables teacher submission.');
    nisha_teacher_apply($secondTeacher,['display_name'=>'Second teacher','bio'=>'Original language teaching experience for the second test teacher.']);
    nisha_teacher_admin_action($owner,['action'=>'approve_teacher','user_id'=>$secondTeacher['id'],'expected_profile_hash'=>nisha_teacher_profile_review_hash(nisha_teacher_profile(db(),$secondTeacher['id']))]);
    teacher_reject(fn()=>nisha_teacher_admin_action($owner,['action'=>'save_policy','watch_percent'=>'70','currency'=>'AUD','video_hosts'=>'localhost','enabled'=>'1']),'Private/local video hosts are rejected.');
    nisha_teacher_admin_action($owner,['action'=>'save_policy','watch_percent'=>'70','currency'=>'AUD','video_hosts'=>'videos.example.org','enabled'=>'1','paid_students_only'=>'1']);
    teacher_ok(nisha_teacher_settings()['enabled'] && nisha_teacher_settings()['share_basis_points']===9000,'Owner activation retains the exact 90% pool.');
    foreach (['http://videos.example.org/a.mp4','https://evil.example.org/a.mp4','https://user:pass@videos.example.org/a.mp4','https://videos.example.org/page.html','javascript:alert(1)'] as $url) teacher_reject(fn()=>nisha_teacher_video_url($url),'Unsupported or unapproved video URLs are rejected.');
    $videoInput=['title'=>'Useful original lesson','description'=>'An original test lesson with clear teaching goals.','video_url'=>'https://videos.example.org/lesson.mp4','duration_seconds'=>'100','course_id'=>'ielts-academic','skill'=>'reading'];
    $video=nisha_teacher_submit_video($teacher,$videoInput);$otherVideo=nisha_teacher_submit_video($secondTeacher,$videoInput+[]);
    teacher_ok(nisha_teacher_video($video)===null,'Unreviewed lessons are not publicly available.');
    teacher_reject(fn()=>nisha_teacher_admin_action($owner,['action'=>'approve_video','video_id'=>$video]),'Publishing requires an explicit content/duration/rights review.');
    foreach ([$video,$otherVideo] as $id) nisha_teacher_admin_action($owner,['action'=>'approve_video','video_id'=>$id,'verified_duration'=>'1','expected_revision'=>'1']);
    teacher_ok(count(nisha_teacher_catalog())===2,'Only approved teachers and published lessons appear in the catalog.');
    teacher_reject(fn()=>nisha_teacher_submit_video($secondTeacher,array_replace($videoInput,['video_id'=>$video])),'A teacher cannot edit another teacher lesson.');
    $start=nisha_teacher_watch($free,['action'=>'start','video_id'=>$video]); teacher_fixture_beat($free,$video,$start['token'],10);
    teacher_ok(!$start['reward_eligible'] && (int)db()->query('SELECT COUNT(*) FROM coaching_qualified_views')->fetchColumn()===0,'Free student playback cannot earn paid-qualified views.');
    teacher_fixture_paid($student);teacher_fixture_paid($secondStudent);
    teacher_ok(!nisha_teacher_student_eligible($teacher,nisha_teacher_video($video)) && !nisha_teacher_student_eligible($owner,nisha_teacher_video($video)),'Teacher self-views and owner views are excluded.');
    $start=nisha_teacher_watch($student,['action'=>'start','video_id'=>$video]);$token=$start['token'];
    $beat=teacher_fixture_beat($student,$video,$token,10);teacher_ok($beat['watched_seconds']===10,'Real server-bounded playback grants verified progress.');
    $restart=nisha_teacher_watch($student,['action'=>'start','video_id'=>$video]);$beat=teacher_fixture_beat($student,$video,$restart['token'],10);teacher_ok($beat['watched_seconds']===10,'Restarting and replaying the same section cannot inflate progress.');
    $beat=teacher_fixture_beat($student,$video,$restart['token'],80);teacher_ok($beat['watched_seconds']===10,'A seek jump grants no unviewed time.');
    $secondStart=nisha_teacher_watch($student,['action'=>'start','video_id'=>$otherVideo]);
    teacher_reject(fn()=>teacher_fixture_beat($student,$video,$restart['token'],90),'One account cannot receive simultaneous reward time across different videos.');
    teacher_reject(fn()=>teacher_fixture_beat($secondStudent,$otherVideo,$secondStart['token'],10),'Watch tokens are bound to their own student account.');
    $start=nisha_teacher_watch($secondStudent,['action'=>'start','video_id'=>$video]);
    for ($position=10;$position<=70;$position+=10) $beat=teacher_fixture_beat($secondStudent,$video,$start['token'],$position);
    teacher_ok($beat['qualified'] && (int)db()->query('SELECT COUNT(*) FROM coaching_qualified_views')->fetchColumn()===1,'70% unique verified playback qualifies one monthly lesson view.');
    $restart=nisha_teacher_watch($secondStudent,['action'=>'start','video_id'=>$video]);
    for ($position=10;$position<=70;$position+=10) teacher_fixture_beat($secondStudent,$video,$restart['token'],$position);
    teacher_ok((int)db()->query('SELECT COUNT(*) FROM coaching_qualified_views')->fetchColumn()===1,'Repeating a lesson never awards a second monthly view.');
    nisha_teacher_submit_video($teacher,array_replace($videoInput,['video_id'=>$video,'title'=>'Changed original lesson']));
    teacher_ok(nisha_teacher_video($video)===null,'A changed lesson returns to moderation.');
    teacher_reject(fn()=>nisha_teacher_admin_action($owner,['action'=>'approve_video','video_id'=>$video,'verified_duration'=>'1','expected_revision'=>'1']),'An owner cannot publish a new lesson version using an older preview approval.');
    nisha_teacher_admin_action($owner,['action'=>'approve_video','video_id'=>$video,'verified_duration'=>'1','expected_revision'=>'2']);
    $start=nisha_teacher_watch($secondStudent,['action'=>'start','video_id'=>$video]);
    teacher_ok($start['watched_seconds']===0 && !$start['qualified'],'A new lesson version cannot inherit old verified playback.');
    for ($position=10;$position<=70;$position+=10) teacher_fixture_beat($secondStudent,$video,$start['token'],$position);
    teacher_ok((int)db()->query('SELECT COUNT(*) FROM coaching_qualified_views')->fetchColumn()===1,'A new lesson revision still cannot create a second reward view in the same month.');
    $q=db()->prepare('UPDATE coaching_qualified_views SET period=?');$q->execute(['2024-02']);
    $q=db()->prepare('INSERT INTO coaching_qualified_views(user_id,video_id,period,teacher_id,video_revision) VALUES (?,?,?,?,1)');
    $q->execute([$student['id'],$video,'2024-02',$teacher['id']]);$q->execute([$student['id'],$otherVideo,'2024-02',$secondTeacher['id']]);
    $periodInput=['action'=>'approve_profit','period'=>'2024-01','currency'=>'AUD','revenue'=>'10.00','refunds'=>'0.00','expenses'=>'30.00','confirmation'=>'1','verification_note'=>'Explicit isolated fixture business totals, not an actual profit or payout.'];
    teacher_reject(fn()=>nisha_teacher_admin_action($student,$periodInput),'Students cannot approve profit or allocate rewards.');
    nisha_teacher_admin_action($owner,$periodInput);
    $loss=db()->query("SELECT * FROM teacher_profit_periods WHERE period='2024-01'")->fetch();
    teacher_ok((int)$loss['net_profit_cents']===-2000 && (int)$loss['pool_cents']===0 && (int)$loss['remaining_loss_cents']===2000,'Losses produce no pool and carry forward before later profit distributions.');
    nisha_teacher_admin_action($owner,array_replace($periodInput,['period'=>'2024-02','revenue'=>'100.00','refunds'=>'10.00','expenses'=>'20.00']));
    $period=db()->query("SELECT * FROM teacher_profit_periods WHERE period='2024-02'")->fetch();
    teacher_ok((int)$period['net_profit_cents']===5000 && (int)$period['pool_cents']===4500 && (int)$period['share_basis_points']===9000,'The pool is exactly 90% after all expenses, refunds and prior losses.');
    $allocations=db()->query('SELECT * FROM teacher_reward_allocations ORDER BY teacher_id')->fetchAll();
    teacher_ok(count($allocations)===2 && (int)$allocations[0]['amount_cents']===3000 && (int)$allocations[1]['amount_cents']===1500,'Teacher rewards follow the actual 2:1 qualified-view proportions.');
    teacher_reject(fn()=>nisha_teacher_admin_action($owner,array_replace($periodInput,['period'=>'2024-02'])),'Approved periods cannot be repeated or changed.');
    teacher_reject(fn()=>nisha_teacher_admin_action($owner,array_replace($periodInput,['period'=>'2023-12'])),'Backdating cannot bypass previously carried losses.');
    teacher_reject(fn()=>nisha_teacher_admin_action($owner,['action'=>'record_payout','allocation_id'=>$allocations[0]['id'],'reference'=>'fixture-not-paid']),'Payout recording requires confirmation of an external transfer.');
    nisha_teacher_admin_action($owner,['action'=>'record_payout','allocation_id'=>$allocations[0]['id'],'reference'=>'isolated-fixture-no-real-transfer','confirmation'=>'1']);
    teacher_reject(fn()=>nisha_teacher_admin_action($owner,['action'=>'record_payout','allocation_id'=>$allocations[0]['id'],'reference'=>'duplicate-fixture','confirmation'=>'1']),'An allocation cannot be recorded as paid twice.');
    teacher_ok((int)db()->query("SELECT COUNT(*) FROM teacher_reward_allocations WHERE status='paid'")->fetchColumn()===1,'Only the explicitly confirmed fixture payout is recorded.');
    nisha_teacher_admin_action($owner,array_replace($periodInput,['period'=>'2024-03','revenue'=>'100.00','refunds'=>'0.00','expenses'=>'20.00']));
    teacher_ok((int)db()->query('SELECT COUNT(*) FROM teacher_reward_allocations')->fetchColumn()===2,'Profit without qualified views creates no teacher allocations or transfers.');
    echo "PASS: $checks teacher policy, moderation, playback and profit-ledger checks. Disposable fixtures only; no money transferred.\n";
} catch (Throwable $exception) {
    fwrite(STDERR,'FAIL: '.($exception instanceof PDOException ? 'A teacher database assertion could not run.' : $exception->getMessage())."\n"); $failed=true;
} finally {
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    try { teacher_test_sql("DROP DATABASE IF EXISTS `$database`;\nDROP USER IF EXISTS '$databaseUser'@'127.0.0.1';\n"); } catch (Throwable $exception) { fwrite(STDERR,"Disposable teacher database cleanup failed.\n");$failed=true; }
    if (is_file($scratch.'/config/config.php')) unlink($scratch.'/config/config.php');
    if (is_dir($scratch.'/config')) rmdir($scratch.'/config'); if (is_dir($scratch)) rmdir($scratch);
}
exit($failed?1:0);
