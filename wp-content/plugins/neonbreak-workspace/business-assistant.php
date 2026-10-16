<?php

defined('ABSPATH') || exit;

/** Private Workspace assistant. No client-supplied owner, SQL, prompt or credentials. */

final class NB_Business_Assistant {

    public static function table() { global $wpdb; return $wpdb->prefix . 'nb_assistant_threads'; }

    public static function install() {

        if (get_option('nb_assistant_schema') === '1') return;

        global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table(); $charset = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE $table (

            id varchar(36) NOT NULL,

            owner_id bigint(20) unsigned NOT NULL,

            title varchar(160) NOT NULL,

            messages longtext NOT NULL,

            created_at datetime NOT NULL,

            updated_at datetime NOT NULL,

            busy_until datetime DEFAULT NULL,

            request_id varchar(32) NOT NULL DEFAULT '',

            PRIMARY KEY  (id),

            KEY owner_updated (owner_id,updated_at)

        ) $charset;");

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) update_option('nb_assistant_schema', '1', false);

    }

    public static function text($en, $hr) { return nb_advanced_language() === 'hr' ? $hr : $en; }

    public static function error($code, $en, $hr, $status = 400) { return new WP_Error($code, self::text($en,$hr), ['status'=>$status]); }

    public static function owned($uid, $id) {

        global $wpdb; $table=self::table();

        if (!is_string($id) || !preg_match('/^[a-f0-9-]{36}$/D', $id)) return null;

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%s AND owner_id=%d AND updated_at >= %s", $id, $uid, gmdate('Y-m-d H:i:s',time()-90*DAY_IN_SECONDS)), ARRAY_A);

    }

    public static function clean_messages($row) { $data=json_decode($row['messages'],true); return is_array($data) ? $data : []; }

    public static function thread($row) { return ['id'=>$row['id'],'title'=>$row['title'],'messages'=>self::clean_messages($row),'updated_at'=>str_replace(' ','T',$row['updated_at']).'Z']; }

    public static function dispatch($uid, $op, $input) {

        global $wpdb; $table=self::table();

        if ($op === 'list') {

            $rows=$wpdb->get_results($wpdb->prepare("SELECT id,title,updated_at FROM $table WHERE owner_id=%d AND updated_at >= %s ORDER BY updated_at DESC,id DESC LIMIT 100",$uid,gmdate('Y-m-d H:i:s',time()-90*DAY_IN_SECONDS)),ARRAY_A);

            return ['threads'=>$rows];

        }

        if ($op === 'create') {

            if (!NB_Advanced_Chat::allowed($uid)) return self::error('access','Advanced access is required.','Potreban je Advanced pristup.',403);

            if (NB_AI_Search::increment('nb_assistant_create_'.gmdate('YmdHi').'_'.$uid)>10) return self::error('rate','Please wait before creating another conversation.','Pričekajte prije izrade novog razgovora.',429);

            $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE owner_id=%d",$uid));

            if ($count>=100) return self::error('limit','Delete an old conversation first (limit: 100).','Najprije izbrišite stari razgovor (limit: 100).');

            $id=wp_generate_uuid4();$now=current_time('mysql',true);

            if (!$wpdb->insert($table,['id'=>$id,'owner_id'=>$uid,'title'=>self::text('New conversation','Novi razgovor'),'messages'=>'[]','created_at'=>$now,'updated_at'=>$now])) return self::error('storage','Unable to save conversation.','Razgovor nije moguće spremiti.',503);

            return self::thread(self::owned($uid,$id));

        }

        $row=self::owned($uid,$input['id']??'');

        if (!$row) return self::error('missing','Conversation not found.','Razgovor nije pronađen.',404);

        if ($op==='load') return self::thread($row);

        if ($op==='delete') { $deleted=$wpdb->delete($table,['id'=>$row['id'],'owner_id'=>$uid]);return $deleted ? ['deleted'=>true] : self::error('storage','Unable to delete conversation.','Razgovor nije moguće izbrisati.',503); }

        if ($op!=='send') return self::error('operation','Invalid action.','Neispravna radnja.');

        if (!NB_Advanced_Chat::allowed($uid)) return self::error('access','Advanced access is required.','Potreban je Advanced pristup.',403);

        $question=$input['message']??null;$request=$input['request']??'';

        if (!is_string($question)||!trim($question)||mb_strlen($question)>1500||!mb_check_encoding($question,'UTF-8')||!is_string($request)||!preg_match('/^[a-f0-9]{32}$/D',$request)) return self::error('input','Enter a question up to 1,500 characters.','Unesite pitanje do 1.500 znakova.');

        $question=sanitize_textarea_field($question);

        $messages=self::clean_messages($row);

        if ($row['request_id']===$request) {

            $last=end($messages);

            if ($last && $last['role']==='assistant' && ($last['request']??'')===$request) return self::thread($row);

            return self::error('pending','This request is processing or was interrupted. Reload the conversation before sending again.','Zahtjev se obrađuje ili je prekinut. Ponovno učitajte razgovor prije novog slanja.',409);

        }

        if (count($messages)>=100) return self::error('limit','Start a new conversation (100 messages per conversation).','Pokrenite novi razgovor (100 poruka po razgovoru).');

        $settings=Neonbreak_Client_Service::settings($uid);

        if (!$settings['_api_key'] || !$settings['model']) return self::error('setup','Save your Fireworks connection in AI Chat first.','Najprije spremite Fireworks vezu u AI Chatu.',503);

        $mode=$input['mode']??'';

        if(!is_string($mode)||!in_array($mode,['knowledge','conversations'],true))return self::error('mode','Choose a source first.','Najprije odaberite izvor.');

        // Atomic per-thread lock. Compare the request snapshot too, so a stale tab cannot overwrite a completed turn.

        $now=current_time('mysql',true);

        $locked=$wpdb->query($wpdb->prepare("UPDATE $table SET busy_until=%s,request_id=%s WHERE id=%s AND owner_id=%d AND request_id=%s AND (busy_until IS NULL OR busy_until < %s)",gmdate('Y-m-d H:i:s',time()+360),$request,$row['id'],$uid,$row['request_id'],$now));

        if ($locked!==1) return self::error('busy','The conversation changed. Reload it and try again.','Razgovor se promijenio. Učitajte ga ponovno i pokušajte.',409);

        $messages[]=['role'=>'user','content'=>$question,'request'=>$request,'created_at'=>gmdate('c')];

        $title=count($messages)===1 ? mb_substr($question,0,100) : $row['title'];

        $saved=$wpdb->update($table,['messages'=>wp_json_encode($messages),'title'=>$title,'updated_at'=>$now],['id'=>$row['id'],'owner_id'=>$uid,'request_id'=>$request]);

        if (!$saved) { $wpdb->update($table,['busy_until'=>null],['id'=>$row['id'],'owner_id'=>$uid,'request_id'=>$request]);return self::error('storage','Unable to save your question.','Pitanje nije moguće spremiti.',503); }

        $failure_message=null;$failure_code='provider_unavailable';

        try {

            $result=NB_Business_Agent::run($uid,$settings,$messages,$mode);

            if(is_wp_error($result)){$failure_message=$result->get_error_message();$failure_code=$result->get_error_code();throw new RuntimeException('Assistant tool unavailable');}

            $messages[]=['role'=>'assistant','content'=>$result['message'],'sources'=>$result['sources'],'tool'=>$result['tool'],'request'=>$request,'created_at'=>gmdate('c')];

        } catch (Throwable $e) {

            $messages[]=['role'=>'assistant','content'=>$failure_message ?? self::text('Unable to complete the knowledge search. Check your connections and try again.','Pretragu baze znanja nije moguće dovršiti. Provjerite veze i pokušajte ponovno.'),'failed'=>true,'request'=>$request,'created_at'=>gmdate('c')];

        }

        // Recheck ownership and access after external work; deleted threads are never recreated.

        if (!NB_Advanced_Chat::allowed($uid)) { $wpdb->update($table,['busy_until'=>null],['id'=>$row['id'],'owner_id'=>$uid,'request_id'=>$request]);return self::error('access','Advanced access is no longer active.','Advanced pristup više nije aktivan.',403); }

        $updated=$wpdb->update($table,['messages'=>wp_json_encode($messages),'updated_at'=>current_time('mysql',true),'busy_until'=>null],['id'=>$row['id'],'owner_id'=>$uid,'request_id'=>$request]);

        if (!$updated) return self::error('storage','Conversation changed or could not be saved. Reload before continuing.','Razgovor je promijenjen ili nije spremljen. Ponovno ga učitajte prije nastavka.',409);

        do_action('nb_workspace_operation_result',$uid,'assistant',!empty(end($messages)['failed'])?$failure_code:'ok');

        return self::thread(self::owned($uid,$row['id']));

    }

    public static function ajax() {

        if (!is_user_logged_in()) wp_send_json_error(['message'=>'Sign in first.'],401);

        check_ajax_referer('nb_business_assistant');nocache_headers();

        if (get_option('nb_assistant_schema')!=='1') wp_send_json_error(['message'=>self::text('Conversation storage is unavailable.','Pohrana razgovora nije dostupna.')],503);

        $input=[];foreach(['id','message','request','mode'] as $key) $input[$key]=isset($_POST[$key])&&is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : '';


        try { $result=self::dispatch(get_current_user_id(),is_string($_POST['operation']??null)?$_POST['operation']:'',$input); }

        catch(Throwable $e) { $result=self::error('unavailable','The assistant is temporarily unavailable.','Asistent trenutačno nije dostupan.',503); }

        if (is_wp_error($result)) wp_send_json_error(['message'=>$result->get_error_message()],$result->get_error_data()['status']??400);

        wp_send_json_success($result);

    }

    public static function cleanup() {

        global $wpdb; $table=self::table();

        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE updated_at < %s",gmdate('Y-m-d H:i:s',time()-90*DAY_IN_SECONDS)));

        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",$wpdb->esc_like('nb_assistant_create_').'%', $wpdb->esc_like('nb_assistant_create_'.gmdate('YmdHi')).'%'));

    }

}

add_action('init',['NB_Business_Assistant','install']);

add_action('wp_ajax_nb_business_assistant',['NB_Business_Assistant','ajax']);

add_action('neonbreak_chat_cleanup',['NB_Business_Assistant','cleanup']);

add_action('delete_user',function($uid){global $wpdb;$wpdb->delete(NB_Business_Assistant::table(),['owner_id'=>$uid]);});

