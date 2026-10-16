<?php
// CLI-only site-chat migration test; temporary user, no provider requests.
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/wp-load.php';require_once ABSPATH.'wp-admin/includes/user.php';
$binding=get_option('nb_site_workspace_owner',null);$backup=get_option('nb_site_workspace_backup',null);
delete_option('nb_site_workspace_owner');delete_option('nb_site_workspace_backup');
$email='nb-site-'.wp_generate_password(10,false).'@example.invalid';$uid=wp_insert_user(['user_login'=>'nb_site_test_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(24),'user_email'=>$email,'role'=>'subscriber']);if(is_wp_error($uid))throw new Exception('Fixture failed');
try{
$public=bin2hex(random_bytes(16));update_user_meta($uid,'_nb_chat_public',$public);update_user_meta($uid,'_nb_chat_presentation',['title'=>'Previous']);update_user_meta($uid,'_nb_chat_key',Neonbreak_Client_Service::encrypt('fixture-previous-key'));$docs=['fixture'=>['title'=>'Existing document','chunks'=>1]];update_user_meta($uid,'_nb_advanced_docs',$docs);update_user_meta($uid,'_nb_chat_history',['enabled'=>1,'days'=>7]);
$legacy=['title'=>'Neonbreak fixture','model'=>'accounts/fireworks/models/gpt-oss-120b','knowledge'=>'Neonbreak offers web development.','daily_limit'=>100,'enabled'=>1];
$result=NB_Site_Workspace::migrate($email,$legacy,'fixture-site-key');if(is_wp_error($result)||$result!==$uid)throw new Exception('Migration failed');
$s=Neonbreak_Client_Service::settings($uid);if($s['title']!==$legacy['title']||$s['knowledge']!==$legacy['knowledge']||$s['_api_key']!=='fixture-site-key')throw new Exception('Settings/key copy failed');
if(!in_array(Neonbreak_Client_Service::host_origin(),$s['domains'],true))throw new Exception('Current site origin missing');
if(get_user_meta($uid,'_nb_chat_public',true)!==$public||get_user_meta($uid,'_nb_advanced_docs',true)!==$docs||NB_Conversation_History::settings($uid)['days']!==7)throw new Exception('Existing client data altered');
$copy=get_option('nb_site_workspace_backup');if(($copy['presentation']['title']??'')!=='Previous'||Neonbreak_Client_Service::decrypt($copy['encrypted_key'])!=='fixture-previous-key')throw new Exception('Backup failed');
$html=Neonbreak_Chat::widget(false);if(strpos($html,'data-chat="'.$public.'"')===false||strpos($html,'data-layout="inline"')===false||strpos($html,'fixture-site-key')!==false)throw new Exception('Inline bridge failed');
ob_start();Neonbreak_Chat::floating();$html=ob_get_clean();if(substr_count($html,'data-chat=')!==1||strpos($html,'nb-chat-toggle')!==false)throw new Exception('Legacy floating widget still rendered');
if(NB_Site_Workspace::migrate($email,$legacy,'changed-fixture-key')!==$uid||Neonbreak_Client_Service::settings($uid)['_api_key']!=='fixture-site-key')throw new Exception('Migration not idempotent');
$saved=get_user_meta($uid,'_nb_chat_presentation',true);$saved['enabled']=0;update_user_meta($uid,'_nb_chat_presentation',$saved);if(NB_Site_Workspace::render(true)!=='')throw new Exception('Workspace disable not respected');
echo 'PASS: migration and encrypted backup, owner binding, existing documents/history preserved, Workspace widget routing, idempotence and disable'.PHP_EOL;
}finally{wp_delete_user($uid);delete_option('nb_site_workspace_owner');delete_option('nb_site_workspace_backup');if($binding!==null)update_option('nb_site_workspace_owner',$binding,false);if($backup!==null)update_option('nb_site_workspace_backup',$backup,false);}
