<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/wp-load.php';
require_once dirname(__DIR__).'/wp-content/plugins/neonbreak-workspace/history-expression.php';
function ex_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$now=strtotime('2026-10-16 15:00:00 UTC');
$valid=[
 "m.role = 'user' AND m.content LIKE '%PHP%' AND m.created_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY",
 "(m.content LIKE '%PHP%' OR m.content LIKE '%Laravel%') AND m.role = 'user'",
 "m.content LIKE '%O''Reilly%'",
 "m.content LIKE '%UNION SELECT%'",
 "m.created_at >= '2026-10-14 22:00:00' AND m.created_at < '2026-10-15 22:00:00'"
];
foreach($valid as $expression){$result=NB_History_Expression::compile($expression,'',$now);ex_check(!is_wp_error($result),'Valid expression rejected: '.(is_wp_error($result)?$result->get_error_message():''));ex_check(substr_count($result['sql'],'%s')===count($result['params']),'Binding mismatch');}
$r=NB_History_Expression::compile("m.content LIKE '%PHP%'",'Je li jučer spomenut PHP?',$now);ex_check(!is_wp_error($r)&&$r['yesterday_enforced']&&array_slice($r['params'],1,2)===['2026-10-14 22:00:00','2026-10-15 22:00:00'],'Calendar guard missing');
$r=NB_History_Expression::compile("m.created_at >= :yesterday_start AND m.created_at < :yesterday_end",'jučer',$now);ex_check(!is_wp_error($r),'Calendar placeholders rejected');
ex_check(is_wp_error(NB_History_Expression::compile("m.created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY",'jučer',$now)),'Incorrect yesterday accepted');
foreach(['2026-03-30 12:00:00 UTC'=>23,'2026-10-26 12:00:00 UTC'=>25] as $at=>$hours){$dates=NB_History_Expression::calendar(strtotime($at));ex_check((strtotime($dates['yesterday_end'].' UTC')-strtotime($dates['yesterday_start'].' UTC'))/3600===$hours,'DST boundary error');}
$bad=[
 "m.content LIKE '%PHP%') OR 1=1 --", "1=1", "c.owner_id = 2", "m.role = 'user' OR c.owner_id=2",
 "m.content LIKE '%PHP%'; DROP TABLE wp_users", "m.content LIKE '%PHP%' UNION SELECT user_pass FROM wp_users",
 "m.content = (SELECT user_pass FROM wp_users LIMIT 1)", "m.content = SLEEP(10)",
 "m.content LIKE '%PHP%' /* comment */", "m.content LIKE '%PHP%' #comment", "m.content LIKE '%PHP%' --comment",
 "m.role='administrator'", "m.content LIKE '%'", "m.created_at >= UTC_TIMESTAMP() - INTERVAL 999 DAY",
 "m.created_at='2026-02-30 00:00:00'", "m.created_at >= NOW()", "m.content LIKE '%a\\' OR 1=1 --'",
 str_repeat('(',20)."m.role='user'".str_repeat(')',20), "m.content REGEXP '.*'", "UNSUPPORTED"
];
foreach($bad as $expression)ex_check(is_wp_error(NB_History_Expression::compile($expression,'',$now)),'Unsafe expression accepted: '.$expression);
// Prepared SQL is inspected only; never sent to the database.
global $wpdb;$r=NB_History_Expression::compile("m.content = 'x'' OR 1=1 --'",'',$now);ex_check(!is_wp_error($r),'Quoted data rejected');$prepared=$wpdb->prepare($r['sql'],...$r['params']);ex_check(strpos($prepared,"m.content = '")!==false&&count($r['params'])===2&&end($r['params'])==='user','Literal not bound as a value');
echo 'PASS: restricted grammar, parameter binding, owner/subquery/function/comment/statement rejection, exact yesterday, 23/25-hour DST days; '.count($bad)." rejected expressions. No generated SQL executed.\n";
