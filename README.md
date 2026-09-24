# Mail Bridge

Riscrittura in **Laravel + Inertia + Vue 3** del progetto "mailer": un ponte SMTP con API HTTP per accodare e inviare email, dashboard di monitoraggio, gestione utenti e installer web al primo avvio.

## Requisiti

- PHP **8.4.1 o superiore** (consigliato 8.5; estensioni: pdo_mysql, mbstring, openssl, fileinfo). `composer.json` dichiara `^8.3`, ma le versioni bloccate in `composer.lock` di Symfony richiedono 8.4.1: con PHP 8.3 sia `composer install` sia l'app falliscono.
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

1. cerca un binario PHP ≥ 8.4.1 tra i percorsi tipici degli hosting (`ea-php85` di cPanel, `/opt/plesk/php/8.x/bin/php`, `php8.5`, `php`…);
2. usa il `composer` di sistema se esiste, altrimenti scarica `composer.phar` nella radice del progetto (firma SHA-384 verificata; il file è in `.gitignore`);
3. esegue `composer install --no-dev --optimize-autoloader`;
4. stampa la riga di cron già pronta con il percorso PHP corretto.

È **idempotente**: al termine salva in `vendor/.install-stamp` l'impronta di `composer.lock`, e a un rilancio successivo esce in una frazione di secondo senza toccare nulla. Reinstalla da sé solo se `composer.lock` è cambiato o se `vendor/` è stato cancellato. Puoi quindi rilanciarlo a ogni deploy, o lasciarlo in un'attività pianificata, senza effetti collaterali.

| Opzione | Effetto |
|---|---|
| `--php /percorso/php` | Forza il binario PHP (equivale alla variabile `PHP_BIN`) |
| `--composer /percorso/composer` | Forza il binario Composer (equivale a `COMPOSER_BIN`) |
| `--dev` | Installa anche le dipendenze di sviluppo |
| `--force` | Reinstalla anche se l'impronta dice che è già aggiornato |

Su un server dedicato puoi ovviamente fare a mano `composer install --no-dev --optimize-autoloader`: il risultato è lo stesso.

Poi:

1. imposta il **document root** del sito su `public/`;
2. apri il sito nel browser: verrai reindirizzato a **`/install`** (vedi [Il wizard](#il-wizard));
3. aggiungi il [cron](#cron-obbligatorio).

### A2. Hosting cPanel con *Git Version Control* (senza SSH)

Se cPanel ti permette di clonare il repository dal pannello ma non hai una shell, il file [`.cpanel.yml`](.cpanel.yml) nella radice del progetto fa il lavoro di `bin/install.sh` al posto tuo:

1. In *Git Version Control* clona il repository direttamente nella cartella finale (es. `/home/utente/subdomains/mailer`).
2. In *MultiPHP Manager* imposta il dominio su PHP 8.4 o 8.5.
3. Imposta il document root del dominio o sottodominio su `.../mailer/public`.
4. In *Git Version Control → Manage → Pull or Deploy* premi **Update from Remote** e poi **Deploy HEAD Commit**: cPanel esegue `bin/install.sh` nella cartella del clone, che installa `vendor/`.
5. Apri il sito: parte il wizard. Poi crea il cron da *Cron Jobs* con il percorso PHP completo (es. `/usr/local/bin/ea-php85`, vedi sotto).

A ogni aggiornamento basta ripetere il punto 4: se `composer.lock` non è cambiato lo script esce subito, quindi il deploy resta veloce. Il file `.cpanel.yml` è volutamente minimo (niente commenti): il parser YAML di cPanel è più severo di quello standard.

> Un 500 subito dopo il clone, con nel log `Failed opening required '.../vendor/autoload.php'`, significa solo che questo passaggio non è ancora stato fatto: manca `vendor/`, che non è versionato.

#### Se il pulsante *Deploy HEAD Commit* è disabilitato

cPanel mostra un messaggio generico ("The system cannot deploy") senza dire quale dei due requisiti manca. Cause tipiche, in ordine di frequenza:

- **Modifiche non committate nel clone.** Capita anche senza che tu abbia toccato nulla: alcuni hosting perdono il bit di eseguibilità dei file, e git segnala `bin/install.sh` come modificato (`mode change 100755 => 100644`). Il deploy resta bloccato finché l'albero non è pulito.
- **La funzione di deploy è disattivata dal provider**, che espone il clone e il pull ma non l'esecuzione dei task.

In entrambi i casi non serve insistere: usa l'**attività pianificata** descritta qui sotto, che ottiene lo stesso risultato.

### A3. Hosting cPanel senza SSH e senza deploy (installazione via *Cron Jobs*)

Se non hai shell e il pulsante di deploy non è utilizzabile, l'installazione si lancia da *Cron Jobs*. Siccome `bin/install.sh` è idempotente, l'attività **non va rimossa**: dopo la prima esecuzione le successive escono subito senza fare nulla.

1. In **cPanel → Cron Jobs** aggiungi un'attività con questo comando (sostituisci il percorso con quello del tuo clone):

   ```
   /bin/bash /home/utente/subdomains/mailer/bin/install.sh >> /home/utente/install.log 2>&1
   ```

2. Come pianificazione scegli un **istante singolo, pochi minuti nel futuro**, compilando i campi a mano invece di usare le *Common Settings*. Esempio per le 14:35 del 25 settembre:

   | Minuto | Ora | Giorno | Mese | Giorno della settimana |
   |---|---|---|---|---|
   | `35` | `14` | `25` | `9` | `*` |

   Così l'attività scatta una volta sola. Tornerà a scattare l'anno prossimo nello stesso istante, quando però non farà nulla.

3. Attendi che l'orario passi, poi apri `install.log` dal *File Manager*: l'ultima riga dice se l'installazione è andata a buon fine.
4. Verifica che esista la cartella `vendor/`, poi apri il sito: parte il wizard.
5. Aggiungi l'attività definitiva dello scheduler, quella descritta in [Cron](#cron-obbligatorio).

Per gli aggiornamenti futuri, dopo un *Update from Remote*, ti basta modificare la data dell'attività e lasciarla scattare di nuovo.

> In alternativa puoi pianificarla *Once Per Minute* (`* * * * *`) e lasciarla dov'è: dopo la prima esecuzione ogni passaggio costa pochi millisecondi. È più semplice ma tiene una riga di log al minuto, quindi conviene togliere il `>> install.log` dal comando.

### B. Hosting condiviso con solo FTP (senza SSH)

Senza shell non si può lanciare Composer, quindi si usa il **pacchetto di release**: uno zip che contiene già `vendor/` e `public/build`. Lo produce chi sviluppa con `bin/build-release.sh` (vedi sotto) e si trova tra le release del repository.

1. **Crea una cartella fuori dalla root pubblica**, es. `/home/utente/mailer`, e caricaci lo zip.
2. **Estrai lo zip dal File Manager del pannello** (cPanel, Plesk…). Evita di caricare i file scompattati via FTP: `vendor/` contiene migliaia di file e il trasferimento è lento e fragile.
3. **Imposta la versione PHP** dal pannello (es. *MultiPHP Manager* su cPanel) ad almeno 8.4 (meglio 8.5).
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
2. **Database** — credenziali MySQL (connessione verificata prima di procedere) e, facoltativo, un **prefisso per le tabelle** (es. `mb_`, salvato in `DB_PREFIX`) utile se il database è condiviso con altre applicazioni
3. **SMTP** — parametri di invio (con test di connessione facoltativo)

Al termine esegue le migrazioni, scrive le credenziali del database nel `.env` e genera la prima **chiave API** (di nome `default`), mostrata a schermo (resta comunque visibile agli admin in *Impostazioni → Chiavi API*, dove se ne possono creare altre). L'installer si disattiva da solo dopo la prima installazione (flag `storage/app/installed.json`).

> Non eseguire `php artisan config:cache` prima dell'installazione: la configurazione cachata ignorerebbe il `.env` scritto dal wizard.

### Rifare l'installer (ambiente locale/di test)

Per riportare l'app allo stato "non installato" e rivedere il wizard da capo:

```bash
php artisan app:uninstall
```

Il comando cancella il flag `storage/app/installed.json` (chiedendo conferma; usa `--force` per saltarla). Da lì apri l'URL dell'app nel browser: verrai reindirizzato a `/install` e potrai ripetere i 3 step. Il wizard esegue `migrate:fresh`, quindi il database viene ricreato da zero automaticamente: non serve droppare le tabelle a mano. Se è stato indicato un prefisso, invece, vengono eliminate solo le tabelle che iniziano con quel prefisso (le altre tabelle del database restano intatte) e poi si esegue `migrate`.

> ⚠️ Non farlo mai in produzione: cancella tutti i dati dell'applicazione (utenti, email in coda, impostazioni SMTP).

### Aggiornare un'installazione

- **Con SSH**: `git pull`, poi `bin/install.sh` e `php artisan migrate --force`. Lo script reinstalla le dipendenze solo se `composer.lock` è cambiato, altrimenti esce subito. Gli asset compilati arrivano con il `pull`.
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
| `webhook_token` | string | facoltativo, solo con `webhook`; token rimandato come `X-API-KEY` e `Authorization: Bearer` al posto di quello di default |
| `webhook_secret` | string | facoltativo, solo con `webhook`; segreto HMAC con cui firmare la notifica al posto di quello di default |
| `webhook_signature_header` | string | facoltativo, solo con `webhook`; intestazione che porta la firma (default `X-Signature`) |

Risposte principali (contratto identico alla vecchia app):

- `201` `{"message":"Queued","ids":["1","2"],"recipients":2}`
- `200` `{"message":"Sent","sent":[...],"failed":[...]}` (sync)
- `500` `{"message":"All emails failed","failed":[...]}` (sync, tutti falliti)
- `401` `{"error":"Unauthorized"}` — chiave mancante o errata
- `403` `{"error":"Mailer disabilitato dalle impostazioni"}` — kill switch attivo
- `400` `{"error":"Missing fields","field":"..."}` / `{"error":"Invalid email address","email":"..."}` / `{"error":"Invalid uuid","uuid":"..."}` / `{"error":"Invalid webhook","webhook":"..."}` / `{"error":"Invalid webhook_token|webhook_secret|webhook_signature_header",...}` / `{"error":"Malformed JSON",...}`
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

L'URL chiamato è quello di **default** configurato in *Impostazioni → Webhook* (vuoto = nessuna notifica), a meno che la richiesta API non abbia indicato un `webhook` proprio: in quel caso l'URL viene salvato sulla riga dell'email (colonna `webhook`) e ha la precedenza, anche sui re-invii. Lo stesso vale per `webhook_token`, `webhook_secret` e `webhook_signature_header`: se arrivano con la richiesta vengono salvati sulla riga (token e segreto cifrati con `APP_KEY`) e usati al posto di quelli di default. È così che il pacchetto [alagaccia/mailer-transport](https://github.com/alagaccia/mailer-transport) annuncia il proprio webhook: l'applicazione mittente non ha nulla da configurare su questo pannello.

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
- **Webhook** (`webhook_url`, `webhook_token`, `webhook_secret`, `webhook_signature_header` nelle impostazioni; colonne `emails.webhook*` per l'override della singola email): la chiamata è sincrona rispetto all'invio ma non può farlo fallire — `App\Services\WebhookService` cattura ogni errore e lo registra nel log.
- Test: `php artisan test` · Lint: `vendor/bin/pint` · Frontend: `npm run lint`, `npm run types:check`.
