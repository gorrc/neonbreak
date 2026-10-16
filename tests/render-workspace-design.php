<?php
if(PHP_SAPI!=='cli')exit;
$_SERVER['SERVER_NAME']='localhost';$_SERVER['HTTP_HOST']='localhost';$_SERVER['REQUEST_URI']='/mobileai/hr/workspace/';
require dirname(__DIR__).'/wp-load.php';require_once ABSPATH.'wp-admin/includes/user.php';
$view=$argv[1]??'assistant';if(!in_array($view,['assistant','overview','history'],true))exit(1);
$uid=wp_insert_user(['user_login'=>'design_'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(24),'user_email'=>'design-'.wp_generate_password(12,false).'@example.invalid','display_name'=>'Demo workspace','role'=>'subscriber']);
if(is_wp_error($uid))throw new RuntimeException('Fixture failed');
try{
 update_user_meta($uid,'_nb_advanced_enabled','1');update_user_meta($uid,'_nb_chat_key',Neonbreak_Client_Service::encrypt('fixture-key-not-real'));
 wp_set_current_user($uid);$GLOBALS['TRP_LANGUAGE']='hr';$_GET=['view'=>$view];global $wp_query;$wp_query=new WP_Query(['pagename'=>'workspace']);$wp_query->the_post();
 ob_start();include ABSPATH.'wp-content/plugins/neonbreak-workspace/workspace-template.php';$html=ob_get_clean();
 if(!in_array('--interactive',$argv,true))$html=preg_replace('~<script\b[^>]*src=[\'"][^\'"]*/business-assistant\.js[^\'"]*[\'"][^>]*>.*?</script>~s','',$html);
 $html=str_replace('<div id="ba-messages" class="ba-messages" role="log" aria-live="polite" aria-label="Poruke"></div>','<div id="ba-messages" class="ba-messages" role="log" aria-live="polite" aria-label="Poruke"><div class="ba-empty"><span class="ba-symbol">✦</span><h2>Što želite pronaći?</h2><p>Pitajte o poslovnim dokumentima ili spremljenim razgovorima posjetitelja.</p><button type="button">Pogledaj primjere pitanja</button></div></div>',$html);
 file_put_contents(ABSPATH.'deployment/workspace-design-preview-'.$view.'.html',$html);echo "Rendered synthetic $view preview.\n";
}finally{wp_set_current_user(0);wp_delete_user($uid);}
