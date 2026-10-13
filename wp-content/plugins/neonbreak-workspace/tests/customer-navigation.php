<?php
// Isolated navigation tests: no real users, browser requests or permissions changed.
define('ABSPATH',__DIR__);
class Redirected extends Exception {public function __construct(public string $url){parent::__construct($url);}}
$GLOBALS['logged']=true;$GLOBALS['caps']=[];$GLOBALS['ajax']=false;$GLOBALS['cron']=false;$GLOBALS['published']=true;
function add_action(...$x){}function add_filter(...$x){}function is_user_logged_in(){return $GLOBALS['logged'];}function current_user_can($c){return in_array($c,$GLOBALS['caps'],true);}
function wp_doing_ajax(){return $GLOBALS['ajax'];}function wp_doing_cron(){return $GLOBALS['cron'];}function get_option($k){return 12;}function get_post_status($id){return $GLOBALS['published']?'publish':'draft';}
function get_permalink($id){return 'https://example.test/neonlib-racun/';}function home_url($p){return 'https://example.test'.$p;}function wp_safe_redirect($url){throw new Redirected($url);}
require __DIR__.'/../customer-navigation.php';
$count=0;function check($ok){global $count;if(!$ok)throw new Exception('Assertion '.($count+1));$count++;}
function redirect_for($page){$GLOBALS['pagenow']=$page;try{Neonbreak_Customer_Navigation::redirect();return null;}catch(Redirected $e){return $e->url;}}
foreach(['index.php','profile.php','plugins.php','options-general.php'] as $page) check(redirect_for($page)==='https://example.test/workspace/');
check(Neonbreak_Customer_Navigation::admin_bar(true)===false);
$GLOBALS['caps']=['neonlib_access_dashboard'];check(redirect_for('index.php')==='https://example.test/neonlib-racun/');
check(redirect_for('admin-post.php')===null);check(redirect_for('admin-ajax.php')===null);
$GLOBALS['ajax']=true;check(redirect_for('index.php')===null);$GLOBALS['ajax']=false;
$GLOBALS['cron']=true;check(redirect_for('index.php')===null);$GLOBALS['cron']=false;
foreach(['manage_options','edit_posts','edit_pages'] as $cap){$GLOBALS['caps']=[$cap];check(redirect_for('index.php')===null);check(Neonbreak_Customer_Navigation::admin_bar(true)===true);check(Neonbreak_Customer_Navigation::admin_bar(false)===false);}
$GLOBALS['caps']=[];$GLOBALS['logged']=false;check(redirect_for('index.php')===null);
echo "$count customer navigation checks passed\n";
