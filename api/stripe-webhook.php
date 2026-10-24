<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/commerce.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function webhook_reply(int$status,array$body):never{http_response_code($status);echo json_encode($body,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');webhook_reply(405,['error'=>'Use POST for payment events.']);}
if((int)($_SERVER['CONTENT_LENGTH']??0)>1048576)webhook_reply(413,['error'=>'The payment event is too large.']);
try{
    $settings=nisha_commerce_settings();if(!$settings['provider_ready'])throw new NishaCommerceException('Payment processing is not configured.','payments_not_configured');
    $raw=(string)file_get_contents('php://input',false,null,0,1048577);
    $event=nisha_stripe_verify_event($raw,(string)($_SERVER['HTTP_STRIPE_SIGNATURE']??''),$settings['webhook_secret']);
    session_write_close();webhook_reply(200,nisha_commerce_apply_event(db(),$event));
}catch(NishaCommerceException$e){webhook_reply($e->httpStatus,['error'=>$e->publicMessage,'code'=>$e->errorCode]);}
catch(Throwable$e){error_log('NISHA payment event failed: '.get_class($e));webhook_reply(503,['error'=>'The payment event could not be processed.']);}
