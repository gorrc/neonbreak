<?php
defined('ABSPATH') || exit;
$has_key = (bool) get_option('nb_qdrant_key', '');
?>
<div class="wrap nb-advanced-admin">
    <div class="nba-heading"><span class="nba-eyebrow">NEONBREAK WORKSPACE</span><h1>AI Advanced Chat</h1><p>Povežite bazu znanja i upravljajte zahtjevima i pristupom klijenata.</p></div>
    <form method="post">
        <?php wp_nonce_field('nb_advanced_config'); ?>
        <div class="nba-grid">
            <section class="nba-card">
                <div class="nba-card-title"><span class="nba-step">01</span><div><h2>Qdrant povezivanje</h2><p>Zajednička infrastruktura za baze znanja korisnika.</p></div></div>
                <div class="nba-field"><label for="nba-url">HTTPS endpoint klastera</label><input id="nba-url" name="url" type="url" required placeholder="https://vas-klaster.eu-central.aws.cloud.qdrant.io" value="<?php echo esc_attr(get_option('nb_qdrant_url', '')); ?>"><p class="description">Kopirajte adresu klastera iz Qdrant Cloud konzole.</p></div>
                <div class="nba-field"><div class="nba-label-row"><label for="nba-key">Qdrant API ključ</label><span class="nba-key-status <?php echo $has_key ? 'is-saved' : ''; ?>"><?php echo $has_key ? 'Ključ spremljen' : 'Ključ nije spremljen'; ?></span></div><input id="nba-key" type="password" name="key" autocomplete="new-password" placeholder="<?php echo $has_key ? 'Unesite samo ako mijenjate ključ' : 'Unesite API ključ'; ?>"><p class="description">Ključ se sprema šifrirano. Prazno polje zadržava postojeći ključ.</p></div>
                <div class="nba-note"><strong>Fireworks povezivanje</strong><p>Svaki korisnik dodaje vlastiti Fireworks ključ u svom radnom prostoru. Ovdje postavljate samo Qdrant.</p></div>
            </section>
            <section class="nba-card">
                <div class="nba-card-title"><span class="nba-step">02</span><div><h2>Aktivacija po ponudi</h2><p>Zahtjevi klijenata prikazani su ispod postavki povezivanja.</p></div></div>
                <p>Pregledajte potrebe korisnika, dogovorite ponudu i račun, pa kliknite „Aktiviraj pristup” uz njegov račun.</p>
                <div class="nba-note"><strong>Trenutačni limiti</strong><p>Do 10 TXT, Markdown ili PDF dokumenata. PDF do 5 MB i 30 stranica; izdvojeni tekst do 24 KB i 20 odlomaka po dokumentu. Online naplata nije uključena.</p></div>
            </section>
        </div>
        <div class="nba-footer"><?php submit_button('Spremi postavke', 'primary', 'submit', false); ?><p>Spremanje postavki ne provjerava vezu s Qdrantom.</p></div>
    </form>
    <?php require __DIR__ . '/advanced-requests.php'; ?>
</div>
