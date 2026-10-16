<?php
defined('ABSPATH') || exit;
/** Bounded, read-only search of the authenticated owner's website-widget history. */
final class NB_MCP_Conversation_Search {
    /** Only a reconstructed, bound expression enters SQL; owner and retention are outside all model branches. */
    public static function expression(int $uid,string $expression,string $question='',int $limit=5) {
        $access=NB_MCP::access($uid);if(is_wp_error($access))return $access;
        if(!class_exists('NB_History_Expression')||!class_exists('NB_Conversation_History')||get_option('nb_history_schema')!=='2')return new WP_Error('nb_history_setup','Conversation expression search is unavailable.',['status'=>503]);
        if($limit<1||$limit>10||mb_strlen($question)>1500)return new WP_Error('nb_history_input','Invalid search parameters.',['status'=>400]);
        $compiled=NB_History_Expression::compile($expression,$question);if(is_wp_error($compiled))return $compiled;
        if(NB_AI_Search::increment('nb_mcp_history_min_'.gmdate('YmdHi').'_'.$uid)>20)return new WP_Error('nb_history_rate','Maximum 20 conversation searches per minute.',['status'=>429]);
        global $wpdb;[$conversations,$messages]=NB_Conversation_History::tables();$cutoff=NB_Conversation_History::cutoff($uid);
        $template='c.owner_id=%d AND c.updated_at >= %s AND m.created_at >= %s AND m.status=%s AND ('.$compiled['sql'].')';
        $where=$wpdb->prepare($template,...array_merge([$uid,$cutoff,$cutoff,'complete'],$compiled['params']));
        $stats=$wpdb->get_row("SELECT COUNT(*) AS matched_messages,COUNT(DISTINCT c.id) AS matched_conversations FROM $messages m INNER JOIN $conversations c ON c.id=m.conversation_id WHERE $where",ARRAY_A);
        if($wpdb->last_error)return new WP_Error('nb_history_storage','Conversation search is unavailable.',['status'=>503]);
        $rows=$wpdb->get_results($wpdb->prepare("SELECT m.id,m.conversation_id,m.role,LEFT(m.content,1600) AS excerpt,CHAR_LENGTH(m.content) AS original_length,m.created_at,c.origin FROM $messages m INNER JOIN $conversations c ON c.id=m.conversation_id WHERE $where ORDER BY m.created_at DESC,m.id DESC LIMIT %d",$limit),ARRAY_A);
        if($wpdb->last_error)return new WP_Error('nb_history_storage','Conversation search is unavailable.',['status'=>503]);
        $results=[];foreach($rows as $row)$results[]=['kind'=>'conversation','conversation_id'=>(int)$row['conversation_id'],'message_id'=>(int)$row['id'],'document_id'=>'conversation:'.$row['conversation_id'].':message:'.$row['id'],'document'=>'Widget conversation #'.$row['conversation_id'].' / message #'.$row['id'],'chunk'=>1,'text'=>($row['role']==='user'?'Visitor':'Widget assistant').' ('.$row['created_at'].' UTC): '.$row['excerpt'].((int)$row['original_length']>1600?' [excerpt shortened; matching text may be outside excerpt]':''),'source_url'=>add_query_arg(['view'=>'history','conversation'=>(int)$row['conversation_id']],home_url('/workspace/')),'score'=>null,'created_at'=>str_replace(' ','T',$row['created_at']).'Z','origin'=>$row['origin']];
        return ['results'=>$results,'summary'=>['matching_messages'=>(int)$stats['matched_messages'],'matching_conversations'=>(int)$stats['matched_conversations'],'returned_messages'=>count($results),'returned_conversations'=>count(array_unique(array_column($results,'conversation_id'))),'truncated'=>(int)$stats['matched_messages']>count($results),'retained_since'=>str_replace(' ','T',$cutoff).'Z','until'=>gmdate('c'),'calendar'=>$compiled['yesterday_enforced']?$compiled['calendar']:null,'scope'=>'Exact matching-message and distinct-conversation counts over the current owner retained complete widget messages. Excerpts are a bounded recent sample. Counts are for the compiled filter only; they do not establish topic frequencies across all conversations.'],'usage'=>['minute_limit'=>20,'embedding_requests'=>0]];
    }
    public static function run(int $uid,string $query='',int $days=30,int $limit=5,int $conversation_id=0) {
        $access=NB_MCP::access($uid);if(is_wp_error($access))return $access;
        if(!class_exists('NB_Conversation_History')||get_option('nb_history_schema')!=='2')return new WP_Error('nb_history_setup','Widget conversation storage is unavailable.',['status'=>503]);
        if(mb_strlen($query)>200||!mb_check_encoding($query,'UTF-8')||$days<1||$days>90||$limit<1||$limit>10||$conversation_id<0)return new WP_Error('nb_history_input','Use up to 200 search characters, 1–90 days and 1–10 conversations.',['status'=>400]);
        if(NB_AI_Search::increment('nb_mcp_history_min_'.gmdate('YmdHi').'_'.$uid)>20)return new WP_Error('nb_history_rate','Maximum 20 conversation searches per minute.',['status'=>429]);
        global $wpdb;[$conversations,$messages]=NB_Conversation_History::tables();
        $cutoff=max(NB_Conversation_History::cutoff($uid),gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS));
        $base=$wpdb->prepare('c.owner_id=%d AND c.updated_at >= %s',$uid,$cutoff);
        $total=(int)$wpdb->get_var("SELECT COUNT(*) FROM $conversations c WHERE $base");
        $stats=$wpdb->get_row($wpdb->prepare("SELECT SUM(m.role='user' AND m.status='complete') AS visitor_messages,SUM(m.status='failed') AS failed_messages FROM $messages m INNER JOIN $conversations c ON c.id=m.conversation_id WHERE $base AND m.created_at >= %s",$cutoff),ARRAY_A);
        $where=$base;$query=trim(sanitize_text_field($query));$like='%'.$wpdb->esc_like($query).'%';
        if($conversation_id)$where.=$wpdb->prepare(' AND c.id=%d',$conversation_id);
        if($query!=='')$where.=$wpdb->prepare(" AND EXISTS (SELECT 1 FROM $messages s WHERE s.conversation_id=c.id AND s.status='complete' AND s.created_at >= %s AND s.content LIKE %s)",$cutoff,$like);
        $matched=(int)$wpdb->get_var("SELECT COUNT(*) FROM $conversations c WHERE $where");
        $rows=$wpdb->get_results($wpdb->prepare("SELECT c.id,c.origin,c.created_at,c.updated_at FROM $conversations c WHERE $where ORDER BY c.updated_at DESC,c.id DESC LIMIT %d",$limit),ARRAY_A);
        $results=[];$budget=20000;$shortened=false;
        foreach($rows as$row) {
            $order=$query!==''?$wpdb->prepare('(m.content LIKE %s) DESC,',$like):'';
            $items=$wpdb->get_results($wpdb->prepare("SELECT m.id,m.role,LEFT(m.content,900) AS excerpt,m.created_at,CHAR_LENGTH(m.content) AS original_length FROM $messages m INNER JOIN $conversations c ON c.id=m.conversation_id WHERE c.owner_id=%d AND c.id=%d AND c.updated_at >= %s AND m.created_at >= %s AND m.status='complete' ORDER BY $order m.id DESC LIMIT 6",$uid,(int)$row['id'],$cutoff,$cutoff),ARRAY_A);
            usort($items,fn($a,$b)=>(int)$a['id']<=>(int)$b['id']);$text='';
            foreach($items as$item){$text.=($item['role']==='user'?'Visitor':'Widget assistant').' ('.$item['created_at'].' UTC): '.$item['excerpt'].((int)$item['original_length']>900?' [excerpt shortened]':'')."\n";}
            if(!$text)continue;
            $size=min(3000,$budget);if($size<=0){$shortened=true;break;}
            if(mb_strlen($text)>$size){$text=mb_substr($text,0,$size).' [excerpt shortened]';$shortened=true;}
            $budget-=$size;$results[]=['kind'=>'conversation','conversation_id'=>(int)$row['id'],'document_id'=>'conversation:'.$row['id'],'document'=>'Widget conversation #'.$row['id'],'chunk'=>1,'text'=>trim($text),'source_url'=>add_query_arg(['view'=>'history','conversation'=>(int)$row['id']],home_url('/workspace/')),'score'=>null,'created_at'=>str_replace(' ','T',$row['created_at']).'Z','updated_at'=>str_replace(' ','T',$row['updated_at']).'Z','origin'=>$row['origin']];
        }
        if($wpdb->last_error)return new WP_Error('nb_history_storage','Conversation search is unavailable.',['status'=>503]);
        return ['query'=>$query,'results'=>$results,'summary'=>['total_conversations_in_period'=>$total,'matching_conversations'=>$matched,'visitor_messages_in_period'=>(int)($stats['visitor_messages']??0),'failed_messages_in_period'=>(int)($stats['failed_messages']??0),'returned_conversations'=>count($results),'truncated'=>$shortened||$matched>count($results),'since'=>str_replace(' ','T',$cutoff).'Z','until'=>gmdate('c'),'scope'=>'Your website widget conversations only. Date filtering uses last conversation activity and message creation time. Results are recent excerpts, not an exhaustive frequency analysis.'],'usage'=>['minute_limit'=>20,'embedding_requests'=>0]];
    }
}
add_action('neonbreak_chat_cleanup',function(){global $wpdb;$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",$wpdb->esc_like('nb_mcp_history_min_').'%', $wpdb->esc_like('nb_mcp_history_min_'.gmdate('YmdHi')).'%'));});
