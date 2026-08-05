<script setup lang="ts">
import { ref } from 'vue';
import CopyField from '@/components/bridge/CopyField.vue';
import { postJson } from '@/lib/http';

const props = defineProps<{
    apiKey: string;
    /** Token della schermata finale: serve solo per invalidarla. */
    token: string | null;
}>();

const leaving = ref(false);

/**
 * L'utente decide quando lasciare la pagina: prima di navigare invalidiamo il
 * token, così la chiave non resta esposta su una rotta pubblica.
 */
async function leaveTo(url: string): Promise<void> {
    leaving.value = true;

    if (props.token) {
        try {
            await postJson('/install/complete/dismiss', { token: props.token });
        } catch {
            // Il token scade comunque da solo: non blocchiamo la navigazione.
        }
    }

    window.location.href = url;
}
</script>

<template>
    <div class="rounded-2xl border border-gray-800 bg-gray-900 p-8 shadow-2xl">
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
                <code class="font-mono text-blue-400">POST /api/send</code>
                (header
                <code class="font-mono text-blue-400">X-API-KEY</code>). Potrai
                rivederla e rigenerarla dalle impostazioni.
            </p>
        </div>

        <CopyField :value="apiKey" />

        <p class="mt-4 text-center text-xs text-gray-500">
            Puoi restare su questa pagina quanto vuoi: ricaricandola la chiave
            resta visibile. Sparirà quando prosegui (o entro due ore).
        </p>

        <div class="mt-6 flex flex-wrap gap-3">
            <button
                type="button"
                :disabled="leaving"
                class="flex-1 cursor-pointer rounded-lg bg-blue-600 py-3 text-center font-bold text-white transition duration-200 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click="leaveTo('/dashboard')"
            >
                Vai alla Dashboard
            </button>
            <button
                type="button"
                :disabled="leaving"
                class="flex-1 cursor-pointer rounded-lg border border-gray-700 bg-gray-800 py-3 text-center font-bold text-gray-300 transition duration-200 hover:bg-gray-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                @click="leaveTo('/login')"
            >
                Vai al Login
            </button>
        </div>
    </div>
</template>
