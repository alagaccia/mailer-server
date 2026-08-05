<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '@/components/bridge/ConfirmDialog.vue';
import CopyField from '@/components/bridge/CopyField.vue';

type ApiKeyRow = {
    id: number;
    name: string;
    key: string | null;
    created_at: string | null;
    updated_at: string | null;
};

defineProps<{
    apiKeys: ApiKeyRow[];
}>();

// --- Form crea/rinomina ------------------------------------------------------

const showForm = ref(false);
const editing = ref<ApiKeyRow | null>(null);

const form = useForm({
    name: '',
});

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function openRename(apiKey: ApiKeyRow): void {
    editing.value = apiKey;
    form.reset();
    form.clearErrors();
    form.name = apiKey.name;
    showForm.value = true;
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    };

    if (editing.value) {
        form.put(`/settings/api-keys/${editing.value.id}`, options);
    } else {
        form.post('/settings/api-keys', options);
    }
}

// --- Rigenerazione / eliminazione -------------------------------------------

const regenerating = ref<ApiKeyRow | null>(null);
const deleting = ref<ApiKeyRow | null>(null);

function confirmRegenerate(): void {
    const apiKey = regenerating.value;
    regenerating.value = null;

    if (apiKey) {
        router.post(
            `/settings/api-keys/${apiKey.id}/regenerate`,
            {},
            { preserveScroll: true },
        );
    }
}

function confirmDelete(): void {
    const apiKey = deleting.value;
    deleting.value = null;

    if (apiKey) {
        router.delete(`/settings/api-keys/${apiKey.id}`, {
            preserveScroll: true,
        });
    }
}

const inputClass =
    'w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition placeholder:text-gray-600 focus:border-blue-500 focus:outline-none';
const labelClass = 'mb-2 block text-xs font-bold text-gray-500 uppercase';
</script>

<template>
    <Head title="Chiavi API" />

    <div
        class="overflow-hidden rounded-2xl border border-gray-800 bg-gray-900 shadow-xl"
    >
        <div
            class="flex items-center justify-between border-b border-gray-800 bg-gray-900/50 px-6 py-4"
        >
            <div>
                <h2 class="font-bold text-gray-200">Chiavi API</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Da inviare alle richieste
                    <code class="font-mono text-blue-400">POST /api/send</code>
                    nell'header
                    <code class="font-mono text-blue-400">X-API-KEY</code> oppure
                    come bearer token
                    <code class="font-mono text-blue-400"
                        >Authorization: Bearer &lt;chiave&gt;</code
                    >. Qualunque chiave dell'elenco è valida.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    href="/settings/api-keys/docs"
                    class="rounded-lg border border-gray-700 bg-gray-800 px-4 py-2 text-sm text-gray-300 transition hover:bg-gray-700 hover:text-white"
                >
                    Documentazione API
                </Link>
                <button
                    type="button"
                    class="cursor-pointer rounded-lg border border-blue-500 bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700"
                    @click="openCreate"
                >
                    + Nuova Chiave
                </button>
            </div>
        </div>

        <p
            v-if="apiKeys.length === 0"
            class="px-6 py-10 text-center text-sm text-gray-500"
        >
            Nessuna chiave presente: finché l'elenco è vuoto l'API risponde 401.
        </p>

        <div v-else class="divide-y divide-gray-800/50">
            <div v-for="apiKey in apiKeys" :key="apiKey.id" class="px-6 py-5">
                <div class="mb-3 flex flex-wrap items-center gap-3">
                    <span class="font-bold text-gray-200">
                        {{ apiKey.name }}
                    </span>
                    <span class="text-xs text-gray-500">
                        creata il {{ apiKey.created_at }} · aggiornata il
                        {{ apiKey.updated_at }}
                    </span>

                    <div class="ml-auto flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="cursor-pointer rounded-lg border border-blue-500/30 bg-blue-600/20 px-3 py-1 text-xs text-blue-400 transition hover:bg-blue-600 hover:text-white"
                            @click="openRename(apiKey)"
                        >
                            Rinomina
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-lg border border-amber-500/30 bg-amber-600/20 px-3 py-1 text-xs text-amber-400 transition hover:bg-amber-600 hover:text-white"
                            @click="regenerating = apiKey"
                        >
                            Rigenera
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-lg border border-red-500/30 bg-red-600/20 px-3 py-1 text-xs text-red-400 transition hover:bg-red-600 hover:text-white"
                            @click="deleting = apiKey"
                        >
                            Elimina
                        </button>
                    </div>
                </div>

                <CopyField v-if="apiKey.key" :value="apiKey.key" />
                <p v-else class="text-xs text-red-400">
                    Valore illeggibile: la chiave è stata cifrata con un'altra
                    <code class="font-mono">APP_KEY</code>. Rigenerala per
                    tornare operativo.
                </p>
            </div>
        </div>
    </div>

    <!-- Modale crea/rinomina -->
    <teleport to="body">
        <transition name="fade">
            <div
                v-if="showForm"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <div
                    class="fixed inset-0 bg-black/70 backdrop-blur-sm"
                    @click="showForm = false"
                ></div>

                <div
                    class="relative z-10 w-full max-w-md rounded-2xl border border-gray-700 bg-gray-900 p-6 shadow-2xl"
                >
                    <h3 class="mb-6 text-lg font-bold text-gray-100">
                        {{
                            editing
                                ? `Rinomina "${editing.name}"`
                                : 'Nuova chiave API'
                        }}
                    </h3>

                    <form @submit.prevent="submit">
                        <div class="mb-6">
                            <label :class="labelClass" for="api_key_name"
                                >Nome</label
                            >
                            <input
                                id="api_key_name"
                                v-model="form.name"
                                type="text"
                                required
                                maxlength="64"
                                placeholder="es: sito-vetrina"
                                :class="inputClass"
                            />
                            <p
                                v-if="form.errors.name"
                                class="mt-2 text-xs text-red-400"
                            >
                                {{ form.errors.name }}
                            </p>
                            <p v-else class="mt-2 text-xs text-gray-500">
                                Serve solo a riconoscere l'integrazione che usa
                                la chiave.
                            </p>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-4 py-2 text-sm text-gray-300 transition hover:bg-gray-700 hover:text-white"
                                @click="showForm = false"
                            >
                                Annulla
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="cursor-pointer rounded-lg border border-blue-500 bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ editing ? 'Salva nome' : 'Crea chiave' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>
    </teleport>

    <ConfirmDialog
        :show="regenerating !== null"
        title="Rigenerare la chiave?"
        :message="`Il valore attuale di &quot;${regenerating?.name}&quot; smetterà immediatamente di funzionare: le integrazioni che lo usano dovranno essere aggiornate.`"
        confirm-label="Rigenera"
        danger
        @confirm="confirmRegenerate"
        @cancel="regenerating = null"
    />

    <ConfirmDialog
        :show="deleting !== null"
        title="Eliminare la chiave?"
        :message="`La chiave &quot;${deleting?.name}&quot; verrà eliminata definitivamente e smetterà subito di funzionare.`"
        confirm-label="Elimina"
        danger
        @confirm="confirmDelete"
        @cancel="deleting = null"
    />
</template>
