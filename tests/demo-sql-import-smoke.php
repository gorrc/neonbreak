<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/wp-load.php';require_once ABSPATH.'wp-admin/includes/user.php';
$email='seed-import-'.wp_generate_password(12,false).'@example.invalid';$uid=wp_insert_user(['user_login'=>'seed_import_'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(24),'user_email'=>$email,'role'=>'subscriber']);
if(is_wp_error($uid))throw new RuntimeException('Fixture creation failed.');
try{
 global $wpdb;[$ct,$mt]=NB_Conversation_History::tables();$owner=get_user_by('email','gsambolic@gmail.com');$before=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ct WHERE owner_id=%d",$owner->ID));
 $sql=file_get_contents(ABSPATH.'deployment/neonbreak-demo-conversations.sql');$needle=bin2hex('gsambolic@gmail.com');if(substr_count($sql,$needle)!==1)throw new RuntimeException('Unexpected target structure.');$sql=str_replace($needle,bin2hex($email),$sql);
 foreach(explode(';',$sql) as $statement)if(trim($statement)!==''&&$wpdb->query($statement)===false)throw new RuntimeException('SQL import failed: '.$wpdb->last_error.'; statement: '.substr($statement,0,140));
 $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ct WHERE owner_id=%d",$uid));$messages=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $mt m JOIN $ct c ON c.id=m.conversation_id WHERE c.owner_id=%d",$uid));
 if($count!==5||$messages!==30||(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ct WHERE owner_id=%d",$owner->ID))!==$before)throw new RuntimeException('Import verification failed.');
 echo "PASS: portable SQL import creates 5 conversations / 30 messages for email-resolved owner; actual demo owner unchanged.\n";
}finally{wp_delete_user($uid);}
