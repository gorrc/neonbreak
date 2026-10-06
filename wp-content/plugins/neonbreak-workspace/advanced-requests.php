<?php
defined('ABSPATH') || exit;
$request_page = max(1, absint($_GET['requests_page'] ?? 1));
$query = new WP_User_Query(['number' => 20, 'paged' => $request_page, 'orderby' => 'ID', 'order' => 'DESC', 'meta_query' => ['relation' => 'OR', ['key' => '_nb_advanced_quote', 'compare' => 'EXISTS'], ['key' => '_nb_advanced_enabled', 'value' => '1']]]);
?>
<section class="nba-card" style="margin-top:24px">
    <h2>Zahtjevi i pristup klijenata</h2>
    <p>Ponudu i račun dogovarate zasebno. Aktivacija omogućuje pristup, ali ne potvrđuje uplatu. Isključivanje pristupa ne briše dokumente.</p>
    <?php if (($_GET['activation_mail'] ?? '') === 'failed') : $mail_reason = get_transient('nb_activation_mail_error_' . get_current_user_id()); if ($mail_reason) : ?><div class="notice notice-error inline"><p><?php echo esc_html($mail_reason); ?></p><p><a href="<?php echo esc_url(admin_url('options-general.php?page=neonbreak-email')); ?>">Otvori Neonbreak Email postavke i SMTP test</a></p></div><?php endif; endif; ?>
    <?php if (isset($_GET['access_updated'])) : ?><div class="notice <?php echo in_array($_GET['activation_mail'] ?? '', ['failed', 'save_failed'], true) ? 'notice-warning' : 'notice-success'; ?> inline"><p><?php echo esc_html(($_GET['activation_mail'] ?? '') === 'save_failed' ? 'Spremanje pristupa nije uspjelo.' : (($_GET['activation_mail'] ?? '') === 'failed' ? 'Pristup je aktiviran, ali e-mail obavijest nije poslana. Obavijestite korisnika zasebno.' : (($_GET['activation_mail'] ?? '') === 'sent' ? 'Pristup je aktiviran i e-mail obavijest je poslana.' : 'Pristup korisnika je ažuriran.'))); ?></p></div><?php endif; ?>
    <?php if (!$query->get_results()) : ?><p>Još nema zahtjeva za Advanced Chat.</p><?php else : ?>
    <div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Korisnik</th><th>Zahtjev</th><th>Status</th><th>Pristup</th></tr></thead><tbody>
    <?php foreach ($query->get_results() as $client) :
        $request = get_user_meta($client->ID, '_nb_advanced_quote', true);
        $fields = is_array($request) ? ($request['fields'] ?? []) : [];
        $enabled = get_user_meta($client->ID, '_nb_advanced_enabled', true) === '1';
    ?>
    <tr><td><strong><?php echo esc_html($client->display_name); ?></strong><br><?php echo esc_html($client->user_email); ?><br>ID: <?php echo (int) $client->ID; ?></td>
    <td><?php if ($fields) : ?><details><summary><?php echo esc_html(wp_date('d.m.Y. H:i', $request['created'])); ?> · Pregledaj zahtjev</summary><?php foreach (['needs' => 'Potrebe', 'documents' => 'Dokumenti', 'usage' => 'Razgovori mjesečno', 'website' => 'Stranica'] as $field => $label) : ?><p><strong><?php echo esc_html($label); ?>:</strong> <?php echo nl2br(esc_html($fields[$field] ?? '')); ?></p><?php endforeach; ?></details><?php if (empty($request['notification_sent'])) : ?><p>E-mail obavijest nije poslana; zahtjev je sačuvan.</p><?php endif; ?><?php else : ?>Ručno aktiviran račun<?php endif; ?></td>
    <td><?php echo esc_html(user_can($client->ID, 'manage_options') ? 'Administrator · automatski pristup' : ($enabled ? 'Aktivan' : (($request['status'] ?? '') === 'pending' ? 'Čeka ponudu' : 'Isključen'))); ?></td>
    <td><?php if (!user_can($client->ID, 'manage_options')) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="nb_advanced_access"><input type="hidden" name="user" value="<?php echo (int) $client->ID; ?>"><input type="hidden" name="access" value="<?php echo $enabled ? 'disable' : 'enable'; ?>"><?php wp_nonce_field('nb_advanced_access_' . $client->ID); ?><button class="button <?php echo $enabled ? '' : 'button-primary'; ?>"><?php echo $enabled ? 'Isključi pristup' : 'Aktiviraj pristup'; ?></button></form><?php if ($enabled) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px"><input type="hidden" name="action" value="nb_advanced_access"><input type="hidden" name="user" value="<?php echo (int) $client->ID; ?>"><input type="hidden" name="access" value="resend"><?php wp_nonce_field('nb_advanced_access_' . $client->ID); ?><button class="button">Ponovno pošalji obavijest</button></form><?php endif; ?><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php echo paginate_links(['base' => add_query_arg('requests_page', '%#%'), 'current' => $request_page, 'total' => max(1, ceil($query->get_total() / 20))]); ?>
    <?php endif; ?>
</section>
