# Povijest razgovora — prva verzija

Status: implementirano lokalno. Testovi koriste privremene korisnike i simulirane model odgovore. Live još treba potvrditi.

## Aktivacija

1. Prepisati oba plugina iz paketa: neonbreak-chat i neonbreak-workspace.
2. Nove tablice kreiraju se automatski na prvom WordPress zahtjevu. Ne prenosi se lokalna baza ni wp-config.php.
3. U Workspace → Razgovori odabrati spremanje i rok 7, 30 ili 90 dana od zadnje aktivnosti.
4. Prije uključivanja ažurirati informacije o privatnosti. Widget prikazuje obavijest o spremanju i roku.
5. Osvježiti stranicu s widgetom kako bi učitala nove postavke i skripte.

Početno je spremanje isključeno. Povijest se ne uvozi iz ranijih sesija.

## Ponašanje

- Prihvaćeno pitanje zapisuje se prije model poziva. Nakon obrade zapisuje se stvarni odgovor i dohvaćeni izvori.
- Greške i prekinute obrade vidljive su statusom uz pitanje, bez spremanja tehničkih tajni u poruku.
- Idempotentni request ID sprječava dupliciranje istog ponovljenog zahtjeva.
- Jedan razgovor obrađuje jednu poruku istodobno; napuštena zaključavanja istječu nakon 5 minuta.
- Posjetitelj nastavlja razgovor u istoj kartici. Privatni token je u sessionStorageu iframea; ako preglednik blokira tu pohranu, nastavak nakon osvježavanja nije moguć.
- Novi razgovor generira novi token. Stari zapis ostaje vlasniku do brisanja ili isteka.
- Zatvaranje kartice može ukloniti token. Ovo nije identifikacija posjetitelja kroz različite uređaje.
- Javni ID widgeta nije dovoljan za dohvat poruka: provjeravaju se potpis zahtjeva, vlasnik, domena i privatni token.
- U bazi je samo sažetak privatnog tokena. API ključevi i sistemske upute nisu dio transkripta.

## Upravljanje

Workspace → Razgovori daje popis, pretragu sadržaja, detalje, brisanje i JSON izvoz jednog razgovora.
Poruke se ne mijenjaju ručno. Izvoz ima stabilni type/version i UTC vremena.
Vlasnik vidi samo vlastite razgovore; globalni pregled tuđih računa nije implementiran.

## Limiti i čuvanje

- Najviše 1.000 razgovora po računu i 500 poruka po razgovoru.
- Neaktivnost se prikazuje nakon 30 minuta bez novih poruka.
- Istekli razgovori odmah su nedostupni kroz sučelje i nastavak widgeta; fizičko brisanje pokreće WordPress cron.
- Za pouzdano zakazivanje produkcijski hosting treba redovito izvršavati WordPress cron.
- Isključivanje spremanja zaustavlja nove zapise; postojeći ostaju do brisanja ili isteka.
- Brisanje ne uklanja sadržaj iz otvorene stranice posjetitelja, prethodno preuzetih JSON datoteka ni vanjskih sigurnosnih kopija.
- Pri obnovi sigurnosne kopije ponovno provjeriti rokove i prethodne zahtjeve za brisanje.

## Granice ove faze

Podržani su korisnički widgeti i njihov spremljeni pregled. Glavni Neonbreak chatbot nakon prijenosa na Workspace također koristi korisnički widget i iste postavke povijesti. Zasebni test odgovora u Advanced Chatu nije uključen u spremanje.
Povijest nije automatski RAG sadržaj. Modelu se i dalje šalje ograničen broj zadnjih poruka.
Business Assistant i njegovi alati za povijest tek su sljedeća faza.

## Provjera na liveu

Na testnom računu uključiti spremanje, otvoriti novi widget razgovor i poslati pitanje.
Provjeriti zapis u Workspaceu, osvježavanje widgeta, JSON izvoz, brisanje i isključivanje spremanja.
Ne koristiti osjetljive podatke u testu.

Automatizirane lokalne provjere:

- tests/conversation-history-smoke.php
- tests/conversation-history-widget.php

Obje se izvršavaju samo kroz CLI i uklanjaju privremene korisnike nakon testa.
