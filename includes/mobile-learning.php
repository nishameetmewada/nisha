<?php
declare(strict_types=1);
require_once __DIR__ . '/learning-catalog.php';
require_once __DIR__ . '/generated-public-library.php';
require_once __DIR__ . '/exam-results.php';
require_once __DIR__ . '/teachers.php';
require_once __DIR__ . '/ai-review-service.php';

class NishaMobileServiceException extends RuntimeException
{
    public function __construct(public string $publicMessage, public string $errorCode = 'mobile_request', public int $httpStatus = 422, public array $details = [])
    { parent::__construct($publicMessage); }
}

function nisha_mobile_learning_user(?array $user): array
{
    if ($user === null || !is_numeric($user['id'] ?? null) || (int)$user['id'] < 1) throw new NishaMobileServiceException('Sign in to use your NISHA account.', 'login_required', 401);
    return $user;
}

function nisha_mobile_learning_fields(array $input, array $allowed): void
{
    if (array_diff(array_keys($input), $allowed)) throw new NishaMobileServiceException('Send only the fields required for this action.', 'mobile_fields', 422);
}

function nisha_mobile_learning_text(mixed $value, int $max = 100, bool $optional = false): string
{
    if ($value === null && $optional) return '';
    if (!nisha_valid_text($value, $max) || (!$optional && $value === '')) throw new NishaMobileServiceException('Choose a valid learning reference.', 'mobile_reference', 422);
    return $value;
}

function nisha_mobile_learning_int(mixed $value, int $default = 1, int $max = 100000): int
{
    if ($value === null) return $default;
    if (is_int($value) && $value >= 1 && $value <= $max) return $value;
    if (is_string($value) && preg_match('/\A[1-9][0-9]{0,17}\z/D', $value)) {
        $number = filter_var($value,FILTER_VALIDATE_INT);
        if (is_int($number) && $number >= 1 && $number <= $max) return $number;
    }
    throw new NishaMobileServiceException('Choose a valid page or record number.', 'mobile_reference', 422);
}

/** Full papers remain metadata until an authenticated, authorised start. */
function nisha_mobile_exam_metadata(array $exam): array
{
    $metadata = array_intersect_key($exam, array_flip(['id','name','exam','version','description','format_note','course_id','module_id','session_type','timing_mode','generated','teacher_reviewed','content_source']));
    $metadata['session_type'] = $exam['session_type'] ?? 'full';
    $metadata['timing_mode'] = $exam['timing_mode'] ?? 'timed';
    $metadata['requires_access'] = $metadata['session_type'] === 'full';
    $metadata['item_count'] = count(nisha_exam_items($exam));
    $metadata['duration_seconds'] = array_sum(array_column($exam['sections'],'duration_seconds'));
    $metadata['resources'] = [];
    $metadata['sections'] = array_map(static fn(array $section): array => ['id'=>$section['id'],'title'=>$section['title'],'duration_seconds'=>$section['duration_seconds'],'navigation'=>$section['navigation'],'item_count'=>count($section['items'])], $exam['sections']);
    return $metadata;
}

function nisha_mobile_learning_exam(mixed $id): array
{
    $id = nisha_mobile_learning_text($id,64);
    $exam = nisha_resolve_exam($id);
    if ($exam === null) throw new NishaMobileServiceException('This practice session or mock is not available.', 'content_not_found', 404);
    return $exam;
}

function nisha_mobile_learning_catalog(): array
{
    $filters = [];
    foreach (nisha_generated_courses() as $id=>$course) $filters[$id] = nisha_generated_public_filters($id);
    return ['success'=>true,'courses'=>nisha_learning_catalog(),
        'full_mocks'=>array_values(array_map('nisha_mobile_exam_metadata', nisha_exams())),
        'generated'=>['stats'=>nisha_generated_public_stats(),'filters'=>$filters],
        'reviewed'=>nisha_generated_stats(),'ai'=>nisha_ai_public_config(),
        'billing_mode'=>'existing_access','purchase_in_app'=>false];
}

function nisha_mobile_learning_complete(array $input, ?array $user): array
{
    return nisha_complete_exam(nisha_mobile_learning_exam($input['exam_id'] ?? null), $input, $user);
}

function nisha_mobile_learning_feedback(array $input): array
{
    nisha_mobile_learning_fields($input,['exam_id','exam_version','item_id','response']);
    $exam = nisha_mobile_learning_exam($input['exam_id'] ?? null);
    if (($exam['session_type'] ?? 'full') !== 'practice') throw new NishaMobileServiceException('Submit a timed test to review its answers.', 'practice_only', 403);
    $id = nisha_mobile_learning_text($input['item_id'] ?? null,80);
    $items = nisha_exam_items($exam);
    if (!isset($items[$id])) throw new NishaMobileServiceException('Choose a question from this practice session.', 'question_not_found', 422);
    foreach ($exam['sections'] as $section) {
        foreach ($section['items'] as $item) if ($item['id'] === $id) {
            $section['items'] = [$item]; $exam['sections'] = [$section]; break 2;
        }
    }
    $submission = nisha_validate_exam_submission($exam,['exam_id'=>$exam['id'],'exam_version'=>$input['exam_version'] ?? null,
        'attempt_id'=>'feedback-'.bin2hex(random_bytes(12)),'answers'=>[$id=>$input['response'] ?? null],
        'elapsed_seconds'=>0,'completed_sections'=>[],'assisted'=>false]);
    $result = nisha_grade_exam($exam,$submission);
    return ['success'=>true,'review'=>$result['review'][0]];
}

function nisha_mobile_learning_history(array $user, array $input): array
{
    nisha_mobile_learning_fields($input,['page','per_page']);
    $perPage = nisha_mobile_learning_int($input['per_page'] ?? null,20,40);
    $page = nisha_mobile_learning_int($input['page'] ?? null);
    $pdo = db();
    $q = $pdo->prepare('SELECT COUNT(*) FROM exam_runs WHERE user_id=?'); $q->execute([(int)$user['id']]); $total = (int)$q->fetchColumn();
    $pages = (int)ceil($total/$perPage); $page = min($page,max(1,$pages));
    $q = $pdo->prepare('SELECT id,exam_id,exam_name,exam,objective_score,objective_total,elapsed_seconds,assisted,created_at FROM exam_runs WHERE user_id=? ORDER BY id DESC LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage));
    $q->execute([(int)$user['id']]); $items = $q->fetchAll();
    foreach ($items as &$item) {
        foreach (['id','objective_score','objective_total','elapsed_seconds'] as $key) $item[$key] = (int)$item[$key];
        $item['assisted'] = (bool)$item['assisted'];
    } unset($item);
    return ['success'=>true,'items'=>$items,'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
}

function nisha_mobile_learning_result(array $user, mixed $id): array
{
    $id = nisha_mobile_learning_int($id,1,PHP_INT_MAX);
    $q = db()->prepare('SELECT id,result_json FROM exam_runs WHERE id=? AND user_id=? LIMIT 1'); $q->execute([$id,(int)$user['id']]); $row = $q->fetch();
    if (!$row) throw new NishaMobileServiceException('This saved result was not found in your account.', 'result_not_found', 404);
    $result = json_decode($row['result_json'],true,64,JSON_THROW_ON_ERROR);
    $reviews = [];
    try { $reviews = nisha_ai_saved_reviews(db(),(int)$user['id'],$result); } catch (Throwable $exception) {}
    return ['success'=>true,'saved'=>true,'result_id'=>(int)$row['id'],'result'=>$result,'ai_reviews'=>$reviews,'save_message'=>'Saved to your NISHA account.'];
}

function nisha_mobile_learning_lesson_allowed(array $user, array $video): bool
{
    return (int)$user['id'] === (int)$video['teacher_id'] || nisha_is_admin($user) || nisha_commerce_has_paid(db(),(int)$user['id']);
}

function nisha_mobile_learning_lesson_metadata(array $video, ?array $user): array
{
    $item = array_intersect_key($video,array_flip(['id','title','description','course_id','skill','teacher_name','duration_seconds','revision']));
    foreach (['id','duration_seconds','revision'] as $field) $item[$field] = (int)$item[$field];
    $item['can_play'] = $user !== null && nisha_mobile_learning_lesson_allowed($user,$video);
    return $item;
}

function nisha_mobile_learning_coaching(array $input, ?array $user): array
{
    nisha_mobile_learning_fields($input,['course','skill','q']);
    $course = nisha_mobile_learning_text($input['course'] ?? null,30,true);
    $skill = nisha_mobile_learning_text($input['skill'] ?? null,20,true);
    $search = nisha_mobile_learning_text($input['q'] ?? null,200,true);
    if (($course !== '' && !isset(nisha_generated_courses()[$course])) || ($skill !== '' && !in_array($skill,['reading','listening','writing','speaking'],true))) throw new NishaMobileServiceException('Choose a supported course and skill.', 'coaching_filter', 422);
    $videos = nisha_teacher_catalog($course ?: null,$skill ?: null,$search);
    $items = array_map(static fn(array $video): array => nisha_mobile_learning_lesson_metadata($video,$user),$videos);
    return ['success'=>true,'items'=>$items,'total'=>nisha_teacher_catalog_count($course ?: null,$skill ?: null,$search),
        'available_total'=>nisha_teacher_catalog_count(),'purchase_in_app'=>false];
}

/** Called by the bearer-token API; no cookie identity or owner operation exists. */
function nisha_mobile_dispatch(string $action, string $method, array $input, ?array $user, ?array $audio = null): array
{
    $methods = ['catalog'=>'GET','generated'=>'GET','content'=>'GET','start_mock'=>'POST','complete'=>'POST','feedback'=>'POST',
        'history'=>'GET','result'=>'GET','coaching'=>'GET','lesson'=>'GET','watch'=>'POST','ai_review'=>'POST','ai_status'=>'GET'];
    if (!isset($methods[$action])) throw new NishaMobileServiceException('This mobile action is not available.', 'action_not_found', 404);
    if ($method !== $methods[$action]) throw new NishaMobileServiceException('Use '.$methods[$action].' for this action.', 'method_not_allowed', 405);
    try {
        switch ($action) {
            case 'catalog':
                nisha_mobile_learning_fields($input,[]); return nisha_mobile_learning_catalog();
            case 'generated':
                nisha_mobile_learning_fields($input,['kind','course','page','per_page','skill','type','reviewed']);
                $course = nisha_mobile_learning_text($input['course'] ?? 'ielts-academic',30);
                $kind = nisha_mobile_learning_text($input['kind'] ?? 'questions',20);
                if (!isset(nisha_generated_courses()[$course]) || !in_array($kind,['questions','mocks'],true)) throw new NishaMobileServiceException('Choose a supported course and collection.', 'catalog_filter', 422);
                $page = nisha_mobile_learning_int($input['page'] ?? null);
                $perPage = nisha_mobile_learning_int($input['per_page'] ?? null,24,48);
                if (($input['reviewed'] ?? '') === '1') $catalog = nisha_generated_page($kind,$course,$page,$perPage);
                else $catalog = nisha_generated_public_page($kind,$course,$page,$perPage,nisha_mobile_learning_text($input['skill'] ?? 'all',20),nisha_mobile_learning_text($input['type'] ?? 'all',120));
                return ['success'=>true]+$catalog;
            case 'content':
                nisha_mobile_learning_fields($input,['id']);
                $exam = nisha_mobile_learning_exam($input['id'] ?? null);
                return ['success'=>true,'exam'=>($exam['session_type'] ?? 'full') === 'full' ? nisha_mobile_exam_metadata($exam) : nisha_public_exam($exam)];
            case 'start_mock':
                $user = nisha_mobile_learning_user($user); nisha_mobile_learning_fields($input,['exam_id','attempt_id']);
                $exam = nisha_mobile_learning_exam($input['exam_id'] ?? null);
                if (($exam['session_type'] ?? 'full') !== 'full') throw new NishaMobileServiceException('Choose a full mock.', 'mock_required', 422);
                $attempt = nisha_mobile_learning_text($input['attempt_id'] ?? null,80);
                $grant = nisha_commerce_reserve_mock(db(),(int)$user['id'],$exam['id'],$attempt);
                return ['success'=>true,'exam'=>nisha_public_exam($exam),'grant'=>$grant,'access'=>nisha_commerce_overview(db(),(int)$user['id'])];
            case 'complete': return nisha_mobile_learning_complete($input,$user);
            case 'feedback': return nisha_mobile_learning_feedback($input);
            case 'history': return nisha_mobile_learning_history(nisha_mobile_learning_user($user),$input);
            case 'result':
                nisha_mobile_learning_fields($input,['id']); return nisha_mobile_learning_result(nisha_mobile_learning_user($user),$input['id'] ?? null);
            case 'coaching': return nisha_mobile_learning_coaching($input,$user);
            case 'lesson':
                $user = nisha_mobile_learning_user($user); nisha_mobile_learning_fields($input,['id']);
                $video = nisha_teacher_video(nisha_mobile_learning_int($input['id'] ?? null,1,PHP_INT_MAX));
                if (!$video) throw new NishaMobileServiceException('This lesson is not available.', 'lesson_not_found', 404);
                if (!nisha_mobile_learning_lesson_allowed($user,$video)) throw new NishaMobileServiceException('An active NISHA access pass is needed to play this lesson.', 'access_required', 402);
                $url = nisha_teacher_video_url($video['video_url']);
                return ['success'=>true,'lesson'=>nisha_mobile_learning_lesson_metadata($video,$user)+['video_url'=>$url]];
            case 'watch':
                $user = nisha_mobile_learning_user($user);
                $video = nisha_teacher_video(nisha_teacher_positive_id($input['video_id'] ?? null,'video'));
                if (!$video || !nisha_mobile_learning_lesson_allowed($user,$video)) throw new NishaMobileServiceException('This lesson is not available for your account.', 'access_required', 403);
                return nisha_teacher_watch($user,$input);
            case 'ai_review': return nisha_ai_submit(nisha_mobile_learning_user($user),$input,$audio);
            case 'ai_status':
                $user = nisha_mobile_learning_user($user); nisha_mobile_learning_fields($input,['request_id']);
                $envelope = nisha_ai_request_status(db(),(int)$user['id'],$input['request_id'] ?? null);
                if (($envelope['state'] ?? '') === 'pending') $envelope['_status'] = 202;
                elseif (($envelope['state'] ?? '') === 'not_found') $envelope['_status'] = 404;
                return $envelope;
        }
    } catch (NishaMobileServiceException $exception) { throw $exception; }
    catch (NishaCommerceException $exception) { throw new NishaMobileServiceException($exception->publicMessage,$exception->errorCode,$exception->httpStatus); }
    catch (NishaAiException $exception) { throw new NishaMobileServiceException($exception->publicMessage,$exception->errorCode,$exception->httpStatus,['retryable'=>$exception->retryable]); }
    catch (ExamVersionConflictException $exception) { throw new NishaMobileServiceException($exception->getMessage(),'exam_version_changed',409); }
    catch (ExamAttemptConflictException $exception) { throw new NishaMobileServiceException($exception->getMessage(),'attempt_conflict',409); }
    catch (InvalidArgumentException $exception) { throw new NishaMobileServiceException($exception->getMessage(),'mobile_validation',422); }
    throw new NishaMobileServiceException('This action is unavailable.', 'action_not_found', 404);
}
