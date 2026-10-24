<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/commerce.php';
require_once dirname(__DIR__).'/includes/learning-catalog.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function start_mock_reply(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_HEX_TAG|JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');start_mock_reply(405,['error'=>'Use POST to start a full mock.']);}
$user=current_user();if(!$user)start_mock_reply(401,['error'=>'Sign in for your first free full mock.','code'=>'login_required']);
if(!verify_csrf(is_string($_SERVER['HTTP_X_CSRF_TOKEN']??null)?$_SERVER['HTTP_X_CSRF_TOKEN']:''))start_mock_reply(403,['error'=>'Your session expired. Refresh before starting this mock.','code'=>'csrf_expired']);
if(!preg_match('/\Aapplication\/json(?:\s*;|\s*\z)/i',$_SERVER['CONTENT_TYPE']??''))start_mock_reply(415,['error'=>'Send the mock reference as JSON.']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)start_mock_reply(413,['error'=>'The mock reference is too large.']);
try{
    $raw=(string)file_get_contents('php://input',false,null,0,4097);if(strlen($raw)>4096)start_mock_reply(413,['error'=>'The mock reference is too large.']);
    $payload=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
    if(!is_array($payload)||array_diff(array_keys($payload),['exam_id','attempt_id'])||!is_string($payload['exam_id']??null)||!is_string($payload['attempt_id']??null))start_mock_reply(422,['error'=>'Send the mock and attempt references.']);
    $exam=nisha_resolve_exam($payload['exam_id']);
    if(!$exam||($exam['session_type']??'full')!=='full')start_mock_reply(422,['error'=>'Choose an available full mock.']);
    $grant=nisha_commerce_reserve_mock(db(),(int)$user['id'],$exam['id'],$payload['attempt_id']);
    start_mock_reply(200,['success'=>true,'exam'=>nisha_public_exam($exam),'grant'=>$grant,'access'=>nisha_commerce_overview(db(),(int)$user['id'])]);
}catch(JsonException$e){start_mock_reply(400,['error'=>'The mock reference is not valid JSON.']);}
catch(NishaCommerceException$e){start_mock_reply($e->httpStatus,['error'=>$e->publicMessage,'code'=>$e->errorCode,'pricing_url'=>'pricing.php']);}
catch(Throwable$e){error_log('NISHA mock access unavailable: '.get_class($e));start_mock_reply(503,['error'=>'Full mock access is temporarily unavailable. The site owner needs to complete the payment/access database setup. Practice remains available.','code'=>'commerce_unavailable']);}
