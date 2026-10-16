# MCP: razgovori posjetitelja i odabir alata

## Novi alat

`search_conversations(query = "", days = 30, limit = 5, conversation_id = 0)` pretražuje spremljene razgovore iz widgeta trenutačnog korisnika. Ne čita privatne Business Assistant razgovore niti razgovore drugih korisnika, uključujući kad je pozivatelj administrator.

- Pojam je obična tekstualna pretraga do 200 znakova; SQL LIKE znakovi su escapirani. Ovo nije embeddings/semantička pretraga.
- Razdoblje: 1–90 dana, dodatno ograničeno postojećim rokom čuvanja povijesti vlasnika. Prozor je klizan u UTC-u, ne kalendarski mjesec.
- Rezultati: do 10 razgovora, zadano 5, najviše šest dovršenih poruka po razgovoru i ograničeni odlomci. Ukupan tekst ograničen je približno na 20.000 znakova.
- `conversation_id` sužava pretragu na konkretan razgovor, ali ne mijenja vlasnika niti zaobilazi rok čuvanja.
- Sažetak vraća broj razgovora, broj poklapanja, dovršenih korisničkih poruka, neuspjelih poruka, broj vraćenih razgovora i oznaku skraćenog uzorka.
- Alat ne mijenja razgovore, ne poziva Qdrant/embeddings i ne troši AI Search dnevni brojač. Ima zasebnu zaštitu od 20 poziva u minuti po računu.
- Trenutačno ostaje dio postojećeg Advanced pristupa za Business Assistant/MCP. Sam korisnikov pregled povijesti widgeta ostaje dostupan prema dosadašnjim pravilima.

## Agentski tok

Business Assistant dobiva popis alata kroz stvarni službeni MCP SDK. Fireworks model prvo vraća strukturirani JSON s odabirom jednog alata i argumentima. Poslužitelj dopušta samo `search_knowledge` i `search_conversations`, a SDK ponovno validira argumente. Identitet dolazi iz WordPress prijave, ne iz modela. Nepoznati alati, dodatni tenant/owner/SQL argumenti i neispravni rasponi se odbijaju.

Nakon izvršavanja alata slijedi model koji sastavlja odgovor na temelju dokaza. Za pozdrav/pomoć može se vratiti objašnjenje mogućnosti bez pretrage. Ovo je ograničeni tok s najviše jednim izvršenim alatom po pitanju, bez autonomne višekoračne petlje. Odabir se radi strukturiranim izlazom modela; ne koristi zaseban model ni slobodno izvršavanje generiranog koda.

U sučelju postoji izbor Automatski / Baza znanja / Razgovori posjetitelja. Ručni izbor ograničava raspoloživi alat; model i dalje priprema argumente pretrage. Odgovor označava korišteni izvor i daje skupljene odlomke i poveznicu na vlasnikov pregled razgovora.

Fireworks naplaćuje odabir alata i generiranje odgovora. Samo alat za bazu znanja dodatno koristi embeddings i postojeće AI Search kvote.

## Ograničenje zaključaka

Brojači razdoblja izračunati su na poslužitelju. Tematski sažetak dobiva ograničen broj recentnih odlomaka; nije potpuna frekvencijska analiza svih pitanja. Model mora navesti koliko je razgovora pregledao i ograničenje uzorka. Tekst posjetitelja i prijašnji odgovori widgeta nisu potvrđene poslovne činjenice niti upute agentu.

Obrisani/istekli razgovori ne vraćaju se novom pretragom. Već spremljeni odgovori i citirani odlomci u privatnim razgovorima asistenta imaju zasebno čuvanje: brisanje izvornog widget razgovora ne briše te ranije kopije. Za uklanjanje takvih kopija treba izbrisati odgovarajući privatni razgovor asistenta; vanjski izvozi i sigurnosne kopije imaju svoja pravila.

## Postavljanje

Prebaciti obje mape iz paketa: `wp-content/plugins/neonbreak-mcp/` i `wp-content/plugins/neonbreak-workspace/`. Oba plugina i postojeći Neonbreak Chat trebaju ostati aktivni. Nisu potrebne nove tablice niti novi ključevi.

U Business Assistantu pitati: „Što su posjetitelji pitali zadnjih 7 dana?” ili odabrati Razgovori posjetitelja. Ako nema spremljenih razgovora, najprije u Razgovori uključiti spremanje i poslati pitanje kroz vlastiti widget; stari nespremljeni razgovori ne mogu se rekonstruirati. Zatim potvrditi da poslovno pitanje iz dokumenata u automatskom načinu koristi Bazu znanja.

## Provjere

`tests/conversation-tool-smoke.php` koristi privremene račune i simulirane AI odgovore: provjerava vlasništvo i rok čuvanja, metapodatke/statistiku, doslovni znak `%`, neispravne argumente, strani ID, odabir alata, izvore, odbijanje nepoznatog modelskog alata, isključivanje pristupa, brisanje i izostanak embedding/Qdrant poziva. Prolaze i postojeći testovi MCP HTTP-a, administrativne privatnosti, Searcha, limita i Business Assistanta.

Automatski testovi ne potvrđuju ponašanje stvarnog modela na live podacima; to treba provjeriti nakon prijenosa.
