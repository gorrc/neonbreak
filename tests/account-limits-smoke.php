<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/wp-load.php';require_once ABSPATH.'wp-admin/includes/user.php';
function limits_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$users=[];$external=0;
$mock=function($pre,$args,$url)use(&$external){$external++;if(strpos($url,'/embeddings')!==false)return ['response'=>['code'=>200],'body'=>json_encode(['data'=>[['index'=>0,'embedding'=>array_fill(0,4096,.1)]]])];if(strpos($url,'https://fixture.cloud.qdrant.io/')===0){if(strpos($url,'/index?')!==false)return ['response'=>['code'=>200],'body'=>'{}'];return ['response'=>['code'=>200],'body'=>'{"result":{"points":[]}}'];}throw new RuntimeException('Unexpected external request');};
add_filter('pre_http_request',$mock,10,3);add_filter('pre_option_nb_qdrant_url',fn()=>'https://fixture.cloud.qdrant.io');add_filter('pre_option_nb_qdrant_key',fn()=>Neonbreak_Client_Service::encrypt('fixture'));
try{
 foreach(['administrator','subscriber','subscriber']as$i=>$role){$uid=wp_insert_user(['user_login'=>'limits_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(24),'user_email'=>'limits-'.wp_generate_password(10,false).'@example.invalid','role'=>$role]);limits_check(!is_wp_error($uid),'Fixture failed');$users[]=$uid;}
 [$admin,$a,$b]=$users;limits_check(NB_Workspace_Limits::get($a)===['search_daily'=>100,'search_minute'=>10,'documents'=>10],'Defaults changed');
 wp_set_current_user($a);limits_check(is_wp_error(NB_Workspace_Limits::save($a,['search_daily'=>99,'search_minute'=>99,'documents'=>99])),'Client can change limits');
 wp_set_current_user($admin);limits_check(NB_Workspace_Limits::save($a,['search_daily'=>'2','search_minute'=>'3','documents'=>'1'])===true,'Admin save failed');
 limits_check(is_wp_error(NB_Workspace_Limits::save($a,['search_daily'=>0,'search_minute'=>3,'documents'=>1])),'Invalid zero accepted');limits_check(is_wp_error(NB_Workspace_Limits::save($a,['search_daily'=>2,'search_minute'=>3,'documents'=>['bad']])),'Array accepted');
 limits_check(NB_Workspace_Limits::get($b)['documents']===10,'Other account changed');
 foreach([$a,$b]as$uid){update_user_meta($uid,'_nb_advanced_enabled','1');update_user_meta($uid,'_nb_chat_key',Neonbreak_Client_Service::encrypt('fixture'));}
 $one=NB_AI_Search::search($a,'test',1);$two=NB_AI_Search::search($a,'test',1);limits_check($one['usage']['daily_limit']===2&&$one['usage']['minute_limit']===3&&$two['usage']['remaining']===0,'Custom usage report');
 $before=$external;$blocked=NB_AI_Search::search($a,'test',1);limits_check(is_wp_error($blocked)&&$blocked->get_error_code()==='nb_search_quota'&&$before===$external,'Custom daily quota not enforced');
 $day=(int)get_option(NB_AI_Search::counter_name($a));NB_Workspace_Limits::save($a,['search_daily'=>8,'search_minute'=>3,'documents'=>1]);limits_check((int)get_option(NB_AI_Search::counter_name($a))===$day,'Saving reset counter');
 $blocked=NB_AI_Search::search($a,'test',1);limits_check(is_wp_error($blocked)&&$blocked->get_error_code()==='nb_search_rate','Minute quota not enforced');
 delete_option('nb_search_min_'.gmdate('YmdHi').'_'.$a);$raised=NB_AI_Search::search($a,'test',1);limits_check(!is_wp_error($raised)&&$raised['usage']['daily_limit']===8,'New quota not used');
 $other=NB_AI_Search::search($b,'test',1);limits_check($other['usage']['daily_limit']===100,'Default quota changed for other user');
 wp_set_current_user($admin);$dto=NB_Workspace_Admin::clients(get_userdata($a)->user_login,'all',1);limits_check($dto['rows'][0]['limits']['documents']===1,'Admin limits display');$_GET=['tab'=>'usage','scope'=>'all','s'=>get_userdata($a)->user_login];ob_start();NB_Workspace_Admin::render();$html=ob_get_clean();limits_check(strpos($html,'Limit postavlja korisnik')!==false&&strpos($html,'Limit postavlja administrator')!==false&&strpos($html,'name="search_daily"')!==false,'Column ownership labels or controls missing');
 echo "PASS: admin-only per-account limits, validation, defaults, shared Search enforcement/usage, preserved counters, other-account isolation and labeled admin controls.\n";
}finally{$_GET=[];wp_set_current_user(0);remove_filter('pre_http_request',$mock,10);foreach($users as$uid){delete_option(NB_AI_Search::counter_name($uid));delete_option('nb_search_min_'.gmdate('YmdHi').'_'.$uid);wp_delete_user($uid);}}
