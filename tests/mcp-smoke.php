<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/user.php';
require_once WP_PLUGIN_DIR.'/neonbreak-mcp/neonbreak-mcp.php';
function mcp_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$users=[];$expected=0;$calls=0;
$mock=function($pre,$args,$url)use(&$expected,&$calls){
 $calls++;$body=json_decode($args['body']??'{}',true);
 if($url==='https://api.fireworks.ai/inference/v1/embeddings')return ['response'=>['code'=>200],'body'=>json_encode(['data'=>[['index'=>0,'embedding'=>array_fill(0,4096,.1)]]])];
 mcp_check(strpos($url,'https://fixture.cloud.qdrant.io/')===0,'Unexpected external request');
 if(strpos($url,'/index?')!==false)return ['response'=>['code'=>200],'body'=>'{}'];
 mcp_check($body['filter']['must'][0]['match']['value']===(string)$expected,'Tenant filter changed');
 return ['response'=>['code'=>200],'body'=>json_encode(['result'=>['points'=>[['score'=>.9,'payload'=>['tenant'=>(string)$expected,'document'=>'doc-'.$expected,'title'=>'Fixture','chunk'=>1,'text'=>'Business: 1200 EUR.']]]]])];
};
add_filter('pre_http_request',$mock,10,3);
add_filter('pre_option_nb_qdrant_url',fn()=>'https://fixture.cloud.qdrant.io');add_filter('pre_option_nb_qdrant_key',fn()=>Neonbreak_Client_Service::encrypt('fixture-secret'));
$decode=function($response){mcp_check(!is_wp_error($response),'Transport failed');$raw=(string)$response->getBody();$data=json_decode($raw,true);if(!$data && preg_match('/data: (.+)/',$raw,$match))$data=json_decode($match[1],true);return $data;};
try{
 foreach([1,2]as$i){$uid=wp_insert_user(['user_login'=>'mcp_test_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(24),'user_email'=>'mcp-'.wp_generate_password(10,false).'@example.invalid','role'=>'subscriber']);mcp_check(!is_wp_error($uid),'Fixture failed');$users[]=$uid;update_user_meta($uid,'_nb_advanced_enabled','1');update_user_meta($uid,'_nb_chat_key',Neonbreak_Client_Service::encrypt('fixture-secret'));update_user_meta($uid,'_nb_advanced_docs',['doc-'.$uid=>['title'=>'Fixture','chunks'=>1]]);}
 [$a,$b]=$users;$expected=$a;wp_set_current_user($a);
 $result=NB_MCP::call($a,'search_knowledge',['query'=>'Business price?','limit'=>2]);
 if(is_wp_error($result))throw new RuntimeException('MCP call: '.$result->get_error_code().' '.$result->get_error_message());
 mcp_check($result['results'][0]['document_id']==='doc-'.$a,'Wrong owner result');
 $before=$calls;mcp_check(is_wp_error(NB_MCP::call($b,'search_knowledge',['query'=>'test']))&&$calls===$before,'Owner override accepted');
 mcp_check(is_wp_error(NB_MCP::call($a,'search_knowledge',['query'=>'test','tenant'=>$b]))&&$calls===$before,'Extra arguments accepted');
 mcp_check(is_wp_error(NB_MCP::call($a,'search_knowledge',['query'=>'test','limit'=>6]))&&$calls===$before,'Limit bypass');
 mcp_check(is_wp_error(NB_MCP::call($a,'run_sql',['query'=>'test']))&&$calls===$before,'Arbitrary tool accepted');
 $meta=['io.modelcontextprotocol/protocolVersion'=>'2026-07-28','io.modelcontextprotocol/clientCapabilities'=>(object)[]];
 $response=NB_MCP::transport($a,wp_json_encode(['jsonrpc'=>'2.0','id'=>2,'method'=>'tools/list','params'=>['_meta'=>$meta]]),['Mcp-Method'=>'tools/list','MCP-Protocol-Version'=>'2026-07-28']);
 $tools=$decode($response);mcp_check($tools['result']['tools'][0]['name']==='search_knowledge','Tool discovery failed');
 $init=NB_MCP::transport($a,wp_json_encode(['jsonrpc'=>'2.0','id'=>3,'method'=>'initialize','params'=>['protocolVersion'=>'2025-11-25','capabilities'=>(object)[],'clientInfo'=>['name'=>'fixture','version'=>'1']]]));
 $initialized=$decode($init);mcp_check(isset($initialized['result']['serverInfo']),'Legacy initialize failed');$session=$init->getHeaderLine('Mcp-Session-Id');mcp_check((bool)$session,'Missing session');
 $headers=['Mcp-Session-Id'=>$session,'MCP-Protocol-Version'=>'2025-11-25'];
 $ready=NB_MCP::transport($a,wp_json_encode(['jsonrpc'=>'2.0','method'=>'notifications/initialized']),$headers);mcp_check($ready->getStatusCode()===202,'Initialize notification');
 $listed=$decode(NB_MCP::transport($a,wp_json_encode(['jsonrpc'=>'2.0','id'=>4,'method'=>'tools/list']),$headers));mcp_check($listed['result']['tools'][0]['name']==='search_knowledge','Persistent legacy session failed');
 wp_set_current_user($b);$foreign=NB_MCP::transport($b,wp_json_encode(['jsonrpc'=>'2.0','id'=>5,'method'=>'tools/list']),$headers);mcp_check($foreign->getStatusCode()>=400,'Foreign session accepted');
 wp_set_current_user($a);update_user_meta($a,'_nb_advanced_enabled','0');mcp_check(is_wp_error(NB_MCP::call($a,'search_knowledge',['query'=>'test'])),'Revoked access accepted');update_user_meta($a,'_nb_advanced_enabled','1');
 $req=new WP_REST_Request('POST','/neonbreak-mcp/v1/mcp');$req->set_header('Origin','https://evil.invalid');mcp_check(is_wp_error(NB_MCP::permission($req)),'Foreign Origin accepted');
 wp_set_current_user(0);mcp_check(is_wp_error(NB_MCP::permission(new WP_REST_Request('POST','/neonbreak-mcp/v1/mcp'))),'Anonymous MCP accepted');
 echo "PASS: official SDK tool calls and discovery, argument validation, tenant isolation, current-user binding, legacy session persistence/isolation, revoked access and Origin/auth guards.\n";
}finally{wp_set_current_user(0);remove_filter('pre_http_request',$mock,10);foreach($users as$uid)wp_delete_user($uid);}
