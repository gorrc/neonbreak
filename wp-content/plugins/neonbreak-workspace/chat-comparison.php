<?php defined('ABSPATH') || exit; ?>
<section class="ws-card ws-chat-comparison ws-comparison-refined" aria-labelledby="ws-chat-options">
  <h2 id="ws-chat-options"><?php echo esc_html($t('Start simple. Grow with your knowledge.', 'Krenite jednostavno. Rastite sa svojim znanjem.')); ?></h2>
  <div class="ws-comparison-scroll"><table>
    <caption><?php echo esc_html($t('AI Chat and AI Advanced Chat', 'AI Chat i AI Advanced Chat')); ?></caption>
    <thead><tr><th scope="col"><?php echo esc_html($t('Feature', 'Mogućnost')); ?></th><th scope="col">AI Chat <span class="ws-free"><?php echo esc_html($t('Free', 'Besplatan')); ?></span></th><th scope="col">AI Advanced Chat <span class="ws-paid"><?php echo esc_html($t('Paid · Request a quote', 'Plaćen · Zatražite ponudu')); ?></span></th></tr></thead>
    <tbody>
    <?php foreach ([
      [$t('Knowledge', 'Baza znanja'), $t('Manually entered business information', 'Ručno unesene informacije o poslovanju'), $t('Uploaded documents and a searchable knowledge base', 'Učitani dokumenti i pretraživa baza znanja')],
      [$t('Answering', 'Odgovaranje'), $t('Uses the text saved in your chat settings', 'Koristi tekst spremljen u postavkama chata'), $t('Retrieves relevant passages for each question (RAG)', 'Za svako pitanje pronalazi relevantne odlomke (RAG)')],
      [$t('Sources', 'Izvori'), $t('No document source excerpts', 'Bez izvora iz dokumenata'), $t('Retrieved passages shown alongside the answer', 'Pronađeni odlomci prikazani uz odgovor')],
      [$t('Best for', 'Namjena'), $t('Services, basic business information and FAQs', 'Usluge, osnovne poslovne informacije i česta pitanja'), $t('Manuals, documentation and larger knowledge collections', 'Priručnici, dokumentacija i veće zbirke znanja')],
      [$t('Service cost', 'Cijena usluge'), $t('Basic chat configuration and widget are free', 'Osnovne postavke chata i widget su besplatni'), $t('Quoted according to your needs; activation after agreement and payment by invoice', 'Prema ponudi za vaše potrebe; aktivacija nakon dogovora i plaćanja po računu')],
      [$t('Content import', 'Uvoz sadržaja'), $t('Enter or paste text', 'Unesite ili zalijepite tekst'), $t('TXT, Markdown, text-based PDF and public HTTPS page URLs; manual refresh', 'TXT, Markdown, PDF s tekstom i URL javne HTTPS stranice; ručno osvježavanje')],
      [$t('Availability', 'Dostupnost'), $t('Available now', 'Dostupan sada'), $t('Request access in Workspace. Uses your existing widget and embed code.', 'Zatražite pristup u Workspaceu. Koristi vaš postojeći widget i kod za ugradnju.')]
    ] as $row) : ?><tr><th scope="row"><?php echo esc_html($row[0]); ?></th><td><?php echo esc_html($row[1]); ?></td><td><?php echo esc_html($row[2]); ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  <p><?php echo esc_html($t('Both tools use your own Fireworks account. Fireworks bills model usage, including document embeddings for Advanced Chat, separately from our service. Advanced Chat improves knowledge retrieval; it does not necessarily use a stronger chat model.', 'Oba alata koriste vaš Fireworks račun. Fireworks zasebno naplaćuje potrošnju modela, uključujući embeddings dokumenata za Advanced Chat. Advanced Chat unapređuje dohvat znanja; ne mora koristiti snažniji chat model.')); ?></p>
</section>

