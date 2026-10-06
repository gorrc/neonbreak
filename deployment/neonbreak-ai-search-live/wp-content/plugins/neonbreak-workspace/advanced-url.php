<?php
defined('ABSPATH') || exit;
function nb_url_text($en, $hr) { return nb_advanced_language() === 'hr' ? $hr : $en; }
final class NB_Advanced_URL {
    public static function public_url($url) {
        $parts = wp_parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || strpos($parts['host'], '.') === false || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443) || filter_var($parts['host'], FILTER_VALIDATE_IP) || !wp_http_validate_url($url)) return false;
        $addresses = gethostbynamel($parts['host']);
        if (!$addresses) return false;
        foreach ($addresses as $address) if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        if (function_exists('dns_get_record')) foreach ((@dns_get_record($parts['host'], DNS_AAAA) ?: []) as $record) if (!empty($record['ipv6']) && !filter_var($record['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        return true;
    }
    public static function extract($html) {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $ok = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        if (!$ok) throw new RuntimeException('parse');
        $xpath = new DOMXPath($dom);
        $title = trim($xpath->evaluate('string(//title)'));
        foreach ($xpath->query('//script|//style|//noscript|//nav|//header|//footer|//form|//aside|//svg|//iframe|//template|//*[@hidden]|//*[@aria-hidden="true"]') as $node) $node->parentNode->removeChild($node);
        $root = $xpath->query('//main')->item(0) ?: ($xpath->query('//article')->item(0) ?: $xpath->query('//body')->item(0));
        if (!$root) throw new RuntimeException('parse');
        foreach ($xpath->query('.//p|.//div|.//li|.//h1|.//h2|.//h3|.//h4|.//tr|.//br', $root) as $node) $node->appendChild($dom->createTextNode("\n"));
        $text = preg_replace('/[\t \x{00a0}]+/u', ' ', $root->textContent);
        $text = preg_replace('/ *\n */u', "\n", $text);
        $text = trim(preg_replace('/\n{3,}/u', "\n\n", $text));
        return ['title' => mb_substr($title, 0, 200), 'text' => $text];
    }
    public static function fetch() {
        NB_Advanced_Chat::authorize('_url');
        try {
            $value = $_POST['url'] ?? '';
            if (!is_string($value) || strlen($value) > 2000) throw new RuntimeException('url');
            $url = esc_url_raw(wp_unslash($value)); $parts = wp_parse_url($url);
            if (!self::public_url($url)) throw new RuntimeException('url');
            $response = wp_safe_remote_get($url, ['timeout' => 20, 'redirection' => 0, 'limit_response_size' => 1048577, 'cookies' => [], 'headers' => ['Accept' => 'text/html']]);
            if (is_wp_error($response)) throw new RuntimeException('fetch');
            $status = wp_remote_retrieve_response_code($response);
            if ($status >= 300 && $status < 400) throw new RuntimeException('redirect');
            if ($status !== 200 || stripos(wp_remote_retrieve_header($response, 'content-type'), 'text/html') === false) throw new RuntimeException('fetch');
            $html = wp_remote_retrieve_body($response);
            if (strlen($html) > 1048576) throw new RuntimeException('size');
            $data = self::extract($html);
            if (!$data['text']) throw new RuntimeException('empty');
            $data['text'] = "URL: $url\n\n" . $data['text'];
            if (strlen($data['text']) > 24000 || mb_strlen($data['text']) > 22000) throw new RuntimeException('textsize');
            if (!$data['title']) $data['title'] = $parts['host'];
            $data['url'] = $url;
            $data['message'] = nb_url_text('Text loaded. Review it below, then index the document. Content is not refreshed automatically.', 'Tekst je učitan. Pregledajte ga ispod pa indeksirajte dokument. Sadržaj se ne osvježava automatski.');
            wp_send_json_success($data);
        } catch (Throwable $e) {
            $messages = [
                'url' => nb_url_text('Enter a public HTTPS page URL, without credentials or a custom port.', 'Unesite javni HTTPS URL stranice, bez pristupnih podataka ili posebnog porta.'),
                'redirect' => nb_url_text('This URL redirects. Enter the final page URL from your browser.', 'URL preusmjerava. Unesite konačnu adresu stranice iz preglednika.'),
                'size' => nb_url_text('The page exceeds the 1 MB download limit.', 'Stranica prelazi limit preuzimanja od 1 MB.'),
                'textsize' => nb_url_text('Page text exceeds 24 KB or 20 chunks. Use a smaller page or paste a selected excerpt.', 'Tekst stranice prelazi 24 KB ili 20 odlomaka. Koristite manju stranicu ili zalijepite odabrani dio teksta.'),
                'empty' => nb_url_text('No readable text found. Pages requiring login or JavaScript are not supported.', 'Nema čitljivog teksta. Stranice koje zahtijevaju prijavu ili JavaScript nisu podržane.')
            ];
            wp_send_json_error(['message' => $messages[$e->getMessage()] ?? nb_url_text('The page could not be read. Check its public URL or paste the text manually.', 'Stranicu nije moguće pročitati. Provjerite javnu adresu ili ručno zalijepite tekst.')], 400);
        }
    }
}
add_action('wp_ajax_nb_advanced_url', ['NB_Advanced_URL', 'fetch']);
function nb_advanced_url_form() { ?>
<section class="ws-card" style="margin-bottom:24px"><h2><?php echo esc_html(nb_url_text('Import a web page', 'Uvezi web stranicu')); ?></h2><p><?php echo esc_html(nb_url_text('Load one public page into the document form. Review the text before indexing. Only import content you have permission to use. To refresh it later, load the URL again and replace the existing document.', 'Učitajte jednu javnu stranicu u obrazac dokumenta. Pregledajte tekst prije indeksiranja i koristite samo sadržaj za koji imate dopuštenje. Za osvježavanje ponovno učitajte URL i zamijenite postojeći dokument.')); ?></p><form id="nb-advanced-url-form"><label><?php echo esc_html(nb_url_text('Public page URL', 'URL javne stranice')); ?><input name="url" type="url" required maxlength="2000" placeholder="https://"></label><button><?php echo esc_html(nb_url_text('Load page text', 'Učitaj tekst stranice')); ?></button><p role="status"></p></form></section>
<?php }
