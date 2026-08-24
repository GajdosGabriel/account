# Nasadenie

Konfigurácia, ktorá na serveri žije mimo repozitára. Je tu preto, aby sa nedala
zmeniť potichu — keď sa niečo mení na serveri, mení sa to aj tu.

Produkcia: `gabriel@78.47.38.184`, projekt v `/var/www/account`, Debian 13,
nginx 1.26, PHP 8.4, MariaDB 11.8. Na tom istom stroji beží aj **anonymizer**.

## Adresy

| adresa | čo to je |
|---|---|
| `http://78.47.38.184:8080` | **kanonická** — `APP_URL` ukazuje sem |
| `https://account.78-47-38-184.sslip.io` | záložná, s Let's Encrypt certifikátom |

Prečo dve. Pôvodne bola kanonická tá sslip.io, ale sieťový filter v prostredí
zákazníka (Fortinet) blokuje celú kategóriu *Dynamic DNS*, do ktorej sslip.io
patrí — hostname je odtiaľ nedostupný a HTTPS je navyše rozbaľované a
podpisované certifikátom brány. Holá IP aj neštandardný port cez ten filter
prejdú, preto je kanonická adresa `IP:8080`. HTTPS na IP adresu certifikát
vydať nejde, takže táto cesta beží po HTTP a `SESSION_SECURE_COOKIE` je `false`.

Keď projekt dostane vlastnú doménu, stačí prepísať `APP_URL` a
`SESSION_SECURE_COOKIE=true` — `AppServiceProvider` si schému odvodí z `APP_URL`
a `URL::forceScheme('https')` sa zapne samo.

## Súbory

| Súbor tu | Kam patrí |
|---|---|
| `nginx/account-ip-8080.conf` | `/etc/nginx/sites-available/account-ip` |
| `nginx/account-https.conf` | `/etc/nginx/sites-available/account` |
| `php-fpm/account.conf` | `/etc/php/8.4/fpm/pool.d/account.conf` |
| `cron/account` | `/etc/cron.d/account` |

Symlinky nginx vhostov do `sites-enabled/` treba vytvoriť ručne, potom
`sudo nginx -t && sudo systemctl reload nginx`.

## HTTP_HOST a port — pozor

Debianov `/etc/nginx/fastcgi_params` nastavuje `HTTP_HOST` na `$host`, teda
**bez portu**. Je to zámerný bezpečnostný workaround, aby sa k aplikácii
nedostala surová `Host` hlavička od klienta.

Pre aplikáciu na neštandardnom porte to znamená, že Laravel generuje
presmerovania bez portu — `http://78.47.38.184/login` namiesto
`http://78.47.38.184:8080/login`. Na porte 80 pritom beží anonymizer, takže
prihlásenie skončilo v úplne inej aplikácii.

Vhost pre 8080 to preto prepisuje:

```nginx
fastcgi_param HTTP_HOST $host:$server_port;
```

`$host` je nginxom overený hostname a `$server_port` je port, na ktorom nginx
reálne počúva — ani jedno klient neovplyvní, takže sa pôvodné bezpečnostné
opatrenie neruší. Od nginx 1.30 sa dá použiť odporúčané
`$host$is_request_port$request_port`; 1.26 tie premenné nepozná.

## Izolácia od anonymizera

Dva projekty na jednom stroji si nesmú vidieť do tajomstiev:

- vlastný PHP-FPM pool pod systémovým používateľom `account`, socket
  `/run/php/php8.4-fpm-account.sock`; anonymizer beží pod `www-data`
- `.env` je `640 gabriel:account`
- `bootstrap/cache/` a `storage/` sú `750`, súbory `640` — `config.php`
  obsahuje `APP_KEY` aj heslo k databáze
- databázový používateľ `account` má grant výhradne na `account.*`

Po každom `config:cache` treba práva nastaviť znova, artisan ich vyrobí
s predvoleným umask:

```bash
sudo chown gabriel:account bootstrap/cache/config.php && sudo chmod 640 bootstrap/cache/config.php
```

## Scheduler

`cron/account` spúšťa `schedule:run` pod používateľom `account`. Projekt má
`QUEUE_CONNECTION=sync`, takže **queue worker nepotrebuje** — na rozdiel od
anonymizera. Naplánované sú `subscriptions:lifecycle`, `webhooks:retry`,
`invoices:generate` a `invoices:remind`.

## Postup nasadenia

```bash
cd /var/www/account
git pull --ff-only origin master
composer install --no-dev --optimize-autoloader
php artisan migrate --force
sudo -u gabriel php artisan config:cache && sudo -u gabriel php artisan route:cache && sudo -u gabriel php artisan view:cache
sudo chown gabriel:account bootstrap/cache/config.php && sudo chmod 640 bootstrap/cache/config.php
sudo systemctl restart php8.4-fpm
```

Frontend je Inertia + Vite a `public/build` je v `.gitignore`, takže **build sa
robí lokálne a nahráva sa cez `scp`**:

```bash
npm run build
scp -r public/build gabriel@78.47.38.184:/var/www/account/public/
```

Node na serveri nainštalovaný nie je. Build trvá ~2 s a má ~420 KB, takže sa to
neoplatí meniť.
