<?php
// Isolated WordPress/API doubles: never sends email or touches live user data.
define('ABSPATH', __DIR__); define('DAY_IN_SECONDS',86400); define('NEONLIB_API_URL','https://neonlib-api.example.test');
class WP_Error { public function __construct(private string $code,public string $message=''){} public function get_error_code(){return $this->code;} }
class RedirectSignal extends Exception { public function __construct(public string $url){parent::__construct($url);} }
class JsonSignal extends Exception { public function __construct(public array $data,public int $status=200){parent::__construct('JSON');} }
$GLOBALS['meta']=[7=>['neonlib_email_verified'=>'1']];$GLOBALS['options']=[];$GLOBALS['transients']=[];$GLOBALS['logged']=true;$GLOBALS['cap']=true;$GLOBALS['administrator']=false;$GLOBALS['nonce']=true;
$GLOBALS['api_status']=['status'=>'WAITING','expiresAt'=>gmdate('c',time()+900)];
$GLOBALS['calls']=[];$GLOBALS['created']=false;$GLOBALS['versions']=[];$GLOBALS['mail']=0;$GLOBALS['publish_timeout']=false;
$id=str_repeat('a',32);
$GLOBALS['draft']=['draft'=>['collection'=>['title'=>'Test <script>bad</script>','description'=>'Imported description','language'=>'hr'], 'documents'=>[['id'=>'doc','title'=>'Document','content'=>'Original č ć ž <script>bad</script>']]]];
function add_action(...$args){} function get_locale(){return 'en_GB';} function is_user_logged_in(){return $GLOBALS['logged'];} function current_user_can($cap){return $cap==='manage_options'?$GLOBALS['administrator']:$GLOBALS['cap'];} function get_current_user_id(){return 7;}
function get_user_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';} function update_user_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;} function delete_user_meta($id,$key){unset($GLOBALS['meta'][$id][$key]);}
function get_option($key){return $GLOBALS['options'][$key]??false;} function add_option($key,$value,...$args){if(array_key_exists($key,$GLOBALS['options']))return false;$GLOBALS['options'][$key]=$value;return true;} function delete_option($key){unset($GLOBALS['options'][$key]);}
function set_transient($key,$value,$ttl){$GLOBALS['transients'][$key]=$value;} function delete_transient($key){unset($GLOBALS['transients'][$key]);}
function wp_schedule_single_event(...$args){} function wp_clear_scheduled_hook(...$args){}
function get_permalink($id){return 'https://example.test/account/';} function home_url($path){return 'https://example.test'.$path;} function admin_url($path){return 'https://example.test/wp-admin/'.$path;}
function wp_verify_nonce(...$args){return $GLOBALS['nonce'];} function wp_create_nonce($key){return 'test-nonce';} function check_ajax_referer(...$args){if(!$GLOBALS['nonce'])throw new JsonSignal(['invalid'=>true],403);}
function wp_unslash($value){return $value;} function sanitize_key($value){return preg_replace('/[^a-z0-9_-]/','',strtolower($value));} function sanitize_text_field($value){return trim(strip_tags($value));} function sanitize_textarea_field($value){return trim(strip_tags($value));}
function wp_json_encode($value,$flags=0){return json_encode($value,$flags);} function untrailingslashit($value){return rtrim($value,'/');}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');} function esc_attr($v){return esc_html($v);} function esc_url($v){return esc_html($v);} function wp_nonce_field($action,$name){echo '<input type="hidden" name="'.$name.'" value="nonce">';}
function add_query_arg($args,$value,$url=null){if(!is_array($args))$args=[$args=>$value];else $url=$value;return $url.'?'.http_build_query($args);} function wp_safe_redirect($url){throw new RedirectSignal($url);} function is_wp_error($v){return $v instanceof WP_Error;}
function wp_send_json_error($data,$status=200){throw new JsonSignal($data,$status);} function wp_send_json_success($data){throw new JsonSignal($data);}
class NeonLib_Users { public static function instance(){return new self();} public function notify_subscription_review(...$args){$GLOBALS['mail']++;} }
class NeonLib_Api_Client {
 public function publisher($user){return ['display_name'=>'Publisher'];}
 public function mobile_transfer($user,$method,$id='', $draft=false){$GLOBALS['calls'][]=[$method,$id,$draft];if($method==='POST')return $GLOBALS['session'];if($method==='DELETE')return ['status'=>'CANCELLED'];if ($draft && $id !== $GLOBALS['session']['sessionId']) return new WP_Error('transfer_not_found');return $draft?$GLOBALS['draft']:$GLOBALS['api_status'];}
 public function subscription($user,$package){return $GLOBALS['created']?['package_id'=>$package]:new WP_Error('subscription_not_found');}
 public function create_subscription($user,$payload){$GLOBALS['created']=true;return ['data'=>$payload];}
 public function versions($user,$package){return $GLOBALS['versions'];}
 public function version($user,$package,$number){return ['documents'=>$GLOBALS['saved_documents']];}
 public function publish_version($user,$package,$documents){$GLOBALS['saved_documents']=$documents;$GLOBALS['versions']=[['version'=>1]];if($GLOBALS['publish_timeout'])return new WP_Error('http_timeout');return ['data'=>['version'=>1]];}
}
require __DIR__.'/../includes/class-neonlib-mobile-transfer.php';
$service=NeonLib_Mobile_Transfer::instance();$checks=0;
function check($ok){global $checks;if(!$ok)throw new RuntimeException('Check '.($checks+1).' failed');$checks++;}
function handle($action,$extra=[]){$_SERVER['REQUEST_METHOD']='POST';$_POST=['neonlib_action'=>$action,'neonlib_mobile_nonce'=>'valid']+$extra;try{NeonLib_Mobile_Transfer::instance()->handle();}catch(RedirectSignal $e){return $e->url;}throw new RuntimeException('No redirect');}
$GLOBALS['session']=['sessionId'=>$id,'manualCode'=>'ABCD-EF01-2345','pairing'=>['sessionId'=>$id,'pairingToken'=>str_repeat('b',64)],'expiresAt'=>gmdate('c',time()+900)];
$GLOBALS['logged']=false;check(str_contains(handle('start_mobile_transfer'),'forbidden'));check(count($GLOBALS['calls'])===0);
$GLOBALS['logged']=true;$GLOBALS['nonce']=false;check(str_contains(handle('start_mobile_transfer'),'expired_request'));check(count($GLOBALS['calls'])===0);$GLOBALS['nonce']=true;
check(str_contains(handle('start_mobile_transfer'),'ready'));check(get_user_meta(7,'neonlib_mobile_transfer',true)===$GLOBALS['session']);
$before=count($GLOBALS['calls']);check(str_contains(handle('start_mobile_transfer'),'ready'));check(count($GLOBALS['calls'])===$before+1); // no second POST
$html=$service->render('https://example.test/account/','Publisher');check(str_contains($html,'ABCD-EF01-2345'));check(str_contains($html,'data-payload='));check(!str_contains($html,'https://api.qrserver.com'));
$values=$service->editor_values(7,$id);check(str_contains($values['documents_json'],'Original č ć ž'));check($values['visibility']==='private');
check(is_wp_error($service->editor_values(7,str_repeat('f',32))));
$GLOBALS['api_status']['status']='UPLOADED';$GLOBALS['api_status']['title']='Malicious <script>alert(1)</script>';$GLOBALS['api_status']['documentCount']=1;
$html=$service->render('https://example.test/account/','Publisher');check(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;'));check(!str_contains($html,'<script>alert(1)</script>'));
check(str_contains(handle('import_mobile_transfer',['mobile_transfer'=>$id]),'new-subscription'));
$_POST=['session_id'=>str_repeat('f',32)];try{$service->ajax_status();}catch(JsonSignal $e){check($e->status===404);}
$_POST=['session_id'=>$id];try{$service->ajax_status();}catch(JsonSignal $e){check($e->data['status']==='UPLOADED');check(!isset($e->data['pairing']));}
delete_user_meta(7,'neonlib_mobile_transfer');
check(!is_wp_error($service->editor_values(7,$id)));
$saved=['mobile_transfer'=>$id,'package_id'=>'collection.test','title'=>'Saved title','description'=>'Saved description','language'=>'hr','visibility'=>'public','documents_json'=>$values['documents_json']];
check(str_contains(handle('save_mobile_draft',$saved),'draft_saved'));
check($service->editor_values(7,$id)['title']==='Saved title');
check($service->editor_values(7,$id)['documents_json']===wp_json_encode(json_decode($values['documents_json'],true),JSON_UNESCAPED_UNICODE));
check(!$GLOBALS['created'] && $GLOBALS['mail']===0);
$html=$service->draft_cards([['sessionId'=>$id,'title'=>'Original title']],7,'https://example.test/account/');
check(str_contains($html,'Saved title') && str_contains($html,'Continue editing'));
check(str_contains($html,'Draft — not submitted'));
delete_user_meta(7,'neonlib_mobile_draft_'.$id);
update_user_meta(7,'neonlib_mobile_transfer',['sessionId'=>str_repeat('e',32)]);
$submission=['mobile_transfer'=>$id,'package_id'=>'collection.test','title'=>'Test','description'=>'Description','language'=>'hr','visibility'=>'private','documents_json'=>$values['documents_json']];
$bad=$submission;$bad['documents_json']='[{"id":"bad","title":"Bad","content":false}]';check(str_contains(handle('submit_mobile_transfer',$bad),'invalid_fields'));check(!$GLOBALS['created']);
$GLOBALS['publish_timeout']=true;check(str_contains(handle('submit_mobile_transfer',$submission),'api_error'));check($GLOBALS['created'] && count($GLOBALS['versions'])===1);check($GLOBALS['mail']===0);
$GLOBALS['publish_timeout']=false;check(str_contains(handle('submit_mobile_transfer',$submission),'submitted'));check(count($GLOBALS['versions'])===1 && $GLOBALS['mail']===1);check(get_user_meta(7,'neonlib_mobile_transfer',true)['sessionId']===str_repeat('e',32));
check(str_contains(handle('submit_mobile_transfer',$submission),'submitted'));check($GLOBALS['mail']===1);
$GLOBALS['options']['neonlib_mobile_lock_'.$id]=['token'=>'new'];$service->release_lock('neonlib_mobile_lock_'.$id,'old');check(isset($GLOBALS['options']['neonlib_mobile_lock_'.$id]));$service->release_lock('neonlib_mobile_lock_'.$id,'new');check(!isset($GLOBALS['options']['neonlib_mobile_lock_'.$id]));
echo "PASS: $checks WordPress checks (verified login, nonce, code reuse, escaping, import, AJAX isolation, recovery, one notification, lock ownership).\n";
