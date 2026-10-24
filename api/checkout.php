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
    session_write_close();$checkout=nisha_commerce_checkout(db(),(int)$user['id']);
    if($json)checkout_reply(200,['success'=>true,'checkout_url'=>$checkout['url']]);
    header('Location: '.$checkout['url'],true,303);exit;
}catch(NishaCommerceException$e){checkout_reply($e->httpStatus,['error'=>$e->publicMessage,'code'=>$e->errorCode]);}
catch(Throwable$e){error_log('NISHA checkout unavailable: '.get_class($e));checkout_reply(503,['error'=>'Checkout is temporarily unavailable. No access was granted; please try later.']);}
