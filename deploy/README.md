# Nasadenie

> **Account beží na Websupporte – a nikde inde.**
>
> Server `78.47.38.184` (`gabriel@78.47.38.184`) patrí **Anonymizéru a Spisoru**,
> nie Accountu. Account sa tam nenasadzuje – ani `git pull`, ani `scp`, ani
> konfigurácia nginx/php-fpm/cron. Ak ťa niečo (starý návod, história,
> pamäť asistenta) posiela na tú IP, je to omyl.

## Produkcia

| | |
|---|---|
| Adresa | `https://account.zastavy-vlajky.sk` (`APP_URL`) |
| SSH | `ssh uid152568@shell.r2.websupport.sk -p<port>` – port je pri každej aktivácii konzoly iný, pozri [SSH konzola](#ssh-konzola) |
| Projekt | `~/zastavy-vlajky.sk/sub/account` (subdoména `account` domény zastavy-vlajky.sk) |
| PHP | 8.5 (`/usr/php85/bin/php`, v shelli `php`) |
| Databáza | MariaDB `db.r2.websupport.sk` |
| Fronta | `QUEUE_CONNECTION=sync` – worker netreba |

Na tom istom hostingu sú aj ďalšie subdomény zastavy-vlajky.sk (`sub/obchod`,
`sub/test`, `sub/x`) a hlavný web (`web/`). Nesiahaj do nich.

Koreňový `.htaccess` projektu presmeruje všetko do `public/`
(`RewriteRule ^(.*)$ public/$1`), takže dokument root subdomény môže ostať
na koreni projektu.

## SSH konzola

Hosting má len **základnú shell konzolu**: po aktivácii beží **1 hodinu**
a pri každej aktivácii dostane **nový SSH port**. Keď SSH hlási
`Network is unreachable` alebo `Connection refused`, konzola je vypnutá –
treba ju znova aktivovať:

1. [admin.websupport.sk](https://admin.websupport.sk) → hosting
   `gabonator_1` → doména `zastavy-vlajky.sk`.
2. V ľavom menu domény *Pokročilá konfigurácia* → *Konzola*
   (priamo: `https://admin.websupport.sk/sk/assignment/143782/shell`).
3. *Základná shell konzola* → **Aktivovať** (zadarmo). Po pár sekundách
   sa ukáže *SSH Port* a *SSH príkaz*.
4. Prihlásenie ide cez SSH kľúč (`ssh-ed25519 … gajdosgabo@gmail.com`
   je v sekcii *SSH kľúče*), heslo z administrácie netreba.

Prémiová konzola (1 €/mesiac) má statický port a neobmedzenú životnosť –
ak by prepisovanie portu prekážalo.

## Plánovač

`schedule:run` spúšťa cron nastavený v administrácii Websupportu (nie
`crontab` v shelli – ten je prázdny). V logu ho poznáš podľa
`/usr/php85/bin/php artisan …`. Čo je naplánované, ukáže
`php artisan schedule:list`.

## Postup nasadenia

Lokálne: commit (hook postaví `public/build`) a `git push origin master`.
Potom na serveri:

```bash
cd ~/zastavy-vlajky.sk/sub/account
git pull --ff-only origin master
composer install --no-dev --optimize-autoloader   # len keď sa zmenil composer.lock
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache
```

Overenie: `git log --oneline -1` sedí s lokálnym `master`,
`curl -I https://account.zastavy-vlajky.sk/login` vráti 200 a
`storage/logs/laravel.log` nemá nové chyby.

## Frontend

Frontend je Inertia + Vite. Na server ide hotový build, preto sa
**`public/build` commituje** a dostane sa tam spolu s kódom cez `git pull`.

Build robí lokálne git hook `.githooks/pre-commit`: keď commit mení
`resources/js`, `resources/css`, `resources/views`, `package*.json` alebo
`vite.config.js`, spustí `npm run build` a pridá `public/build` do commitu.
Zapnutie (raz na klon):

```bash
git config core.hooksPath .githooks
```

Bez hooku sa zmena vo Vue na web nedostane a nič na to neupozorní — server
ďalej servíruje starý `app.js` (tak „zmizol“ inbox).
