<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import InstallSuccess from '@/components/bridge/InstallSuccess.vue';
import { Toaster } from '@/components/ui/sonner';

defineProps<{
    apiKey: string;
}>();

/** Il token viaggia in query string: è ciò che autorizza questa pagina. */
const token = computed(
    () => new URLSearchParams(window.location.search).get('token'),
);
</script>

<template>
    <Head title="Installazione completata" />

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

            <InstallSuccess :api-key="apiKey" :token="token" />

            <p
                class="mt-8 text-center text-[10px] tracking-[0.2em] text-gray-700 uppercase"
            >
                Bridge Powered by Andrea Lagaccia &bull; Laravel Engine
            </p>
        </div>

        <Toaster position="top-right" theme="dark" />
    </div>
</template>
