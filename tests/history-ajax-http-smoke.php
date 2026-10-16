<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/wp-load.php';require_once ABSPATH.'wp-admin/includes/user.php';
$uid=wp_insert_user(['user_login'=>'history_ajax_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(24),'user_email'=>'history-ajax-'.wp_generate_password(10,false).'@example.invalid','role'=>'subscriber']);
if(is_wp_error($uid))throw new RuntimeException('Fixture failed');
try{
 update_user_meta($uid,'_nb_chat_history',['enabled'=>1,'days'=>7]);wp_set_current_user($uid);
 NB_Conversation_History::begin($uid,bin2hex(random_bytes(32)),bin2hex(random_bytes(16)),'https://example.invalid','AJAX fixture question');NB_Conversation_History::finish(['message'=>'Fixture answer','sources'=>[]],true);
 $cookie=wp_generate_auth_cookie($uid,time()+300,'logged_in');$_COOKIE[LOGGED_IN_COOKIE]=$cookie;$nonce=wp_create_nonce('nb_history_ajax');
 $send=function($args)use($cookie){return wp_remote_post(admin_url('admin-ajax.php'),['timeout'=>15,'headers'=>['Cookie'=>LOGGED_IN_COOKIE.'='.$cookie],'body'=>$args]);};
 $args=['action'=>'nb_history_fragment','operation'=>'list','language'=>'hr','q'=>'AJAX fixture','role'=>'user','_ajax_nonce'=>$nonce];$response=$send($args);$data=json_decode(wp_remote_retrieve_body($response),true);
 if(is_wp_error($response)||empty($data['success'])||strpos($data['data']['html'],'Pronađeno razgovora: 1')===false)throw new RuntimeException('Authenticated AJAX filtering failed');
 $args['_ajax_nonce']='invalid';$response=$send($args);if(wp_remote_retrieve_response_code($response)!==403)throw new RuntimeException('Invalid nonce accepted');
 echo "PASS: authenticated local AJAX JSON fragments, Croatian filter results and nonce rejection. No AI calls.\n";
}finally{unset($_COOKIE[LOGGED_IN_COOKIE]);wp_set_current_user(0);wp_delete_user($uid);}
