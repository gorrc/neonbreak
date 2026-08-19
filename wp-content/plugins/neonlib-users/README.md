# NeonLib Users

Početni WordPress plugin za NeonLib korisničke račune i korisničko sučelje.

## Trenutačni opseg

- vlastita uloga `neonlib_user`
- granularne NeonLib ovlasti
- registracija i prijava na frontendu
- potvrda e-maila jednokratnom poveznicom koja vrijedi 24 sata
- ponovno slanje e-maila za potvrdu
- zaključavanje korisničkih funkcija do potvrde adrese
- korisnički dashboard neovisan o aktivnoj temi
- automatsko stvaranje stranice `/neonlib-racun/` pri aktivaciji
- serverski HMAC klijent za NeonLib API
- automatsko povezivanje potvrđenog WordPress korisnika s NeonLib `account_id`
- spremanje `account_id` u `wp_usermeta` pod ključem `neonlib_account_id`
- dohvat, izrada, uređivanje i brisanje vlastitih subscriptiona
- objava nove immutable verzije iz JSON popisa dokumenata

Sve subscription akcije obrađuju se serverski, uz WordPress nonce, capability i
provjeru potvrđenog e-maila. API tajna nikada se ne šalje pregledniku.

## API konfiguracija

Tajne moraju ostati izvan plugina i WordPress baze. U `wp-config.php`, prije
retka koji učitava `wp-settings.php`, definirajte:

```php
define( 'NEONLIB_API_URL', 'http://localhost/mobileai-api' );
define( 'NEONLIB_API_CLIENT_ID', 'wordpress-primary' );
define( 'NEONLIB_API_CLIENT_SECRET', 'isti-dugi-nasumicni-secret-kao-na-api-serveru' );
define( 'NEONLIB_API_SITE_ID', 'localhost-mobileai' );
```

Na API serveru odgovarajuće vrijednosti su `WORDPRESS_CLIENT_ID`,
`WORDPRESS_CLIENT_SECRET` i `WORDPRESS_SITE_ID`. Produkcija mora koristiti HTTPS.

## Pokretanje

1. U WordPress administraciji otvorite **Plugins**.
2. Aktivirajte **NeonLib Users**.
3. Otvorite stranicu `/neonlib-racun/`.

Shortcode `[neonlib_account]` može se ručno postaviti i na drugu stranicu.
