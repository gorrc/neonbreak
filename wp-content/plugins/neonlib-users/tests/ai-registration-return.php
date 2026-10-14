<?php
// Isolated verification redirect tests: no emails, HTTP requests or WordPress data.
define('ABSPATH', __DIR__);
class RedirectSignal extends Exception {}
function wp_unslash($v){return $v;} function sanitize_key($v){return $v;} function sanitize_text_field($v){return $v;} function absint($v){return abs((int)$v);}
function wp_salt($v){return 'test-salt';}
function get_user_meta($id,$key,$single){return $GLOBALS['meta'][$key]??'';}
function update_user_meta($id,$key,$v){$GLOBALS['meta'][$key]=$v;}
function delete_user_meta($id,$key){unset($GLOBALS['meta'][$key]);}
function home_url($p){return 'https://example.test'.$p;}
function get_option($k){return 1;} function get_permalink($id){return 'https://example.test/account/';}
function add_query_arg($k,$v,$url){return $url.'?'.http_build_query([$k=>$v]);}
function wp_safe_redirect($url){throw new RedirectSignal($url);}
require __DIR__.'/../includes/class-neonlib-users.php';
$r=new ReflectionClass(NeonLib_Users::class);$obj=$r->newInstanceWithoutConstructor();
$method=$r->getMethod('requested_ai_connection');$id=str_repeat('a',32);$n=0;
foreach([$id=>'a', '../bad'=>'', 'https://evil.test'=>''] as $value=>$expected){$_GET=['neonlib_ai_connection'=>$value];$actual=$method->invoke($obj);if($actual!==($expected==='a'?$id:''))throw new Exception('Invalid id validation');$n++;}
$_GET=['neonlib_ai_connection'=>['bad']];if($method->invoke($obj)!=='')throw new Exception('Array id');$n++;
foreach(['valid','ordinary','invalid-token','expired'] as $case){
$GLOBALS['meta']=['neonlib_email_verify_token'=>hash_hmac('sha256','test-token','test-salt'),'neonlib_email_verify_expires'=>time()+60,'neonlib_account_id'=>'acc_'.str_repeat('a',26),'neonlib_pending_ai_connection'=>$case==='ordinary'?'':$id];
if($case==='expired')$GLOBALS['meta']['neonlib_email_verify_expires']=time()-60;
$_GET=['neonlib_action'=>'verify_email','user'=>7,'token'=>$case==='invalid-token'?'wrong':'test-token'];
try{$obj->handle_email_verification();throw new Exception('Missing redirect');}catch(RedirectSignal $e){
$expected=match($case){'valid'=>'https://example.test/?neonlib_ai='.$id,'ordinary'=>'https://example.test/account/?neonlib_verification=verified','invalid-token'=>'https://example.test/account/?neonlib_verification=invalid','expired'=>'https://example.test/account/?neonlib_verification=expired'};
if($e->getMessage()!==$expected)throw new Exception('Wrong redirect '.$case);$n++;
if(in_array($case,['invalid-token','expired'],true)){if(($GLOBALS['meta']['neonlib_email_verified']??'')==='1')throw new Exception('Unverified account accepted');}
else {if($GLOBALS['meta']['neonlib_email_verified']!=='1'||isset($GLOBALS['meta']['neonlib_pending_ai_connection']))throw new Exception('Verification state');}$n++;
}}
echo "$n registration return assertions passed\n";
