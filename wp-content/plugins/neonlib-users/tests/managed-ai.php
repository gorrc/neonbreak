<?php
// Isolated account/approval page tests; no HTTP calls, email or real WordPress data.
define('ABSPATH',__DIR__);
class WP_User {public int $ID=7;public function has_cap($v){return false;}}
class WP_Error {function __construct(private $code){} function get_error_code(){return $this->code;} function get_error_message(){return $this->code;}}
class Stop extends Exception{}
$GLOBALS['verified']='1';$GLOBALS['nonce']=true;$GLOBALS['approved']=0;$GLOBALS['logged']=true;
function add_action(...$x){}function add_filter(...$x){}function wp_parse_url($u){return parse_url($u);}function home_url($p){return 'https://example.test'.$p;}
function add_query_arg($key,$value,$url){return $url.'?'.urlencode($key).'='.urlencode($value);}function wp_unslash($v){return $v;}
function nocache_headers(){}function status_header($v){}function is_user_logged_in(){return $GLOBALS['logged'];}function wp_get_current_user(){return new WP_User();}
function get_locale(){return 'en_GB';}function get_user_meta(...$x){return $GLOBALS['verified'];}function wp_die($v){throw new Stop($v);}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES);}function esc_attr($v){return esc_html($v);}function esc_url($v){return esc_html($v);}
function is_wp_error($v){return $v instanceof WP_Error;}function check_admin_referer($v){if(!$GLOBALS['nonce'])throw new Stop('nonce');}
function get_header(){echo 'header';}function get_footer(){throw new Stop('rendered');}function wp_nonce_field($v){echo '<input name="_wpnonce">';}
function get_option($v){return 1;}function get_permalink($v){return 'https://example.test/account/';}function wp_login_url($u){return 'https://example.test/wp-login.php?redirect_to='.urlencode($u);}
class NeonLib_Api_Client {function get_account_link($u){return ['account'=>[]];}function link_account($u){throw new Exception('Unexpected relink');}function managed_ai_device($u,$id,$code=null){if($code!==null){$GLOBALS['approved']++;return [];}return ['code'=>'ABC123','dailyLimit'=>50];}}
require __DIR__.'/../includes/class-neonlib-managed-ai.php';
$n=0;function check($ok){global $n;if(!$ok)throw new Exception('Check '.($n+1));$n++;}
$id=str_repeat('a',32);$url='https://example.test/?neonlib_ai='.$id;$user=new WP_User();
check(NeonLib_Managed_AI::login_redirect('fallback',$url,$user)===$url);
foreach(['https://evil.test/?neonlib_ai='.$id,'https://example.test/evil?neonlib_ai='.$id,'https://example.test/?neonlib_ai=../evil','https://name:pw@example.test/?neonlib_ai='.$id] as $bad)check(NeonLib_Managed_AI::login_redirect('fallback',$bad,$user)==='fallback');
$_GET=['neonlib_ai'=>$id];$_SERVER['REQUEST_METHOD']='GET';ob_start();try{NeonLib_Managed_AI::page();}catch(Stop $e){check($e->getMessage()==='rendered');}$html=ob_get_clean();check(str_contains($html,'ABC123')&&str_contains($html,'_wpnonce'));check($GLOBALS['approved']===0);
$GLOBALS['verified']='0';try{NeonLib_Managed_AI::page();}catch(Stop $e){check(str_contains($e->getMessage(),'Verify'));}check($GLOBALS['approved']===0);$GLOBALS['verified']='1';
$_SERVER['REQUEST_METHOD']='POST';$_POST=['device_code'=>'ABC123'];$GLOBALS['nonce']=false;try{NeonLib_Managed_AI::page();}catch(Stop $e){check($e->getMessage()==='nonce');}check($GLOBALS['approved']===0);
$GLOBALS['nonce']=true;ob_start();try{NeonLib_Managed_AI::page();}catch(Stop $e){check($e->getMessage()==='rendered');}$html=ob_get_clean();check($GLOBALS['approved']===1&&str_contains($html,'Return to NeonLib'));
$GLOBALS['logged']=false;$_SERVER['REQUEST_METHOD']='GET';ob_start();try{NeonLib_Managed_AI::page();}catch(Stop $e){}$html=ob_get_clean();check(str_contains($html,'Create a NeonLib account')&&str_contains($html,'redirect_to'));
echo "$n WordPress assertions passed\n";
