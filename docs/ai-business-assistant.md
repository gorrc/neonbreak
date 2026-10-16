# AI Business Assistant — prva verzija

Privatni alat u Workspaceu: `?view=assistant`. Prva funkcija je razgovor s vlastitom bazom znanja. Koristi postojeću bazu dokumenata i tenant filtre AI Searcha; ne uvodi novu Qdrant kolekciju ni Search ključ. Pretraga sada ide kroz zaseban PHP plugin `neonbreak-mcp` sa službenim MCP SDK-om; detalji su u `docs/neonbreak-mcp.md`. Poslužitelj prvo traži od modela izbor alata, izvršava validirani MCP poziv i zatim generira odgovor kroz korisnikov Fireworks račun. Model sada odabire između baze znanja i razgovora widgeta kroz kontrolirani strukturirani odabir jednog alata; detalji su u `docs/mcp-conversations.md`.

## Pristup i troškovi

- Za nove razgovore i pitanja potreban je postojeći Advanced pristup.
- Fireworks ključ i model preuzimaju se iz korisnikovih postavki AI Chata.
- Upiti troše postojeći AI Search limit: zadano 10/min i 100/dan, uz administratorsku prilagodbu po klijentu. Fireworks zasebno naplaćuje embeddings i generiranje odgovora.
- Povijest i brisanje ostaju dostupni vlasniku nakon isključenja Advanced pristupa.
- Javna povijest widgeta ostaje odvojena i zadržava svoje postojeće postavke.

## Razgovori

Nova tablica `PREFIX_nb_assistant_threads` kreira se automatski pri prvom učitavanju WordPressa. Potrebne su dozvole kreiranja tablice. Svaki zapis pripada prijavljenom korisniku. Identitet vlasnika ne dolazi iz obrasca.

Do 100 razgovora po računu, 100 poruka po razgovoru, 1.500 znakova po pitanju. Zadnjih 11 poruka daje kontekst modelu; prethodno pitanje pomaže pretrazi kod potpitanja. Starije tvrdnje asistenta nisu potvrđeni poslovni podaci. Pretraživanje svih dokumenata ili razgovora odjednom nije obećano: dohvaća se najviše pet odlomaka po pitanju.

Poruka korisnika sprema se prije vanjskih poziva. Dovršeni odgovor, izvori ili poruka greške spremaju se nakon obrade. Jednaki dovršeni zahtjev ne ponavlja vanjske pozive. Privremeno zaključavanje sprečava usporedno slanje u istom razgovoru. Prekinute zahtjeve treba provjeriti ponovnim učitavanjem prije sljedećeg slanja. Obrisani razgovori ne kreiraju se ponovno dovršetkom starog zahtjeva.

Povijest se skriva nakon 90 dana od zadnje aktivnosti i fizički briše postojećim WordPress cron čišćenjem. Brisanjem korisnika uklanjaju se njegovi razgovori. JSON izvoz je lokalno preuzimanje jednog učitanog razgovora. Vanjski izvozi i sigurnosne kopije imaju vlastiti rok čuvanja.

Prikaz je običan tekst; HTML iz modela se ne izvršava. Izvori su skupljeni u detalje. API ključevi i sistemske upute nisu dio spremljenih razgovora niti izvoza.

## Postavljanje i provjera

1. Prebaciti cijelu mapu `wp-content/plugins/neonbreak-workspace/` iz novog paketa. Bazu, konfiguraciju i ključeve ne kopirati s lokalnog računala.
2. Otvoriti Workspace i očistiti cache ako je potrebno.
3. Prijaviti se računom s aktivnim Advanced pristupom, spremljenim Fireworks ključem i barem jednim dokumentom.
4. Otvoriti AI Business Assistant, postaviti konkretno pitanje iz dokumenta i provjeriti odgovor/izvor.
5. Postaviti potpitanje te pitanje za koje dokument nema podatke. Provjeriti odbijanje izmišljanja.
6. Otvoriti drugi razgovor, ponovno učitati prvi, preuzeti JSON te izbrisati testni razgovor.
7. Drugim računom potvrditi da ne vidi prvi račun ni njegove razgovore.

Automatska provjera: `tests/business-assistant-smoke.php`, CLI, privremeni računi i simulirani vanjski servisi. Provjerava vlasništvo, tenant filter, korisnikov ključ, odgovor s izvorima, ponovljeni zahtjev, prazan dohvat, grešku modela, odbijanje stranog dokumenta, isključen pristup, brisanje i istek povijesti. Ne potvrđuje stvarni saldo, kvalitetu odgovora ni ispravnost live ključeva.

## Sljedeće funkcije (nisu dio ove verzije)

- Potpuna frekvencijska analiza tema nad cijelim korpusom; sada je dostupan kontrolirani alat s brojevima razdoblja i sažetkom ograničenog uzorka razgovora.
- Priprema konfiguracije chata i pregled prije potvrde spremanja.
- Odabir alata od strane modela kad postoji više alata.
