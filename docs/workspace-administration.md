# Administracija AI Workspacea

U WordPress administraciji nova je stavka **Neonbreak AI**, dostupna samo korisnicima s `manage_options` ovlasti.

## Kartice

- **Klijenti:** ime, e-mail, ID, pristup, broj dokumenata, broj važećih razgovora widgeta i privatnih razgovora asistenta, zadnja spremljena aktivnost.
- **Usluge i alati:** status widgeta, zajednički Advanced pristup za Advanced Chat / AI Search / Assistant, indikator spremljenog Fireworks ključa. Aktivacija/isključivanje koristi postojeći nonce, ovlasti i postupak obavijesti. Promjena pristupa ne potvrđuje uplatu i ne briše podatke.
- **Potrošnja:** današnji brojači widgeta i AI Searcha te kapacitet dokumenata. To nisu tokeni niti novčani troškovi. Fireworks račun/saldo se ne dohvaća.
- **Tehnički status:** lokalna konfiguracija PHP/MCP/pohrane/Qdranta i kategorije događaja po računu. Provjera ne poziva vanjske servise. Spremljen ključ ne znači da je veza provjerena.
- **Zahtjevi za ponudu:** postojeći pregled izričito poslanih zahtjeva i aktivacija pristupa.
- **Podrška i privatnost:** granice pristupa i poveznica na zahtjeve. Dijeljenje pojedinačnih razgovora s podrškom još nije implementirano.

Pretraga po imenu/e-mailu, izbor Workspace / svih WordPress računa, paginacija po 20. Workspace računi prepoznaju se po postojećim postavkama ili oznaci prvog prijavljenog posjeta Workspaceu. Za novoregistrirane račune bez postavki i prvog posjeta služi izbor svih WordPress računa. Upit koristi jedan EXISTS umjesto više paralelnih usermeta spojeva.

## Privatnost

Administratorski pregled nema otvaranje, izvoz niti impersonaciju tuđih razgovora. Agregatni upiti po tablicama razgovora koriste COUNT / MAX i status; ne dohvaćaju stupce s porukama, naslovima privatnih razgovora, izvorima ili tokenima. Konfiguracija se čita samo radi izdvajanja statusa i limita; ključevi se ne dešifriraju. Dokumenti se prikazuju kao broj, bez naslova ili sadržaja.

`_nb_workspace_health` čuva samo kategoriju, vrijeme zadnjeg događaja/uspjeha/greške i približne ukupne brojače po komponenti `search` i `assistant`. Dopuštena je fiksna lista kategorija. Sirove poruke iznimki, upiti, odgovori i ključevi nisu dio evidencije. Greške se prikupljaju od uvođenja funkcije; stara povijest razgovora asistenta ne otvara se radi retroaktivne analize. Događaji stariji od 90 dana ne prikazuju se. Usporedni događaji mogu prebrisati neki metapodatkovni brojač; evidencija nije podloga za financijsku naplatu.

Razgovori ostaju na našem poslužitelju; ograničenje administratorskog sučelja nije end-to-end enkripcija. Sigurnosne kopije i direktan pristup bazi imaju zasebna pravila pristupa.

## Live

Prebaciti cijelu mapu `wp-content/plugins/neonbreak-workspace/` iz paketa i zamijeniti postojeće datoteke. Ne prenosi se baza niti korisnički ključevi. Postojeći Neonbreak Chat i Neonbreak MCP pluginovi ostaju aktivni. Nova shema baze nije potrebna.

U WordPressu otvoriti Neonbreak AI i provjeriti kartice. Ovaj paket uključuje prethodne dorade izgleda razgovora asistenta i pozicije izbornika jezika.

## Provjere

`tests/workspace-admin-smoke.php`: privremeni administrator i klijent, zabrana pristupa klijentu, pretraga/filtriranje računa, agregatni brojevi, provjera da SQL ne čita stupce sadržaja, testni tajni tekst ne izlazi u DTO/HTML, sirova greška se ne bilježi, render svih novih preglednih kartica. Testni računi i razgovori uklanjaju se.

Prolaze i postojeće provjere Business Assistanta, AI Searcha i MCP-a uz simulirane vanjske servise.


## Limiti po računu

Kartica Potrošnja sada uz zaglavlja stupaca označava tko postavlja limit: korisnik za widget, administrator za AI Search i dokumente. Svaki red ima Uredi limite s administrativnim obrascem i nonce zaštitom. Klijenti kroz Workspace, MCP ili uvoz postavki ne mogu mijenjati te vrijednosti.

Vrijednosti se spremaju u zasebni `_nb_workspace_limits` metapodatak. Zadano ostaje 100 Search zahtjeva/dan, 10/min i 10 dokumenata. Admin rasponi su 1–100.000/dan, 1–1.000/min i 1–100 dokumenata. Svi oblici AI Search poziva (API, test i Business Assistant/MCP) primjenjuju iste per-account limite. Javni widget i njegov RAG dohvat imaju zaseban postojeći widget brojač koji određuje korisnik.

Promjena ne resetira dnevni/minutni brojač niti briše postojeće dokumente. Smanjenje ispod trenutačnog broja blokira nove dokumente, a zamjena postojećeg ostaje dostupna. Dohvat postojećih dokumenata ostaje moguć. Ograničenja veličine pojedinog dokumenta/odlomaka ostaju nepromijenjena. Prikaz baze znanja, pregled kapaciteta u Workspaceu i JSON Search usage koriste iste spremljene vrijednosti.

Provjere: `tests/account-limits-smoke.php` i `tests/document-limits-smoke.php cap|replace` koriste privremene račune i simulirane servise; potvrđuju prava, provjeru vrijednosti, provođenje kvota i kapaciteta, zamjenu postojećeg dokumenta iznad smanjenog limita te očuvanje dokumenata i brojača.
