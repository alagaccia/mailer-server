<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    endpoint: string;
}>();

type Param = {
    name: string;
    type: string;
    required: boolean;
    description: string;
};

const params: Param[] = [
    {
        name: 'to',
        type: 'string | string[]',
        required: true,
        description:
            'Destinatario o elenco di destinatari. Ogni valore deve essere un indirizzo email valido; con più destinatari viene creata una email per ciascuno.',
    },
    {
        name: 'subject',
        type: 'string',
        required: true,
        description: 'Oggetto del messaggio.',
    },
    {
        name: 'body',
        type: 'string',
        required: true,
        description: 'Corpo del messaggio, in HTML.',
    },
    {
        name: 'attachments',
        type: 'array',
        required: false,
        description:
            'Elenco di allegati: ogni elemento accetta filename, content (contenuto codificato in base64) e mime.',
    },
    {
        name: 'uuid',
        type: 'string',
        required: false,
        description:
            "Identificativo UUID dell'email, utile per correlarla a un record del tuo software. Se assente ne viene generato uno automaticamente. Con più destinatari lo stesso uuid viene assegnato a tutte le email create dalla richiesta.",
    },
    {
        name: 'webhook',
        type: 'string',
        required: false,
        description:
            "URL http/https chiamato in POST quando l'email viene elaborata, per notificare l'esito dell'invio. Sostituisce il webhook di default configurato in Impostazioni → Webhook. Con più destinatari viene assegnato a tutte le email create dalla richiesta, che riceve quindi una notifica per ciascuna.",
    },
    {
        name: 'sync',
        type: 'boolean',
        required: false,
        description:
            "Se true l'invio avviene subito nella richiesta e la risposta riporta l'esito. Se assente o false (default) l'email viene messa in coda e inviata dal cron entro un minuto.",
    },
];

const requestExample = `{
  "to": ["mario@example.com", "lucia@example.com"],
  "uuid": "9f1c2b7e-5d3a-4a8b-9f2e-6c1d7a4b3e50",
  "subject": "Conferma ordine #1234",
  "body": "<p>Grazie per il tuo ordine.</p>",
  "attachments": [
    {
      "filename": "fattura.pdf",
      "content": "JVBERi0xLjQKJcfs...",
      "mime": "application/pdf"
    }
  ],
  "webhook": "https://tuo-software.it/hooks/mail-bridge",
  "sync": false
}`;

const queuedExample = `HTTP/1.1 201 Created

{
  "message": "Queued",
  "ids": ["18", "19"],
  "recipients": 2
}`;

const syncExample = `HTTP/1.1 200 OK

{
  "message": "Sent",
  "sent": ["mario@example.com"],
  "failed": []
}`;

const errors = [
    {
        code: '400',
        body: '{"error": "Malformed JSON", "details": "..."}',
        when: 'Il corpo della richiesta non è JSON valido.',
    },
    {
        code: '400',
        body: '{"error": "Invalid JSON body"}',
        when: 'Il JSON è valido ma vuoto o non un oggetto.',
    },
    {
        code: '400',
        body: '{"error": "Missing fields", "field": "subject"}',
        when: 'Manca uno dei campi obbligatori: il campo mancante è indicato in field.',
    },
    {
        code: '400',
        body: '{"error": "Invalid email address", "email": "..."}',
        when: 'Uno dei destinatari non è un indirizzo email valido.',
    },
    {
        code: '400',
        body: '{"error": "Invalid uuid", "uuid": "..."}',
        when: 'Il campo uuid è presente ma non è un UUID valido.',
    },
    {
        code: '400',
        body: '{"error": "Invalid webhook", "webhook": "..."}',
        when: 'Il campo webhook è presente ma non è un URL http/https valido.',
    },
    {
        code: '401',
        body: '{"error": "Unauthorized"}',
        when: 'Chiave API mancante o non riconosciuta.',
    },
    {
        code: '403',
        body: '{"error": "Mailer disabilitato dalle impostazioni"}',
        when: "L'invio è stato disattivato dal pannello.",
    },
    {
        code: '500',
        body: '{"error": "Database error", "details": "..."}',
        when: "L'email non è stata registrata a causa di un errore del database.",
    },
    {
        code: '500',
        body: '{"message": "All emails failed", "failed": [...]}',
        when: 'Solo con sync: true, quando nessun destinatario è stato raggiunto.',
    },
];

const cardClass =
    'overflow-hidden rounded-2xl border border-gray-800 bg-gray-900 shadow-xl';
const headerClass =
    'border-b border-gray-800 bg-gray-900/50 px-6 py-4 font-bold text-gray-200';
const preClass =
    'overflow-x-auto rounded-lg border border-gray-800 bg-gray-950 p-4 font-mono text-xs leading-relaxed text-gray-300';
</script>

<template>
    <Head title="Documentazione API" />

    <div class="space-y-6">
        <div :class="cardClass">
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-800 bg-gray-900/50 px-6 py-4"
            >
                <div>
                    <h2 class="font-bold text-gray-200">Documentazione API</h2>
                    <p class="mt-1 text-xs text-gray-500">
                        Come inviare email dal tuo software tramite questo
                        server.
                    </p>
                </div>
                <Link
                    href="/settings/api-keys"
                    class="rounded-lg border border-gray-700 bg-gray-800 px-4 py-2 text-sm text-gray-300 transition hover:bg-gray-700 hover:text-white"
                >
                    ← Torna alle chiavi
                </Link>
            </div>

            <div class="space-y-4 px-6 py-5 text-sm text-gray-400">
                <div class="flex flex-wrap items-center gap-3">
                    <span
                        class="rounded-lg border border-green-500/30 bg-green-600/20 px-3 py-1 font-mono text-xs font-bold text-green-400"
                    >
                        POST
                    </span>
                    <code class="font-mono text-blue-400">{{ endpoint }}</code>
                </div>
                <p>
                    Il corpo della richiesta è JSON e l'header
                    <code class="font-mono text-blue-400"
                        >Content-Type: application/json</code
                    >
                    va sempre inviato. Ogni richiesta va autenticata con una
                    chiave presente nella pagina
                    <Link
                        href="/settings/api-keys"
                        class="text-blue-400 underline hover:text-blue-300"
                        >Chiavi API</Link
                    >.
                </p>
            </div>
        </div>

        <div :class="cardClass">
            <h3 :class="headerClass">Autenticazione</h3>
            <div class="space-y-4 px-6 py-5 text-sm text-gray-400">
                <p>
                    La chiave può essere inviata in due modi equivalenti: è
                    valida qualunque chiave dell'elenco.
                </p>
                <pre :class="preClass">
X-API-KEY: &lt;chiave&gt;

// oppure

Authorization: Bearer &lt;chiave&gt;</pre>
                <p class="text-xs text-gray-500">
                    Senza una chiave valida l'endpoint risponde
                    <code class="font-mono text-gray-400">401 Unauthorized</code
                    >. Se non esiste nessuna chiave, ogni richiesta viene
                    rifiutata.
                </p>
            </div>
        </div>

        <div :class="cardClass">
            <h3 :class="headerClass">Parametri</h3>
            <div class="divide-y divide-gray-800/50">
                <div
                    v-for="param in params"
                    :key="param.name"
                    class="px-6 py-4"
                >
                    <div class="mb-1 flex flex-wrap items-center gap-3">
                        <code class="font-mono text-sm text-blue-400">{{
                            param.name
                        }}</code>
                        <span class="font-mono text-xs text-gray-500">{{
                            param.type
                        }}</span>
                        <span
                            v-if="param.required"
                            class="rounded border border-red-500/30 bg-red-600/20 px-2 py-0.5 text-[10px] font-bold text-red-400 uppercase"
                        >
                            obbligatorio
                        </span>
                        <span
                            v-else
                            class="rounded border border-gray-700 bg-gray-800 px-2 py-0.5 text-[10px] font-bold text-gray-500 uppercase"
                        >
                            facoltativo
                        </span>
                    </div>
                    <p class="text-sm text-gray-400">{{ param.description }}</p>
                </div>
            </div>
        </div>

        <div :class="cardClass">
            <h3 :class="headerClass">Esempio di richiesta</h3>
            <div class="space-y-4 px-6 py-5">
                <pre :class="preClass">{{ requestExample }}</pre>
                <p class="text-xs text-gray-500">Con curl:</p>
                <pre :class="preClass">
curl -X POST {{ endpoint }} \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: &lt;chiave&gt;" \
  -d '{"to":"mario@example.com","subject":"Ciao","body":"&lt;p&gt;Test&lt;/p&gt;"}'</pre>
            </div>
        </div>

        <div :class="cardClass">
            <h3 :class="headerClass">Risposte di successo</h3>
            <div class="space-y-5 px-6 py-5">
                <div>
                    <p class="mb-2 text-sm text-gray-400">
                        Invio in coda (default): l'email viene registrata e
                        spedita dal cron.
                    </p>
                    <pre :class="preClass">{{ queuedExample }}</pre>
                </div>
                <div>
                    <p class="mb-2 text-sm text-gray-400">
                        Invio immediato con
                        <code class="font-mono text-blue-400">sync: true</code>:
                        la risposta riporta destinatari serviti e falliti.
                    </p>
                    <pre :class="preClass">{{ syncExample }}</pre>
                </div>
            </div>
        </div>

        <div :class="cardClass">
            <h3 :class="headerClass">Errori</h3>
            <div class="divide-y divide-gray-800/50">
                <div
                    v-for="error in errors"
                    :key="error.body"
                    class="px-6 py-4"
                >
                    <div class="mb-1 flex flex-wrap items-center gap-3">
                        <span
                            class="rounded border border-amber-500/30 bg-amber-600/20 px-2 py-0.5 font-mono text-xs font-bold text-amber-400"
                        >
                            {{ error.code }}
                        </span>
                        <code class="font-mono text-xs text-gray-400">{{
                            error.body
                        }}</code>
                    </div>
                    <p class="text-sm text-gray-400">{{ error.when }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
