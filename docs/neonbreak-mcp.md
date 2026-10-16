# Neonbreak MCP — PHP WordPress plugin

Zaseban plugin `neonbreak-mcp` koristi službeni `mcp/sdk` 0.8.1 i pruža centralno mjesto za MCP alate. Zahtijeva PHP 8.1 ili noviji. SDK i ovisnosti uključeni su u paket, pa Composer nije potreban na live hostingu.

## Arhitektura

AI Business Assistant → službeni MCP SDK i njegov HTTP transport unutar istog PHP procesa → `search_knowledge` → postojeći `NB_AI_Search::search()` → Fireworks embeddings i Qdrant.

Pretraga, filtriranje tenant-a i aktivnih dokumenata, Fireworks ključ, limiti i naplata ostaju u postojećim pluginovima. Ne kopiramo podatke niti uvodimo novu kolekciju ili novi korisnički ključ. Generiranje odgovora i privatna povijest ostaju u Business Assistantu. Asistent sada bira između `search_knowledge` i `search_conversations`; odabir, argumenti i vlasništvo provjeravaju se na poslužitelju. Detalji su u `docs/mcp-conversations.md`.

Unutarnji klijent koristi istu JSON-RPC obradu službenog SDK-a kao HTTP endpoint. Ne šalje dodatni HTTP zahtjev prema WordPressu. Identitet vlasnika dolazi iz trenutačne WordPress prijave; nije argument alata.

## Prvi alat

`search_knowledge(query, limit = 5)`

- Upit: 1–1.500 znakova, limit: cijeli broj od 1 do 5.
- Dopušteni su samo `query` i `limit`; `owner_id`, `tenant`, SQL i drugi argumenti nisu dopušteni.
- Rezultat: `query`, `results` s dokumentima/odlomcima i `usage` iz postojeće AI Search funkcije.
- Ne generira tekst odgovora i ne mijenja poslovne podatke. Troši postojeći Search limit i Fireworks embeddings.
- Provjerava prijavljenog vlasnika i Advanced pristup pri svakom pozivu.

## HTTP MCP endpoint

`https://VAŠA-DOMENA/wp-json/neonbreak-mcp/v1/mcp`

POST za protokol i DELETE za završetak starije MCP sesije. GET streamovi i pretplate nisu dio ove početne izvedbe. Službeni SDK obrađuje noviji stateless protokol i stariji `initialize` tok. Starije sesije čuvaju se kao WordPress transients do 15 minuta, s odvojenim ključevima po prijavljenom vlasniku.

Autentikacija je WordPress prijava uz REST nonce ili postojeća WordPress Application Password autentikacija preko HTTPS-a. AI Search ključ nije MCP ključ. Ne stvaramo nove lozinke niti otvaramo anonimni pristup. OAuth/autorizacijska discovery usluga za povezivanje proizvoljnih vanjskih aplikacija nije implementirana; ovo je trenutačno MCP servis za naš Workspace i autentificirane WordPress klijente.

Na udaljenim domenama zahtijeva HTTPS. Origin zaglavlje, ako postoji, mora odgovarati WordPress stranici. Ograničenje tijela zahtjeva: 16 KB, jedan JSON-RPC objekt bez batch poziva. Odgovori se ne predmemoriraju. Sistemske upute, Fireworks i Qdrant ključevi nisu dio rezultata. Pristup direktoriju `vendor` preko Apachea zabranjen je `.htaccess` datotekom.

## Postavljanje na live

1. Napraviti uobičajenu sigurnosnu kopiju pluginova.
2. Iz paketa prebaciti obje mape: `wp-content/plugins/neonbreak-mcp/` i `wp-content/plugins/neonbreak-workspace/`.
3. U WordPressu otvoriti Dodaci i aktivirati **Neonbreak MCP**. Neonbreak Chat i Neonbreak AI Workspace trebaju ostati aktivni.
4. U Postavke → Neonbreak MCP provjeriti status i HTTP adresu.
5. Otvoriti AI Business Assistant računom s Advanced pristupom, vlastitim Fireworks ključem i dokumentima. Postaviti konkretno pitanje te provjeriti odgovor i izvor.

Ako MCP nije aktivan, Workspace prikazuje jasnu obavijest i ne šalje pitanja. Nije potreban VPS, Node.js niti dodatno kopiranje baze ili ključeva. Ako je PHP na hostingu stariji od 8.1, najprije treba nadograditi PHP.

## Provjere

- `tests/mcp-smoke.php`: stvarni službeni SDK, otkrivanje i pozivanje alata, validacija argumenata, nepoznati alat, izolacija tenant-a i trenutačnog korisnika, starije sesije između odvojenih instanci SDK-a, odbijanje tuđe sesije, isključen pristup i Origin zaštita. Vanjski AI/Qdrant servisi su simulirani.
- `tests/mcp-http-smoke.php`: stvarni lokalni HTTP endpoint, privremeni WordPress račun, cookie i nonce, neenkodirani JSON-RPC odgovor, odbijanje nedostajućeg/pogrešnog nonce-a; bez AI poziva.
- `tests/business-assistant-smoke.php`: asistent prolazi kroz novi MCP sloj, uz provjere odgovora, izvora, duplih zahtjeva, grešaka i čuvanja povijesti.
- Postojeće AI Search i conversation-history provjere i dalje prolaze.

Provjere ne potvrđuju live PHP verziju, stvarne ključeve, saldo ni kvalitetu modelskih odgovora na korisnikovim dokumentima.

## Dodavanje budućih alata

Registrirati zasebni handler i strogu shemu u `NB_MCP::server()`, izvesti poslovnu funkciju iz postojećeg plugina i ponovno provjeriti vlasništvo unutar handlera. Prava se ne zaključuju iz modelskog teksta. Promjene konfiguracije moraju prvo proći pregled i izričitu korisničku potvrdu. Ne izlagati proizvoljan SQL niti generičko pozivanje PHP/WordPress funkcija.


## Drugi alat

Dodano: `search_conversations(query, days, limit, conversation_id)` za vlasničku pretragu razgovora widgeta i ograničene statistike. Privatni razgovori asistenta nisu dio tog izvora. Vidjeti `docs/mcp-conversations.md`.
