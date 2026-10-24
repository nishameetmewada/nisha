<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/commerce.php';
header('Cache-Control: no-store');
$json=str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
function checkout_reply(int$status,array$body):never{header('Content-Type: application/json; charset=utf-8');http_response_code($status);echo json_encode($body,JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');checkout_reply(405,['error'=>'Use POST to open secure checkout.']);}
$user=current_user();if(!$user)checkout_reply(401,['error'=>'Sign in before purchasing a mock access pass.']);
$token=is_string($_SERVER['HTTP_X_CSRF_TOKEN']??null)?$_SERVER['HTTP_X_CSRF_TOKEN']:null;
if(!verify_csrf($token))checkout_reply(403,['error'=>'Your session expired. Refresh the pricing page and try again.']);
try{
    $contentType=$_SERVER['CONTENT_TYPE']??'';
    if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)checkout_reply(413,['error'=>'The checkout request is too large.']);
    if(preg_match('/\Aapplication\/json(?:\s*;|\s*\z)/i',$contentType)){
        $raw=(string)file_get_contents('php://input',false,null,0,4097);
        if(strlen($raw)>4096)checkout_reply(413,['error'=>'The checkout request is too large.']);
        try{$input=json_decode($raw,true,8,JSON_THROW_ON_ERROR);}catch(JsonException$e){checkout_reply(400,['error'=>'Send a valid checkout request.']);}
        if(!is_array($input)||array_is_list($input)&&$input!==[])checkout_reply(422,['error'=>'Send a promotion code only.']);
    }else{
        $input=$_POST;
        // Native HTML forms carry this field. It has already been verified
        // above; it is transport metadata, never a client price setting.
        unset($input['csrf_token']);
    }
    if(array_diff(array_keys($input),['promotion_code']))checkout_reply(422,['error'=>'The price and access period are set by the server. Send only a promotion code.']);
    $promotionCode=$input['promotion_code']??'';
    if(!is_string($promotionCode)||strlen($promotionCode)>80)checkout_reply(422,['error'=>'Enter a valid promotion code.']);
    session_write_close();$checkout=nisha_commerce_checkout(db(),(int)$user['id'],null,$promotionCode);
    if($json)checkout_reply(200,['success'=>true,'checkout_url'=>$checkout['url']]);
    header('Location: '.$checkout['url'],true,303);exit;
}catch(NishaCommerceException$e){checkout_reply($e->httpStatus,['error'=>$e->publicMessage,'code'=>$e->errorCode]);}
catch(Throwable$e){error_log('NISHA checkout unavailable: '.get_class($e));checkout_reply(503,['error'=>'Checkout is temporarily unavailable. No access was granted; please try later.']);}
