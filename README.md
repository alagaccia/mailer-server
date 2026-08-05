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

Al termine viene generata automaticamente la **chiave API**, mostrata una sola volta a schermo (resta comunque visibile agli admin in *Impostazioni*). L'installer si disattiva da solo dopo la prima installazione (flag `storage/app/installed.json`).

> Non eseguire `php artisan config:cache` prima dell'installazione: la configurazione cachata ignorerebbe il `.env` scritto dal wizard.

### Cron (obbligatorio)

La coda email viene processata **solo** dallo scheduler, ogni minuto:

```cron
* * * * * cd /percorso/mailer-server && php artisan schedule:run >> /dev/null 2>&1
```

In locale: `php artisan schedule:work`.

## API

### `POST /api/send`

Autenticazione tramite header **`X-API-KEY`** (chiave visibile/rigenerabile dagli admin in *Impostazioni → Chiave API*).

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
- **`/settings/smtp`** *(solo admin)* — configurazione SMTP (test connessione + email di prova) e chiave API (copia/rigenerazione).
- **`/users`** *(solo admin)* — CRUD utenti con flag amministratore. Protezioni: niente auto-eliminazione, deve sempre esistere almeno un admin.
- **`/settings/profile`**, **`/settings/security`** — profilo e cambio password personale.

La registrazione pubblica è disabilitata: gli utenti vengono creati dall'installer o dagli admin.

## Note tecniche

- **Impostazioni** in tabella `settings`: `api_key` e `smtp_password` sono cifrate con `APP_KEY` (ruotare `APP_KEY` le invalida: reinserire la password SMTP e rigenerare la chiave API).
- **Coda**: tabella `emails` (`pending → sending → sent|failed`), claim atomico con lock, batch da 50, sweep automatico delle righe `sending` bloccate da più di 10 minuti. Nessun retry automatico: le email fallite si re-inviano dalla dashboard.
- **Kill switch** (`mailer_enabled`): blocca sia l'API sia il worker della coda.
- Test: `php artisan test` · Lint: `vendor/bin/pint` · Frontend: `npm run lint`, `npm run types:check`.
