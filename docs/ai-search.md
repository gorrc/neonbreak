# AI Search — početna implementacija

Status: implementirano lokalno; prije korištenja potvrditi na liveu.

## Korištenje

1. Otvorite Workspace → AI Search računom s aktivnim Advanced pristupom.
2. Fireworks ključ koristi se iz AI Chata, a dokumentima se upravlja u zasebnoj Bazi znanja istog računa.
3. Testirajte upit u obrascu. Rezultat su odlomci i JSON, bez generiranog odgovora.
4. Za integraciju kliknite Izradi Search ključ i odmah ga sigurno sačuvajte.
5. Ključ se prikazuje jednom. Zamjena poništava stari ključ, a poništavanje zaustavlja API pristup.

## Endpoint

POST https://neonbreak.com/wp-json/neonbreak/v1/search

Primjer serverskog poziva:

```sh
curl -X POST 'https://neonbreak.com/wp-json/neonbreak/v1/search' \
  -H 'Content-Type: application/json' \
  -H 'X-Neonbreak-Search-Key: YOUR_SEARCH_KEY' \
  --data '{"query":"uvjeti povrata","limit":3}'
```

Alternativno je podržano Authorization: Bearer YOUR_SEARCH_KEY.
Ključ nikada ne stavljati u javni JavaScript, widget ili URL.
Ne koristi se Qdrant ključ niti Fireworks ključ kao ključ ovog API-ja.

## Rezultat

Rezultat sadrži query, results i usage. Svaki odlomak sadrži document_id,
document, chunk, text, source_url i score. Ako izvorni URL nije zabilježen,
source_url je null. Ocjena sličnosti nije vjerojatnost točnog odgovora.
Najbliži rezultat može biti slabo relevantan; nema automatskog praga relevantnosti.
Ako nema aktivnih indeksiranih dokumenata, results je prazan niz.

## Limiti početne verzije

- Upit: do 1500 znakova.
- Rezultati: 1–5 odlomaka.
- Do 10 zahtjeva u minuti i 100 dnevno po korisniku.
- Dnevni reset: 00:00 UTC.
- Workspace test i API dijele isti Search limit, odvojen od chat widget limita.
- Prihvaćeni zahtjevi troše limit i ako dohvat ne uspije.
- Fireworks embeddings upita plaća korisnik; model ne generira chat odgovor.
- Koristi postojeće dokumente i iste limite Advanced baze znanja.

## Pristup i infrastruktura

Početna verzija koristi postojeću Advanced aktivaciju. Zasebni paketi nisu definirani.
Svaki Search ključ pristupa samo dokumentima vlasnika računa. API ne prihvaća
parametar tenant niti korisnički ID za odabir druge baze. Qdrant klaster i kolekcija
zajednički su; odvajanje je logičko putem tenant oznake i popisa aktivnih dokumenata.
Nadogradnja Qdrant kapaciteta izvodi se centralno bez promjene korisničkog endpointa,
ako putanja i kolekcija ostanu kompatibilni.

API vraća HTTP 401 za neispravan ključ, 403 za neaktivan pristup, 400 za neispravan
upit, 429 za dosegnuti limit, 503 za nedostajući Fireworks ključ i 502 za grešku dohvata.

## Provjera prije live korištenja

1. Testirati poznati upit i JSON rezultat u Workspaceu.
2. Napraviti jedan serverski API poziv novim Search ključem.
3. Poništiti ključ i provjeriti HTTP 401 za stari ključ.
4. Nakon zamjene dokumenta provjeriti da Search vraća novu verziju.

Lokalne automatizirane provjere: tests/ai-search-smoke.php.
Koriste privremene korisnike i simulirane vanjske API odgovore; ne šalju zahtjeve
Fireworksu ni Qdrantu i ne zamjenjuju stvarni live test.
