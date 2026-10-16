<?php
if(PHP_SAPI!=='cli')exit;
define('DOING_AJAX',true);
require dirname(__DIR__).'/wp-load.php';require_once ABSPATH.'wp-admin/includes/user.php';
$mode=$argv[1]??'cap';$calls=0;$uid=wp_insert_user(['user_login'=>'doccap_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(24),'user_email'=>'doccap-'.wp_generate_password(10,false).'@example.invalid','role'=>'subscriber']);
if(is_wp_error($uid))throw new RuntimeException('Fixture failed');
update_user_meta($uid,'_nb_advanced_enabled','1');update_user_meta($uid,'_nb_chat_key',Neonbreak_Client_Service::encrypt('fixture'));update_user_meta($uid,'_nb_workspace_limits',['search_daily'=>100,'search_minute'=>10,'documents'=>1]);
$first=wp_generate_uuid4();$second=wp_generate_uuid4();update_user_meta($uid,'_nb_advanced_docs',[$first=>['title'=>'First','chunks'=>1],$second=>['title'=>'Second','chunks'=>1]]);wp_set_current_user($uid);
add_filter('pre_option_nb_qdrant_url',fn()=>'https://fixture.cloud.qdrant.io');add_filter('pre_option_nb_qdrant_key',fn()=>Neonbreak_Client_Service::encrypt('fixture'));
add_filter('pre_http_request',function($pre,$args,$url)use(&$calls){$calls++;if(strpos($url,'/embeddings')!==false)return ['response'=>['code'=>200],'body'=>json_encode(['data'=>[['index'=>0,'embedding'=>array_fill(0,4096,.1)]]])];if(strpos($url,'https://fixture.cloud.qdrant.io/')===0)return ['response'=>['code'=>200],'body'=>'{}'];throw new RuntimeException('Unexpected network');},10,3);
$_POST=['_ajax_nonce'=>wp_create_nonce('nb_advanced'),'text'=>'Replacement fixture information.','title'=>'Fixture','replace_document'=>$mode==='replace'?$first:'','language'=>'en'];$_REQUEST=$_POST;
remove_action('shutdown','wp_ob_end_flush_all',1);ob_start();
register_shutdown_function(function()use($uid,$mode,&$calls){$output=ob_get_clean();$data=json_decode($output,true);$docs=get_user_meta($uid,'_nb_advanced_docs',true);wp_set_current_user(0);wp_delete_user($uid);if($mode==='cap')$ok=isset($data['success'])&&$data['success']===false&&$calls===0&&count($docs)===2;else$ok=!empty($data['success'])&&$calls>0&&count($docs)===2;if(!$ok){echo "FAIL: document-cap $mode\n";exit(1);}echo "PASS: document-cap $mode (existing documents preserved).\n";});
NB_Advanced_Chat::ingest();
