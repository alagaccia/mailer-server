<script setup lang="ts">
import { toast } from 'vue-sonner';

const props = defineProps<{
    value: string;
}>();

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.value);
        toast.success('Copiato negli appunti.');
    } catch {
        toast.error('Copia non riuscita: seleziona e copia manualmente.');
    }
}
</script>

<template>
    <div class="flex items-center gap-2">
        <code
            class="min-w-0 flex-1 overflow-x-auto rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 font-mono text-xs break-all text-emerald-400"
        >
            {{ value }}
        </code>
        <button
            type="button"
            class="shrink-0 cursor-pointer rounded-lg border border-blue-500/30 bg-blue-600/20 px-3 py-2 text-xs font-bold text-blue-400 transition hover:bg-blue-600 hover:text-white"
            @click="copy"
        >
            Copia
        </button>
    </div>
</template>
