# Mail Bridge

Riscrittura in **Laravel + Inertia + Vue 3** del progetto "mailer": un ponte SMTP con API HTTP per accodare e inviare email, dashboard di monitoraggio, gestione utenti e installer web al primo avvio.

## Requisiti

- PHP 8.5+ (estensioni: pdo_mysql, mbstring, openssl)
- MySQL 8+
- Node 20+ (solo per la build degli asset)
- Un cron attivo sul server

## Installazione

```bash
composer install --no-dev
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

Configura il virtual host con **document root su `public/`**, poi apri l'applicazione nel browser: verrai reindirizzato a **`/install`**, il wizard guidato che richiede:

1. **Amministratore** — il primo utente (avrà `is_admin = true`)
2. **Database** — credenziali MySQL (connessione verificata prima di procedere)
3. **SMTP** — parametri di invio (con test di connessione facoltativo)
4. **Composer** — passo facoltativo: se Composer non è disponibile sul server, il wizard può scaricare `composer.phar` nella radice del progetto (download da `getcomposer.org` con verifica SHA-256, `chmod 0755` e riga in `.gitignore`, quindi non viene versionato). Da lì si usa con `php composer.phar <comando>`.

Al termine viene generata automaticamente la prima **chiave API** (di nome `default`), mostrata a schermo (resta comunque visibile agli admin in *Impostazioni → Chiavi API*, dove se ne possono creare altre). L'installer si disattiva da solo dopo la prima installazione (flag `storage/app/installed.json`).

> Non eseguire `php artisan config:cache` prima dell'installazione: la configurazione cachata ignorerebbe il `.env` scritto dal wizard.

### Rifare l'installer (ambiente locale/di test)

Per riportare l'app allo stato "non installato" e rivedere il wizard da capo:

```bash
php artisan app:uninstall
```

Il comando cancella il flag `storage/app/installed.json` (chiedendo conferma; usa `--force` per saltarla). Da lì apri l'URL dell'app nel browser: verrai reindirizzato a `/install` e potrai ripetere i 4 step. Il wizard esegue `migrate:fresh`, quindi il database viene ricreato da zero automaticamente: non serve droppare le tabelle a mano.

> ⚠️ Non farlo mai in produzione: cancella tutti i dati dell'applicazione (utenti, email in coda, impostazioni SMTP).

### Hosting condiviso (senza build su server)

Se il server di produzione non permette di eseguire `npm ci && npm run build` (hosting condiviso, niente accesso a Node), compila gli asset in locale e caricali via `rsync`: `public/build` resta ignorato da git (vedi `.gitignore`) e viaggia solo tramite trasferimento diretto, senza toccare il repository.

#### Comando `deploy:assets` (consigliato)

Il progetto include un comando artisan che incapsula la sincronizzazione, con i parametri del server presi dal `.env`:

```bash
npm run build
php artisan deploy:assets
```

oppure, in un colpo solo (build + upload):

```bash
php artisan deploy:assets --build
```

| Opzione | Effetto |
|---|---|
| `--build` | Esegue `npm run build` prima di sincronizzare (si ferma se la build fallisce) |
| `--dry-run` | Mostra cosa verrebbe trasferito/cancellato senza scrivere nulla sul server |
| `--force` | Salta la richiesta di conferma (utile in script non interattivi) |

Il comando stampa sempre la riga `rsync` che sta per eseguire e chiede conferma prima di procedere (`--dry-run` e `--force` la saltano).

Variabili di configurazione (in `.env`, vedi `.env.example`; mappate in `config/deploy.php`):

| Variabile | Descrizione | Default |
|---|---|---|
| `DEPLOY_SSH_USER` | Utente SSH del server | — (obbligatoria) |
| `DEPLOY_SSH_HOST` | Host del server | — (obbligatoria) |
| `DEPLOY_SSH_PORT` | Porta SSH (se diversa da 22 viene passata come `-e "ssh -p N"`) | `22` |
| `DEPLOY_LOCAL_PATH` | Cartella locale da inviare (relativa alla radice del progetto o assoluta) | `public/build` |
| `DEPLOY_REMOTE_PATH` | Cartella remota di destinazione (percorso assoluto) | — (obbligatoria) |
| `DEPLOY_RSYNC_OPTIONS` | Opzioni passate a rsync | `-avz --delete` |

Esempio:

```dotenv
DEPLOY_SSH_USER=utente
DEPLOY_SSH_HOST=server.esempio.it
DEPLOY_SSH_PORT=22
DEPLOY_LOCAL_PATH=public/build
DEPLOY_REMOTE_PATH=/home/utente/subdomains/mailer/public/build
DEPLOY_RSYNC_OPTIONS="-avz --delete"
```

che genera il comando:

```bash
rsync -avz --delete public/build/ utente@server.esempio.it:/home/utente/subdomains/mailer/public/build
```

> Se hai già fatto `php artisan config:cache` in locale, ricordati di rilanciarlo (o `config:clear`) dopo aver cambiato queste variabili.

#### rsync a mano

Lo stesso risultato, senza passare dal comando:

```bash
npm run build
rsync -avz --delete public/build/ utente@server:/percorso/mailer-server/public/build/
```

`rsync` trasporta i dati via SSH, quindi usa automaticamente le chiavi già installate (nessuna password richiesta). `--delete` rimuove sul server i file che non esistono più nella build locale — utile perché Vite genera nomi con hash diversi ad ogni build e altrimenti si accumulerebbero versioni vecchie.

#### scp

In alternativa, con lo stesso accesso SSH puoi usare `scp`, che copia l'intera cartella in un colpo solo:

```bash
npm run build
ssh utente@server 'rm -rf /percorso/mailer-server/public/build'
scp -r public/build utente@server:/percorso/mailer-server/public/
```

`scp` non fa sync incrementale: va bene per deploy occasionali, ma trasferisce sempre tutti i file (anche quelli invariati) ed è per questo che conviene cancellare prima la cartella remota, altrimenti si accumulano gli asset con hash vecchi. `rsync` resta la scelta più efficiente per deploy frequenti.

Ripeti questi comandi a ogni deploy che tocca frontend/asset. Il resto del codice (PHP, migrazioni, ecc.) continua a essere aggiornato come al solito (es. `git pull`).

### Cron (obbligatorio)

La coda email viene processata **solo** dallo scheduler, ogni minuto:

```cron
* * * * * cd /percorso/mailer-server && php artisan schedule:run >> /dev/null 2>&1
```

In locale: `php artisan schedule:work`.

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
| `sync` | bool | `true` = invio immediato nella richiesta; default `false` (coda, invio entro un minuto) |
| `attachments` | array | facoltativo; contenuto base64 |

Risposte principali (contratto identico alla vecchia app):

- `201` `{"message":"Queued","ids":["1","2"],"recipients":2}`
- `200` `{"message":"Sent","sent":[...],"failed":[...]}` (sync)
- `500` `{"message":"All emails failed","failed":[...]}` (sync, tutti falliti)
- `401` `{"error":"Unauthorized"}` — chiave mancante o errata
- `403` `{"error":"Mailer disabilitato dalle impostazioni"}` — kill switch attivo
- `400` `{"error":"Missing fields","field":"..."}` / `{"error":"Invalid email address","email":"..."}` / `{"error":"Malformed JSON",...}`
- `405` `{"error":"Method Not Allowed. Use POST."}`

## Dashboard

- **`/dashboard`** — statistiche globali, filtri (destinatario, oggetto, intervallo date), log invii paginato, anteprima email con download allegati, re-invio manuale, pulsante *Ferma/Riattiva Invio Email*.
- **`/settings/smtp`** *(solo admin)* — configurazione SMTP (test connessione + email di prova).
- **`/settings/api-keys`** *(solo admin)* — elenco chiavi API: copia, creazione, rinomina, rigenerazione, eliminazione.
- **`/users`** *(solo admin)* — CRUD utenti con flag amministratore. Protezioni: niente auto-eliminazione, deve sempre esistere almeno un admin.
- **`/settings/profile`**, **`/settings/security`** — profilo e cambio password personale.

La registrazione pubblica è disabilitata: gli utenti vengono creati dall'installer o dagli admin.

## Note tecniche

- **Impostazioni** in tabella `settings`: `smtp_password` è cifrata con `APP_KEY`.
- **Chiavi API** in tabella `api_keys` (`name` univoco, `key`, timestamps): anche i segreti sono cifrati con `APP_KEY` — servono in chiaro nel pannello, quindi la cifratura è reversibile e il confronto in fase di autenticazione avviene riga per riga. Ruotare `APP_KEY` invalida sia la password SMTP sia le chiavi: vanno reinserita l'una e rigenerate le altre.
- **Coda**: tabella `emails` (`pending → sending → sent|failed`), claim atomico con lock, batch da 50, sweep automatico delle righe `sending` bloccate da più di 10 minuti. Nessun retry automatico: le email fallite si re-inviano dalla dashboard.
- **Kill switch** (`mailer_enabled`): blocca sia l'API sia il worker della coda.
- Test: `php artisan test` · Lint: `vendor/bin/pint` · Frontend: `npm run lint`, `npm run types:check`.
