# PC Patrol Private Bin

Laravel-applicatie op basis van de officiële Laravel Svelte starter kit. Deze repository bevat
de initiële installatie: de applicatie draait volledig in Docker via Laravel Sail met MySQL.

Op dit moment staat alleen de basis klaar (welkomstpagina, dashboard en de authenticatie uit de
starter kit). De functionaliteit van Private Bin moet nog gebouwd worden.

## Stack

| Onderdeel | Versie |
| --- | --- |
| PHP | 8.4 |
| Laravel | 13 |
| Inertia | 3 |
| Svelte | 5 |
| Tailwind CSS | 4 |
| MySQL | 8.4 |
| Auth | Laravel Fortify (incl. 2FA en passkeys) |

Verder in gebruik: Wayfinder (getypeerde route-functies voor de frontend), Pest voor tests,
Pint voor code-style en Larastan (level 7) voor statische analyse.

## Vereisten

- Docker Desktop
- Node.js 22+ en npm (het bouwen van de frontend gebeurt op de host)

PHP en Composer heb je lokaal niet nodig; die draaien in de container.

## Installatie

```bash
git clone git@github.com:pcpatrol/pcpatrol-private-bin.git
cd pcpatrol-private-bin

cp .env.example .env

# Composer-dependencies installeren zonder lokale PHP-installatie
docker run --rm -v "$(pwd)":/opt -w /opt laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate

npm install
npm run build
```

De applicatie draait daarna op **http://localhost:8001**.

### Poorten

De standaardpoorten van Sail zijn aangepast, zodat dit project naast de andere Sail-projecten
op dezelfde machine kan draaien:

| Dienst | Host-poort | Instelling in `.env` |
| --- | --- | --- |
| Applicatie | 8001 | `APP_PORT` |
| Vite dev server | 5175 | `VITE_PORT` |
| MySQL | 3308 | `FORWARD_DB_PORT` |

Draai je dit project als enige, dan kun je die waarden gerust terugzetten naar 8000, 5173 en 3306.
Pas `APP_URL` dan mee aan.

## Dagelijks gebruik

```bash
./vendor/bin/sail up -d      # containers starten
./vendor/bin/sail down       # containers stoppen
npm run dev                  # Vite dev server met hot reload
```

Handig is een alias voor Sail, zodat je `sail` kunt typen in plaats van `./vendor/bin/sail`:

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

### Database benaderen

Vanaf de host verbind je op `127.0.0.1:3308` met gebruiker `sail` en wachtwoord `password`.
Of open direct een MySQL-prompt in de container:

```bash
./vendor/bin/sail mysql
```

## Tests en kwaliteitscontrole

Sail draait de tests tegen de aparte `testing`-database, die de MySQL-container bij het
aanmaken automatisch voor je klaarzet.

```bash
./vendor/bin/sail test                  # volledige testsuite
./vendor/bin/sail test --filter=naam    # één test
./vendor/bin/sail composer lint         # code-style corrigeren (Pint)
./vendor/bin/sail composer types:check  # statische analyse (Larastan)
npm run types:check                     # TypeScript- en Svelte-check
```

`./vendor/bin/sail composer test` voert style-check, statische analyse en de testsuite
achter elkaar uit. Dat is dezelfde controle die CI draait.

## Projectstructuur

```
app/                     Applicatiecode (controllers, models, Fortify-acties)
resources/js/pages/      Svelte-pagina's, gekoppeld via Inertia::render / Route::inertia
resources/js/components/ Herbruikbare Svelte-componenten
routes/web.php           Webroutes
routes/settings.php      Routes voor profiel- en beveiligingsinstellingen
database/migrations/     Migraties
tests/Feature/           Feature-tests (Pest)
compose.yaml             Sail-servicedefinities (app + MySQL)
```
