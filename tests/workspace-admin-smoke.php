<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/wp-load.php';require_once ABSPATH.'wp-admin/includes/user.php';
function nwa_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$users=[];$prefix='nwa'.strtolower(wp_generate_password(7,false));$secret='PRIVATE_SENTINEL_'.bin2hex(random_bytes(10));$queries=[];
$capture=function($sql)use(&$queries){if(strpos($sql,'nb_assistant_threads')!==false||strpos($sql,'nb_messages')!==false)$queries[]=$sql;return $sql;};
try{
 foreach(['administrator','subscriber']as$role){$uid=wp_insert_user(['user_login'=>$prefix.'_'.$role,'user_pass'=>wp_generate_password(24),'user_email'=>$prefix.'-'.$role.'@example.invalid','display_name'=>$prefix.' '.$role,'role'=>$role]);nwa_check(!is_wp_error($uid),'Fixture failed');$users[]=$uid;update_user_meta($uid,'_nb_workspace_member',time());}
 [$admin,$client]=$users;update_user_meta($client,'_nb_advanced_enabled','1');update_user_meta($client,'_nb_chat_history',['enabled'=>1,'days'=>30]);update_user_meta($client,'_nb_chat_presentation',['enabled'=>1,'daily_limit'=>17,'knowledge'=>$secret]);update_user_meta($client,'_nb_chat_key',Neonbreak_Client_Service::encrypt($secret));update_user_meta($client,'_nb_advanced_docs',['doc'=>['title'=>$secret,'chunks'=>1]]);
 $token=bin2hex(random_bytes(32));NB_Conversation_History::begin($client,$token,bin2hex(random_bytes(16)),'https://example.invalid',$secret);NB_Conversation_History::finish(['message'=>$secret,'sources'=>[['title'=>$secret,'text'=>$secret]]],true);
 wp_set_current_user($client);$thread=NB_Business_Assistant::dispatch($client,'create',[]);global$wpdb;$wpdb->update(NB_Business_Assistant::table(),['title'=>$secret,'messages'=>wp_json_encode([['role'=>'user','content'=>$secret]])],['id'=>$thread['id']]);
 nwa_check(is_wp_error(NB_Workspace_Admin::clients()),'Non-admin metadata allowed');
 NB_Workspace_Admin::record($client,'assistant',$secret);$health=get_user_meta($client,'_nb_workspace_health',true);nwa_check(strpos(wp_json_encode($health),$secret)===false&&$health['assistant']['code']==='unknown','Raw error content stored');
 NB_Workspace_Admin::record($client,'search','nb_search_quota');NB_Workspace_Admin::record($client,'search','ok');
 wp_set_current_user($admin);add_filter('query',$capture);$data=NB_Workspace_Admin::clients($prefix,'workspace',1);remove_filter('query',$capture);
 nwa_check($data['total']===2,'Account filter/count');$row=array_values(array_filter($data['rows'],fn($r)=>$r['id']===$client))[0];nwa_check($row['widget_conversations']===1&&$row['assistant_conversations']===1&&$row['documents']===1,'Aggregate counts');nwa_check($row['widget_limit']===17&&$row['key_saved']===true,'Configuration metadata');nwa_check(strpos(wp_json_encode($data),$secret)===false,'Sensitive data in admin DTO');
 foreach($queries as$sql)if(preg_match('/SELECT (.*?) FROM /si',$sql,$match))nwa_check(!preg_match('/\b(content|sources|messages|title|token_hash)\b/i',$match[1]),'Conversation content column selected');
 foreach(['clients','services','usage','health','support']as$tab){$_GET=['tab'=>$tab,'s'=>$prefix];ob_start();NB_Workspace_Admin::render();$html=ob_get_clean();nwa_check(strpos($html,$secret)===false,'Private content rendered');nwa_check(strpos($html,'Neonbreak AI')!==false,'Admin view failed');}
 echo "PASS: admin-only access, account filters, aggregate counts, no content-column queries or sensitive output, sanitized telemetry and all admin tabs.\n";
}finally{$_GET=[];wp_set_current_user(0);remove_filter('query',$capture);foreach($users as$uid){wp_delete_user($uid);}}
