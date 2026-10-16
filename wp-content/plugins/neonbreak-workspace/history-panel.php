<?php
defined('ABSPATH') || exit;
add_action('admin_post_nb_history_manage', function () {
    if (!is_user_logged_in()) wp_die('Sign in first.', '', ['response' => 403]);
    check_admin_referer('nb_history_manage');
    $uid = get_current_user_id(); $action = $_POST['history_action'] ?? '';
    $id = absint($_POST['conversation'] ?? 0);
    if ($action === 'settings') {
        $days = absint($_POST['days'] ?? 30);
        update_user_meta($uid, '_nb_chat_history', ['enabled' => empty($_POST['enabled']) ? 0 : 1, 'days' => in_array($days, [7,30,90], true) ? $days : 30]);
        NB_Conversation_History::cleanup($uid);
    } elseif ($action === 'delete') {
        if (!NB_Conversation_History::delete($uid, $id)) wp_die('Conversation not found.', '', ['response' => 404]);
    } elseif ($action === 'export') {
        $data = NB_Conversation_History::export_data($uid, $id);
        if (!$data) wp_die('Conversation not found.', '', ['response' => 404]);
        nocache_headers(); header('Content-Type: application/json; charset=UTF-8'); header('X-Content-Type-Options: nosniff'); header('Content-Disposition: attachment; filename="conversation-' . $id . '.json"');
        echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
    } else wp_die('Invalid action.', '', ['response' => 400]);
    $fallback = home_url('/workspace/');
    $back = isset($_POST['return_url']) && is_string($_POST['return_url']) ? wp_validate_redirect(esc_url_raw(wp_unslash($_POST['return_url'])), $fallback) : $fallback;
    wp_safe_redirect(add_query_arg(['view' => 'history', 'history_saved' => '1'], remove_query_arg('conversation', $back))); exit;
});
function nb_history_form_fields($action, $id = 0) {
    echo '<input type="hidden" name="action" value="nb_history_manage"><input type="hidden" name="history_action" value="' . esc_attr($action) . '"><input type="hidden" name="conversation" value="' . (int) $id . '"><input type="hidden" name="return_url" value="' . esc_url(get_permalink(get_queried_object_id())) . '">';
    wp_nonce_field('nb_history_manage');
}
function nb_history_render($base_url = null) {
    if (!is_user_logged_in()) return;
    $t = function ($en, $hr) { return nb_advanced_language() === 'hr' ? $hr : $en; };
    $uid = get_current_user_id(); $settings = NB_Conversation_History::settings($uid);
    if (get_option('nb_history_schema') !== '2') { echo '<p>' . esc_html($t('Conversation storage could not be created. Check database permissions.', 'Pohranu razgovora nije moguće kreirati. Provjerite dozvole baze podataka.')) . '</p>'; return; }
    $date = function ($value) { return wp_date('d.m.Y. H:i', strtotime($value . ' UTC')); };
    $base_url=$base_url ?: get_permalink(get_queried_object_id());
    $url = add_query_arg('view', 'history', $base_url);
    echo '<h1>' . esc_html($t('Conversation history', 'Povijest razgovora')) . '</h1><p>' . esc_html($t('Review conversations from your own website widget. Recording is optional and starts only after you enable it. This does not import old browser sessions or add messages to your knowledge base.', 'Pregledajte razgovore iz svojeg widgeta. Spremanje je opcionalno i počinje tek nakon uključivanja. Ne uvozi stare sesije preglednika niti dodaje poruke u bazu znanja.')) . '</p>';
    if (isset($_GET['history_saved'])) echo '<p class="ws-free" role="status">' . esc_html($t('Changes saved.', 'Promjene su spremljene.')) . '</p>';
    ?>
    <section class="ws-card ws-history-settings"><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php nb_history_form_fields('settings'); ?>
        <div class="ws-history-settings-row"><label class="ws-history-switch"><input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled']); ?>> <?php echo esc_html($t('Save conversations', 'Spremanje razgovora')); ?></label>
        <label class="ws-history-retention"><?php echo esc_html($t('Retention', 'Čuvanje')); ?><select name="days"><?php foreach ([7,30,90] as $days) : ?><option value="<?php echo $days; ?>" <?php selected($settings['days'], $days); ?>><?php echo $days . ' ' . esc_html($t('days', 'dana')); ?></option><?php endforeach; ?></select></label>
        <button><?php echo esc_html($t('Save settings', 'Spremi postavke')); ?></button></div>
        <details class="ws-history-help"><summary><?php echo esc_html($t('Recording and retention details', 'Detalji spremanja i čuvanja')); ?></summary><p><?php echo esc_html($t('Retention starts from last activity. Update your privacy information before enabling. Disabling stops new recording; existing history remains until deleted or expired. Reducing retention can permanently delete older conversations. Refresh widgets after changes.', 'Rok počinje od zadnje aktivnosti. Prije uključivanja ažurirajte informacije o privatnosti. Isključivanje zaustavlja novo spremanje; postojeća povijest ostaje do brisanja ili isteka. Smanjenje roka može trajno obrisati starije razgovore. Nakon izmjena osvježite widgete.')); ?></p></details>
    </form></section>
    <?php
    $id = absint($_GET['conversation'] ?? 0);
    if ($id) {
        $conversation = NB_Conversation_History::owned($uid, $id);
        if (!$conversation) { echo '<p>' . esc_html($t('Conversation not found.', 'Razgovor nije pronađen.')) . '</p>'; return; }
        echo '<section class="ws-card ws-history-section"><a href="' . esc_url($url) . '">← ' . esc_html($t('All conversations', 'Svi razgovori')) . '</a><h2>' . esc_html($t('Conversation', 'Razgovor')) . ' #' . $id . '</h2><p>' . esc_html($date($conversation['created_at']) . ' · ' . $conversation['origin']) . '</p>';
        echo '<div class="ws-history-actions">';
        foreach (['export' => $t('Export JSON', 'Izvezi JSON'), 'delete' => $t('Delete conversation', 'Izbriši razgovor')] as $action => $label) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"' . ($action === 'delete' ? ' data-history-delete' : '') . '>'; nb_history_form_fields($action, $id); echo '<button>' . esc_html($label) . '</button></form>';
        }
        echo '</div><div class="ws-history-messages">';
        foreach (NB_Conversation_History::messages($id) as $message) {
            echo '<article class="ws-history-message ' . ($message['role'] === 'assistant' ? 'is-assistant' : '') . '"><strong>' . esc_html($message['role'] === 'assistant' ? $t('Assistant', 'Asistent') : $t('Visitor', 'Posjetitelj')) . '</strong><small>' . esc_html($date($message['created_at'])) . '</small><p>' . nl2br(esc_html($message['content'])) . '</p>';
            if ($message['status'] !== 'complete') echo '<span>' . esc_html($message['status'] === 'failed' ? $t('Answer failed', 'Odgovor nije uspio') : $t('Pending or interrupted', 'U obradi ili prekinuto')) . '</span>';
            if ($message['sources']) { echo '<details><summary>' . esc_html($t('Retrieved sources', 'Dohvaćeni izvori')) . '</summary>'; foreach ($message['sources'] as $source) echo '<h4>' . esc_html($source['title'] ?? '') . '</h4><p>' . nl2br(esc_html($source['text'] ?? '')) . '</p>'; echo '</details>'; }
            echo '</article>';
        }
        echo '</div></section>';
    } else {
        global $wpdb; [$conversations, $messages] = NB_Conversation_History::tables();
        $page = max(1, absint($_GET['history_page'] ?? 1)); $filter = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(sanitize_text_field(wp_unslash($_GET['q'])), 0, 200) : '';
        $period=is_string($_GET['period']??null)&&in_array($_GET['period'],['7','30','90'],true)?$_GET['period']:'';
        $role=is_string($_GET['role']??null)&&in_array($_GET['role'],['user','assistant'],true)?$_GET['role']:'';
        $cutoff=NB_Conversation_History::cutoff($uid);
        if($period!=='')$cutoff=max($cutoff,gmdate('Y-m-d H:i:s',time()-(int)$period*DAY_IN_SECONDS));
        $sort_columns = ['id' => 'c.id', 'created' => 'c.created_at', 'updated' => 'c.updated_at', 'messages' => 'message_count'];
        $sort = isset($_GET['sort']) && is_string($_GET['sort']) && isset($sort_columns[$_GET['sort']]) ? $_GET['sort'] : 'updated';
        $direction = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';
        $order_sql = $sort_columns[$sort] . ' ' . strtoupper($direction) . ', c.id ' . strtoupper($direction);
        $url = add_query_arg(['sort' => $sort, 'order' => $direction, 'q' => $filter, 'period'=>$period, 'role'=>$role], $url);
        $where = $wpdb->prepare('c.owner_id=%d AND c.updated_at >= %s', $uid, $cutoff);
        if ($filter!=='' || $role!=='' || $period!=='') {
            $mw=$wpdb->prepare('m.conversation_id=c.id AND m.created_at >= %s',$cutoff);
            if($filter!=='')$mw.=$wpdb->prepare(' AND m.content LIKE %s','%'.$wpdb->esc_like($filter).'%');
            if($role!=='')$mw.=$wpdb->prepare(' AND m.role=%s',$role);
            $where.=" AND EXISTS (SELECT 1 FROM $messages m WHERE $mw)";
        }
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $conversations c WHERE $where");
        $page=min($page,max(1,(int)ceil($total/20)));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT c.*, (SELECT COUNT(*) FROM $messages m WHERE m.conversation_id=c.id) AS message_count FROM $conversations c WHERE $where ORDER BY $order_sql LIMIT 20 OFFSET %d", ($page - 1) * 20), ARRAY_A);
        echo '<section class="ws-card ws-history-section ws-history-list"><h2>' . esc_html($t('Your conversations', 'Vaši razgovori')) . '</h2><form method="get" class="ws-history-filter"><input type="hidden" name="view" value="history"><input type="hidden" name="sort" value="' . esc_attr($sort) . '"><input type="hidden" name="order" value="' . esc_attr($direction) . '"><label>' . esc_html($t('Find message text', 'Pretraži tekst poruka')) . '<input name="q" value="' . esc_attr($filter) . '" maxlength="200"></label><label>' . esc_html($t('Period','Razdoblje')) . '<select name="period"><option value="">' . esc_html($t('All retained history','Sva sačuvana povijest')) . '</option>';
        foreach([7,30,90] as $days)echo '<option value="'.$days.'" '.selected($period,(string)$days,false).'>'.esc_html($t('Last','Zadnjih').' '.$days.' '.$t('days','dana')).'</option>';
        echo '</select></label><label>'.esc_html($t('Message author','Autor poruke')).'<select name="role">';
        foreach([''=>$t('All messages','Sve poruke'),'user'=>$t('Visitor','Posjetitelj'),'assistant'=>$t('Assistant','Asistent')] as $value=>$label)echo '<option value="'.esc_attr($value).'" '.selected($role,$value,false).'>'.esc_html($label).'</option>';
        echo '</select></label><div class="ws-history-filter-actions"><button>'.esc_html($t('Search','Pretraži')).'</button><a href="'.esc_url(remove_query_arg(['q','period','role','history_page'],$url)).'">'.esc_html($t('Reset','Poništi')).'</a></div></form><p class="ws-history-result-count" role="status">'.esc_html($t('Conversations found: ','Pronađeno razgovora: ').$total.' · '.$t('Search covers all retained records.','Pretraga obuhvaća sve sačuvane zapise.')).'</p>';
        echo '<div class="ws-history-table-wrap"><table class="ws-history-table"><thead><tr>';
        foreach (['id' => $t('Conversation', 'Razgovor'), 'created' => $t('Created', 'Kreiran'), 'updated' => $t('Last activity', 'Zadnja aktivnost'), 'messages' => $t('Messages', 'Poruke')] as $key => $heading) {
            $active = $sort === $key;
            $next = $active && $direction === 'desc' ? 'asc' : 'desc';
            $link = add_query_arg(['sort' => $key, 'order' => $next], remove_query_arg('history_page', $url));
            echo '<th scope="col"' . ($active ? ' aria-sort="' . ($direction === 'asc' ? 'ascending' : 'descending') . '"' : '') . '><a href="' . esc_url($link) . '">' . esc_html($heading) . ' <span aria-hidden="true">' . ($active ? ($direction === 'asc' ? '↑' : '↓') : '↕') . '</span></a></th>';
        }
        foreach ([$t('Status', 'Status'), $t('Actions', 'Akcije')] as $heading) echo '<th scope="col">' . esc_html($heading) . '</th>';
        echo '</tr></thead><tbody>';
        if (!$rows) echo '<tr><td colspan="6" class="ws-history-empty">' . esc_html($t('No saved conversations found.', 'Nema pronađenih spremljenih razgovora.')) . '</td></tr>';
        foreach ($rows as $row) {
            $inactive = strtotime($row['updated_at'] . ' UTC') < time() - 30 * MINUTE_IN_SECONDS;
            $detail_url = add_query_arg('conversation', (int) $row['id'], $url);
            echo '<tr><td><a href="' . esc_url($detail_url) . '">#' . (int) $row['id'] . '</a><small>' . esc_html(wp_parse_url($row['origin'], PHP_URL_HOST) ?: $row['origin']) . '</small></td><td>' . esc_html($date($row['created_at'])) . '</td><td>' . esc_html($date($row['updated_at'])) . '</td><td>' . (int) $row['message_count'] . '</td><td><span class="ws-history-status ' . ($inactive ? '' : 'is-recent') . '">' . esc_html($inactive ? $t('Inactive', 'Neaktivan') : $t('Recent', 'Nedavno')) . '</span></td><td><div class="ws-history-table-actions"><a href="' . esc_url($detail_url) . '">' . esc_html($t('View', 'Pregledaj')) . '</a>';
            foreach (['export' => 'JSON ↓', 'delete' => $t('Delete', 'Izbriši')] as $action => $label) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"' . ($action === 'delete' ? ' data-history-delete' : '') . '>'; nb_history_form_fields($action, (int) $row['id']); echo '<button>' . esc_html($label) . '</button></form>';
            }
            echo '</div></td></tr>';
        }
        echo '</tbody></table></div>';
        echo wp_kses_post(paginate_links(['base' => add_query_arg(['history_page' => '%#%', 'q' => $filter, 'period'=>$period, 'role'=>$role], $url), 'current' => $page, 'total' => max(1, (int) ceil($total / 20))]));
        echo '<p>' . esc_html($t('Up to 1,000 stored conversations per account and 500 messages per conversation. Expired history is hidden immediately and removed by scheduled cleanup. Exported files and external backups have their own retention.', 'Do 1.000 spremljenih razgovora po računu i 500 poruka po razgovoru. Istekla povijest odmah se skriva i briše zakazanim čišćenjem. Izvezene datoteke i vanjske sigurnosne kopije imaju zasebne rokove čuvanja.')) . '</p></section>';
    }
    wp_enqueue_script('nb-history-panel', plugins_url('history-panel.js', __FILE__), [], '2.0', true);
    wp_localize_script('nb-history-panel', 'NBHistoryPanel', ['endpoint'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('nb_history_ajax'),'language'=>nb_advanced_language(),'confirm' => $t('Permanently delete this conversation?', 'Trajno izbrisati ovaj razgovor?')]);
}

/** Authenticated fragments reuse the same owner-scoped rendering and queries as the full page. */
function nb_history_fragment($uid, $operation, array $params) {
    if(!$uid || $uid!==get_current_user_id())return new WP_Error('access','Sign in first.',['status'=>403]);
    if(!in_array($operation,['list','detail','delete'],true))return new WP_Error('operation','Invalid action.',['status'=>400]);
    $id=isset($params['conversation'])&&is_scalar($params['conversation'])?absint($params['conversation']):0;
    if($operation!=='list' && (!$id || !NB_Conversation_History::owned($uid,$id)))return new WP_Error('missing','Conversation not found.',['status'=>404]);
    if($operation==='delete')return NB_Conversation_History::delete($uid,$id)?['deleted'=>true]:new WP_Error('storage','Unable to delete conversation.',['status'=>503]);
    $saved=$_GET;$_GET=[];
    foreach(['q','period','role','sort','order','history_page'] as $key)if(isset($params[$key])&&is_string($params[$key]))$_GET[$key]=$params[$key];
    if($operation==='detail')$_GET['conversation']=$id;
    ob_start();
    try{nb_history_render(home_url(nb_advanced_language()==='hr'?'/hr/workspace/':'/workspace/'));$html=ob_get_contents();}
    finally{ob_end_clean();$_GET=$saved;}
    return ['html'=>$html];
}
add_action('wp_ajax_nb_history_fragment',function(){
    check_ajax_referer('nb_history_ajax');nocache_headers();
    $params=[];foreach(['q','period','role','sort','order','history_page','conversation'] as $key)if(isset($_POST[$key])&&is_string($_POST[$key]))$params[$key]=wp_unslash($_POST[$key]);
    $operation=is_string($_POST['operation']??null)?$_POST['operation']:'';
    $result=nb_history_fragment(get_current_user_id(),$operation,$params);
    if(is_wp_error($result))wp_send_json_error(['message'=>$result->get_error_message()],$result->get_error_data()['status']??400);
    wp_send_json_success($result);
});
