<?php
defined('ABSPATH') || exit;
function nb_advanced_quote_form() {
    global $TRP_LANGUAGE;
    $hr = $TRP_LANGUAGE === 'hr';
    $t = function ($en, $cro) use ($hr) { return $hr ? $cro : $en; };
    $request = get_user_meta(get_current_user_id(), '_nb_advanced_quote', true);
    if (is_array($request) && ($request['status'] ?? '') === 'pending') {
        echo '<section class="ws-card"><h2>' . esc_html($t('Request received', 'Zahtjev zaprimljen')) . '</h2><p>' . esc_html($t('Your Advanced Chat request is awaiting review. We will reply to your account email with a quote. Access is activated after agreement and payment by invoice.', 'Vaš zahtjev za Advanced Chat čeka pregled. Ponudu ćemo poslati na e-mail vašeg računa. Pristup aktiviramo nakon dogovora i plaćanja po računu.')) . '</p></section>';
        return;
    }
    ?>
    <section class="ws-card ws-quote">
        <span class="ws-quote-tag"><?php echo esc_html($t('TAILORED TO YOUR BUSINESS', 'PREMA POTREBAMA VAŠEG POSLOVANJA')); ?></span>
        <h2><?php echo esc_html($t('Request a quote', 'Zatražite ponudu')); ?></h2>
        <p><?php echo esc_html($t('Basic AI Chat is free. Advanced Chat is priced according to your needs; Fireworks usage is billed separately. We activate access after you accept the quote.', 'Osnovni AI Chat je besplatan. Advanced Chat naplaćuje se prema vašim potrebama, a Fireworks potrošnja zasebno. Pristup aktiviramo nakon prihvaćanja ponude.')); ?></p>
        <?php if (isset($_GET['quote_status'])) : ?><p role="status"><?php echo esc_html($_GET['quote_status'] === 'sent' ? $t('Request sent. We will reply to your account email.', 'Zahtjev je poslan. Odgovorit ćemo na e-mail vašeg računa.') : $t('The request could not be sent. Please try again later.', 'Zahtjev nije moguće poslati. Pokušajte ponovno kasnije.')); ?></p><?php endif; ?>
        <div class="ws-quote-layout"><form class="ws-quote-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="nb_advanced_quote">
            <input type="hidden" name="language" value="<?php echo $hr ? 'hr' : 'en'; ?>">
            <?php wp_nonce_field('nb_advanced_quote'); ?>
            <input type="hidden" name="return_url" value="<?php echo esc_url(add_query_arg('view', 'advanced', get_permalink(get_queried_object_id()))); ?>">
            <label class="ws-quote-wide"><?php echo esc_html($t('What should your chat know and do?', 'Što želite da chat zna i radi?')); ?><textarea name="needs" required maxlength="3000" rows="5" placeholder="<?php echo esc_attr($t('For example: answer customer questions using our product guides.', 'Primjer: odgovarati kupcima na pitanja prema našim uputama za proizvode.')); ?>"></textarea></label>
            <label><?php echo esc_html($t('How many documents and which formats?', 'Koliko dokumenata imate i u kojim formatima?')); ?><input name="documents" required maxlength="300" placeholder="PDF, TXT, Markdown…"></label>
            <label><?php echo esc_html($t('Expected conversations per month', 'Očekivani broj razgovora mjesečno')); ?><input name="usage" required maxlength="100" placeholder="<?php echo esc_attr($t('An estimate, or not sure yet', 'Procjena ili još ne znam')); ?>"></label>
            <label class="ws-quote-wide"><?php echo esc_html($t('Website URL', 'Adresa web stranice')); ?><input name="website" type="url" required maxlength="500" placeholder="https://"></label>
            <div class="ws-quote-wide ws-quote-submit"><button type="submit"><?php echo esc_html($t('Request a quote', 'Zatraži ponudu')); ?> <span aria-hidden="true">↗</span></button><p><?php echo esc_html($t('No payment is made by submitting this request.', 'Slanjem zahtjeva ništa ne plaćate.')); ?></p></div>
            <p class="ws-quote-wide ws-quote-privacy"><?php echo esc_html($t('We will send the quote to your account email. Do not include passwords, API keys or confidential document content.', 'Ponudu šaljemo na e-mail vašeg računa. Nemojte unositi lozinke, API ključeve ni povjerljivi sadržaj dokumenata.')); ?></p>
        </form>
        <aside class="ws-quote-next"><h3><?php echo esc_html($t('What happens next?', 'Što slijedi?')); ?></h3><ol><li><?php echo esc_html($t('We review your documents and expected usage.', 'Pregledamo vaše potrebe i očekivano korištenje.')); ?></li><li><?php echo esc_html($t('You receive a quote with scope and price.', 'Dobivate ponudu s opsegom i cijenom.')); ?></li><li><?php echo esc_html($t('After agreement and payment, we activate your existing chat.', 'Nakon dogovora i plaćanja aktiviramo nadogradnju vašeg postojećeg chata.')); ?></li></ol><p><?php echo esc_html($t('Your widget and embed code stay the same. Fireworks usage is billed separately.', 'Widget i kod za ugradnju ostaju isti. Fireworks potrošnja naplaćuje se zasebno.')); ?></p></aside></div>
    </section>
    <?php
}
add_action('admin_post_nb_advanced_quote', function () {
    if (!is_user_logged_in()) wp_die('Sign in first.', '', ['response' => 403]);
    check_admin_referer('nb_advanced_quote');
    $user = wp_get_current_user();
    $existing = get_user_meta($user->ID, '_nb_advanced_quote', true);
    if (is_array($existing) && ($existing['status'] ?? '') === 'pending') {
        wp_safe_redirect(add_query_arg('view', 'advanced', home_url('/workspace/'))); exit;
    }
    $back = wp_validate_redirect(esc_url_raw(wp_unslash($_POST['return_url'] ?? '')), add_query_arg('view', 'advanced', home_url('/workspace/')));
    $fields = [];
    foreach (['needs' => 3000, 'documents' => 300, 'usage' => 100, 'website' => 500] as $field => $limit) {
        $value = $_POST[$field] ?? '';
        if (!is_string($value)) wp_die('Invalid request.', '', ['response' => 400]);
        $fields[$field] = sanitize_textarea_field(wp_unslash($value));
        if (!trim($fields[$field]) || mb_strlen($fields[$field]) > $limit) wp_die('Invalid request.', '', ['response' => 400]);
    }
    if (!filter_var($fields['website'], FILTER_VALIDATE_URL) || !in_array(wp_parse_url($fields['website'], PHP_URL_SCHEME), ['http', 'https'], true)) wp_die('Invalid website URL.', '', ['response' => 400]);
    $rate = 'nb_quote_rate_' . $user->ID;
    if (get_transient($rate)) { wp_safe_redirect(add_query_arg('quote_status', 'error', $back)); exit; }
    set_transient($rate, 1, MINUTE_IN_SECONDS);
    update_user_meta($user->ID, '_nb_advanced_quote', ['status' => 'pending', 'created' => time(), 'fields' => $fields, 'language' => ($_POST['language'] ?? '') === 'hr' ? 'hr' : 'en']);
    $body = "Advanced Chat — zahtjev za ponudu\n\nKorisnički ID: {$user->ID}\nE-mail: {$user->user_email}\n\nPotrebe: {$fields['needs']}\nDokumenti: {$fields['documents']}\nRazgovori mjesečno: {$fields['usage']}\nStranica: {$fields['website']}\n\nZahtjev nije automatska aktivacija niti narudžba.";
    $sent = wp_mail(get_option('admin_email'), 'Neonbreak — Advanced Chat: zahtjev za ponudu', $body);
    $request = get_user_meta($user->ID, '_nb_advanced_quote', true);
    $request['notification_sent'] = (bool) $sent;
    update_user_meta($user->ID, '_nb_advanced_quote', $request);
    wp_safe_redirect(add_query_arg('quote_status', $sent ? 'sent' : 'error', $back));
    exit;
});
function nb_advanced_set_access($uid, $enable, $resend = false) {
    $was_enabled = get_user_meta($uid, '_nb_advanced_enabled', true) === '1';
    update_user_meta($uid, '_nb_advanced_enabled', $enable ? '1' : '0');
    if ((get_user_meta($uid, '_nb_advanced_enabled', true) === '1') !== $enable) return 'save_failed';
    $request = get_user_meta($uid, '_nb_advanced_quote', true);
    if (!is_array($request)) $request = [];
    $request['status'] = $enable ? 'active' : 'inactive';
    $request['updated'] = time();
    $result = 'unchanged';
    if ($enable && (!$was_enabled || $resend)) {
        $user = get_userdata($uid);
        $hr = ($request['language'] ?? substr(get_user_locale($user), 0, 2)) === 'hr';
        $link = add_query_arg('view', 'advanced', untrailingslashit(get_option('home')) . ($hr ? '/hr/workspace/' : '/workspace/'));
        $subject = $hr ? 'Neonbreak — Advanced Chat je aktiviran' : 'Neonbreak — Advanced Chat is active';
        $body = $hr
            ? "Vaš AI Advanced Chat je aktiviran.\n\nOtvorite Workspace i prijavite se:\n$link\n\n1. Provjerite Fireworks povezivanje u AI Chatu.\n2. U Advanced Chatu dodajte PDF s tekstom, TXT ili Markdown dokument.\n3. Pregledajte tekst i kliknite Indeksiraj dokument.\n4. Isprobajte odgovor u novom razgovoru postojećeg widgeta.\n\nKod widgeta ostaje isti. Fireworks potrošnju plaćate putem vlastitog računa.\n\nNeonbreak"
            : "Your AI Advanced Chat is now active.\n\nOpen your Workspace and sign in:\n$link\n\n1. Check your Fireworks connection in AI Chat.\n2. Add a text-based PDF, TXT or Markdown document in Advanced Chat.\n3. Review the extracted text and select Index document.\n4. Test an answer in a new conversation in your existing widget.\n\nYour embed code stays the same. Fireworks usage is billed through your own account.\n\nNeonbreak";
        $failure = '';
        $capture = function ($error) use (&$failure) {
            $failure = class_exists('Neonbreak_Email') ? Neonbreak_Email::failure_reason($error->get_error_message()) : 'Slanje nije uspjelo. Provjerite SMTP postavke.';
        };
        add_action('wp_mail_failed', $capture);
        try { $sent = wp_mail($user->user_email, $subject, $body); }
        catch (Throwable $e) { $sent = false; $failure = class_exists('Neonbreak_Email') ? Neonbreak_Email::failure_reason($e->getMessage()) : 'Provjerite SMTP postavke.'; }
        finally { remove_action('wp_mail_failed', $capture); }
        if (!$sent) set_transient('nb_activation_mail_error_' . get_current_user_id(), $failure ?: 'Slanje je odbijeno. Provjerite e-mail primatelja i Neonbreak Email postavke.', 600);
        $request['activation_notification_sent'] = (bool) $sent;
        $request['activation_notification_time'] = time();
        $result = $sent ? 'sent' : 'failed';
    }
    update_user_meta($uid, '_nb_advanced_quote', $request);
    return $result;
}
add_action('admin_post_nb_advanced_access', function () {
    if (!current_user_can('manage_options')) wp_die('Forbidden.', '', ['response' => 403]);
    $uid = absint($_POST['user'] ?? 0);
    check_admin_referer('nb_advanced_access_' . $uid);
    if (!$uid || !get_userdata($uid)) wp_die('User not found.', '', ['response' => 400]);
    $resend = ($_POST['access'] ?? '') === 'resend';
    $enable = $resend || ($_POST['access'] ?? '') === 'enable';
    if (!in_array($_POST['access'] ?? '', ['enable', 'disable', 'resend'], true)) wp_die('Invalid action.', '', ['response' => 400]);
    if ($resend && get_user_meta($uid, '_nb_advanced_enabled', true) !== '1') wp_die('Activate access first.', '', ['response' => 400]);
    $notification = nb_advanced_set_access($uid, $enable, $resend);
    wp_safe_redirect(add_query_arg(['access_updated' => '1', 'activation_mail' => $notification], admin_url('options-general.php?page=nb-advanced')));
    exit;
});
