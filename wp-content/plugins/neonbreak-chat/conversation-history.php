<?php
defined('ABSPATH') || exit;

final class NB_Conversation_History {
    private static $active = null;
    public static function tables() { global $wpdb; return [$wpdb->prefix . 'nb_conversations', $wpdb->prefix . 'nb_messages']; }
    public static function install() {
        if (get_option('nb_history_schema') === '2') return;
        global $wpdb; [$conversations, $messages] = self::tables();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $conversations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            owner_id bigint(20) unsigned NOT NULL,
            token_hash char(64) NOT NULL,
            origin varchar(255) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            active_request char(32) NOT NULL DEFAULT '',
            busy_until datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY owner_token (owner_id,token_hash),
            KEY owner_updated (owner_id,updated_at),
            KEY updated_at (updated_at)
        ) $charset;");
        dbDelta("CREATE TABLE $messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            request_id char(32) NOT NULL,
            role varchar(12) NOT NULL,
            content longtext NOT NULL,
            sources longtext NOT NULL,
            status varchar(12) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY request_role (conversation_id,request_id,role),
            KEY conversation_id (conversation_id)
        ) $charset;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($conversations))) === $conversations && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($messages))) === $messages && $wpdb->get_var("SHOW COLUMNS FROM $conversations LIKE 'active_request'")) update_option('nb_history_schema', '2', false);
    }
    public static function settings($uid) {
        $value = get_user_meta($uid, '_nb_chat_history', true);
        $days = (int) ($value['days'] ?? 30);
        return ['enabled' => !empty($value['enabled']), 'days' => in_array($days, [7, 30, 90], true) ? $days : 30];
    }
    public static function cutoff($uid) { return gmdate('Y-m-d H:i:s', time() - self::settings($uid)['days'] * DAY_IN_SECONDS); }
    public static function find($uid, $token, $origin) {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
        global $wpdb; [$conversations] = self::tables();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $conversations WHERE owner_id=%d AND token_hash=%s AND origin=%s AND updated_at >= %s", $uid, hash('sha256', $token), $origin, self::cutoff($uid)), ARRAY_A);
    }
    public static function owned($uid, $id) {
        global $wpdb; [$conversations] = self::tables();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $conversations WHERE id=%d AND owner_id=%d AND updated_at >= %s", $id, $uid, self::cutoff($uid)), ARRAY_A);
    }
    public static function messages($id, $limit = 500) {
        global $wpdb; [, $messages] = self::tables();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT role,content,sources,status,created_at,updated_at FROM $messages WHERE conversation_id=%d ORDER BY id DESC LIMIT %d", $id, min(500, max(1, $limit))), ARRAY_A);
        return array_map(function ($row) { $row['sources'] = json_decode($row['sources'], true) ?: []; return $row; }, array_reverse($rows));
    }
    public static function export_data($uid, $id) {
        $conversation = self::owned($uid, $id);
        if (!$conversation) return null;
        $rows = self::messages($id);
        foreach ($rows as &$row) foreach (['created_at', 'updated_at'] as $field) $row[$field] = str_replace(' ', 'T', $row[$field]) . 'Z';
        unset($row);
        return ['type' => 'neonbreak-conversation', 'version' => 1, 'exported_at' => gmdate('c'), 'conversation' => ['id' => (int) $id, 'origin' => $conversation['origin'], 'created_at' => str_replace(' ', 'T', $conversation['created_at']) . 'Z', 'updated_at' => str_replace(' ', 'T', $conversation['updated_at']) . 'Z', 'timezone' => 'UTC', 'messages' => $rows]];
    }
    public static function begin($uid, $token, $request_id, $origin, $question) {
        self::$active = null;
        if (!self::settings($uid)['enabled']) return null;
        if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !preg_match('/^[a-f0-9]{32}$/D', $request_id)) return new WP_Error('history_session', 'Refresh the chat to start a new conversation.', ['status' => 400]);
        if (get_option('nb_history_schema') !== '2') return new WP_Error('history_storage', 'Conversation storage is unavailable.', ['status' => 503]);
        global $wpdb; [$conversations, $messages] = self::tables();
        $conversation = self::find($uid, $token, $origin); $now = gmdate('Y-m-d H:i:s');
        if (!$conversation) {
            // A removed or expired token cannot silently resurrect an old conversation.
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $conversations WHERE owner_id=%d AND token_hash=%s", $uid, hash('sha256', $token)));
            if ($existing) return new WP_Error('history_expired', 'Start a new chat. This conversation has expired.', ['status' => 409]);
            if ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $conversations WHERE owner_id=%d", $uid)) >= 1000) return new WP_Error('history_limit', 'Conversation storage limit reached. Contact the website owner.', ['status' => 429]);
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $conversations (owner_id,token_hash,origin,created_at,updated_at) VALUES (%d,%s,%s,%s,%s)", $uid, hash('sha256', $token), $origin, $now, $now));
            $conversation = self::find($uid, $token, $origin);
            if (!$conversation) return new WP_Error('history_storage', 'Conversation storage is unavailable.', ['status' => 503]);
        }
        $id = (int) $conversation['id'];
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $messages WHERE conversation_id=%d AND request_id=%s AND role='user'", $id, $request_id), ARRAY_A);
        if ($user) {
            if ($user['content'] !== $question) return new WP_Error('history_conflict', 'Start a new chat or retry the original question.', ['status' => 409]);
            $answer = $wpdb->get_row($wpdb->prepare("SELECT content,sources FROM $messages WHERE conversation_id=%d AND request_id=%s AND role='assistant'", $id, $request_id), ARRAY_A);
            if ($answer) return ['message' => $answer['content'], 'sources' => json_decode($answer['sources'], true) ?: []];
        }
        $locked = $wpdb->query($wpdb->prepare("UPDATE $conversations SET active_request=%s,busy_until=%s WHERE id=%d AND (active_request='' OR busy_until < %s)", $request_id, gmdate('Y-m-d H:i:s', time() + 300), $id, $now));
        if (!$locked) return new WP_Error('history_pending', 'Your previous question is still processing. Please wait.', ['status' => 409]);
        self::$active = ['id' => $id, 'request' => $request_id, 'owner' => $uid];
        if ($user) {
            $claimed = $wpdb->query($wpdb->prepare("UPDATE $messages SET status='pending',updated_at=%s WHERE id=%d AND (status='failed' OR updated_at < %s)", $now, $user['id'], gmdate('Y-m-d H:i:s', time() - 300)));
            if (!$claimed) return new WP_Error('history_pending', 'Your previous question is still processing. Please wait.', ['status' => 409]);
        } else {
            if ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $messages WHERE conversation_id=%d", $id)) >= 498) return new WP_Error('history_full', 'Please start a new chat.', ['status' => 400]);
            $inserted = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $messages (conversation_id,request_id,role,content,sources,status,created_at,updated_at) VALUES (%d,%s,'user',%s,'[]','pending',%s,%s)", $id, $request_id, $question, $now, $now));
            if (!$inserted) return new WP_Error('history_pending', 'Your previous question is still processing. Please wait.', ['status' => 409]);
        }
        $wpdb->update($conversations, ['updated_at' => $now], ['id' => $id]);
        self::$active = ['id' => $id, 'request' => $request_id, 'owner' => $uid];
        return null;
    }
    public static function finish($data, $success) {
        if (!self::$active) return true;
        global $wpdb; [$conversations, $messages] = self::tables(); $active = self::$active; self::$active = null;
        // Do not recreate data if the owner disabled history or removed this conversation during generation.
        $conversation = self::owned($active['owner'], $active['id']);
        if (!$conversation || $conversation['active_request'] !== $active['request']) return false;
        if (!self::settings($active['owner'])['enabled']) { $wpdb->update($conversations, ['active_request' => '', 'busy_until' => null], ['id' => $active['id'], 'active_request' => $active['request']]); return true; }
        $now = gmdate('Y-m-d H:i:s');
        $recorded = true;
        if ($success && is_string($data['message'] ?? null)) {
            $sources = array_slice(is_array($data['sources'] ?? null) ? $data['sources'] : [], 0, 5);
            $stored = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $messages (conversation_id,request_id,role,content,sources,status,created_at,updated_at) VALUES (%d,%s,'assistant',%s,%s,'complete',%s,%s)", $active['id'], $active['request'], $data['message'], wp_json_encode($sources), $now, $now));
            if ($stored === false) { $success = false; $recorded = false; }
        }
        if ($wpdb->update($messages, ['status' => $success ? 'complete' : 'failed', 'updated_at' => $now], ['conversation_id' => $active['id'], 'request_id' => $active['request'], 'role' => 'user']) === false) $recorded = false;
        $wpdb->update($conversations, ['updated_at' => $now], ['id' => $active['id']]);
        $wpdb->update($conversations, ['active_request' => '', 'busy_until' => null], ['id' => $active['id'], 'active_request' => $active['request']]);
        return $recorded;
    }
    public static function restore($uid, $token, $origin) {
        if (!self::settings($uid)['enabled']) return [];
        $conversation = self::find($uid, $token, $origin);
        return $conversation ? self::messages((int) $conversation['id']) : [];
    }
    public static function delete($uid, $id) {
        if (!self::owned($uid, $id)) return false;
        global $wpdb; [$conversations, $messages] = self::tables();
        // Remove the parent first so retrieval stops immediately; no messages are exposed without ownership.
        if (!$wpdb->delete($conversations, ['id' => $id, 'owner_id' => $uid])) return false;
        $wpdb->delete($messages, ['conversation_id' => $id]);
        return true;
    }
    public static function cleanup($only_uid = null) {
        if (get_option('nb_history_schema') !== '2') return;
        global $wpdb; [$conversations, $messages] = self::tables();
        // Bounded batch; repeat on the next cron run for larger installations.
        $owners = is_int($only_uid) && $only_uid > 0 ? [$only_uid] : $wpdb->get_col("SELECT DISTINCT owner_id FROM $conversations LIMIT 1000");
        foreach ($owners as $uid) $wpdb->query($wpdb->prepare("DELETE FROM $conversations WHERE owner_id=%d AND updated_at < %s LIMIT 1000", $uid, self::cutoff($uid)));
        $wpdb->query("DELETE m FROM $messages m LEFT JOIN $conversations c ON c.id=m.conversation_id WHERE c.id IS NULL");
    }
}
add_action('init', ['NB_Conversation_History', 'install']);
add_action('neonbreak_chat_cleanup', ['NB_Conversation_History', 'cleanup']);
add_action('deleted_user', function ($uid) {
    global $wpdb; [$conversations, $messages] = NB_Conversation_History::tables();
    if (get_option('nb_history_schema') !== '2') return;
    $wpdb->query($wpdb->prepare("DELETE m FROM $messages m INNER JOIN $conversations c ON c.id=m.conversation_id WHERE c.owner_id=%d", $uid));
    $wpdb->delete($conversations, ['owner_id' => $uid]);
});
