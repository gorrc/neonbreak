<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/wp-load.php';require_once ABSPATH.'wp-admin/includes/user.php';
$uid=wp_insert_user(['user_login'=>'mcp_http_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(24),'user_email'=>'mcp-http-'.wp_generate_password(10,false).'@example.invalid','role'=>'subscriber']);
if(is_wp_error($uid))throw new RuntimeException('Fixture creation failed');
$previous_cookie=$_COOKIE[LOGGED_IN_COOKIE]??null;
try{
 update_user_meta($uid,'_nb_advanced_enabled','1');wp_set_current_user($uid);
 $cookie=wp_generate_auth_cookie($uid,time()+300,'logged_in');$_COOKIE[LOGGED_IN_COOKIE]=$cookie;
 $nonce=wp_create_nonce('wp_rest');
 $body=wp_json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/list','params'=>['_meta'=>['io.modelcontextprotocol/protocolVersion'=>'2026-07-28','io.modelcontextprotocol/clientCapabilities'=>(object)[]]]]);
 $headers=['Content-Type'=>'application/json','Accept'=>'application/json, text/event-stream','Cookie'=>LOGGED_IN_COOKIE.'='.$cookie,'X-WP-Nonce'=>$nonce,'Mcp-Method'=>'tools/list','MCP-Protocol-Version'=>'2026-07-28'];
 $url=rtrim(get_option('home'),'/').'/wp-json/neonbreak-mcp/v1/mcp';
 $send=fn($h)=>wp_remote_post($url,['headers'=>$h,'body'=>$body,'timeout'=>15,'redirection'=>0]);
 $response=$send($headers);$data=json_decode(wp_remote_retrieve_body($response),true);
 if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200||($data['result']['tools'][0]['name']??'')!=='search_knowledge')throw new RuntimeException('Authenticated HTTP MCP response failed; status '.wp_remote_retrieve_response_code($response));
 unset($headers['X-WP-Nonce']);$without=$send($headers);if(wp_remote_retrieve_response_code($without)!==401)throw new RuntimeException('Cookie without nonce was accepted');
 $headers['X-WP-Nonce']='invalid';$invalid=$send($headers);if(wp_remote_retrieve_response_code($invalid)!==403)throw new RuntimeException('Invalid REST nonce accepted');
 echo "PASS: real local HTTP MCP tool discovery, raw JSON-RPC response, cookie authentication, missing/invalid REST nonce rejected. No AI calls.\n";
}finally{if($previous_cookie===null)unset($_COOKIE[LOGGED_IN_COOKIE]);else $_COOKIE[LOGGED_IN_COOKIE]=$previous_cookie;wp_set_current_user(0);wp_delete_user($uid);}
