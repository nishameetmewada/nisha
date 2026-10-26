<?php
declare(strict_types=1);
ini_set('display_errors','0');
require_once dirname(__DIR__).'/includes/mobile-auth.php';
// Native bearer identity is independent of any browser cookie sent by a client.
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
header_remove('Set-Cookie');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('Vary: Origin');
function mobile_reply(int $status,array $body): never
{
    http_response_code($status);
    echo json_encode(['protocol_version'=>NISHA_MOBILE_PROTOCOL_VERSION]+$body,JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES);
    exit;
}
$origin=is_string($_SERVER['HTTP_ORIGIN']??null)?$_SERVER['HTTP_ORIGIN']:null;
if(!nisha_mobile_origin_allowed($origin))mobile_reply(403,['error'=>'This app origin is not allowed.','code'=>'mobile_origin_denied']);
if($origin!==null&&$origin!=='')header('Access-Control-Allow-Origin: '.$origin);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if(!nisha_mobile_transport_allowed($_SERVER))mobile_reply(426,['error'=>'Use HTTPS to connect to NISHA.','code'=>'mobile_https_required']);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='OPTIONS'){
    $requested=strtolower((string)($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']??''));
    foreach(array_filter(array_map('trim',explode(',',$requested)))as$header)if(!in_array($header,['authorization','content-type'],true))mobile_reply(403,['error'=>'This request header is not allowed.','code'=>'mobile_preflight_denied']);
    if(isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])&&!in_array($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'],['GET','POST'],true))mobile_reply(405,['error'=>'Use GET or POST.','code'=>'mobile_method_invalid']);
    http_response_code(204);exit;
}
if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST, OPTIONS');mobile_reply(405,['error'=>'Use GET or POST.','code'=>'mobile_method_invalid']);}
$action=is_string($_GET['action']??null)?$_GET['action']:'';
if(!preg_match('/\A[a-z][a-z_]{0,39}\z/D',$action))mobile_reply(422,['error'=>'Choose a known app action.','code'=>'mobile_action_invalid']);
$audio=null;
try{
    $input=$_GET;unset($input['action']);
    if($method==='POST'){
        $isAudio=$action==='ai_review'&&preg_match('/\Amultipart\/form-data\s*;/i',$_SERVER['CONTENT_TYPE']??'');
        $max=$isAudio?11*1024*1024:2*1024*1024;
        if((int)($_SERVER['CONTENT_LENGTH']??0)>$max)mobile_reply(413,['error'=>'This app request is too large.','code'=>'mobile_request_too_large']);
        if($isAudio){
            $input=$_POST;
            if(array_keys($_FILES)!==['audio']||!is_array($_FILES['audio'])||($_FILES['audio']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($_FILES['audio']['tmp_name']??''))throw new NishaMobileException('Choose a valid WAV recording.','mobile_audio_invalid',422);
            $file=$_FILES['audio'];
            if(!is_int($file['size'])||$file['size']<100||$file['size']>10*1024*1024)throw new NishaMobileException('Use a recording between 100 bytes and 10 MB.','mobile_audio_invalid',422);
            $audio=['path'=>$file['tmp_name'],'mime'=>(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name'])];
        }else{
            if(!preg_match('/\Aapplication\/json(?:\s*;|\s*\z)/i',$_SERVER['CONTENT_TYPE']??''))mobile_reply(415,['error'=>'Send the app request as JSON.','code'=>'mobile_content_type_invalid']);
            $raw=(string)file_get_contents('php://input',false,null,0,$max+1);
            if(strlen($raw)>$max)mobile_reply(413,['error'=>'This app request is too large.','code'=>'mobile_request_too_large']);
            try{$input=json_decode($raw,true,40,JSON_THROW_ON_ERROR);}catch(JsonException$e){throw new NishaMobileException('Send valid JSON.','mobile_json_invalid',400);}
            if(!is_array($input)||array_is_list($input)&&$input!==[])throw new NishaMobileException('Send a JSON object.','mobile_request_invalid',422);
        }
    }
    if($action==='health'){
        if($method!=='GET')throw new NishaMobileException('Use GET for app status.','mobile_method_invalid',405);
        $ready=false;try{nisha_mobile_ready(db());$ready=true;}catch(Throwable$e){}
        mobile_reply(200,['success'=>true,'status'=>$ready?'ready':'setup_required','mobile_ready'=>$ready,'app_name'=>'NISHA','website'=>'https://nishaglobal.com','token_lifetime_days'=>30]);
    }
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    if(in_array($action,['login','register','logout','deletion_request'],true)&&$method!=='POST')throw new NishaMobileException('Use POST for this account action.','mobile_method_invalid',405);
    if($action==='login')mobile_reply(200,nisha_mobile_login(db(),$input,$ip));
    if($action==='register')mobile_reply(201,nisha_mobile_register(db(),$input,$ip));
    // Fresh credentials can recover an expired app session. An old stored
    // Authorization header must not prevent a new, rate-limited sign-in.
    $bearer=nisha_mobile_bearer($_SERVER);$user=$bearer!==null?nisha_mobile_authenticate(db(),$bearer):null;
    if(in_array($action,['me','logout','deletion_request'],true)){
        if(!$user)throw new NishaMobileException('Sign in to use your NISHA account.','mobile_login_required',401);
        if($action==='logout'){nisha_mobile_body_keys($input,[]);mobile_reply(200,nisha_mobile_logout(db(),$bearer));}
        if($action==='deletion_request')mobile_reply(202,nisha_mobile_deletion_request(db(),$user,$input,$ip));
        if($method!=='GET')throw new NishaMobileException('Use GET for your account.','mobile_method_invalid',405);
        $access=null;try{require_once dirname(__DIR__).'/includes/commerce.php';$access=nisha_commerce_overview(db(),(int)$user['id']);}catch(Throwable$e){}
        mobile_reply(200,['success'=>true,'user'=>$user,'access'=>$access]);
    }
    $serviceFile=dirname(__DIR__).'/includes/mobile-learning.php';
    if(!is_file($serviceFile))throw new NishaMobileException('The mobile learning service is being updated. Offline practice remains available.','mobile_setup_required');
    require_once$serviceFile;
    $reply=nisha_mobile_dispatch($action,$method,$input,$user,$audio);
    $status=is_int($reply['_status']??null)?$reply['_status']:200;unset($reply['_status']);
    if($audio!==null&&is_uploaded_file($audio['path']))@unlink($audio['path']);
    mobile_reply($status,$reply);
}catch(Throwable$exception){
    if($audio!==null&&is_uploaded_file($audio['path']))@unlink($audio['path']);
    if($exception instanceof NishaMobileException||$exception instanceof NishaMobileServiceException){
        if($exception->httpStatus===429)header('Retry-After: 900');
        $details=$exception instanceof NishaMobileServiceException?array_intersect_key($exception->details,array_flip(['retryable'])):[];
        mobile_reply($exception->httpStatus,['error'=>$exception->publicMessage,'code'=>$exception->errorCode]+$details);
    }
    error_log('NISHA mobile request unavailable: '.get_class($exception));
    mobile_reply(503,['error'=>'The app service is temporarily unavailable. Your offline practice remains available.','code'=>'mobile_unavailable']);
}
