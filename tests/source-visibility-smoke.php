<?php
require dirname(__DIR__) . '/wp-load.php';
$cases = [[[],0], [['show_sources'=>'1'],1], [['show_sources'=>'0'],0], [['show_sources'=>'invalid'],0]];
if (Neonbreak_Client_Panel::defaults()['show_sources'] !== 0) throw new RuntimeException('Sources must default off.');
foreach ($cases as [$input,$expected]) {
    if (Neonbreak_Client_Panel::sanitize($input)['show_sources'] !== $expected) throw new RuntimeException('Invalid source visibility.');
}
echo "PASS: sources default off and explicit opt-in is sanitized.\n";
