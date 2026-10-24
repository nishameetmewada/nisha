<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__); $suffix=bin2hex(random_bytes(6)); $scratch=sys_get_temp_dir().'/nisha-content-'.$suffix;
$database='nisha_content_'.$suffix; $account='nisha_content_'.$suffix; $password=bin2hex(random_bytes(24));
$admin=getenv('NISHA_TEST_MYSQL_ADMIN_BIN')?:'/workspace/.tools/bin/mariadb'; $withDatabase=in_array('--database',$argv,true);
$checks=0; $failed=false; $created=false;
function cr_check(bool $condition,string $label):void{global $checks;if(!$condition)throw new LogicException('TEST: '.$label);++$checks;}
function cr_reject(callable $call,string $label):void{try{$call();}catch(InvalidArgumentException $e){cr_check(true,$label);return;}throw new LogicException('TEST: '.$label);}
function cr_sql(string $sql):void{global $admin;$p=proc_open([$admin],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Local client unavailable.');fwrite($pipes[0],$sql);fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Local database fixture failed.');}
try{
    mkdir($scratch,0700);mkdir($scratch.'/config',0700);mkdir($scratch.'/sessions',0700);
    $config=['db_host'=>'127.0.0.1','db_port'=>3306,'db_name'=>$withDatabase?$database:'','db_user'=>$withDatabase?$account:'','db_password'=>$withDatabase?$password:''];
    file_put_contents($scratch.'/config/config.php','<?php return '.var_export($config,true).';');chmod($scratch.'/config/config.php',0600);
    define('NISHA_ROOT',$scratch);ini_set('session.save_path',$scratch.'/sessions');
    require $root.'/includes/generated-library.php';require $root.'/includes/teachers.php';
    foreach(nisha_generated_courses() as $course=>$definition)for($n=1;$n<=20;$n++){nisha_content_validate_session(nisha_generated_question_session($course,$n));cr_check(true,'Draft formats validate structurally.');}
    foreach(nisha_generated_courses() as $course=>$definition){$m=nisha_generated_mock($course,1);nisha_content_validate_session($m);cr_check(count(nisha_exam_items($m))===array_sum(array_map(static fn($s)=>count($s['items']),nisha_exams()[$definition['base']]['sections'])),'Draft mock retains configured task count.');}
    cr_check(nisha_generated_parse_id('../draft')===null && nisha_generated_parse_id('gp.ielts-academic.07501')===null,'Resolver rejects unavailable IDs.');
    cr_check(nisha_generated_resolve_exam('gp.ielts-academic.00001')===null,'A draft cannot resolve publicly without a reviewed publication.');
    $copy=nisha_generated_question_session('ielts-academic',1);$renamed=$copy;$renamed['id']='gp.ielts-general.00001';$renamed['name']='A different label';$renamed['course_id']='ielts-general';
    $renamed['sections'][0]['items'][0]['id']='another-id';foreach($renamed['resources'] as &$resource)$resource['title']='A different resource label';unset($resource);
    cr_check(nisha_content_semantic_hash($copy)===nisha_content_semantic_hash($renamed),'IDs, names and course labels cannot inflate semantic counts.');
    $reordered=$copy;$reordered['sections'][0]['items'][0]['options']=array_reverse($reordered['sections'][0]['items'][0]['options']);
    cr_check(nisha_content_semantic_hash($copy)===nisha_content_semantic_hash($reordered),'Reordering options does not create a new question.');
    if($withDatabase){
        if(!is_executable($admin))throw new RuntimeException('Local admin client unavailable.');
        cr_sql("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '$account'@'127.0.0.1' IDENTIFIED BY '$password'; GRANT SELECT,INSERT,UPDATE,DELETE ON `$database`.* TO '$account'@'127.0.0.1';");$created=true;
        $schema=file_get_contents($root.'/database/schema.sql')."\n".file_get_contents($root.'/database/migrations/004_commerce.sql')."\n".file_get_contents($root.'/database/migrations/005_teachers.sql')."\n".file_get_contents($root.'/database/migrations/006_content_reviews.sql');
        cr_sql("USE `$database`;\n".$schema);
        $pdo=db();$users=[];
        foreach(['student','admin','teacher','other'] as $role){$q=$pdo->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');$q->execute(['Content fixture '.$role,$role.'@example.test',password_hash('fixture-password-only',PASSWORD_DEFAULT)]);$users[$role]=['id'=>(int)$pdo->lastInsertId(),'name'=>'Fixture '.$role,'email'=>$role.'@example.test'];}
        $pdo->prepare("INSERT INTO user_roles(user_id,role) VALUES(?,'admin')")->execute([$users['admin']['id']]);
        foreach(['teacher','other'] as $role)$pdo->prepare("INSERT INTO teacher_profiles(user_id,display_name,bio,status) VALUES(?,?,?,'approved')")->execute([$users[$role]['id'],'Fixture teacher','Artificial local review fixture only.']);
        $note='Artificial local test fixture review; not a real teaching endorsement.';
        cr_check(nisha_generated_stats()['question_count']===0 && nisha_generated_stats()['mock_count']===0,'Targets and drafts do not count as published inventory.');
        cr_reject(fn()=>nisha_content_publish($users['student'],$copy['id'],$copy,$note,true),'Students cannot certify content.');
        cr_reject(fn()=>nisha_content_publish($users['admin'],$copy['id'],$copy,$note,true),'An admin without approved teacher profile cannot certify content.');
        cr_reject(fn()=>nisha_content_publish($users['teacher'],$copy['id'],$copy,$note,false),'Publication needs explicit teacher attestation.');
        $edited=$copy;$edited['name']='Teacher-edited fixture exercise';$edited['sections'][0]['items'][0]['prompt'].=' Check the exact stated purpose.';
        $firstResource=array_key_first($edited['resources']);$edited['resources'][$firstResource]['paragraphs'][]='This fixture sentence verifies that the edited source, rather than the regenerated source, is served.';
        $version=nisha_content_publish($users['teacher'],$edited['id'],$edited,$note,true);
        $public=nisha_generated_resolve_exam($edited['id']);
        cr_check($public!==null && $public['name']===$edited['name'] && $public['resources']===$edited['resources'],'Public content serves frozen reviewed edits and sources.');
        cr_check(str_starts_with($public['version'],'reviewed-') && $public['teacher_reviewed']===true,'Published version records reviewed identity and stable content version.');
        cr_check(!str_contains(json_encode(nisha_public_exam($public)), '"answer":') && !str_contains(json_encode(nisha_public_exam($public)), '"explanation":'),'Public payload strips answer keys and explanations.');
        $duplicate=$edited;$duplicate['id']='gp.ielts-general.00001';$duplicate['course_id']='ielts-general';$duplicate['name']='Different name only';
        cr_reject(fn()=>nisha_content_publish($users['teacher'],$duplicate['id'],$duplicate,$note,true),'Duplicate content cannot inflate a different course.');
        cr_check((int)$pdo->query('SELECT COUNT(*) FROM content_review_versions')->fetchColumn()===1,'Rejected duplicate publication rolls back its audit write.');
        cr_reject(fn()=>nisha_generated_mock('ielts-academic',1,true),'A paper cannot use unreviewed draft questions.');
        $ids=[];foreach(nisha_generated_mock_sources('ielts-academic',1) as $definition)$ids=array_merge($ids,$definition['question_ids']);
        foreach($ids as $id){if(nisha_content_public_row($id))continue;$p=nisha_generated_parse_id($id);$s=nisha_generated_question_session($p['course'],$p['number']);nisha_content_publish($users['teacher'],$id,$s,$note,true);}
        $paper=nisha_generated_mock('ielts-academic',1,true);$tampered=$paper;$tampered['sections'][0]['items'][0]['prompt'].=' Unreviewed alteration.';
        cr_reject(fn()=>nisha_content_publish($users['teacher'],$paper['id'],$tampered,$note,true),'A paper cannot alter a reviewed source question without its own question review.');
        $tampered=$paper;$resource=array_key_first($tampered['resources']);$tampered['resources'][$resource]['title'].=' Altered';
        cr_reject(fn()=>nisha_content_publish($users['teacher'],$paper['id'],$tampered,$note,true),'A paper cannot substitute unreviewed resource content.');
        nisha_content_publish($users['teacher'],$paper['id'],$paper,$note,true);
        cr_check(nisha_generated_resolve_exam($paper['id'])!==null && nisha_generated_stats()['mock_count']===1,'An individually reviewed bank plus a whole-paper review publishes one paper.');
        $samePaper=$paper;$samePaper['id']='gm.ielts-academic.0002';$samePaper['name']='Renamed paper';
        cr_reject(fn()=>nisha_content_publish($users['teacher'],$samePaper['id'],$samePaper,$note,true),'Renaming a paper cannot inflate mock counts.');
        $page=nisha_generated_page('mocks','ielts-academic',1,24);cr_check($page['total']===1&&count($page['items'])===1,'Public pagination returns published rows only.');
        $dependency=$ids[0];$question=nisha_generated_resolve_exam($dependency);$question['sections'][0]['items'][0]['prompt'].=' Read the revised task carefully.';
        nisha_content_publish($users['teacher'],$dependency,$question,$note,true);
        cr_check(nisha_generated_resolve_exam($paper['id'])===null && nisha_generated_stats()['mock_count']===0,'Editing a question hides papers bound to its previous version.');
        nisha_content_publish($users['teacher'],$paper['id'],nisha_generated_mock('ielts-academic',1,true),$note,true);
        cr_check(nisha_generated_stats()['mock_count']===1,'Fresh whole-paper review restores a paper after source updates.');
        cr_reject(fn()=>nisha_content_withdraw($users['other'],$dependency),'A teacher cannot withdraw another teacher publication.');
        nisha_content_withdraw($users['admin'],$dependency);
        cr_check(nisha_generated_resolve_exam($dependency)===null && nisha_generated_resolve_exam($paper['id'])===null,'Owner withdrawal hides the question and dependent papers.');
        $pdo->prepare("UPDATE teacher_profiles SET status='rejected' WHERE user_id=?")->execute([$users['teacher']['id']]);
        cr_check(nisha_generated_stats()['question_count']===0,'Revoking teacher approval removes their publications from public totals.');
    }
    echo "PASS: $checks teacher-reviewed content checks. ".($withDatabase?'Isolated local fixtures cleaned; ':'')."No publication of production drafts or real teacher certification.\n";
}catch(Throwable $exception){fwrite(STDERR,$exception instanceof LogicException&&str_starts_with($exception->getMessage(),'TEST: ')?$exception->getMessage()."\n":"Content fixture failed safely (".get_class($exception).").\n");$failed=true;}
finally{
    if($created){try{cr_sql("DROP DATABASE IF EXISTS `$database`; DROP USER IF EXISTS '$account'@'127.0.0.1';");}catch(Throwable $e){fwrite(STDERR,"Disposable database cleanup failed.\n");$failed=true;}}
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    foreach(glob($scratch.'/sessions/*')?:[] as $file)unlink($file);
    if(is_file($scratch.'/config/config.php'))unlink($scratch.'/config/config.php');
    foreach(['sessions','config',''] as $dir)if(is_dir($scratch.'/'.$dir))rmdir($scratch.'/'.$dir);
}
exit($failed?1:0);
