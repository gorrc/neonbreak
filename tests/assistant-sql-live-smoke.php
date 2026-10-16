<?php
/** Real model + synthetic local data only. Deletes temporary fixtures afterwards. */
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/wp-load.php';require_once ABSPATH.'wp-admin/includes/user.php';
$owner=get_user_by('email','gsambolic@gmail.com');$settings=$owner?Neonbreak_Client_Service::settings($owner->ID):[];
if(empty($settings['_api_key']))throw new RuntimeException('Local Fireworks connection is missing.');
$settings['model']='accounts/fireworks/models/gpt-oss-120b';$users=[];
try{
 foreach([1,2] as $i){$uid=wp_insert_user(['user_login'=>'expr_live_'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(24),'user_email'=>'expr-'.wp_generate_password(12,false).'@example.invalid','role'=>'subscriber']);if(is_wp_error($uid))throw new RuntimeException('Fixture failed');$users[]=$uid;update_user_meta($uid,'_nb_advanced_enabled','1');update_user_meta($uid,'_nb_chat_history',['enabled'=>1,'days'=>7]);}
 [$a,$b]=$users;$make=function($uid,$question){$token=bin2hex(random_bytes(32));$origin='https://example.invalid';NB_Conversation_History::begin($uid,$token,bin2hex(random_bytes(16)),$origin,$question);NB_Conversation_History::finish(['message'=>'Ovo je sintetički testni odgovor.','sources'=>[]],true);return NB_Conversation_History::find($uid,$token,$origin);};
 $own=$make($a,'Izrađujete li PHP aplikacije?');$foreign=$make($b,'FOREIGN_SECRET_FIXTURE PHP');
 [$ct,$mt]=NB_Conversation_History::tables();global $wpdb;$dates=NB_History_Expression::calendar();$yesterday=gmdate('Y-m-d H:i:s',strtotime($dates['yesterday_start'].' UTC')+12*HOUR_IN_SECONDS);$wpdb->update($mt,['created_at'=>$yesterday],['conversation_id'=>$own['id']]);
 wp_set_current_user($a);
 foreach(['Je li netko u zadnjih 7 dana spomenuo PHP?','Je li netko jučer pitao o PHP aplikacijama?'] as $question){
  $result=NB_Business_Agent::run($a,$settings,[['role'=>'user','content'=>$question]],'conversations');
  if(is_wp_error($result))throw new RuntimeException($result->get_error_message());
  if(count($result['sources'])!==1||(int)$result['sources'][0]['conversation_id']!==(int)$own['id']||strpos(wp_json_encode($result),'FOREIGN_SECRET_FIXTURE')!==false)throw new RuntimeException('Real model search failed isolation or intent check.');
  echo wp_json_encode(['question'=>$question,'answer'=>$result['message'],'matched_sources'=>count($result['sources'])],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
 }
 echo "PASS: real Fireworks predicate generation, validated local SELECT, owner isolation, yesterday boundaries and final answer using synthetic messages only.\n";
}finally{wp_set_current_user(0);foreach($users as $uid){delete_option('nb_mcp_history_min_'.gmdate('YmdHi').'_'.$uid);wp_delete_user($uid);}}
