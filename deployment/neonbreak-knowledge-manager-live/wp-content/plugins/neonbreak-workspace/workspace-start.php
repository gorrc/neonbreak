<?php
defined('ABSPATH') || exit;
if (!is_user_logged_in()) return;
$start_uid = get_current_user_id();
$start_settings = Neonbreak_Client_Service::settings($start_uid);
$start_key = !empty($start_settings['_api_key']);
$start_knowledge = trim($start_settings['knowledge']) !== '' && trim($start_settings['title']) !== '';
$start_widget = !empty($start_settings['enabled']) && !empty($start_settings['domains']) && (bool) get_user_meta($start_uid, '_nb_chat_public', true);
$start_complete = (int) $start_key + (int) $start_knowledge + (int) $start_widget;
$start_steps = [
    [$t('Connect Fireworks', 'Povežite Fireworks'), $t('Save your own API key and use “Check connection and save” in AI Chat. Usage is billed to your Fireworks account.', 'Spremite vlastiti API ključ i u AI Chatu odaberite „Provjeri vezu i spremi”. Potrošnja se naplaćuje putem vašeg Fireworks računa.'), $url('chat') . '#nb-connection', $start_key ? $t('Key saved', 'Ključ spremljen') : $t('Setup required', 'Potrebno povezivanje'), $start_key],
    [$t('Add branding and knowledge', 'Dodajte izgled i znanje'), $t('Set your assistant name, colours and welcome message. Add public business facts and save your settings.', 'Podesite naziv, boje i pozdravnu poruku asistenta. Dodajte javne informacije o poslovanju i spremite postavke.'), $url('chat') . '#nb-knowledge', $start_knowledge ? $t('Knowledge saved', 'Znanje spremljeno') : $t('Add business facts', 'Dodajte informacije'), $start_knowledge],
    [$t('Enable and embed your widget', 'Uključite i ugradite widget'), $t('Allow your website origin, enable the embedded chat and copy its script into your website. Include https:// and any port.', 'Dopustite adresu svoje stranice, uključite ugrađeni chat i kopirajte njegov kod na stranicu. Uključite https:// i port ako ga koristite.'), $url('chat') . '#nb-connection', $start_widget ? $t('Embed code ready', 'Kod spreman za ugradnju') : $t('Configure your website', 'Podesite svoju stranicu'), $start_widget],
    [$t('Test real questions', 'Testirajte stvarna pitanja'), $t('In your website widget, ask about your business and a fact that is missing. Verify accuracy and your contact link. Repeat in a new conversation after changes.', 'U widgetu na svojoj stranici pitajte o poslovanju i podatku koji nije naveden. Provjerite točnost i kontakt poveznicu. Nakon izmjena testirajte u novom razgovoru.'), $url('chat'), $t('Manual check', 'Ručna provjera'), false],
    [$t('Expand with Advanced Chat', 'Proširite uz Advanced Chat'), $t('Optional: request a quote for document search. After activation, import PDF, TXT, Markdown or a public URL in Knowledge Base. Your widget code stays the same.', 'Opcionalno: zatražite ponudu za pretraživanje dokumenata. Nakon aktivacije u Bazi znanja uvezite PDF, TXT, Markdown ili javni URL. Kod widgeta ostaje isti.'), $url('advanced'), NB_Advanced_Chat::allowed($start_uid) ? $t('Access active', 'Pristup aktivan') : $t('Optional upgrade', 'Opcionalna nadogradnja'), NB_Advanced_Chat::allowed($start_uid)]
];
?>
<section class="ws-card ws-start" aria-labelledby="ws-start-heading">
    <div class="ws-start-heading"><div><span class="ws-label"><?php echo esc_html($t('GETTING STARTED', 'KRENITE OVDJE')); ?></span><h2 id="ws-start-heading"><?php echo esc_html($t('Set up your first assistant.', 'Postavite svog prvog asistenta.')); ?></h2></div><span class="ws-start-progress"><?php echo esc_html(sprintf($t('%d / 3 setup steps saved', '%d / 3 koraka postavljanja spremljeno'), $start_complete)); ?></span></div>
    <ol class="ws-start-list"><?php foreach ($start_steps as $index => $step) : ?><li><span class="ws-start-number" aria-hidden="true"><?php echo $index + 1; ?></span><div><h3><?php echo esc_html($step[0]); ?></h3><p><?php echo esc_html($step[1]); ?></p></div><a class="ws-start-link <?php echo $step[4] ? 'is-ready' : ''; ?>" href="<?php echo esc_url($step[2]); ?>"><?php echo esc_html($step[3]); ?> <span aria-hidden="true">→</span></a></li><?php endforeach; ?></ol>
    <p class="ws-start-note"><?php echo esc_html($t('Statuses reflect saved settings. They do not verify your Fireworks balance, website installation or answer quality.', 'Statusi prikazuju spremljene postavke. Ne potvrđuju Fireworks saldo, ugradnju na stranicu ni kvalitetu odgovora.')); ?></p>
</section>
