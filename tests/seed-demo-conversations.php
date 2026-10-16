<?php
/** Explicit CLI-only replacement of one owner's widget history, with an out-of-webroot backup. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/wp-load.php';
$email='gsambolic@gmail.com';$user=get_user_by('email',$email);
if(!$user)throw new RuntimeException('Target account not found.');
$fixtures=json_decode(ltrim(file_get_contents(__DIR__.'/fixtures/neonbreak-demo-conversations.json'),"\xEF\xBB\xBF"),true,32,JSON_THROW_ON_ERROR);
if(count($fixtures)!==5)throw new RuntimeException('Expected five fixture conversations.');
global $wpdb;[$ct,$mt]=NB_Conversation_History::tables();$uid=(int)$user->ID;
$hex=fn($text)=>'CONVERT(0x'.bin2hex($text).' USING utf8mb4)';
$sql="-- SYNTHETIC DEMO: replace ONLY gsambolic@gmail.com website-widget history.\n-- Export both history tables before import. Verify the WordPress table prefix below.\n-- Private Business Assistant threads, settings, keys and other owners are not modified.\nSTART TRANSACTION;\nSET @nb_demo_owner = (SELECT ID FROM {$wpdb->users} WHERE BINARY user_email = BINARY ".$hex($email)." LIMIT 1);\nDELETE m FROM $mt m INNER JOIN $ct c ON c.id=m.conversation_id WHERE c.owner_id=@nb_demo_owner;\nDELETE FROM $ct WHERE owner_id=@nb_demo_owner;\n";
$plans=[];
foreach($fixtures as $fixture){
 $token=hash('sha256',random_bytes(32));$days=(int)$fixture['days_ago'];$base=time()-$days*DAY_IN_SECONDS;$start=gmdate('Y-m-d H:i:s',$base);$end=gmdate('Y-m-d H:i:s',$base+300);
 $sql.="INSERT INTO $ct (owner_id,token_hash,origin,created_at,updated_at,active_request) SELECT ID,'$token','https://neonbreak.com',UTC_TIMESTAMP() - INTERVAL $days DAY,UTC_TIMESTAMP() - INTERVAL $days DAY + INTERVAL 300 SECOND,'' FROM {$wpdb->users} WHERE ID=@nb_demo_owner;\nSET @nb_demo_conversation=(SELECT id FROM $ct WHERE owner_id=@nb_demo_owner AND token_hash='$token' LIMIT 1);\n";
 $prepared=[];foreach($fixture['messages'] as $i=>$message){$request=substr(hash('sha256',$token.':'.intdiv($i,2)),0,32);$offset=$i*60;$stamp=gmdate('Y-m-d H:i:s',$base+$offset);
  $prepared[]=['request_id'=>$request,'role'=>$message[0],'content'=>$message[1],'sources'=>'[]','status'=>'complete','created_at'=>$stamp,'updated_at'=>$stamp];
  $sql.="INSERT INTO $mt (conversation_id,request_id,role,content,sources,status,created_at,updated_at) SELECT c.id,'$request',".$hex($message[0]).','.$hex($message[1]).",'[]','complete',UTC_TIMESTAMP() - INTERVAL $days DAY + INTERVAL $offset SECOND,UTC_TIMESTAMP() - INTERVAL $days DAY + INTERVAL $offset SECOND FROM $ct c WHERE c.id=@nb_demo_conversation AND c.owner_id=@nb_demo_owner;\n";
 }
 $plans[]=['conversation'=>['owner_id'=>$uid,'token_hash'=>$token,'origin'=>'https://neonbreak.com','created_at'=>$start,'updated_at'=>$end,'active_request'=>''],'messages'=>$prepared];
}
$sql.="SELECT @nb_demo_owner AS owner_id,COUNT(*) AS demo_conversations FROM $ct WHERE owner_id=@nb_demo_owner;\nCOMMIT;\n";
$path=ABSPATH.'deployment/neonbreak-demo-conversations.sql';file_put_contents($path,$sql);
echo "Prepared SQL: $path\n";
if(!in_array('--apply',$argv,true)){echo "No local changes. Use --apply for owner-scoped replacement.\n";exit;}
$run=function($query)use($wpdb){$result=$wpdb->query($query);if($result===false)throw new RuntimeException('Database operation failed.');return $result;};
$run("LOCK TABLES $ct WRITE, $ct AS c WRITE, $mt WRITE, $mt AS m WRITE");
$changed=false;
try{
 $old=$wpdb->get_results($wpdb->prepare("SELECT * FROM $ct WHERE owner_id=%d FOR UPDATE",$uid),ARRAY_A);
 foreach($old as $conversation)if(!empty($conversation['busy_until'])&&strtotime($conversation['busy_until'].' UTC')>time())throw new RuntimeException('A conversation is currently active; retry later.');
 $oldMessages=$wpdb->get_results($wpdb->prepare("SELECT m.* FROM $mt m INNER JOIN $ct c ON c.id=m.conversation_id WHERE c.owner_id=%d FOR UPDATE",$uid),ARRAY_A);
 $backup=tempnam(sys_get_temp_dir(),'neonbreak-history-backup-');if(!$backup)throw new RuntimeException('Unable to create backup.');
 $backupData=wp_json_encode(['owner_email'=>$email,'created_at'=>gmdate('c'),'tables'=>[$ct=>$old,$mt=>$oldMessages]],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
 if(file_put_contents($backup,$backupData)!==strlen($backupData))throw new RuntimeException('Backup write failed.');@chmod($backup,0600);
 $otherBefore=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ct WHERE owner_id<>%d",$uid));
 $changed=true;
 $run($wpdb->prepare("DELETE m FROM $mt m INNER JOIN $ct c ON c.id=m.conversation_id WHERE c.owner_id=%d",$uid));$run($wpdb->prepare("DELETE FROM $ct WHERE owner_id=%d",$uid));
 foreach($plans as $plan){if(!$wpdb->insert($ct,$plan['conversation']))throw new RuntimeException('Conversation insert failed.');$id=(int)$wpdb->insert_id;foreach($plan['messages'] as $message)if(!$wpdb->insert($mt,['conversation_id'=>$id]+$message))throw new RuntimeException('Message insert failed.');}
 $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ct WHERE owner_id=%d",$uid));$messageCount=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $mt m JOIN $ct c ON c.id=m.conversation_id WHERE c.owner_id=%d",$uid));
 if($count!==5||$messageCount!==30||(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ct WHERE owner_id<>%d",$uid))!==$otherBefore)throw new RuntimeException('Post-import verification failed.');
 $run('UNLOCK TABLES');echo wp_json_encode(['replaced_conversations'=>count($old),'replaced_messages'=>count($oldMessages),'demo_conversations'=>$count,'demo_messages'=>$messageCount,'backup'=>$backup,'other_owners_unchanged'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){
 if($changed){
  $wpdb->query($wpdb->prepare("DELETE m FROM $mt m INNER JOIN $ct c ON c.id=m.conversation_id WHERE c.owner_id=%d",$uid));$wpdb->query($wpdb->prepare("DELETE FROM $ct WHERE owner_id=%d",$uid));
  foreach($old as $row)$wpdb->insert($ct,$row);foreach($oldMessages as $row)$wpdb->insert($mt,$row);
 }
 $wpdb->query('UNLOCK TABLES');throw $e;
}

