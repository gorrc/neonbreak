<?php
// CLI only; creates isolated temporary users and removes all fixtures.
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
function nb_history_check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$users=[];
try {
 foreach([1,2] as $i){$uid=wp_insert_user(['user_login'=>'nb_history_test_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(24),'user_email'=>'nb-history-'.wp_generate_password(10,false).'@example.invalid','role'=>'subscriber']);nb_history_check(!is_wp_error($uid),'Fixture creation');$users[]=$uid;update_user_meta($uid,'_nb_chat_history',['enabled'=>1,'days'=>30]);}
 [$a,$b]=$users;$token=bin2hex(random_bytes(32));$request=bin2hex(random_bytes(16));$origin='https://example.invalid';
 nb_history_check(NB_Conversation_History::begin($a,$token,$request,$origin,'Question one')===null,'First message');
 $conversation=NB_Conversation_History::find($a,$token,$origin);$id=(int)$conversation['id'];
 nb_history_check(count(NB_Conversation_History::messages($id))===1,'User message not immediate');
 NB_Conversation_History::finish(['message'=>'Answer one','sources'=>[['title'=>'Fixture','text'=>'Excerpt']]],true);
 nb_history_check(count(NB_Conversation_History::restore($a,$token,$origin))===2,'Restore pair');
 $cached=NB_Conversation_History::begin($a,$token,$request,$origin,'Question one');nb_history_check($cached['message']==='Answer one','Idempotent result');
 nb_history_check(count(NB_Conversation_History::messages($id))===2,'Duplicate request created messages');
 nb_history_check(is_wp_error(NB_Conversation_History::begin($a,$token,$request,$origin,'Changed question')),'Request conflict accepted');
 nb_history_check(NB_Conversation_History::restore($b,$token,$origin)===[],'Cross-owner restore');
 nb_history_check(NB_Conversation_History::restore($a,$token,'https://other.invalid')===[],'Cross-origin restore');
 nb_history_check(NB_Conversation_History::export_data($b,$id)===null,'Cross-owner export');
 nb_history_check(!NB_Conversation_History::delete($b,$id),'Cross-owner delete');
 $second=bin2hex(random_bytes(16));NB_Conversation_History::begin($a,$token,$second,$origin,'Question two');NB_Conversation_History::finish(['message'=>'Generic error'],false);
 $rows=NB_Conversation_History::messages($id);nb_history_check(count($rows)===3&&$rows[2]['status']==='failed','Failure status');
 NB_Conversation_History::begin($a,$token,$second,$origin,'Question two');NB_Conversation_History::finish(['message'=>'Answer two'],true);
 nb_history_check(count(NB_Conversation_History::messages($id))===4,'Retry duplicated user message');
 $export=NB_Conversation_History::export_data($a,$id);$json=wp_json_encode($export);nb_history_check(strpos($json,'token_hash')===false&&strpos($json,$token)===false&&strpos($export['conversation']['created_at'],'T')!==false,'Export secret or format');
 wp_set_current_user($a);$GLOBALS['TRP_LANGUAGE']='hr';$_GET['conversation']=$id;ob_start();nb_history_render();$html=ob_get_clean();nb_history_check(strpos($html,'Question one')!==false&&strpos($html,'Izvezi JSON')!==false,'Owner UI');
 wp_set_current_user($b);ob_start();nb_history_render();$html=ob_get_clean();nb_history_check(strpos($html,'Question one')===false,'Foreign UI exposure');$_GET=[];
 wp_set_current_user($a);
 $detail=nb_history_fragment($a,'detail',['conversation'=>(string)$id]);nb_history_check(!is_wp_error($detail)&&strpos($detail['html'],'Question one')!==false,'AJAX detail missing');
 wp_set_current_user($b);nb_history_check(is_wp_error(nb_history_fragment($b,'detail',['conversation'=>(string)$id])),'AJAX foreign detail exposed');nb_history_check(is_wp_error(nb_history_fragment($b,'delete',['conversation'=>(string)$id])),'AJAX foreign deletion accepted');nb_history_check(is_wp_error(nb_history_fragment($a,'list',[])),'AJAX supplied owner accepted');wp_set_current_user($a);
 $fragment=nb_history_fragment($a,'list',['q'=>'Question one','role'=>'user','period'=>'7']);nb_history_check(!is_wp_error($fragment)&&strpos($fragment['html'],'>#'.$id.'</a>')!==false,'AJAX filtered list missing');
 $fragment=nb_history_fragment($a,'list',['q'=>'Question one','role'=>'assistant','period'=>'7']);nb_history_check(strpos($fragment['html'],'>#'.$id.'</a>')===false,'AJAX role filter failed');
 foreach([
  ['q'=>'Question one','role'=>'user','period'=>'7','expected'=>true],
  ['q'=>'Question one','role'=>'assistant','period'=>'7','expected'=>false],
  ['q'=>'Answer one','role'=>'assistant','period'=>'7','expected'=>true],
  ['q'=>'%','role'=>'','period'=>'7','expected'=>false],
  ['q'=>'Question one','role'=>'user','period'=>'7','history_page'=>'999','expected'=>true],
 ] as $case){$_GET=$case;ob_start();nb_history_render();$html=ob_get_clean();nb_history_check((strpos($html,'>#'.$id.'</a>')!==false)===$case['expected'],'History parameter filter mismatch');nb_history_check(strpos($html,'name="period"')!==false&&strpos($html,'name="role"')!==false,'Search controls missing');}
 foreach (['id','created','updated','messages','invalid SQL'] as $sort) {
  $_GET=['sort'=>$sort,'order'=>'asc','q'=>'Question'];ob_start();nb_history_render();$html=ob_get_clean();
  nb_history_check(strpos($html,'aria-sort="ascending"')!==false && strpos($html,'name="order" value="asc"')!==false,'Sorting state not preserved');
  global $wpdb;nb_history_check(!$wpdb->last_error,'Sorting query failed');
 }
 $_GET=[];
 update_user_meta($a,'_nb_chat_history',['enabled'=>0,'days'=>30]);$third=bin2hex(random_bytes(16));NB_Conversation_History::begin($a,$token,$third,$origin,'Do not record');NB_Conversation_History::finish(['message'=>'Not recorded'],true);nb_history_check(count(NB_Conversation_History::messages($id))===4,'Disabled recording');
 update_user_meta($a,'_nb_chat_history',['enabled'=>1,'days'=>7]);global $wpdb;[$conversations,$messages]=NB_Conversation_History::tables();$wpdb->update($conversations,['updated_at'=>gmdate('Y-m-d H:i:s',time()-8*DAY_IN_SECONDS)],['id'=>$id]);nb_history_check(NB_Conversation_History::restore($a,$token,$origin)===[],'Expired visible');NB_Conversation_History::cleanup();nb_history_check(!(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $messages WHERE conversation_id=%d",$id)),'Expired messages retained');
 echo 'PASS: immediate persistence, retry idempotency, failure status, owner/origin isolation, safe JSON export, owner UI, opt-out and retention cleanup'.PHP_EOL;
} finally {wp_set_current_user(0);foreach($users as $uid)wp_delete_user($uid);}
