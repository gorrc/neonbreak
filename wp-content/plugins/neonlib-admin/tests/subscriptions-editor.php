<?php
// Isolated WordPress renderer test; no network or WordPress database.
define('ABSPATH', __DIR__);
function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function esc_attr($v) { return esc_html($v); }
function esc_textarea($v) { return esc_html($v); }
function esc_url($v) { return esc_html($v); }
function admin_url($v) { return 'https://example.test/'.$v; }
function add_query_arg($args,$url) { return $url.'?'.http_build_query($args); }
function is_wp_error($v) { return false; }
function wp_nonce_field($v) { echo '<input name="_wpnonce" value="test">'; }
function selected($a,$b) { if($a===$b) echo 'selected'; }
final class NeonLib_Admin_Api_Client {
    public function vector_job($id,$version,$start=false) { return ['data'=>$GLOBALS['vector_job']??null]; }
    public function subscription($id) {
        $review = ['subscription'=>['title'=>'Title','publisher_name'=>'Publisher','language'=>'en','visibility'=>'PUBLIC','status'=>'ARCHIVED','description'=>'Description'],
            'version'=>['version_number'=>1], 'review_token'=>str_repeat('a',64),
            'documents'=>[['id'=>'article','title'=>'<img src=x onerror=alert(1)>','content'=>'</textarea><script>attack</script>']]];
        return ['data'=>['is_featured'=>true,'review'=>$review,'editor'=>['metadata'=>$review['subscription'],'review'=>$review,'edit_token'=>str_repeat('b',64)]]];
    }
}
require dirname(__DIR__).'/includes/class-neonlib-admin.php';
$class=new ReflectionClass('NeonLib_Admin'); $instance=$class->newInstanceWithoutConstructor();
ob_start(); $class->getMethod('render_editor')->invoke($instance,'editor.test'); $html=ob_get_clean();
foreach (['&lt;img','&lt;/textarea&gt;&lt;script&gt;attack','documents_json','edit_token','_wpnonce','Save draft for review','data-field="content"'] as $expected) {
    if (!str_contains($html,$expected)) throw new RuntimeException('Missing '.$expected);
}
if (str_contains($html,'<script>attack') || str_contains($html,'<img src=x')) throw new RuntimeException('Unescaped content');
ob_start(); $class->getMethod('render_review')->invoke($instance,'editor.test'); $reviewHtml=ob_get_clean();
if (!str_contains($reviewHtml,'Restore to Draft') || str_contains($reviewHtml,'Approve reviewed version')) throw new RuntimeException('Archived approval bypass');
echo "Admin editor rendering passed: escaping, form fields, nonce and archive restore.\n";
ob_start();$class->getMethod('render_vectors')->invoke($instance,'editor.test',2,'DRAFT');$vectors=ob_get_clean();
if(!str_contains($vectors,'Pripremi vektore')||!str_contains($vectors,'neonlib_admin_vectors')||!str_contains($vectors,'_wpnonce'))throw new RuntimeException('Missing vector action');
$GLOBALS['vector_job']=['status'=>'RUNNING','progress'=>2,'total'=>10];
ob_start();$class->getMethod('render_vectors')->invoke($instance,'editor.test',2,'DRAFT');$vectors=ob_get_clean();
if(!str_contains($vectors,'2 / 10')||str_contains($vectors,'name="action"'))throw new RuntimeException('Missing progress or duplicate enqueue action');
$GLOBALS['vector_job']=['status'=>'FAILED','error'=>'<script>attack</script>'];
ob_start();$class->getMethod('render_vectors')->invoke($instance,'editor.test',2,'DRAFT');$vectors=ob_get_clean();
if(str_contains($vectors,'<script>attack'))throw new RuntimeException('Unescaped worker error');
ob_start();$class->getMethod('render_vectors')->invoke($instance,'editor.test',1,'PUBLISHED');$vectors=ob_get_clean();
if(str_contains($vectors,'name="action"'))throw new RuntimeException('Published vector action');
echo "Vector renderer passed: draft action, nonce, progress, escaped errors and published guard.\n";
ob_start();$class->getMethod('render_vectors')->invoke($instance,'editor.test',4,'');$vectors=ob_get_clean();
if(!str_contains($vectors,'API nije vratio status')||str_contains($vectors,'napravi novu verziju'))throw new RuntimeException('Missing version status misdiagnosed as published');
echo "Missing API version status now reports deployment mismatch instead of creating another draft.\n";
