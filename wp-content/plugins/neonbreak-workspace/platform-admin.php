<?php
defined('ABSPATH') || exit;
/** Platform administration: aggregate metadata only, never conversation content. */
final class NB_Workspace_Admin {
    public static function allowed() { return current_user_can('manage_options'); }
    public static function url($tab='clients', $extra=[]) { return add_query_arg(array_merge(['page'=>'nb-workspace-admin','tab'=>$tab],$extra),admin_url('admin.php')); }
    public static function label($code) {
        $labels=['ok'=>'Uspješno','setup'=>'Potrebno povezivanje','access'=>'Pristup nije aktivan','rate'=>'Limit u minuti','quota'=>'Dnevni limit','provider'=>'Veza s AI / bazom znanja','storage'=>'Pohrana razgovora','mcp'=>'MCP povezivanje','input'=>'Neispravan zahtjev','unknown'=>'Obrada nije dovršena'];
        return $labels[$code]??$labels['unknown'];
    }
    public static function record($uid,$component,$code) {
        $uid=absint($uid);if(!$uid||!get_userdata($uid)||!in_array($component,['search','assistant'],true))return;
        $map=['ok'=>'ok','nb_search_input'=>'input','nb_search_access'=>'access','nb_search_setup'=>'setup','nb_search_rate'=>'rate','nb_search_quota'=>'quota','nb_search_unavailable'=>'provider','provider_unavailable'=>'provider','storage'=>'storage','nb_mcp_setup'=>'mcp','nb_mcp_protocol'=>'mcp','nb_mcp_failed'=>'mcp','nb_mcp_access'=>'access','nb_mcp_auth'=>'access','nb_history_setup'=>'setup','nb_history_input'=>'input','nb_history_rate'=>'rate','nb_history_storage'=>'storage','nb_assistant_routing'=>'provider'];
        $safe=$map[is_string($code)?$code:'']??'unknown';
        $health=get_user_meta($uid,'_nb_workspace_health',true);$health=is_array($health)?$health:[];
        $row=$health[$component]??[];$row=is_array($row)?$row:[];
        $row=['last_event'=>time(),'last_ok'=>absint($row['last_ok']??0),'last_error'=>absint($row['last_error']??0),'code'=>$safe,'last_error_code'=>in_array($row['last_error_code']??'', ['setup','access','rate','quota','provider','storage','mcp','input','unknown'],true)?$row['last_error_code']:'unknown','successes'=>absint($row['successes']??0),'failures'=>absint($row['failures']??0)];
        if($safe==='ok'){$row['last_ok']=time();$row['successes']++;}else{$row['last_error']=time();$row['last_error_code']=$safe;$row['failures']++;}
        $health[$component]=$row;update_user_meta($uid,'_nb_workspace_health',$health);
    }
    /** Counts and latest timestamps; SELECT never fetches message JSON, text, titles or sources. */
    public static function metrics(array $ids) {
        global $wpdb;$ids=array_values(array_filter(array_map('absint',$ids)));$out=[];foreach($ids as$id)$out[$id]=['widget_conversations'=>0,'assistant_conversations'=>0,'widget_failures'=>0,'last_activity'=>null];
        if(!$ids)return $out;
        $where=[];foreach($ids as$id)$where[]=$wpdb->prepare('(owner_id=%d AND updated_at >= %s)',$id,NB_Conversation_History::cutoff($id));
        if(get_option('nb_history_schema')==='2') {
            [$conversations,$messages]=NB_Conversation_History::tables();
            $rows=$wpdb->get_results("SELECT owner_id,COUNT(*) AS total,MAX(updated_at) AS latest FROM $conversations WHERE ".implode(' OR ',$where).' GROUP BY owner_id',ARRAY_A);
            foreach($rows as$row){$id=(int)$row['owner_id'];$out[$id]['widget_conversations']=(int)$row['total'];$out[$id]['last_activity']=$row['latest'];}
            $where=[];foreach($ids as$id)$where[]=$wpdb->prepare('(c.owner_id=%d AND c.updated_at >= %s)',$id,NB_Conversation_History::cutoff($id));
            $rows=$wpdb->get_results("SELECT c.owner_id,COUNT(*) AS total FROM $messages m INNER JOIN $conversations c ON c.id=m.conversation_id WHERE m.status='failed' AND (".implode(' OR ',$where).') GROUP BY c.owner_id',ARRAY_A);
            foreach($rows as$row)$out[(int)$row['owner_id']]['widget_failures']=(int)$row['total'];
        }
        if(get_option('nb_assistant_schema')==='1') {
            $table=NB_Business_Assistant::table();$placeholders=implode(',',array_fill(0,count($ids),'%d'));
            $rows=$wpdb->get_results($wpdb->prepare("SELECT owner_id,COUNT(*) AS total,MAX(updated_at) AS latest FROM $table WHERE owner_id IN ($placeholders) AND updated_at >= %s GROUP BY owner_id",array_merge($ids,[gmdate('Y-m-d H:i:s',time()-90*DAY_IN_SECONDS)])),ARRAY_A);
            foreach($rows as$row){$id=(int)$row['owner_id'];$out[$id]['assistant_conversations']=(int)$row['total'];if(!$out[$id]['last_activity']||$row['latest']>$out[$id]['last_activity'])$out[$id]['last_activity']=$row['latest'];}
        }
        return $out;
    }
    public static function clients($search='',$scope='workspace',$page=1) {
        if(!self::allowed())return new WP_Error('forbidden','Forbidden.',['status'=>403]);
        $args=['number'=>20,'paged'=>max(1,(int)$page),'orderby'=>'ID','order'=>'DESC','fields'=>['ID','display_name','user_email','user_registered']];
        if($search!==''){$args['search']='*'.str_replace(['*','%'], '',mb_substr($search,0,100)).'*';$args['search_columns']=['user_login','user_email','display_name'];}
        // One correlated EXISTS avoids a Cartesian product across seven usermeta joins.
        global $wpdb;
        $query=new WP_User_Query();$query->prepare_query($args);
        if($scope!=='all') {
            $keys=['_nb_workspace_member','_nb_chat_presentation','_nb_chat_public','_nb_chat_key','_nb_advanced_quote','_nb_advanced_enabled','_nb_workspace_health'];
            $marks=implode(',',array_fill(0,count($keys),'%s'));
            $query->query_where.=$wpdb->prepare(" AND EXISTS (SELECT 1 FROM {$wpdb->usermeta} nwa_member WHERE nwa_member.user_id={$wpdb->users}.ID AND nwa_member.meta_key IN ($marks))",$keys);
        }
        $query->query();$users=$query->get_results();$ids=array_map(fn($u)=>(int)$u->ID,$users);update_meta_cache('user',$ids);$metrics=self::metrics($ids);
        global $wpdb;$names=[];foreach($ids as$id){$names[]='nb_chat_day_'.gmdate('Ymd').'_'.md5('client:'.$id);$names[]=NB_AI_Search::counter_name($id);}
        $counts=[];if($names){$marks=implode(',',array_fill(0,count($names),'%s'));foreach($wpdb->get_results($wpdb->prepare("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name IN ($marks)",$names),ARRAY_A)as$row)$counts[$row['option_name']]=max(0,(int)$row['option_value']);}
        $rows=[];foreach($users as$user){$id=(int)$user->ID;$presentation=get_user_meta($id,'_nb_chat_presentation',true);$presentation=is_array($presentation)?$presentation:[];$docs=get_user_meta($id,'_nb_advanced_docs',true);$quote=get_user_meta($id,'_nb_advanced_quote',true);
            $health=get_user_meta($id,'_nb_workspace_health',true);$safe_health=[];
            if(is_array($health))foreach(['search','assistant']as$component){$entry=$health[$component]??null;if(is_array($entry)&&absint($entry['last_event']??0)>=time()-90*DAY_IN_SECONDS)$safe_health[$component]=['code'=>in_array($entry['code']??'', ['ok','setup','access','rate','quota','provider','storage','mcp','input','unknown'],true)?$entry['code']:'unknown','last_event'=>absint($entry['last_event']),'last_ok'=>absint($entry['last_ok']??0),'last_error'=>absint($entry['last_error']??0),'last_error_code'=>in_array($entry['last_error_code']??'', ['setup','access','rate','quota','provider','storage','mcp','input','unknown'],true)?$entry['last_error_code']:'unknown','successes'=>absint($entry['successes']??0),'failures'=>absint($entry['failures']??0)];}
            $rows[]=array_merge(['id'=>$id,'name'=>$user->display_name,'email'=>$user->user_email,'widget_enabled'=>!empty($presentation['enabled']),'advanced'=>NB_Advanced_Chat::allowed($id),'administrator'=>user_can($id,'manage_options'),'documents'=>is_array($docs)?count($docs):0,'key_saved'=>metadata_exists('user',$id,'_nb_chat_key') && (bool)get_user_meta($id,'_nb_chat_key',true),'widget_today'=>$counts['nb_chat_day_'.gmdate('Ymd').'_'.md5('client:'.$id)]??0,'widget_limit'=>max(1,(int)($presentation['daily_limit']??100)),'limits'=>NB_Workspace_Limits::get($id),'search_today'=>$counts[NB_AI_Search::counter_name($id)]??0,'pending'=>is_array($quote)&&($quote['status']??'')==='pending','health'=>$safe_health],$metrics[$id]);
        }
        return ['rows'=>$rows,'total'=>(int)$query->get_total()];
    }
    public static function date($value) { if(!$value)return '—';return wp_date('d.m.Y. H:i',is_numeric($value)?(int)$value:strtotime($value.' UTC')); }
    public static function limits_form(array $row) {
        echo '<details class="nwa-limits"><summary>Uredi limite</summary><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="nb_workspace_limits"><input type="hidden" name="user" value="'.$row['id'].'">';
        wp_nonce_field('nb_workspace_limits_'.$row['id']);
        $return_search=is_string($_GET['s']??null)?mb_substr(sanitize_text_field(wp_unslash($_GET['s'])),0,100):'';echo '<input type="hidden" name="return_s" value="'.esc_attr($return_search).'"><input type="hidden" name="return_scope" value="'.(($_GET['scope']??'')==='all'?'all':'workspace').'"><input type="hidden" name="return_page" value="'.max(1,absint($_GET['nwa_page']??1)).'">';
        $labels=['search_daily'=>'AI Search / dan','search_minute'=>'AI Search / minuta','documents'=>'Broj dokumenata'];
        foreach(NB_Workspace_Limits::ranges()as$key=>$range)echo '<label>'.esc_html($labels[$key]).'<input type="number" required name="'.esc_attr($key).'" min="'.$range[0].'" max="'.$range[1].'" value="'.(int)$row['limits'][$key].'"></label>';
        echo '<button class="button button-primary">Spremi limite</button><small>Smanjenje ne briše postojeće dokumente niti resetira potrošnju. Novi dokumenti čekaju slobodno mjesto.</small></form></details>';
    }
    public static function access_form(array $row) {
        if($row['administrator']){echo '<span class="nwa-muted">Automatski administratorski pristup</span>';return;}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="nb_advanced_access"><input type="hidden" name="user" value="'.$row['id'].'"><input type="hidden" name="access" value="'.($row['advanced']?'disable':'enable').'"><input type="hidden" name="return_to_workspace" value="1">';wp_nonce_field('nb_advanced_access_'.$row['id']);echo '<button class="button '.($row['advanced']?'':'button-primary').'">'.($row['advanced']?'Isključi Advanced pristup':'Aktiviraj Advanced pristup').'</button></form>';
    }
    public static function render() {
        if(!self::allowed())wp_die('Forbidden.','',['response'=>403]);
        nocache_headers();$tabs=['clients'=>'Klijenti','services'=>'Usluge i alati','usage'=>'Potrošnja','health'=>'Tehnički status','requests'=>'Zahtjevi za ponudu','support'=>'Podrška i privatnost'];$tab=is_string($_GET['tab']??null)&&isset($tabs[$_GET['tab']])?$_GET['tab']:'clients';
        echo '<div class="wrap nwa"><header class="nwa-header"><div><h1>Neonbreak AI · Administracija</h1><p>Pristupi, korištenje i tehnički status. Sadržaj korisničkih razgovora ostaje u njihovu Workspaceu.</p></div><a class="button" href="'.esc_url(home_url('/workspace/')).'">Otvori moj Workspace ↗</a></header><nav class="nav-tab-wrapper">';foreach($tabs as$key=>$label)echo '<a class="nav-tab '.($tab===$key?'nav-tab-active':'').'" href="'.esc_url(self::url($key)).'">'.esc_html($label).'</a>';echo '</nav>';
        if($tab==='requests'){require __DIR__.'/advanced-requests.php';echo '</div>';return;}
        if($tab==='support'){echo '<section class="nwa-card"><h2>Razgovori su privatni prema zadanim postavkama</h2><p>Ovaj pregled ne prikazuje pitanja, odgovore, naslove privatnih razgovora, izvore dokumenata ni API ključeve. Administrator vidi samo račune, brojeve, prava pristupa i tehničke kategorije grešaka.</p><p>Podrška zasad koristi zahtjeve za ponudu i kontakt. Korisnik po potrebi sam može izvesti i podijeliti odabrani razgovor. Gumb za dijeljenje razgovora s podrškom i evidencija takvog pristupa nisu još implementirani.</p><p>Podaci su pohranjeni na našem poslužitelju. Ograničen pristup kroz sučelje nije isto što i šifriranje sadržaja dostupno samo korisniku.</p><a class="button" href="'.esc_url(self::url('requests')).'">Zahtjevi za ponudu</a></section></div>';return;}
        if($tab==='health')self::system_status();
        if(isset($_GET['access_updated']))echo '<div class="notice notice-info inline"><p>Pristup je obrađen. Status obavijesti: '.esc_html(in_array($_GET['activation_mail']??'', ['sent','failed','save_failed','unchanged','disabled'],true)?$_GET['activation_mail']:'provjerite zahtjeve za ponudu').'.</p></div>';
        if(isset($_GET['limits_saved']))echo '<div class="notice '.(($_GET['limits_saved']??'')==='1'?'notice-success':'notice-error').' inline"><p>'.(($_GET['limits_saved']??'')==='1'?'Limiti su spremljeni.':'Limiti nisu spremljeni. Provjerite unesene raspone.').'</p></div>';
        $search=is_string($_GET['s']??null)?mb_substr(sanitize_text_field(wp_unslash($_GET['s'])),0,100):'';$scope=($_GET['scope']??'')==='all'?'all':'workspace';$page=max(1,absint($_GET['nwa_page']??1));$data=self::clients($search,$scope,$page);
        echo '<form method="get" class="nwa-toolbar"><input type="hidden" name="page" value="nb-workspace-admin"><input type="hidden" name="tab" value="'.esc_attr($tab).'"><label><span class="screen-reader-text">Ime ili e-mail</span><input name="s" value="'.esc_attr($search).'" placeholder="Ime ili e-mail"></label><select name="scope" aria-label="Skup računa"><option value="workspace" '.selected($scope,'workspace',false).'>Workspace računi</option><option value="all" '.selected($scope,'all',false).'>Svi WordPress računi</option></select><button class="button">Pretraži</button><span class="nwa-muted">'.(int)$data['total'].' računa · 20 po stranici</span></form>';
        $columns=['clients'=>['Korisnik','Pristup','Dokumenti','Razgovori widgeta','Privatni razgovori asistenta','Zadnja spremljena aktivnost'],'services'=>['Korisnik','AI Chat widget','Advanced / Search / Assistant','Fireworks','Upravljanje'],'usage'=>['Korisnik','Widget danas','AI Search danas','Dokumenti','Povijest','Upravljanje limitima'],'health'=>['Korisnik','Fireworks','AI Search / Asistent','Neuspjele obrade widgeta']];
        echo '<div class="nwa-table-wrap"><table class="widefat striped"><thead><tr>';foreach($columns[$tab]as$column=>$label){$owner=$tab==='usage'&&in_array($column,[1,2,3],true)?($column===1?'korisnik':'administrator'):'';echo '<th>'.esc_html($label).($owner?'<small class="nwa-limit-owner">Limit postavlja '.$owner.'</small>':'').'</th>';}echo '</tr></thead><tbody>';
        foreach($data['rows']as$row){echo '<tr id="nwa-user-'.(int)$row['id'].'"><td><strong>'.esc_html($row['name']).'</strong><span class="nwa-email">'.esc_html($row['email']).'</span><small>ID: '.$row['id'].'</small></td>';
            if($tab==='clients')echo '<td>'.($row['advanced']?'<span class="nwa-tag">Advanced aktivan</span>':'Besplatan').($row['pending']?'<small>Zahtjev čeka obradu</small>':'').'</td><td>'.$row['documents'].'</td><td>'.$row['widget_conversations'].'</td><td>'.$row['assistant_conversations'].'</td><td>'.esc_html(self::date($row['last_activity'])).'</td>';
            elseif($tab==='services'){echo '<td>'.($row['widget_enabled']?'Uključen':'Nije uključen').'</td><td>'.($row['advanced']?'<span class="nwa-tag">Aktivno</span>':'Nije aktivirano').'<small>Trenutačno zajednički Advanced pristup</small></td><td>'.($row['key_saved']?'Ključ spremljen':'Nije povezan').'</td><td>';self::access_form($row);echo '</td>';}
            elseif($tab==='usage'){echo '<td>'.$row['widget_today'].' / '.$row['widget_limit'].'</td><td>'.$row['search_today'].' / '.$row['limits']['search_daily'].'<small>'.$row['limits']['search_minute'].' zahtjeva / min</small></td><td>'.$row['documents'].' / '.$row['limits']['documents'].'</td><td>Widget: '.$row['widget_conversations'].'<br>Asistent: '.$row['assistant_conversations'].'</td><td>';self::limits_form($row);echo '</td>'; }
            else{echo '<td>'.($row['key_saved']?'Ključ spremljen':'Potrebno povezivanje').'</td><td>';if(!$row['health'])echo '<span class="nwa-muted">Još nema tehničkih događaja</span>';foreach($row['health']as$component=>$health)echo '<div class="nwa-health"><strong>'.($component==='search'?'AI Search':'Asistent').': '.esc_html(self::label($health['code'])).'</strong><small>Zadnji događaj: '.esc_html(self::date($health['last_event'])).' · Uspješno: '.$health['successes'].' · Neuspjelo: '.$health['failures'].'</small>'.($health['last_error']?'<small>Zadnja greška: '.esc_html(self::label($health['last_error_code'])).' · '.esc_html(self::date($health['last_error'])).'</small>':'').'</div>';echo '</td><td>'.$row['widget_failures'].'<small>Samo zapisi unutar roka čuvanja</small></td>';}
            echo '</tr>';
        }
        if(!$data['rows'])echo '<tr><td colspan="'.count($columns[$tab]).'">Nema pronađenih računa. Za nove račune bez spremljenih postavki odaberite sve WordPress račune.</td></tr>';
        echo '</tbody></table></div><div class="nwa-pagination">'.wp_kses_post(paginate_links(['base'=>self::url($tab,['s'=>$search,'scope'=>$scope,'nwa_page'=>'%#%']),'current'=>$page,'total'=>max(1,(int)ceil($data['total']/20))])).'</div>';
        echo '<p class="nwa-muted">Brojači zahtjeva nisu izvještaj o tokenima ni iznosu računa; uključuju i prihvaćene neuspjele pozive. Dnevni brojači resetiraju se u 00:00 UTC. AI Search limit dijele API, test pretrage i Business Assistant; widget ima zaseban brojač. Spremljen ključ ne potvrđuje saldo ni ispravnost veze. Tehnički događaji bilježe se od uvođenja ovog pregleda, bez sadržaja poruka.</p></div>';
    }
    public static function system_status() {
        $checks=['PHP'=>PHP_VERSION,'MCP'=>class_exists('NB_MCP')&&NB_MCP::ready()?'Spreman':'Nije aktivan / nedostaje povezivanje','Pohrana widgeta'=>get_option('nb_history_schema')==='2'?'Spremna':'Nije spremna','Pohrana asistenta'=>get_option('nb_assistant_schema')==='1'?'Spremna':'Nije spremna','Qdrant postavke'=>get_option('nb_qdrant_url')&&get_option('nb_qdrant_key')?'Spremljene':'Nedostaju'];
        echo '<section class="nwa-system">';foreach($checks as$label=>$value)echo '<div><span>'.esc_html($label).'</span><strong>'.esc_html($value).'</strong></div>';echo '</section><p class="nwa-muted">Provjera konfiguracije, bez vanjskih poziva i otkrivanja ključeva. Za povezivanje i SMTP test koristite postojeće postavke.</p><p><a class="button" href="'.esc_url(admin_url('options-general.php?page=nb-advanced')).'">Advanced / Qdrant</a> <a class="button" href="'.esc_url(admin_url('options-general.php?page=neonbreak-email')).'">SMTP postavke</a></p>';
    }
}
add_action('nb_workspace_operation_result',['NB_Workspace_Admin','record'],10,3);
add_action('admin_menu',function(){add_menu_page('Neonbreak AI','Neonbreak AI','manage_options','nb-workspace-admin',['NB_Workspace_Admin','render'],'dashicons-chart-area',59);});
add_action('admin_enqueue_scripts',function($hook){if($hook==='toplevel_page_nb-workspace-admin'){wp_enqueue_style('nb-workspace-admin',plugins_url('platform-admin.css',__FILE__),[],'0.1.0');wp_enqueue_style('nb-workspace-admin-requests',plugins_url('advanced-admin.css',__FILE__),[],'0.1.0');}});
