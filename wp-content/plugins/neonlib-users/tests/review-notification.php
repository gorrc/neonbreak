<?php
// Isolated mail contract check; no network or actual emails.
define('ABSPATH', __DIR__);
class WP_User { public string $user_email = 'publisher@example.test'; }
class NeonLib_Api_Client {
    public function subscriptions($id) { return $GLOBALS['subscriptions']; }
}
function get_user_by($type, $id) { return new WP_User(); }
function get_option($key) { return 'admin@example.test'; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function is_wp_error($value) { return false; }
function wp_mail($to, $subject, $body) {
    $GLOBALS['mail'] = compact('to', 'subject', 'body');
    return $GLOBALS['mail_success'];
}
require __DIR__ . '/../includes/class-neonlib-users.php';
$class = new ReflectionClass(NeonLib_Users::class);
$instance = $class->newInstanceWithoutConstructor();
$notify = $class->getMethod('notify_subscription_review');
$GLOBALS['mail_success'] = true;
$notify->invoke($instance, 7, 'test.collection', ['data'=>['version'=>1]], 'Test collection');
foreach (['Test collection', 'Verzija: 1', 'publisher@example.test', 'review_package=test.collection', 'Nije još objavljena'] as $expected) {
    if (!str_contains($GLOBALS['mail']['body'], $expected)) throw new RuntimeException('Missing: ' . $expected);
}
if ($GLOBALS['mail']['to'] !== 'admin@example.test') throw new RuntimeException('Wrong recipient');
$GLOBALS['subscriptions'] = [['package_id'=>'other.collection','title'=>'Wrong'], ['package_id'=>'test.collection','title'=>'Updated title']];
$notify->invoke($instance, 7, 'test.collection', ['data'=>['version'=>2]]);
if (!str_contains($GLOBALS['mail']['body'], 'Updated title') || !str_contains($GLOBALS['mail']['body'], 'Verzija: 2')) throw new RuntimeException('Wrong revision metadata');
$GLOBALS['subscriptions'] = [];
$GLOBALS['mail_success'] = false;
$notify->invoke($instance, 7, 'test.collection', ['data'=>['version'=>3]]);
if (!str_contains($GLOBALS['mail']['body'], 'Naziv: test.collection')) throw new RuntimeException('Missing fallback');
echo "PASS: initial submission, revision metadata, admin review link, title fallback, mail failure does not throw.\n";
