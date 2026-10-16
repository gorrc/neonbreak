# Planirane nadogradnje

## Povijest razgovora

Status: implementirano lokalno za korisniÄŤke widgete i provjereno automatiziranim testovima. Potrebna je provjera na liveu. Spremanje je iskljuÄŤeno dok ga vlasnik ne ukljuÄŤi u Workspaceu. Glavni Neonbreak asistent nakon prijenosa na Workspace koristi istu povijest raÄŤuna.

- [x] Spremati razgovore i poruke u WordPress bazu, odvojeno po vlasniku chata.
- [x] Spremati svaku prihvaÄ‡enu korisniÄŤku poruku i odgovor ÄŤim nastanu; zabiljeĹľiti neuspjeĹˇne obrade bez dupliciranja poruka pri ponovnom pokuĹˇaju.
- [x] OmoguÄ‡iti nastavak razgovora u istoj kartici putem privatnog tokena; javni ID widgeta nije dozvola za ÄŤitanje povijesti.
- [x] Dodati pregled, pretragu teksta, brisanje i JSON izvoz jednog razgovora vlasniku chata u Workspaceu.
- [x] OmoguÄ‡iti ukljuÄŤivanje/iskljuÄŤivanje spremanja po klijentu.
- [x] Ponuditi rok 7/30/90 dana nakon zadnje aktivnosti, s poÄŤetnim izborom 30 dana. Istekla povijest se skriva i briĹˇe zakazanim ÄŤiĹˇÄ‡enjem.
- [x] Dodati obavijest o spremanju u korisniÄŤki widget.
- [ ] Uskladiti opis privatnosti i odabrati rok prema svrsi prije ukljuÄŤivanja na liveu.
- [x] Neaktivan razgovor: 30 minuta bez aktivnosti. Limiti: 1.000 razgovora po raÄŤunu, 500 poruka po razgovoru.
- [ ] Dogovoriti politiku ÄŤuvanja vanjskih sigurnosnih kopija i izvoza; brisanje u aplikaciji ih ne briĹˇe automatski.
- [ ] Potvrditi spremanje, osvjeĹľavanje widgeta, vlasniÄŤki pregled, izvoz i brisanje na liveu.

Razgovori se ne ureÄ‘uju ruÄŤno; ÄŤuvaju se stvarne poruke i statusi. Nema spremanja IP adrese niti prikupljanja e-maila posjetitelja u ovoj fazi. Vlasnik vidi samo svoj raÄŤun. Nastavak u pregledniku koristi privatni sessionStorage token, bez spremanja transkripta u sessionStorage. Model i dalje prima ograniÄŤenu povijest zadnjih poruka, a razgovori se ne dodaju automatski u RAG bazu.

## AI Business Assistant â€” sljedeÄ‡a faza

- [x] Zasebno radno suÄŤelje i ograniÄŤeni agentski odabir i izvrĹˇavanje jednog alata po pitanju.
- [x] Prvi alat: postojeÄ‡i AI Search nad korisniÄŤkom bazom znanja.
- [x] Drugi alat: kontrolirana pretraga povijesti, ograniÄŤeni dohvat razgovora i statistika; bez neograniÄŤenog SQL pristupa modelu.

## AI Business Assistant
- [x] Privatno razgovorno suÄŤelje s vlastitom povijeĹˇÄ‡u, brisanjem i JSON izvozom.
- [x] PretraĹľivanje postojeÄ‡e baze znanja kroz interni AI Search i generiranje odgovora korisnikovim Fireworks raÄŤunom.
- [x] Advanced prava pristupa, odvajanje vlasnika, limiti i 90-dnevno ÄŤuvanje privatnih razgovora.
- [ ] Potvrditi stvarni odgovor na liveu nakon postavljanja novog paketa.
- [x] Dodati alat za pregled razgovora posjetitelja, brojeve razdoblja i saĹľetak ograniÄŤenog uzorka.
- [ ] Dodati pripremu konfiguracije chata s pregledom i potvrdom prije spremanja.

## MCP alati
- [x] Zaseban PHP WordPress plugin `neonbreak-mcp` sa sluĹľbenim SDK-om.
- [x] `search_knowledge` i prebacivanje Business Assistanta na zajedniÄŤki MCP sloj.
- [x] Autentificirani HTTP endpoint, izolirane sesije i provjere vlasniĹˇtva/argumenata.
- [ ] Aktivirati novi plugin na liveu i potvrditi pitanje iz stvarne baze znanja.
- [ ] Dodati sljedeÄ‡i alat nakon definiranja opsega (analiza razgovora / nacrt konfiguracije chata).

## Administracija platforme
- [x] WordPress izbornik Neonbreak AI: klijenti, usluge, potroĹˇnja, tehniÄŤki status i zahtjevi.
- [x] Brojevi razgovora bez administratorskog prikaza sadrĹľaja, naslova i izvora.
- [x] Sanitizirani tehniÄŤki dogaÄ‘aji pretrage i asistenta, bez poruka i kljuÄŤeva.
- [x] Aktivacija nadogradnje preko postojeÄ‡eg postupka i pregleda zahtjeva.
- [ ] Dijeljenje konkretnog razgovora s podrĹˇkom uz suglasnost, ograniÄŤenje pristupa i evidenciju pregleda.

## Limiti po klijentu
- [x] Odvojeni administratorski limiti za AI Search / dan, AI Search / minuta i kapacitet dokumenata.
- [x] UreÄ‘ivanje kroz PotroĹˇnja uz oznake tko postavlja pojedini limit.
- [x] ProvoÄ‘enje limita kroz Search API / MCP / Assistant i zajedniÄŤki unos dokumenata.
- [x] OÄŤuvanje brojaÄŤa i postojeÄ‡ih dokumenata kod promjene limita.

## Naplata i Revolut â€” kasnija faza

Cilj: vlastita administracija prati klijente, ugovorene usluge, raÄŤune i uplate; Revolut sluĹľi kao izvor potvrÄ‘enih transakcija i neovisna financijska evidencija za provjeru.

- [ ] Provjeriti dostupnost i ovlasti Revolut Business API-ja na naĹˇem raÄŤunu te pripremiti testno povezivanje.
- [ ] Dodati evidenciju ugovorenog paketa, cijene, valute, obraÄŤunskog razdoblja i poveznice/broja sluĹľbenog raÄŤuna. Izdavanje sluĹľbenih raÄŤuna povezati s odabranim sustavom za raÄŤune.
- [ ] Dodati pregled uplata i dospijeÄ‡a: ÄŤeka uplatu, djelomiÄŤno plaÄ‡eno, plaÄ‡eno, dospjelo, povrat; omoguÄ‡iti ruÄŤnu evidenciju uz zapis tko je i kada napravio promjenu.
- [ ] Prvo povezati dolazne bankovne uplate preko Revolut Business API-ja i pridruĹľivati ih raÄŤunima prema referenci, iznosu i valuti; nepodudarne uplate ostaviti za ruÄŤni pregled.
- [ ] ObraÄ‘ivati provjerene webhook obavijesti bez dvostrukog knjiĹľenja te periodiÄŤno usklaÄ‘ivati transakcije s Revolutom radi propuĹˇtenih obavijesti i promjena statusa.
- [ ] Odvojeno voditi financijski status i pravo pristupa: plaÄ‡eno razdoblje, probni pristup, ruÄŤno produĹľenje i dogovoreni limiti. Povezati potvrÄ‘ene uplate s aktivacijom/produĹľenjem prema definiranim pravilima.
- [ ] Dodati administrativne podsjetnike prije isteka i pregled dugovanja; definirati postupak za kaĹˇnjenje, povrat i prekid usluge prije automatiziranog iskljuÄŤivanja pristupa.
- [ ] Testirati djelomiÄŤne uplate, duplikate, propuĹˇtene obavijesti, povrate i ruÄŤno usklaÄ‘ivanje prije ukljuÄŤivanja na liveu.
- [ ] Naknadno razmotriti Revolut Merchant API za kartiÄŤnu naplatu i automatske pretplate, ako bude potrebno, uz provjeru aktivacije Merchant raÄŤuna.

Ovo je plan za kasniju implementaciju; Revolut trenutaÄŤno nije povezan i automatska naplata nije ukljuÄŤena.

## Cron poslovi â€” provjera i postavljanje na liveu

- [ ] Popisati sve postojeÄ‡e WordPress zakazane dogaÄ‘aje i njihove callbackove, ukljuÄŤujuÄ‡i ostale aktivne pluginove; potvrditi uÄŤestalost, zadnje izvrĹˇavanje, zakaĹˇnjele i duplicirane dogaÄ‘aje.
- [ ] Provjeriti dnevni `neonbreak_chat_cleanup`: isti dogaÄ‘aj pokreÄ‡e viĹˇe poslova, ukljuÄŤujuÄ‡i ÄŤiĹˇÄ‡enje brojaÄŤa zahtjeva, istek povijesti widgeta i Business Assistanta te ÄŤiĹˇÄ‡enje Search/Assistant brojaÄŤa. Provjeriti da je dogaÄ‘aj doista zakazan i na liveu.
- [ ] Postaviti serverski cron na live hostingu koji redovito pokreÄ‡e WordPress cron neovisno o posjetima; odabrati dostupnu metodu i interval prema poslovima i ograniÄŤenjima hostinga.
- [ ] Tek nakon uspjeĹˇnog testa serverskog crona iskljuÄŤiti pokretanje WP-Crona kroz posjete pomoÄ‡u `DISABLE_WP_CRON`, uz oÄŤuvanje svih zakazanih dogaÄ‘aja.
- [ ] Provjeriti vremensku zonu, zaĹˇtitu od preklapanja, zapis izvrĹˇavanja i obavijest administratoru kod neuspjeha ili duljeg izostanka izvrĹˇavanja.
- [ ] Pri povezivanju Revoluta dodati periodiÄŤno usklaÄ‘ivanje transakcija i podsjetnike za dospijeÄ‡e/istek usluge kao zasebne zakazane poslove.

Cron na hostingu joĹˇ treba provjeriti i postaviti; postojeÄ‡i WordPress dogaÄ‘aji sami po sebi ne potvrÄ‘uju da se redovito izvrĹˇavaju.


## MCP razgovori i odabir alata
- [x] `search_conversations` uz vlasniÄŤku provjeru, rok ÄŤuvanja, ograniÄŤene odlomke i statistiku.
- [x] Modelski odabir read-only alata i ruÄŤno ograniÄŤenje na bazu znanja ili razgovore u suÄŤelju.
- [ ] Potvrditi automatski odabir oba alata sa stvarnim modelom na liveu.
- [ ] Po potrebi razviti potpunu frekvencijsku analizu tema izvan ograniÄŤenog uzorka.
- [ ] Razraditi uklanjanje ranije citiranih kopija razgovora iz privatnih odgovora asistenta pri zahtjevu za potpunim brisanjem.

- [x] Business Assistant: obavezan ručni izbor izvora, izravan MCP poziv bez modelskog routinga, razdoblje i ključna riječ za povijest. Lokalni testovi prošli; opis: docs/assistant-required-source.md.
- [ ] Nakon FTP prijenosa provjeriti oba izvora u live Business Assistantu.
