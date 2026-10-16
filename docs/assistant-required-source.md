# Obavezni odabir izvora u Business Assistantu

Prije slanja korisnik odabire Bazu znanja ili Razgovore posjetitelja. Nema modelskog odabira alata ni automatskog načina rada. Poslužitelj izravno poziva odgovarajući MCP alat, a model potom sastavlja odgovor iz rezultata.

Za razgovore korisnik odabire 7, 30 ili 90 dana (zadano 7) i neobaveznu doslovnu ključnu riječ. Prazna ključna riječ vraća nedavne razgovore u odabranom razdoblju. Stvarno dostupan period ovisi i o postavkama čuvanja povijesti. Sažetak tema predstavlja ograničen uzorak, ne potpun rang svih pitanja.

Ova izmjena zamjenjuje raniji opis automatskog modelskog odabira u dokumentima ai-business-assistant.md, mcp-conversations.md i neonbreak-mcp.md.

Lokalno provjereno: PHP/JS sintaksa, business-assistant-smoke.php i conversation-tool-smoke.php, uključujući odbijanje zahtjeva bez izvora prije spremanja poruke ili vanjskog poziva. Vanjski AI odgovori simulirani; live test slijedi nakon FTP prijenosa.
