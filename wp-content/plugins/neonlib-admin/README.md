# NeonLib Admin

Odvojeno WordPress administratorsko sučelje za NeonLib admin JSON API.

## Konfiguracija

Uz postojeći `NEONLIB_API_URL`, u `wp-config.php` definirajte token aktivnog API
`SUPERADMIN` korisnika:

```php
define( 'NEONLIB_ADMIN_API_TOKEN', 'nlat_...' );
```

Token se generira samo na API serveru naredbom:

```bash
php scripts/create_admin_token.php admin@example.com "WordPress admin"
```

Plugin zahtijeva WordPress `manage_options`, koristi nonce za svaku promjenu i
nikada ne šalje token pregledniku. Sve mutacije dodatno auditira NeonLib API.
