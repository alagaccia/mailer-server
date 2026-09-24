# Mail Bridge

Riscrittura in **Laravel + Inertia + Vue 3** del progetto "mailer": un ponte SMTP con API HTTP per accodare e inviare email, dashboard di monitoraggio, gestione utenti e installer web al primo avvio.

## Requisiti

- PHP 8.3+ (estensioni: pdo_mysql, mbstring, openssl, fileinfo)
- MySQL 8+
- Un cron attivo sul server (la coda email viene processata solo dallo scheduler)
- Composer e Node 20+ **solo** per lo sviluppo o per creare il pacchetto di release: gli asset compilati (`public/build`) sono versionati nel repository, quindi in produzione Node non serve mai.

## Installazione

Il progetto si installa in due modi, a seconda di cosa offre il server. In entrambi i casi la configurazione (`.env`, `APP_KEY`, database, SMTP, primo admin) viene fatta dal **wizard web** al primo avvio: non è richiesto alcun comando artisan.

### A. Server con accesso SSH (dedicato, VPS, hosting condiviso con shell)

```bash
git clone <url-del-repository> mailer
cd mailer
bin/install.sh
```

`bin/install.sh` installa le dipendenze PHP senza che tu debba sapere come si chiama PHP su quel server:

1. cerca un binario PHP ≥ 8.3 tra i percorsi tipici degli hosting (`ea-php85` di cPanel, `/opt/plesk/php/8.x/bin/php`, `php8.5`, `php`…);
2. usa il `composer` di sistema se esiste, altrimenti scarica `composer.phar` nella radice del progetto (firma SHA-384 verificata; il file è in `.gitignore`);
3. esegue `composer install --no-dev --optimize-autoloader`;
4. stampa la riga di cron già pronta con il percorso PHP corretto.

| Opzione | Effetto |
|---|---|
| `--php /percorso/php` | Forza il binario PHP (equivale alla variabile `PHP_BIN`) |
| `--composer /percorso/composer` | Forza il binario Composer (equivale a `COMPOSER_BIN`) |
| `--dev` | Installa anche le dipendenze di sviluppo |

Su un server dedicato puoi ovviamente fare a mano `composer install --no-dev --optimize-autoloader`: il risultato è lo stesso.

Poi:

1. imposta il **document root** del sito su `public/`;
2. apri il sito nel browser: verrai reindirizzato a **`/install`** (vedi [Il wizard](#il-wizard));
3. aggiungi il [cron](#cron-obbligatorio).

### B. Hosting condiviso con solo FTP (senza SSH)

Senza shell non si può lanciare Composer, quindi si usa il **pacchetto di release**: uno zip che contiene già `vendor/` e `public/build`. Lo produce chi sviluppa con `bin/build-release.sh` (vedi sotto) e si trova tra le release del repository.

1. **Crea una cartella fuori dalla root pubblica**, es. `/home/utente/mailer`, e caricaci lo zip.
2. **Estrai lo zip dal File Manager del pannello** (cPanel, Plesk…). Evita di caricare i file scompattati via FTP: `vendor/` contiene migliaia di file e il trasferimento è lento e fragile.
3. **Imposta la versione PHP** dal pannello (es. *MultiPHP Manager* su cPanel) ad almeno 8.3.
4. **Punta il document root** del dominio o sottodominio su `/home/utente/mailer/public`. Su cPanel si fa creando un sottodominio (o un dominio aggiuntivo) con *Document Root* personalizzata. Se il pannello non lo consente, chiedi all'hosting di farlo: il progetto non va mai esposto dalla sua radice.
5. **Apri il sito nel browser**: verrai reindirizzato a **`/install`** (vedi [Il wizard](#il-wizard)).
6. **Crea il cron dal pannello**, usando il percorso PHP dell'hosting (su cPanel di solito `/usr/local/bin/ea-php85`, `ea-php84`, …):

   ```cron
   * * * * * cd /home/utente/mailer && /usr/local/bin/ea-php85 artisan schedule:run >> /dev/null 2>&1
   ```

> Limite noto: senza SSH non si possono lanciare le migrazioni. Gli aggiornamenti che modificano lo schema del database richiedono un accesso shell (anche temporaneo) per `php artisan migrate --force`.

### Il wizard

Al primo avvio, prima che Laravel legga l'ambiente, l'applicazione crea da sola il `.env` copiando `.env.example` e genera la `APP_KEY` (`App\Support\EnvBootstrap`, invocato da `bootstrap/app.php`). Se la cartella del progetto non è scrivibile dal web server il wizard mostra un avviso: in quel caso copia `.env.example` in `.env` a mano e rendilo scrivibile.

Il wizard richiede:

1. **Amministratore** — il primo utente (avrà `is_admin = true`)
2. **Database** — credenziali MySQL (connessione verificata prima di procedere)
3. **SMTP** — parametri di invio (con test di connessione facoltativo)

Al termine esegue le migrazioni, scrive le credenziali del database nel `.env` e genera la prima **chiave API** (di nome `default`), mostrata a schermo (resta comunque visibile agli admin in *Impostazioni → Chiavi API*, dove se ne possono creare altre). L'installer si disattiva da solo dopo la prima installazione (flag `storage/app/installed.json`).

> Non eseguire `php artisan config:cache` prima dell'installazione: la configurazione cachata ignorerebbe il `.env` scritto dal wizard.

### Rifare l'installer (ambiente locale/di test)

Per riportare l'app allo stato "non installato" e rivedere il wizard da capo:

```bash
php artisan app:uninstall
```

Il comando cancella il flag `storage/app/installed.json` (chiedendo conferma; usa `--force` per saltarla). Da lì apri l'URL dell'app nel browser: verrai reindirizzato a `/install` e potrai ripetere i 3 step. Il wizard esegue `migrate:fresh`, quindi il database viene ricreato da zero automaticamente: non serve droppare le tabelle a mano.

> ⚠️ Non farlo mai in produzione: cancella tutti i dati dell'applicazione (utenti, email in coda, impostazioni SMTP).

### Aggiornare un'installazione

- **Con SSH**: `git pull`, poi `bin/install.sh` (applica eventuali cambi di `composer.lock`) e `php artisan migrate --force`. Gli asset compilati arrivano con il `pull`.
- **Solo FTP**: carica il nuovo zip ed estrailo sopra l'installazione esistente. Il pacchetto non contiene `.env` né `storage/`, quindi configurazione e dati restano intatti. Vale il limite sulle migrazioni descritto sopra.

### Cron (obbligatorio)

La coda email viene processata **solo** dallo scheduler, ogni minuto:

```cron
* * * * * cd /percorso/mailer && php artisan schedule:run >> /dev/null 2>&1
```

Sugli hosting condivisi sostituisci `php` con il binario completo (es. `/usr/local/bin/ea-php85`): `bin/install.sh` stampa la riga già corretta. In locale: `php artisan schedule:work`.

## Sviluppo

```bash
composer install
npm install
npm run dev          # Vite con hot reload (oppure: composer run dev)
```

Gli asset compilati in `public/build` **sono versionati**: prima di ogni commit che tocca il frontend lancia `npm run build` e includi la cartella nel commit, così chi fa il deploy con `git pull` o con lo zip di release non ha bisogno di Node. La cartella è marcata come generata in `.gitattributes`, quindi non compare nei diff.

### Creare il pacchetto di release (per l'installazione via FTP)

```bash
bin/build-release.sh
# -> dist/mail-bridge-<versione>.zip
```

Lo script parte dai **soli file committati** (`git archive HEAD`), esegue `composer install --no-dev --optimize-autoloader`, ricompila gli asset con `npm ci && npm run build`, rimuove `node_modules/`, `tests/` e l'eventuale `.env`, e produce lo zip in `dist/` (cartella ignorata da git). Il nome della versione viene da `git describe --tags`: crea un tag prima di rilasciare (`git tag v1.2.0`). Richiede `git`, `php`, `composer`, `zip` e, salvo `--no-build`, `npm`.

| Opzione | Effetto |
|---|---|
| `--no-build` | Riusa il `public/build` committato invece di ricompilare |
| `--output <cartella>` | Cartella di destinazione dello zip (default `dist/`) |

### Aggiornare solo gli asset via rsync (`deploy:assets`)

Se vuoi provare una build sul server senza passare da un commit, il comando `deploy:assets` sincronizza `public/build` via rsync con i parametri presi dal `.env`:

```bash
php artisan deploy:assets --build     # npm run build + rsync
php artisan deploy:assets --dry-run   # mostra cosa cambierebbe
php artisan deploy:assets --force     # salta la conferma
```

Variabili (in `.env`, vedi `.env.example`; mappate in `config/deploy.php`): `DEPLOY_SSH_USER`, `DEPLOY_SSH_HOST`, `DEPLOY_SSH_PORT` (default 22), `DEPLOY_LOCAL_PATH` (default `public/build`), `DEPLOY_REMOTE_PATH` (assoluto, obbligatorio), `DEPLOY_RSYNC_OPTIONS` (default `-avz --delete`). Il comando stampa sempre la riga `rsync` che sta per eseguire e chiede conferma.

## API

### `POST /api/send`

Autenticazione tramite header **`X-API-KEY`** oppure **Bearer token** (`Authorization: Bearer <chiave>`). È valida **qualunque** chiave presente in *Impostazioni → Chiavi API*, dove gli admin possono crearne di nuove, rinominarle, rigenerarle o eliminarle (una per integrazione, così si revoca solo quella che serve).

```bash
curl -X POST https://esempio.it/api/send \
  -H "X-API-KEY: <chiave>" \
  -H "Content-Type: application/json" \
  -d '{
    "to": ["utente@dominio.it"],
    "subject": "Benvenuto",
    "body": "<h1>Ciao!</h1>",
    "sync": false,
    "attachments": [
      {"filename": "doc.pdf", "content": "<base64>", "mime": "application/pdf"}
    ]
  }'
```

In alternativa, tramite Bearer token:

```bash
curl -X POST https://esempio.it/api/send \
  -H "Authorization: Bearer <chiave>" \
  -H "Content-Type: application/json" \
  -d '{"to": ["utente@dominio.it"], "subject": "Benvenuto", "body": "<h1>Ciao!</h1>"}'
```

| Campo | Tipo | Note |
|---|---|---|
| `to` | string \| string[] | obbligatorio; una riga in coda per destinatario |
| `subject` | string | obbligatorio |
| `body` | string | obbligatorio, HTML |
| `uuid` | string | facoltativo; UUID dell'email, generato se assente |
| `sync` | bool | `true` = invio immediato nella richiesta; default `false` (coda, invio entro un minuto) |
| `attachments` | array | facoltativo; contenuto base64 |
| `webhook` | string | facoltativo; URL http/https notificato a elaborazione avvenuta, al posto di quello di default |

Risposte principali (contratto identico alla vecchia app):

- `201` `{"message":"Queued","ids":["1","2"],"recipients":2}`
- `200` `{"message":"Sent","sent":[...],"failed":[...]}` (sync)
- `500` `{"message":"All emails failed","failed":[...]}` (sync, tutti falliti)
- `401` `{"error":"Unauthorized"}` — chiave mancante o errata
- `403` `{"error":"Mailer disabilitato dalle impostazioni"}` — kill switch attivo
- `400` `{"error":"Missing fields","field":"..."}` / `{"error":"Invalid email address","email":"..."}` / `{"error":"Invalid uuid","uuid":"..."}` / `{"error":"Invalid webhook","webhook":"..."}` / `{"error":"Malformed JSON",...}`
- `405` `{"error":"Method Not Allowed. Use POST."}`

## Webhook

Ogni volta che un'email viene **elaborata** (inviata dal cron, inviata subito con `sync: true`, o re-inviata a mano dalla dashboard) il server esegue una `POST` JSON verso un webhook per comunicare l'esito:

```json
{
  "event": "email.processed",
  "uuid": "9f1c2b7e-5d3a-4a8b-9f2e-6c1d7a4b3e50",
  "recipient": "mario@example.com",
  "subject": "Conferma ordine #1234",
  "status": "sent",
  "success": true,
  "attempts": 1,
  "error": null,
  "sent_at": "2026-08-05T17:24:11+02:00",
  "created_at": "2026-08-05T17:24:09+02:00"
}
```

L'URL chiamato è quello di **default** configurato in *Impostazioni → Webhook* (vuoto = nessuna notifica), a meno che la richiesta API non abbia indicato un `webhook` proprio: in quel caso l'URL viene salvato sulla riga dell'email (colonna `webhook`) e ha la precedenza, anche sui re-invii.

Timeout di 10 secondi, risposta attesa `2xx`, nessun retry: un webhook lento o irraggiungibile non blocca né altera l'invio dell'email, l'errore finisce solo nel log.

## Dashboard

- **`/dashboard`** — statistiche globali, filtri (destinatario, oggetto, intervallo date), log invii paginato, anteprima email con download allegati, re-invio manuale, pulsante *Ferma/Riattiva Invio Email*.
- **`/settings/smtp`** *(solo admin)* — configurazione SMTP (test connessione + email di prova).
- **`/settings/webhook`** *(solo admin)* — webhook di default per l'esito degli invii (con notifica di prova) e documentazione del payload.
- **`/settings/api-keys`** *(solo admin)* — elenco chiavi API: copia, creazione, rinomina, rigenerazione, eliminazione.
- **`/users`** *(solo admin)* — CRUD utenti con flag amministratore. Protezioni: niente auto-eliminazione, deve sempre esistere almeno un admin.
- **`/settings/profile`**, **`/settings/security`** — profilo e cambio password personale.

La registrazione pubblica è disabilitata: gli utenti vengono creati dall'installer o dagli admin.

## Note tecniche

- **Impostazioni** in tabella `settings`: `smtp_password` è cifrata con `APP_KEY`.
- **Chiavi API** in tabella `api_keys` (`name` univoco, `key`, timestamps): anche i segreti sono cifrati con `APP_KEY` — servono in chiaro nel pannello, quindi la cifratura è reversibile e il confronto in fase di autenticazione avviene riga per riga. Ruotare `APP_KEY` invalida sia la password SMTP sia le chiavi: vanno reinserita l'una e rigenerate le altre.
- **Coda**: tabella `emails` (`pending → sending → sent|failed`), claim atomico con lock, batch da 50, sweep automatico delle righe `sending` bloccate da più di 10 minuti. Nessun retry automatico: le email fallite si re-inviano dalla dashboard.
- **Kill switch** (`mailer_enabled`): blocca sia l'API sia il worker della coda.
- **Webhook** (`webhook_url` nelle impostazioni, colonna `emails.webhook` per l'override della singola email): la chiamata è sincrona rispetto all'invio ma non può farlo fallire — `App\Services\WebhookService` cattura ogni errore e lo registra nel log.
- Test: `php artisan test` · Lint: `vendor/bin/pint` · Frontend: `npm run lint`, `npm run types:check`.
