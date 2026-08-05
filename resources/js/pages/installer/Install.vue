<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import CopyField from '@/components/bridge/CopyField.vue';
import { Toaster } from '@/components/ui/sonner';
import { postJson } from '@/lib/http';

type ComposerStatus = {
    available: boolean;
    source: 'project' | 'system' | null;
    path: string | null;
    version: string | null;
};

const props = defineProps<{
    envWritable: boolean;
    composer: ComposerStatus;
}>();

const steps = [
    { id: 1, title: 'Amministratore' },
    { id: 2, title: 'Database' },
    { id: 3, title: 'SMTP' },
    { id: 4, title: 'Composer' },
];

const step = ref(1);
const busy = ref(false);
const testingSmtp = ref(false);
const installingComposer = ref(false);
const composer = ref<ComposerStatus>(props.composer);
const apiKey = ref<string | null>(null);
const errors = ref<Record<string, string[]>>({});

const admin = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const db = reactive({
    host: 'localhost',
    port: 3306,
    database: '',
    username: '',
    password: '',
});

const smtp = reactive({
    host: '',
    port: 465,
    username: '',
    password: '',
    encryption: 'ssl',
    from_name: '',
    from_address: '',
    reply_to: '',
});

const installed = computed(() => apiKey.value !== null);

/**
 * Primo errore per il campo: gli endpoint per step usano chiavi semplici
 * ("name"), il finalize le prefissa ("admin.name").
 */
function err(field: string, prefix?: string): string | null {
    return (
        errors.value[field]?.[0] ??
        (prefix ? (errors.value[`${prefix}.${field}`]?.[0] ?? null) : null)
    );
}

function goTo(target: number): void {
    if (target < step.value) {
        step.value = target;
    }
}

async function validateStep(
    url: string,
    payload: unknown,
    nextStep: number,
): Promise<void> {
    busy.value = true;
    errors.value = {};

    try {
        const { ok, status, data } = await postJson(url, payload);

        if (ok) {
            step.value = nextStep;
        } else if (status === 422 && data.errors) {
            errors.value = data.errors;
        } else {
            toast.error(data.message ?? 'Errore imprevisto, riprova.');
        }
    } catch {
        toast.error('Errore di rete, riprova.');
    } finally {
        busy.value = false;
    }
}

async function testSmtp(): Promise<void> {
    testingSmtp.value = true;
    errors.value = {};

    try {
        const { ok, status, data } = await postJson('/install/test-smtp', smtp);

        if (ok) {
            toast.success('Connessione SMTP riuscita!');
        } else if (status === 422 && data.errors) {
            errors.value = data.errors;
            toast.error('Connessione SMTP non riuscita.');
        } else {
            toast.error(data.message ?? 'Errore imprevisto durante il test.');
        }
    } catch {
        toast.error('Errore di rete durante il test.');
    } finally {
        testingSmtp.value = false;
    }
}

/**
 * Scarica composer.phar dentro al progetto (passo facoltativo).
 */
async function installComposer(): Promise<void> {
    installingComposer.value = true;
    errors.value = {};

    try {
        const { ok, status, data } = await postJson<{
            composer?: ComposerStatus;
        }>('/install/composer', {});

        if (ok && data.composer) {
            composer.value = data.composer;
            toast.success('Composer installato nel progetto!');
        } else if (status === 422 && data.errors) {
            errors.value = data.errors;
            toast.error('Installazione di Composer non riuscita.');
        } else {
            toast.error(
                data.message ?? 'Errore imprevisto durante il download.',
            );
        }
    } catch {
        toast.error('Errore di rete durante il download di Composer.');
    } finally {
        installingComposer.value = false;
    }
}

async function finalize(): Promise<void> {
    busy.value = true;
    errors.value = {};

    try {
        const { ok, status, data } = await postJson<{ api_key?: string }>(
            '/install/finalize',
            {
                admin,
                db,
                smtp,
            },
        );

        if (ok && data.api_key) {
            apiKey.value = data.api_key;

            // L'installer è ora disattivato: un refresh su /install darebbe
            // 404. Aggiorniamo la barra indirizzi senza ricaricare la pagina,
            // così un F5 accidentale porta al login invece che a un 404.
            window.history.replaceState({}, '', '/login');
        } else if (status === 422 && data.errors) {
            errors.value = data.errors;

            const keys = Object.keys(data.errors).join(' ');

            if (keys.includes('admin.')) {
                step.value = 1;
            } else if (keys.includes('db.')) {
                step.value = 2;
            } else if (keys.includes('smtp.')) {
                step.value = 3;
            }

            toast.error(
                "Controlla i dati inseriti: l'installazione non è andata a buon fine.",
            );
        } else {
            toast.error(
                data.message ?? "Errore imprevisto durante l'installazione.",
            );
        }
    } catch {
        toast.error("Errore di rete durante l'installazione.");
    } finally {
        busy.value = false;
    }
}

const inputClass =
    'w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition placeholder:text-gray-600 focus:border-blue-500 focus:outline-none';
const labelClass = 'mb-2 block text-xs font-bold text-gray-500 uppercase';
const errorClass = 'mt-2 text-xs text-red-400';
</script>

<template>
    <Head title="Installazione" />

    <div
        class="flex min-h-screen items-center justify-center bg-gray-950 p-6 text-gray-100 antialiased"
    >
        <div class="w-full max-w-2xl">
            <div class="mb-8 text-center">
                <h1
                    class="bg-gradient-to-r from-blue-400 to-emerald-400 bg-clip-text text-3xl font-extrabold text-transparent"
                >
                    Installazione Mail Bridge
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    Configurazione guidata del primo avvio
                </p>
            </div>

            <div
                v-if="!envWritable"
                class="mb-6 rounded-xl border border-red-500 bg-red-900/50 p-4 text-center text-sm text-red-200"
            >
                Il file <code class="font-mono">.env</code> non è scrivibile:
                correggi i permessi prima di procedere.
            </div>

            <!-- Schermata finale -->
            <div
                v-if="installed"
                class="rounded-2xl border border-gray-800 bg-gray-900 p-8 shadow-2xl"
            >
                <div class="mb-6 text-center">
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-emerald-500/30 bg-emerald-500/10 text-3xl"
                    >
                        ✓
                    </div>
                    <h2 class="text-xl font-bold text-emerald-400">
                        Installazione completata!
                    </h2>
                    <p class="mt-2 text-sm text-gray-400">
                        Questa è la chiave API per l'endpoint
                        <code class="font-mono text-blue-400"
                            >POST /api/send</code
                        >
                        (header
                        <code class="font-mono text-blue-400">X-API-KEY</code>).
                        Potrai rivederla e rigenerarla dalle impostazioni.
                    </p>
                </div>

                <CopyField :value="apiKey ?? ''" />

                <a
                    href="/login"
                    class="mt-6 block w-full rounded-lg bg-blue-600 py-3 text-center font-bold text-white transition duration-200 hover:bg-blue-700"
                >
                    Vai al Login
                </a>
            </div>

            <!-- Wizard -->
            <div
                v-else
                class="rounded-2xl border border-gray-800 bg-gray-900 shadow-2xl"
            >
                <div class="flex border-b border-gray-800">
                    <button
                        v-for="item in steps"
                        :key="item.id"
                        type="button"
                        class="flex-1 px-4 py-4 text-center text-sm font-bold transition"
                        :class="[
                            item.id === step
                                ? 'border-b-2 border-blue-500 text-blue-400'
                                : item.id < step
                                  ? 'cursor-pointer text-emerald-500 hover:text-emerald-400'
                                  : 'cursor-default text-gray-600',
                        ]"
                        @click="goTo(item.id)"
                    >
                        {{ item.id }}. {{ item.title }}
                        <span v-if="item.id < step" class="ml-1">✓</span>
                    </button>
                </div>

                <div class="p-8">
                    <!-- Step 1: amministratore -->
                    <form
                        v-if="step === 1"
                        @submit.prevent="
                            validateStep('/install/validate-admin', admin, 2)
                        "
                    >
                        <p class="mb-6 text-sm text-gray-400">
                            Crea il primo utente: avrà i privilegi di
                            amministratore.
                        </p>

                        <div class="mb-4">
                            <label :class="labelClass" for="admin_name"
                                >Nome</label
                            >
                            <input
                                id="admin_name"
                                v-model="admin.name"
                                type="text"
                                required
                                :class="inputClass"
                            />
                            <p v-if="err('name', 'admin')" :class="errorClass">
                                {{ err('name', 'admin') }}
                            </p>
                        </div>

                        <div class="mb-4">
                            <label :class="labelClass" for="admin_email"
                                >Email</label
                            >
                            <input
                                id="admin_email"
                                v-model="admin.email"
                                type="email"
                                required
                                :class="inputClass"
                            />
                            <p v-if="err('email', 'admin')" :class="errorClass">
                                {{ err('email', 'admin') }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label :class="labelClass" for="admin_password"
                                    >Password</label
                                >
                                <input
                                    id="admin_password"
                                    v-model="admin.password"
                                    type="password"
                                    required
                                    autocomplete="new-password"
                                    :class="inputClass"
                                />
                                <p
                                    v-if="err('password', 'admin')"
                                    :class="errorClass"
                                >
                                    {{ err('password', 'admin') }}
                                </p>
                            </div>
                            <div>
                                <label
                                    :class="labelClass"
                                    for="admin_password_confirmation"
                                    >Conferma password</label
                                >
                                <input
                                    id="admin_password_confirmation"
                                    v-model="admin.password_confirmation"
                                    type="password"
                                    required
                                    autocomplete="new-password"
                                    :class="inputClass"
                                />
                            </div>
                        </div>

                        <div class="mt-8 flex justify-end">
                            <button
                                type="submit"
                                :disabled="busy"
                                class="cursor-pointer rounded-lg border border-blue-500 bg-blue-600 px-6 py-2 font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ busy ? 'Verifica...' : 'Avanti →' }}
                            </button>
                        </div>
                    </form>

                    <!-- Step 2: database -->
                    <form
                        v-else-if="step === 2"
                        @submit.prevent="
                            validateStep('/install/test-database', db, 3)
                        "
                    >
                        <p class="mb-6 text-sm text-gray-400">
                            Credenziali del database MySQL: la connessione viene
                            verificata prima di proseguire.
                        </p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label :class="labelClass" for="db_host"
                                    >Host</label
                                >
                                <input
                                    id="db_host"
                                    v-model="db.host"
                                    type="text"
                                    required
                                    :class="inputClass"
                                />
                                <p v-if="err('host', 'db')" :class="errorClass">
                                    {{ err('host', 'db') }}
                                </p>
                            </div>
                            <div>
                                <label :class="labelClass" for="db_port"
                                    >Porta</label
                                >
                                <input
                                    id="db_port"
                                    v-model.number="db.port"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    required
                                    :class="inputClass"
                                />
                                <p v-if="err('port', 'db')" :class="errorClass">
                                    {{ err('port', 'db') }}
                                </p>
                            </div>
                            <div>
                                <label :class="labelClass" for="db_database"
                                    >Nome database</label
                                >
                                <input
                                    id="db_database"
                                    v-model="db.database"
                                    type="text"
                                    required
                                    :class="inputClass"
                                />
                                <p
                                    v-if="err('database', 'db')"
                                    :class="errorClass"
                                >
                                    {{ err('database', 'db') }}
                                </p>
                            </div>
                            <div>
                                <label :class="labelClass" for="db_username"
                                    >Username</label
                                >
                                <input
                                    id="db_username"
                                    v-model="db.username"
                                    type="text"
                                    required
                                    :class="inputClass"
                                />
                                <p
                                    v-if="err('username', 'db')"
                                    :class="errorClass"
                                >
                                    {{ err('username', 'db') }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <label :class="labelClass" for="db_password"
                                    >Password</label
                                >
                                <input
                                    id="db_password"
                                    v-model="db.password"
                                    type="password"
                                    autocomplete="off"
                                    :class="inputClass"
                                />
                                <p
                                    v-if="err('password', 'db')"
                                    :class="errorClass"
                                >
                                    {{ err('password', 'db') }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-between">
                            <button
                                type="button"
                                class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-6 py-2 text-gray-300 transition hover:bg-gray-700 hover:text-white"
                                @click="step = 1"
                            >
                                ← Indietro
                            </button>
                            <button
                                type="submit"
                                :disabled="busy"
                                class="cursor-pointer rounded-lg border border-blue-500 bg-blue-600 px-6 py-2 font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{
                                    busy
                                        ? 'Connessione...'
                                        : 'Testa e Prosegui →'
                                }}
                            </button>
                        </div>
                    </form>

                    <!-- Step 3: SMTP -->
                    <form v-else-if="step === 3" @submit.prevent="step = 4">
                        <p class="mb-6 text-sm text-gray-400">
                            Parametri SMTP per l'invio delle email. Potrai
                            modificarli in seguito dalle impostazioni.
                        </p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label :class="labelClass" for="smtp_host"
                                    >Host SMTP</label
                                >
                                <input
                                    id="smtp_host"
                                    v-model="smtp.host"
                                    type="text"
                                    required
                                    placeholder="es: smtp.example.com"
                                    :class="inputClass"
                                />
                                <p
                                    v-if="err('host', 'smtp')"
                                    :class="errorClass"
                                >
                                    {{ err('host', 'smtp') }}
                                </p>
                            </div>
                            <div>
                                <label :class="labelClass" for="smtp_port"
                                    >Porta</label
                                >
                                <input
                                    id="smtp_port"
                                    v-model.number="smtp.port"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    required
                                    :class="inputClass"
                                />
                                <p
                                    v-if="err('port', 'smtp')"
                                    :class="errorClass"
                                >
                                    {{ err('port', 'smtp') }}
                                </p>
                            </div>
                            <div>
                                <label :class="labelClass" for="smtp_username"
                                    >Username</label
                                >
                                <input
                                    id="smtp_username"
                                    v-model="smtp.username"
                                    type="text"
                                    autocomplete="off"
                                    :class="inputClass"
                                />
                                <p
                                    v-if="err('username', 'smtp')"
                                    :class="errorClass"
                                >
                                    {{ err('username', 'smtp') }}
                                </p>
                            </div>
                            <div>
                                <label :class="labelClass" for="smtp_password"
                                    >Password</label
                                >
                                <input
                                    id="smtp_password"
                                    v-model="smtp.password"
                                    type="password"
                                    autocomplete="off"
                                    :class="inputClass"
                                />
                                <p
                                    v-if="err('password', 'smtp')"
                                    :class="errorClass"
                                >
                                    {{ err('password', 'smtp') }}
                                </p>
                            </div>
                            <div>
                                <label :class="labelClass" for="smtp_encryption"
                                    >Cifratura</label
                                >
                                <select
                                    id="smtp_encryption"
                                    v-model="smtp.encryption"
                                    :class="inputClass"
                                >
                                    <option value="ssl">SSL (porta 465)</option>
                                    <option value="tls">
                                        TLS / STARTTLS (porta 587)
                                    </option>
                                    <option value="none">Nessuna</option>
                                </select>
                                <p
                                    v-if="err('encryption', 'smtp')"
                                    :class="errorClass"
                                >
                                    {{ err('encryption', 'smtp') }}
                                </p>
                            </div>
                            <div>
                                <label :class="labelClass" for="smtp_from_name"
                                    >Nome mittente</label
                                >
                                <input
                                    id="smtp_from_name"
                                    v-model="smtp.from_name"
                                    type="text"
                                    :class="inputClass"
                                />
                                <p
                                    v-if="err('from_name', 'smtp')"
                                    :class="errorClass"
                                >
                                    {{ err('from_name', 'smtp') }}
                                </p>
                            </div>
                            <div>
                                <label
                                    :class="labelClass"
                                    for="smtp_from_address"
                                    >Indirizzo mittente</label
                                >
                                <input
                                    id="smtp_from_address"
                                    v-model="smtp.from_address"
                                    type="email"
                                    placeholder="Se vuoto viene usato lo username"
                                    :class="inputClass"
                                />
                                <p
                                    v-if="err('from_address', 'smtp')"
                                    :class="errorClass"
                                >
                                    {{ err('from_address', 'smtp') }}
                                </p>
                            </div>
                            <div>
                                <label :class="labelClass" for="smtp_reply_to"
                                    >Reply-To</label
                                >
                                <input
                                    id="smtp_reply_to"
                                    v-model="smtp.reply_to"
                                    type="email"
                                    placeholder="Facoltativo"
                                    :class="inputClass"
                                />
                                <p
                                    v-if="err('reply_to', 'smtp')"
                                    :class="errorClass"
                                >
                                    {{ err('reply_to', 'smtp') }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="mt-8 flex flex-wrap items-center justify-between gap-2"
                        >
                            <button
                                type="button"
                                class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-6 py-2 text-gray-300 transition hover:bg-gray-700 hover:text-white"
                                @click="step = 2"
                            >
                                ← Indietro
                            </button>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    :disabled="testingSmtp"
                                    class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-6 py-2 text-gray-300 transition hover:bg-gray-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="testSmtp"
                                >
                                    {{
                                        testingSmtp
                                            ? 'Test...'
                                            : 'Testa Connessione'
                                    }}
                                </button>
                                <button
                                    type="submit"
                                    class="cursor-pointer rounded-lg border border-blue-500 bg-blue-600 px-6 py-2 font-bold text-white transition hover:bg-blue-700"
                                >
                                    Avanti →
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Step 4: Composer (facoltativo) -->
                    <form v-else @submit.prevent="finalize">
                        <p class="mb-6 text-sm text-gray-400">
                            Composer serve per aggiornare le dipendenze del
                            bridge. Se non è disponibile sul server, puoi
                            installarne una copia locale nel progetto: viene
                            scaricato
                            <code class="font-mono text-blue-400"
                                >composer.phar</code
                            >
                            dal sito ufficiale (con verifica SHA-256) e aggiunto
                            a <code class="font-mono">.gitignore</code>, quindi
                            non finisce su git.
                        </p>

                        <div
                            v-if="composer.available"
                            class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-200"
                        >
                            <p class="font-bold">
                                {{
                                    composer.source === 'project'
                                        ? 'Composer è installato nel progetto.'
                                        : 'Composer è già disponibile sul server.'
                                }}
                            </p>
                            <p class="mt-1 text-emerald-300/80">
                                <span class="font-mono">{{
                                    composer.path
                                }}</span>
                                <span v-if="composer.version">
                                    — {{ composer.version }}</span
                                >
                            </p>
                        </div>

                        <div
                            v-else
                            class="rounded-xl border border-gray-700 bg-gray-800/50 p-4 text-sm text-gray-300"
                        >
                            <p class="font-bold text-gray-200">
                                Composer non è disponibile su questo server.
                            </p>
                            <p class="mt-1 text-gray-400">
                                Puoi installarlo ora dentro al progetto oppure
                                saltare questo passaggio e proseguire.
                            </p>
                        </div>

                        <p v-if="err('composer')" :class="errorClass">
                            {{ err('composer') }}
                        </p>

                        <div
                            v-if="composer.source !== 'project'"
                            class="mt-4"
                        >
                            <button
                                type="button"
                                :disabled="installingComposer"
                                class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-6 py-2 text-gray-300 transition hover:bg-gray-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                                @click="installComposer"
                            >
                                {{
                                    installingComposer
                                        ? 'Download in corso...'
                                        : 'Installa Composer nel progetto'
                                }}
                            </button>
                        </div>

                        <div
                            class="mt-8 flex flex-wrap items-center justify-between gap-2"
                        >
                            <button
                                type="button"
                                class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-6 py-2 text-gray-300 transition hover:bg-gray-700 hover:text-white"
                                @click="step = 3"
                            >
                                ← Indietro
                            </button>
                            <button
                                type="submit"
                                :disabled="busy || !envWritable"
                                class="cursor-pointer rounded-lg border border-emerald-500 bg-emerald-600 px-6 py-2 font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{
                                    busy
                                        ? 'Installazione...'
                                        : 'Completa Installazione'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <p
                class="mt-8 text-center text-[10px] tracking-[0.2em] text-gray-700 uppercase"
            >
                Bridge Powered by Andrea Lagaccia &bull; Laravel Engine
            </p>
        </div>

        <Toaster position="top-right" theme="dark" />
    </div>
</template>
