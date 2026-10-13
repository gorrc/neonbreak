<?php
// Status presentation only: no live requests or account changes.
define('ABSPATH', __DIR__);
class WP_Error {}
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_locale() { return $GLOBALS['locale'] ?? 'en_GB'; }
require __DIR__ . '/../includes/class-neonlib-mobile-transfer.php';
require __DIR__ . '/../includes/class-neonlib-users.php';
$cases = array(
    array('draft', array(), 'Draft'),
    array('draft', array(array('status'=>'draft')), 'Review'),
    array('published', array(array('status'=>'published')), 'Published'),
    array('published', array(array('status'=>'draft'), array('status'=>'published')), 'Review'),
    array('published', array(array('status'=>'retired'), array('status'=>'published')), 'Published'),
    array('archived', array(array('status'=>'draft')), 'Archived'),
    array('draft', new WP_Error(), 'Draft'),
);
foreach ($cases as [$status, $versions, $expected]) {
    $actual = NeonLib_Users::subscription_status_label(array('status'=>$status), $versions);
    if ($actual !== $expected) throw new RuntimeException("Expected $expected, got $actual");
}
$GLOBALS['locale']='hr';
if (NeonLib_Users::subscription_status_label(array('status'=>'draft'), array(array('status'=>'draft'))) !== 'Na pregledu') throw new RuntimeException('Croatian review label missing');
echo "PASS: 8 status checks (unsubmitted draft, first review, revision review, approved, retired, archived, failed lookup, Croatian).\n";
