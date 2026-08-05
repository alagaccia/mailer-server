<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { firstValidationError, postJson } from '@/lib/http';

const props = defineProps<{
    webhook: {
        url: string;
        token: string;
        secret: string;
        signature_header: string;
    };
    defaultSignatureHeader: string;
    signatureAlgo: string;
}>();

const form = useForm({
    url: props.webhook.url,
    token: props.webhook.token,
    secret: props.webhook.secret,
    signature_header: props.webhook.signature_header,
});

function save(): void {
    form.put('/settings/webhook', { preserveScroll: true });
}

// --- Notifica di prova -------------------------------------------------------

const testing = ref(false);

async function testWebhook(): Promise<void> {
    testing.value = true;

    try {
        const { status, data } = await postJson<{ ok?: boolean }>(
            '/settings/webhook/test',
            {
                url: form.url,
                token: form.token,
                secret: form.secret,
                signature_header: form.signature_header,
            },
        );

        if (status === 422) {
            toast.error(firstValidationError(data));
        } else if (data.ok) {
            toast.success('Notifica di prova consegnata al webhook.');
        } else {
            toast.error(
                `Chiamata fallita: ${data.error ?? 'errore sconosciuto'}`,
            );
        }
    } catch {
        toast.error('Errore di rete durante il test.');
    } finally {
        testing.value = false;
    }
}

const signatureHeader = computed(
    () => form.signature_header.trim() || props.defaultSignatureHeader,
);

const verifyExample = computed(
    () => `// PHP — il corpo va letto grezzo, non ri-serializzato
$body = file_get_contents('php://input');
$atteso = '${props.signatureAlgo}=' . hash_hmac('${props.signatureAlgo}', $body, $segreto);

if (! hash_equals($atteso, $_SERVER['HTTP_${signatureHeader.value.toUpperCase().replace(/-/g, '_')}'] ?? '')) {
    http_response_code(401);
    exit;
}`,
);

const payloadExample = computed(
    () => `POST <url del webhook>
Content-Type: application/json
X-API-KEY: <token configurato>
Authorization: Bearer <token configurato>
${signatureHeader.value}: ${props.signatureAlgo}=<firma HMAC del corpo>

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
}`,
);

const failedExample = `{
  "event": "email.processed",
  "uuid": "3b8d1f42-7c60-4e19-b2a5-0d9e5c7a1f83",
  "recipient": "lucia@example.com",
  "subject": "Conferma ordine #1234",
  "status": "failed",
  "success": false,
  "attempts": 1,
  "error": "SMTP connect failed",
  "sent_at": null,
  "created_at": "2026-08-05T17:24:09+02:00"
}`;

const overrideExample = `{
  "to": "mario@example.com",
  "subject": "Conferma ordine #1234",
  "body": "<p>Grazie per il tuo ordine.</p>",
  "webhook": "https://tuo-software.it/hooks/mail-bridge"
}`;

const inputClass =
    'w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition placeholder:text-gray-600 focus:border-blue-500 focus:outline-none';
const labelClass = 'mb-2 block text-xs font-bold text-gray-500 uppercase';
const cardClass = 'rounded-2xl border border-gray-800 bg-gray-900 shadow-xl';
const preClass =
    'overflow-x-auto rounded-lg border border-gray-800 bg-gray-950 p-4 font-mono text-xs leading-relaxed text-gray-300';
</script>

<template>
    <Head title="Impostazioni Webhook" />

    <div class="space-y-6">
        <div :class="cardClass" class="p-6">
            <h2 class="font-bold text-gray-200">Webhook di default</h2>
            <p class="mt-1 mb-6 text-xs text-gray-500">
                Indirizzo chiamato ogni volta che un'email viene elaborata, per
                comunicare al tuo software se l'invio è andato a buon fine.
                Lascialo vuoto per non inviare nessuna notifica.
            </p>

            <form @submit.prevent="save">
                <div>
                    <label :class="labelClass" for="url">URL del webhook</label>
                    <input
                        id="url"
                        v-model="form.url"
                        type="url"
                        placeholder="https://tuo-software.it/hooks/mail-bridge"
                        :class="inputClass"
                    />
                    <p v-if="form.errors.url" class="mt-2 text-xs text-red-400">
                        {{ form.errors.url }}
                    </p>
                </div>

                <div class="mt-6">
                    <label :class="labelClass" for="token"
                        >Token di autenticazione (opzionale)</label
                    >
                    <input
                        id="token"
                        v-model="form.token"
                        type="text"
                        autocomplete="off"
                        placeholder="Lascia vuoto per non autenticare la chiamata"
                        :class="inputClass"
                    />
                    <p
                        v-if="form.errors.token"
                        class="mt-2 text-xs text-red-400"
                    >
                        {{ form.errors.token }}
                    </p>
                    <p class="mt-2 text-xs text-gray-500">
                        Se compilato, viene inviato con la notifica sia come
                        <code class="font-mono text-gray-400">X-API-KEY</code>
                        sia come
                        <code class="font-mono text-gray-400"
                            >Authorization: Bearer</code
                        >, così puoi validarlo nel modo che preferisci.
                    </p>
                </div>

                <div class="mt-6">
                    <label :class="labelClass" for="secret"
                        >Chiave per la firma HMAC (opzionale)</label
                    >
                    <input
                        id="secret"
                        v-model="form.secret"
                        type="text"
                        autocomplete="off"
                        placeholder="Lascia vuoto per non firmare la notifica"
                        :class="inputClass"
                    />
                    <p
                        v-if="form.errors.secret"
                        class="mt-2 text-xs text-red-400"
                    >
                        {{ form.errors.secret }}
                    </p>
                    <p class="mt-2 text-xs text-gray-500">
                        Se compilata, ogni notifica viene firmata con
                        <code class="font-mono text-gray-400">{{
                            signatureAlgo
                        }}</code>
                        sul corpo grezzo della richiesta: il tuo software può
                        così verificare che la chiamata arrivi davvero da Mail
                        Bridge e non sia stata alterata.
                    </p>
                </div>

                <div class="mt-6">
                    <label :class="labelClass" for="signature_header"
                        >Intestazione della firma (opzionale)</label
                    >
                    <input
                        id="signature_header"
                        v-model="form.signature_header"
                        type="text"
                        autocomplete="off"
                        :placeholder="defaultSignatureHeader"
                        :class="inputClass"
                    />
                    <p
                        v-if="form.errors.signature_header"
                        class="mt-2 text-xs text-red-400"
                    >
                        {{ form.errors.signature_header }}
                    </p>
                    <p class="mt-2 text-xs text-gray-500">
                        Nome dell'intestazione in cui viaggia la firma. Vuoto =
                        <code class="font-mono text-gray-400">{{
                            defaultSignatureHeader
                        }}</code
                        >. Utile se il tuo software si aspetta un nome
                        specifico, ad esempio
                        <code class="font-mono text-gray-400"
                            >X-Hub-Signature-256</code
                        >.
                    </p>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="cursor-pointer rounded-lg border border-blue-500 bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Salva
                    </button>
                    <button
                        type="button"
                        :disabled="testing || form.url === ''"
                        class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-4 py-2 text-sm text-gray-300 transition hover:bg-gray-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                        @click="testWebhook"
                    >
                        {{
                            testing
                                ? 'Invio in corso...'
                                : 'Invia Notifica di Prova'
                        }}
                    </button>
                </div>
            </form>
        </div>

        <div :class="cardClass" class="p-6">
            <h2 class="font-bold text-gray-200">Come funziona</h2>
            <div class="mt-4 space-y-4 text-sm text-gray-400">
                <p>
                    Quando un'email viene <strong>elaborata</strong> — inviata
                    dal cron della coda, inviata subito con
                    <code class="font-mono text-blue-400">sync: true</code>,
                    oppure re-inviata a mano dalla dashboard — Mail Bridge
                    esegue una <code class="font-mono text-blue-400">POST</code>
                    JSON verso il webhook, riportando l'esito dell'invio.
                </p>
                <p>
                    La chiamata avviene sia in caso di successo (<code
                        class="font-mono text-blue-400"
                        >"success": true</code
                    >) sia in caso di errore (<code
                        class="font-mono text-blue-400"
                        >"success": false</code
                    >, con il motivo in
                    <code class="font-mono text-blue-400">error</code>).
                    L'accodamento della richiesta API, di per sé, non genera
                    nessuna notifica: quella arriva solo quando l'email viene
                    effettivamente lavorata.
                </p>
                <p class="text-xs text-gray-500">
                    Il webhook ha 10 secondi di tempo per rispondere e ci si
                    aspetta uno stato
                    <code class="font-mono text-gray-400">2xx</code>. Un webhook
                    lento, irraggiungibile o che risponde con un errore non
                    blocca né altera l'invio dell'email: il problema viene solo
                    annotato nel log dell'applicazione. Non sono previsti
                    tentativi ripetuti.
                </p>
            </div>
        </div>

        <div :class="cardClass" class="p-6">
            <h2 class="font-bold text-gray-200">
                Webhook specifico per una email
            </h2>
            <div class="mt-4 space-y-4 text-sm text-gray-400">
                <p>
                    Se la richiesta a
                    <code class="font-mono text-blue-400">POST /api/send</code>
                    contiene il parametro
                    <code class="font-mono text-blue-400">webhook</code>, la
                    notifica di quella email viene inviata a
                    <strong>quell'indirizzo</strong> e non a quello di default
                    configurato qui sopra. L'URL viene salvato sulla riga
                    dell'email, quindi vale anche per i re-invii successivi.
                </p>
                <pre :class="preClass">{{ overrideExample }}</pre>
                <p class="text-xs text-gray-500">
                    Il parametro accetta solo URL
                    <code class="font-mono text-gray-400">http</code> o
                    <code class="font-mono text-gray-400">https</code>; se non è
                    valido la richiesta viene rifiutata con
                    <code class="font-mono text-gray-400">400</code>
                    <code class="font-mono text-gray-400"
                        >{"error": "Invalid webhook"}</code
                    >. Con più destinatari lo stesso webhook viene assegnato a
                    tutte le email create dalla richiesta, che riceve quindi una
                    notifica per ciascuna. Tutti i dettagli nella
                    <Link
                        href="/settings/api-keys/docs"
                        class="text-blue-400 underline hover:text-blue-300"
                        >documentazione API</Link
                    >.
                </p>
            </div>
        </div>

        <div :class="cardClass" class="p-6">
            <h2 class="font-bold text-gray-200">Verifica della firma HMAC</h2>
            <div class="mt-4 space-y-4 text-sm text-gray-400">
                <p>
                    Con una chiave segreta configurata, ogni notifica include
                    l'intestazione
                    <code class="font-mono text-blue-400">{{
                        signatureHeader
                    }}</code>
                    con valore
                    <code class="font-mono text-blue-400"
                        >{{ signatureAlgo }}=&lt;hex&gt;</code
                    >, dove l'hex è l'HMAC-{{ signatureAlgo.toUpperCase() }} del
                    corpo JSON esatto della richiesta.
                </p>
                <pre :class="preClass">{{ verifyExample }}</pre>
                <p class="text-xs text-gray-500">
                    Calcola la firma sui byte grezzi ricevuti: ri-serializzare
                    il JSON dopo averlo decodificato può cambiare spaziatura e
                    ordine dei campi, e la verifica fallirebbe. Confronta sempre
                    con una funzione a tempo costante (<code
                        class="font-mono text-gray-400"
                        >hash_equals</code
                    >). Senza chiave segreta l'intestazione non viene inviata
                    affatto.
                </p>
            </div>
        </div>

        <div :class="cardClass" class="p-6">
            <h2 class="font-bold text-gray-200">Corpo della notifica</h2>
            <div class="mt-4 space-y-4">
                <p class="text-sm text-gray-400">
                    Email inviata correttamente:
                </p>
                <pre :class="preClass">{{ payloadExample }}</pre>
                <p class="text-sm text-gray-400">Invio fallito:</p>
                <pre :class="preClass">{{ failedExample }}</pre>
                <p class="text-xs text-gray-500">
                    Il campo
                    <code class="font-mono text-gray-400">uuid</code> è quello
                    passato nella richiesta API (o generato automaticamente) ed
                    è il modo consigliato per correlare la notifica al record
                    del tuo software.
                </p>
            </div>
        </div>
    </div>
</template>
