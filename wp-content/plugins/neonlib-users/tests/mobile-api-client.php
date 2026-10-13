<?php
define('ABSPATH',__DIR__);define('NEONLIB_API_URL','https://api.example.test');define('NEONLIB_API_CLIENT_ID','test-client');define('NEONLIB_API_CLIENT_SECRET',str_repeat('s',64));define('NEONLIB_API_SITE_ID','test-site');
class WP_Error {public function __construct(...$args){}}
function untrailingslashit($v){return rtrim($v,'/');} function is_wp_error($v){return $v instanceof WP_Error;} function wp_json_encode($v,$flags=0){return json_encode($v,$flags);} function __($v,...$args){return $v;}
function wp_remote_request($url,$args){$GLOBALS['request']=[$url,$args];return ['code'=>200,'body'=>'{"data":{"status":"WAITING"}}'];}
function wp_remote_retrieve_response_code($r){return $r['code'];} function wp_remote_retrieve_body($r){return $r['body'];}
require __DIR__.'/../includes/class-neonlib-api-client.php';
$client=new NeonLib_Api_Client();$count=0;
foreach([['POST','',false],['GET',str_repeat('a',32),false],['GET',str_repeat('a',32),true],['DELETE',str_repeat('a',32),false]] as [$method,$id,$draft]){
 $client->mobile_transfer(7,$method,$id,$draft);[$url,$args]=$GLOBALS['request'];$path=parse_url($url,PHP_URL_PATH);$headers=$args['headers'];
 $canonical=implode("\n",[$method,$path,$headers['X-NeonLib-Timestamp'],$headers['X-NeonLib-Nonce'],hash('sha256',$args['body']),'7']);
 if($headers['X-NeonLib-Signature']!=='v1='.hash_hmac('sha256',$canonical,NEONLIB_API_CLIENT_SECRET))throw new RuntimeException('Invalid request signature');
 if($headers['X-NeonLib-Subject']!=='7' || $args['redirection']!==0)throw new RuntimeException('Wrong identity/redirect policy');
 if($draft && !str_ends_with($path,'/draft'))throw new RuntimeException('Wrong draft path');$count++;
}
if(!is_wp_error($client->mobile_transfer(7,'GET','../private')))throw new RuntimeException('Traversal accepted');$count++;
echo "PASS: $count API client checks (signed transfer routes, owner identity, draft route, no redirects, invalid ID).\n";
