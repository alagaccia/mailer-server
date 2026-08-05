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
