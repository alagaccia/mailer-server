<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/bridge/ConfirmDialog.vue';
import CopyField from '@/components/bridge/CopyField.vue';
import { firstValidationError, postJson } from '@/lib/http';

const props = defineProps<{
    smtp: {
        host: string;
        port: number;
        username: string;
        encryption: string;
        from_name: string;
        from_address: string;
        reply_to: string;
        password_set: boolean;
    };
    apiKey: string | null;
}>();

const form = useForm({
    host: props.smtp.host,
    port: props.smtp.port,
    username: props.smtp.username,
    password: '',
    encryption: props.smtp.encryption,
    from_name: props.smtp.from_name,
    from_address: props.smtp.from_address,
    reply_to: props.smtp.reply_to,
});

function save(): void {
    form.put('/settings/smtp', {
        preserveScroll: true,
        onSuccess: () => form.reset('password'),
    });
}

// --- Test connessione / email di prova --------------------------------------

const testing = ref(false);
const sendingTest = ref(false);
const testTo = ref('');

async function testConnection(): Promise<void> {
    testing.value = true;

    try {
        const { status, data } = await postJson<{ ok?: boolean }>(
            '/settings/smtp/test',
            form.data(),
        );

        if (status === 422) {
            toast.error(firstValidationError(data));
        } else if (data.ok) {
            toast.success('Connessione SMTP riuscita!');
        } else {
            toast.error(
                `Connessione fallita: ${data.error ?? 'errore sconosciuto'}`,
            );
        }
    } catch {
        toast.error('Errore di rete durante il test.');
    } finally {
        testing.value = false;
    }
}

async function sendTestEmail(): Promise<void> {
    sendingTest.value = true;

    try {
        const { status, data } = await postJson<{ ok?: boolean }>(
            '/settings/smtp/test-email',
            {
                ...form.data(),
                to: testTo.value,
            },
        );

        if (status === 422) {
            toast.error(firstValidationError(data));
        } else if (data.ok) {
            toast.success(`Email di prova inviata a ${testTo.value}.`);
        } else {
            toast.error(`Invio fallito: ${data.error ?? 'errore sconosciuto'}`);
        }
    } catch {
        toast.error("Errore di rete durante l'invio di prova.");
    } finally {
        sendingTest.value = false;
    }
}

// --- Chiave API --------------------------------------------------------------

const confirmRegenerate = ref(false);

function regenerate(): void {
    confirmRegenerate.value = false;
    router.post('/settings/api-key/regenerate', {}, { preserveScroll: true });
}

const inputClass =
    'w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition placeholder:text-gray-600 focus:border-blue-500 focus:outline-none';
const labelClass = 'mb-2 block text-xs font-bold text-gray-500 uppercase';
</script>

<template>
    <Head title="Impostazioni SMTP" />

    <div class="space-y-8">
        <div
            class="rounded-2xl border border-gray-800 bg-gray-900 p-6 shadow-xl"
        >
            <h2 class="font-bold text-gray-200">Configurazione SMTP</h2>
            <p class="mt-1 mb-6 text-xs text-gray-500">
                Parametri usati per l'invio delle email. Le modifiche hanno
                effetto immediato.
            </p>

            <form @submit.prevent="save">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label :class="labelClass" for="host">Host SMTP</label>
                        <input
                            id="host"
                            v-model="form.host"
                            type="text"
                            placeholder="es: smtp.example.com"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.host"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.host }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass" for="port">Porta</label>
                        <input
                            id="port"
                            v-model.number="form.port"
                            type="number"
                            min="1"
                            max="65535"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.port"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.port }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass" for="username"
                            >Username</label
                        >
                        <input
                            id="username"
                            v-model="form.username"
                            type="text"
                            autocomplete="off"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.username"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.username }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass" for="password"
                            >Password</label
                        >
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                smtp.password_set
                                    ? 'Lascia vuoto per non modificarla'
                                    : ''
                            "
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.password"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass" for="encryption"
                            >Cifratura</label
                        >
                        <select
                            id="encryption"
                            v-model="form.encryption"
                            :class="inputClass"
                        >
                            <option value="ssl">SSL (porta 465)</option>
                            <option value="tls">
                                TLS / STARTTLS (porta 587)
                            </option>
                            <option value="none">Nessuna</option>
                        </select>
                        <p
                            v-if="form.errors.encryption"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.encryption }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass" for="from_name"
                            >Nome mittente</label
                        >
                        <input
                            id="from_name"
                            v-model="form.from_name"
                            type="text"
                            placeholder="es: Mail Bridge"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.from_name"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.from_name }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass" for="from_address"
                            >Indirizzo mittente</label
                        >
                        <input
                            id="from_address"
                            v-model="form.from_address"
                            type="email"
                            placeholder="Se vuoto viene usato lo username"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.from_address"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.from_address }}
                        </p>
                    </div>

                    <div>
                        <label :class="labelClass" for="reply_to"
                            >Reply-To</label
                        >
                        <input
                            id="reply_to"
                            v-model="form.reply_to"
                            type="email"
                            placeholder="Facoltativo"
                            :class="inputClass"
                        />
                        <p
                            v-if="form.errors.reply_to"
                            class="mt-2 text-xs text-red-400"
                        >
                            {{ form.errors.reply_to }}
                        </p>
                    </div>
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
                        :disabled="testing"
                        class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-4 py-2 text-sm text-gray-300 transition hover:bg-gray-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                        @click="testConnection"
                    >
                        {{ testing ? 'Test in corso...' : 'Testa Connessione' }}
                    </button>

                    <div class="ml-auto flex items-center gap-2">
                        <input
                            v-model="testTo"
                            type="email"
                            placeholder="destinatario@prova.it"
                            class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition placeholder:text-gray-600 focus:border-blue-500 focus:outline-none"
                        />
                        <button
                            type="button"
                            :disabled="sendingTest || testTo === ''"
                            class="cursor-pointer rounded-lg border border-emerald-500/30 bg-emerald-600/20 px-4 py-2 text-sm text-emerald-400 transition hover:bg-emerald-600 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                            @click="sendTestEmail"
                        >
                            {{
                                sendingTest
                                    ? 'Invio...'
                                    : 'Invia Email di Prova'
                            }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div
            class="rounded-2xl border border-gray-800 bg-gray-900 p-6 shadow-xl"
        >
            <h2 class="font-bold text-gray-200">Chiave API</h2>
            <p class="mt-1 mb-6 text-xs text-gray-500">
                Da inviare nell'header
                <code class="font-mono text-blue-400">X-API-KEY</code> alle
                richieste
                <code class="font-mono text-blue-400">POST /api/send</code>.
            </p>

            <CopyField v-if="apiKey" :value="apiKey" />

            <button
                type="button"
                class="mt-4 cursor-pointer rounded-lg border border-red-900/50 bg-red-600/20 px-4 py-2 text-sm font-bold text-red-400 transition hover:bg-red-600 hover:text-white"
                @click="confirmRegenerate = true"
            >
                Rigenera Chiave
            </button>
        </div>
    </div>

    <ConfirmDialog
        :show="confirmRegenerate"
        title="Rigenerare la chiave API?"
        message="La chiave attuale smetterà immediatamente di funzionare: tutte le integrazioni esistenti dovranno essere aggiornate."
        confirm-label="Rigenera"
        danger
        @confirm="regenerate"
        @cancel="confirmRegenerate = false"
    />
</template>
