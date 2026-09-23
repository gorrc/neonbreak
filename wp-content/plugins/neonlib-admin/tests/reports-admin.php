<?php
// Isolated rendering test: no WordPress/database/network required.
define('ABSPATH',__DIR__);
function esc_html($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function esc_attr($v) { return esc_html($v); }
function esc_textarea($v) { return esc_html($v); }
function esc_url($v) { return esc_html($v); }
function admin_url($v) { return 'https://example.test/'.$v; }
function add_query_arg($args,$url) { return $url.'?'.http_build_query($args); }
function is_wp_error($v) { return false; }
function wp_nonce_field($v) { echo '<input name="_wpnonce" value="test">'; }
function selected($a,$b) { if($a===$b) echo 'selected'; }
final class NeonLib_Admin_Api_Client {
    public function reports($filters) { return ['data'=>[['report_id'=>'00000000-0000-4000-8000-000000000001','kind'=>'SUBSCRIPTION','reason'=>'OTHER','created_at'=>'2026-09-23','target_id'=>'test.collection','target_label'=>'<script>alert(1)</script>','content_version'=>2,'excerpt'=>'<img onerror="alert(1)">','details'=>'<script>private</script>','admin_note'=>'</textarea><script>attack</script>','status'=>'NEW','revision'=>1]]]; }
}
require dirname(__DIR__).'/includes/class-neonlib-admin.php';
$class=new ReflectionClass('NeonLib_Admin');
$instance=$class->newInstanceWithoutConstructor();
ob_start(); $class->getMethod('render_reports')->invoke($instance); $html=ob_get_clean();
foreach (['&lt;script&gt;','&lt;img','&lt;/textarea&gt;','_wpnonce','revision','neonlib_admin_report_update','review_package=test.collection'] as $expected) {
    if (!str_contains($html,$expected)) throw new RuntimeException('Missing field: '.$expected);
}
if(str_contains($html,'<script>') || str_contains($html,'<img ')) throw new RuntimeException('Unescaped report content');
echo "Report admin rendering, escaping and form fields passed.\n";
