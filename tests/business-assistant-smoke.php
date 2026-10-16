<?php
if (PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/user.php';
function ba_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$users=[];$expected=0;$calls=0;$model_calls=0;$no_sources=false;$bad_model=false;$foreign=false;
$mock=function($pre,$args,$url)use(&$expected,&$calls,&$model_calls,&$no_sources,&$bad_model,&$foreign){
 $calls++;$body=json_decode($args['body']??'{}',true);
 if($url==='https://api.fireworks.ai/inference/v1/chat/completions'){
  ba_check(empty($body['response_format']),'Unexpected model routing call');
  $model_calls++;ba_check(strpos($args['headers']['Authorization'],'fixture-key')!==false,'Owner key missing');
  ba_check($body['messages'][0]['role']==='system' && strpos($body['messages'][0]['content'],'Business price: 1200 EUR')!==false,'Retrieved context missing');
  if($bad_model)return new WP_Error('fixture','Provider failure');
  return ['response'=>['code'=>200],'body'=>json_encode(['choices'=>[['mode'=>'knowledge','message'=>['content'=>'Business price is 1200 EUR [1].']]]])];
 }
 if($url==='https://api.fireworks.ai/inference/v1/embeddings')return ['response'=>['code'=>200],'body'=>json_encode(['data'=>[['index'=>0,'embedding'=>array_fill(0,4096,.1)]]])];
 ba_check(strpos($url,'https://fixture.cloud.qdrant.io/')===0,'Unexpected network destination');
 if(strpos($url,'/index?')!==false)return ['response'=>['code'=>200],'body'=>'{}'];
 ba_check($body['filter']['must'][0]['match']['value']===(string)$expected,'Cross-tenant query');
 return ['response'=>['code'=>200],'body'=>json_encode(['result'=>['points'=>$no_sources?[]:[['score'=>.95,'payload'=>['tenant'=>(string)($foreign?$expected+1:$expected),'document'=>'doc-'.$expected,'title'=>'Business','chunk'=>1,'text'=>'Business price: 1200 EUR']]]]])];
};
add_filter('pre_http_request',$mock,10,3);
add_filter('pre_option_nb_qdrant_url',fn()=>'https://fixture.cloud.qdrant.io');
add_filter('pre_option_nb_qdrant_key',fn()=>Neonbreak_Client_Service::encrypt('fixture-qdrant'));
try{
 ba_check(get_option('nb_assistant_schema')==='1','Schema not installed');
 foreach([1,2]as$i){$uid=wp_insert_user(['user_login'=>'ba_fixture_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(24),'user_email'=>'ba-'.wp_generate_password(10,false).'@example.invalid','role'=>'subscriber']);ba_check(!is_wp_error($uid),'Fixture failed');$users[]=$uid;update_user_meta($uid,'_nb_advanced_enabled','1');update_user_meta($uid,'_nb_chat_key',Neonbreak_Client_Service::encrypt('fixture-key'));update_user_meta($uid,'_nb_advanced_docs',['doc-'.$uid=>['title'=>'Business','chunks'=>1]]);}
 [$a,$b]=$users;$expected=$a;wp_set_current_user($a);
 $thread=NB_Business_Assistant::dispatch($a,'create',[]);$id=$thread['id'];
 ba_check(is_wp_error(NB_Business_Assistant::dispatch($b,'load',['id'=>$id])),'Foreign load accepted');
 ba_check(is_wp_error(NB_Business_Assistant::dispatch($b,'delete',['id'=>$id])),'Foreign delete accepted');
 $request=bin2hex(random_bytes(16));$data=['id'=>$id,'mode'=>'knowledge','message'=>'What is the Business price?','request'=>$request];
 $reply=NB_Business_Assistant::dispatch($a,'send',$data);
 ba_check(!is_wp_error($reply)&&count($reply['messages'])===2,'Reply failed');
 ba_check($reply['messages'][1]['sources'][0]['document']==='Business','Sources not saved');
 ba_check(strpos(json_encode($reply),'fixture-key')===false,'Secret exposed');
 $before=$calls;$again=NB_Business_Assistant::dispatch($a,'send',$data);ba_check(count($again['messages'])===2&&$calls===$before,'Duplicate external request');
 $no_sources=true;$before=$model_calls;$empty=NB_Business_Assistant::dispatch($a,'send',['id'=>$id,'mode'=>'knowledge','message'=>'Unknown policy?','request'=>bin2hex(random_bytes(16))]);ba_check(empty($empty['messages'][3]['sources'])&&$model_calls===$before,'No-source response generated facts');$no_sources=false;
 $bad_model=true;$failed=NB_Business_Assistant::dispatch($a,'send',['id'=>$id,'mode'=>'knowledge','message'=>'What is the price?','request'=>bin2hex(random_bytes(16))]);ba_check($failed['messages'][5]['failed']===true,'Provider failure not persisted');$bad_model=false;
 $foreign=true;$denied=NB_Business_Assistant::dispatch($a,'send',['id'=>$id,'mode'=>'knowledge','message'=>'Check price','request'=>bin2hex(random_bytes(16))]);ba_check($denied['messages'][7]['failed']===true,'Foreign source not rejected');$foreign=false;
 update_user_meta($a,'_nb_advanced_enabled','0');$before=$calls;ba_check(is_wp_error(NB_Business_Assistant::dispatch($a,'send',['id'=>$id,'mode'=>'knowledge','message'=>'test','request'=>bin2hex(random_bytes(16))]))&&$calls===$before,'Revoked access accepted');ba_check(!is_wp_error(NB_Business_Assistant::dispatch($a,'load',['id'=>$id])),'Old history inaccessible after revocation');
 ba_check(NB_Business_Assistant::dispatch($b,'list',[])['threads']===[],'Foreign thread listed');
 ba_check(NB_Business_Assistant::dispatch($a,'delete',['id'=>$id])['deleted'],'Delete failed');ba_check(is_wp_error(NB_Business_Assistant::dispatch($a,'load',['id'=>$id])),'Deleted thread restored');
 update_user_meta($a,'_nb_advanced_enabled','1');$old=NB_Business_Assistant::dispatch($a,'create',[]);global $wpdb;$wpdb->update(NB_Business_Assistant::table(),['updated_at'=>gmdate('Y-m-d H:i:s',time()-91*DAY_IN_SECONDS)],['id'=>$old['id']]);ba_check(NB_Business_Assistant::dispatch($a,'list',[])['threads']===[],'Expired history visible');NB_Business_Assistant::cleanup();ba_check(!$wpdb->get_var($wpdb->prepare('SELECT id FROM '.NB_Business_Assistant::table().' WHERE id=%s',$old['id'])),'Expired history retained');
 echo "PASS: tenant isolation, owner key, knowledge answer, sources, idempotency, no-source refusal, provider failure, revoked access, deletion and retention.\n";
}finally{wp_set_current_user(0);remove_filter('pre_http_request',$mock,10);foreach($users as$uid){$wpdb->delete(NB_Business_Assistant::table(),['owner_id'=>$uid]);wp_delete_user($uid);}}
