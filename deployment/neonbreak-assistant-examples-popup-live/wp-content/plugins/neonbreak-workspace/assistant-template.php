<?php

defined('ABSPATH') || exit;

$assistant_uid=get_current_user_id();

wp_enqueue_style('nb-business-assistant',plugins_url('business-assistant.css',__FILE__),['nb-workspace'],'0.3.3');

wp_enqueue_script('nb-assistant-format',plugins_url('assistant-format.js',__FILE__),[],'0.3.3',true);
wp_enqueue_script('nb-business-assistant',plugins_url('business-assistant.js',__FILE__),['nb-assistant-format'],'0.3.3',true);

wp_localize_script('nb-business-assistant','NBBA',['endpoint'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('nb_business_assistant'),'language'=>$hr?'hr':'en','allowed'=>NB_Advanced_Chat::allowed($assistant_uid),'ready'=>class_exists('NB_MCP') && (bool)Neonbreak_Client_Service::settings($assistant_uid)['_api_key']]);

?>

<header class="ba-heading"><div><p class="ws-label">AI WORKSPACE</p><h1>AI Business Assistant</h1><p><?php echo esc_html($t('Search business knowledge and review your website conversations.','Pretražite poslovno znanje i pregledajte razgovore posjetitelja svojeg widgeta.')); ?></p></div><a href="<?php echo esc_url($url('knowledge')); ?>"><?php echo esc_html($t('Manage knowledge →','Upravljaj bazom znanja →')); ?></a></header>

<?php if (!NB_Advanced_Chat::allowed($assistant_uid)) : ?><p class="ba-notice"><?php echo esc_html($t('AI Business Assistant requires active Advanced access. You can still read or delete your previous assistant conversations.','AI Business Assistant zahtijeva aktivan Advanced pristup. Prethodne razgovore s asistentom i dalje možete pregledavati ili brisati.')); ?> <a href="<?php echo esc_url($url('advanced')); ?>"><?php echo esc_html($t('Request access →','Zatraži pristup →')); ?></a></p><?php elseif (!class_exists('NB_MCP')) : ?><p class="ba-notice"><?php echo esc_html($t('The Neonbreak MCP plugin must be activated by the site administrator.','Administrator stranice treba aktivirati Neonbreak MCP plugin.')); ?></p><?php elseif (!Neonbreak_Client_Service::settings($assistant_uid)['_api_key']) : ?><p class="ba-notice"><?php echo esc_html($t('To start, save your Fireworks connection in AI Chat. Documents are needed for knowledge search; saved widget conversations are needed for history review.','Za početak spremite Fireworks vezu u AI Chatu. Za pretragu znanja trebaju dokumenti, a za pregled povijesti spremljeni razgovori widgeta.')); ?></p><?php endif; ?>

<div class="ba-app" id="ba-app"><aside class="ba-sidebar"><button type="button" id="ba-new"><?php echo esc_html($t('+ New conversation','+ Novi razgovor')); ?></button><nav id="ba-threads" aria-label="<?php echo esc_attr($t('Assistant conversations','Razgovori s asistentom')); ?>"></nav><p><?php echo esc_html($t('Private conversations · stored for 90 days after last activity.','Privatni razgovori · čuvaju se 90 dana od zadnje aktivnosti.')); ?></p></aside><section class="ba-chat" aria-label="AI Business Assistant"><header class="ba-chat-heading"><strong id="ba-title"><?php echo esc_html($t('Search your knowledge','Pretražite svoju bazu znanja')); ?></strong><div><button type="button" id="ba-show-examples" aria-haspopup="dialog" aria-controls="ba-examples"><?php echo esc_html($t('Question examples','Primjeri pitanja')); ?></button><button type="button" id="ba-export" hidden>JSON ↓</button><button type="button" id="ba-delete" hidden><?php echo esc_html($t('Delete','Izbriši')); ?></button></div></header><div id="ba-messages" class="ba-messages" role="log" aria-live="polite" aria-label="<?php echo esc_attr($t('Messages','Poruke')); ?>"></div><div class="ba-composer"><dialog class="ba-examples" id="ba-examples" aria-labelledby="ba-examples-title"><header class="ba-examples-header"><h2 id="ba-examples-title"><?php echo esc_html($t('Question examples','Primjeri pitanja')); ?></h2><button type="button" class="ba-example-close" id="ba-close-examples" aria-label="<?php echo esc_attr($t('Close','Zatvori')); ?>">×</button></header><div class="ba-example-groups">
<?php
$example_groups=[
    'knowledge'=>[
        'title'=>$t('Knowledge base','Baza znanja'),
        'hint'=>$t('Ask about information in your uploaded documents. Name the service, package or document.','Pitajte o informacijama iz učitanih dokumenata. Navedite uslugu, paket ili dokument.'),
        'questions'=>[
            $t('What does the Business package include, and what is its delivery time according to my documents?','Što uključuje Business paket i koji je rok izrade prema mojim dokumentima?'),
            $t('According to my documents, what information must a customer prepare before a project starts?','Koje podatke klijent treba pripremiti prije početka projekta prema mojim dokumentima?'),
            $t('Find the cancellation terms in my documents and explain them in simple language.','Pronađi uvjete otkazivanja u mojim dokumentima i objasni ih jednostavnim jezikom.')
        ]
    ],
    'conversations'=>[
        'title'=>$t('Visitor conversations','Razgovori posjetitelja'),
        'hint'=>$t('Specify a topic and period. Distinguish messages from conversations when asking for counts.','Navedite temu i razdoblje. Kod brojanja razlikujte poruke od razgovora.'),
        'questions'=>[
            $t('How many visitor messages mentioned PHP in the last 7 days, and in how many conversations?','Koliko je poruka posjetitelja spominjalo PHP u zadnjih 7 dana i u koliko razgovora?'),
            $t('Did any visitors ask about prices yesterday? Show examples.','Je li netko od posjetitelja jučer pitao o cijeni? Navedi primjere.'),
            $t('Group visitor questions from the last 7 days by topic and suggest what we should explain more clearly on our website. Base suggestions on the retrieved messages.','Grupiraj pitanja posjetitelja zadnjih 7 dana po temama i predloži što trebamo jasnije objasniti na stranici. Prijedloge temelji na pronađenim porukama.')
        ]
    ]
];
foreach($example_groups as $source=>$group): ?>
<section><h3><?php echo esc_html($group['title']); ?></h3><p><?php echo esc_html($group['hint']); ?></p><?php foreach($group['questions'] as $question): ?><button type="button" data-ba-example="<?php echo esc_attr($source); ?>"><?php echo esc_html($question); ?></button><?php endforeach; ?></section>
<?php endforeach; ?>
</div><p class="ba-examples-note"><?php echo esc_html($t('Click an example to select its source and fill in your question. Edit it before sending. Answers use retrieved excerpts; conversation analysis includes up to 10 messages, while counts cover all matching retained messages.','Klik odabire izvor i popunjava pitanje. Prilagodite ga prije slanja. Odgovori koriste dohvaćene izvatke; analiza razgovora uključuje do 10 poruka, a brojanje sve podudarne sačuvane poruke.')); ?></p></dialog><label class="ba-mode-label" for="ba-mode"><?php echo esc_html($t('Search in','Pretraži')); ?> <select id="ba-mode" name="mode" form="ba-form" required><option value=""><?php echo esc_html($t('Choose a source…','Odaberite izvor…')); ?></option><option value="knowledge"><?php echo esc_html($t('Knowledge base','Baza znanja')); ?></option><option value="conversations"><?php echo esc_html($t('Visitor conversations','Razgovori posjetitelja')); ?></option></select></label><form id="ba-form"><label class="ba-input-label" for="ba-message"><?php echo esc_html($t('Your question','Vaše pitanje')); ?></label><textarea id="ba-message" name="message" rows="2" maxlength="1500" required placeholder="<?php echo esc_attr($t('Ask about your documents or visitor conversations…','Pitajte o dokumentima ili razgovorima posjetitelja…')); ?>"></textarea><button id="ba-send" type="submit"><?php echo esc_html($t('Send ↗','Pošalji ↗')); ?></button></form><p id="ba-status" role="status"></p><small><?php echo esc_html($t('Your selected source determines the read-only tool. For conversations, describe the topic and period in your question. Knowledge search uses AI Search limits; conversation review uses saved widget history. Fireworks bills search interpretation and answers, plus embeddings for knowledge search. Answers may be inaccurate. Shift+Enter adds a new line.','Odabrani izvor određuje alat za čitanje. Za razgovore navedite temu i razdoblje u pitanju. Baza znanja koristi AI Search limite, a pregled razgovora spremljenu povijest widgeta. Fireworks naplaćuje tumačenje pretrage i odgovor te embeddings za pretragu znanja. Odgovori mogu biti netočni. Shift+Enter dodaje novi redak.')); ?></small></div></section></div>

