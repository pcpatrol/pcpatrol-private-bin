# PC Patrol Private Bin

Een zelf gehoste pastebin waarin de server de inhoud niet kan lezen, in de huisstijl van
PC Patrol. Gebouwd op de Laravel Svelte starter kit en draaiend in Docker via Laravel Sail
met MySQL.

## Hoe de versleuteling werkt

Alles gebeurt in de browser van de bezoeker:

1. De browser maakt een willekeurige sleutel van 256 bit.
2. Die sleutel wordt samen met een eventueel wachtwoord via PBKDF2-SHA256 (310.000 rondes,
   eigen salt) uitgerekt tot een AES-256-GCM-sleutel.
3. De tekst wordt daarmee versleuteld. Alleen het versleutelde resultaat gaat naar de server.
4. De sleutel komt achter het `#` in de deel-link te staan. Browsers sturen dat deel van een
   URL nooit mee, dus de server krijgt hem nooit te zien.

De weergavevorm en de "vernietigen na lezen"-vlag worden als _additional authenticated data_
meeversleuteld. Een server die daaraan sleutelt laat het ontsleutelen mislukken in plaats van
dat het onopgemerkt blijft.

Wat dit wel en niet biedt: wie de link heeft, kan de paste lezen. De beveiliging zit in het
geheim houden van de link, niet in een account. Wie de server beheert kan een paste wel
verwijderen, maar niet uitlezen.

## Functies

- Vervaltermijn van 5 minuten tot nooit, met een uurlijkse opruimtaak
- Vernietigen na lezen, met een bevestigingsscherm zodat een linkvoorbeeld de paste niet wist
- Optioneel extra wachtwoord bovenop de sleutel in de link
- Weergave als platte tekst, Markdown of code met syntax highlighting
- Eigen verwijderlink voor de maker, met een token dat alleen als hash wordt bewaard

## Stack

| Onderdeel    | Versie                                  |
| ------------ | --------------------------------------- |
| PHP          | 8.4                                     |
| Laravel      | 13                                      |
| Inertia      | 3                                       |
| Svelte       | 5                                       |
| Tailwind CSS | 4                                       |
| MySQL        | 8.4                                     |
| Auth         | Laravel Fortify (incl. 2FA en passkeys) |

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

| Dienst          | Host-poort | Instelling in `.env` |
| --------------- | ---------- | -------------------- |
| Applicatie      | 8001       | `APP_PORT`           |
| Vite dev server | 5175       | `VITE_PORT`          |
| MySQL           | 3308       | `FORWARD_DB_PORT`    |

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
./vendor/bin/sail test                  # volledige PHP-testsuite
./vendor/bin/sail test --filter=naam    # één test
npx vp test                             # tests van de versleuteling in de browser
./vendor/bin/sail composer lint         # code-style corrigeren (Pint)
./vendor/bin/sail composer types:check  # statische analyse (Larastan)
npm run types:check                     # TypeScript- en Svelte-check
```

`./vendor/bin/sail composer test` voert style-check, statische analyse en de testsuite
achter elkaar uit. Dat is dezelfde controle die CI draait.

## Projectstructuur

```
app/Http/Controllers/PasteController.php  Opslaan, tonen, vrijgeven en verwijderen
app/Models/Paste.php                     Het versleutelde record
app/Console/Commands/PrunePastes.php     Ruimt verlopen pastes op
config/paste.php                         Vervaltermijnen en maximale omvang
resources/js/lib/paste-crypto.ts         Versleuteling in de browser
resources/js/lib/paste-render.ts         Markdown en code, gesaneerd
resources/js/pages/paste/                Schrijven, lezen en verwijderen
resources/js/layouts/PasteLayout.svelte  Huisstijl-omlijsting
tests/Feature/PasteTest.php              Server-side gedrag (Pest)
compose.yaml                             Sail-services (app + MySQL)
```
