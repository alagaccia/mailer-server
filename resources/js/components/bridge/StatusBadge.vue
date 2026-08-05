<script setup lang="ts">
defineProps<{
    status: string;
}>();

const classes: Record<string, string> = {
    sent: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
    pending: 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20',
    failed: 'bg-red-500/10 text-red-500 border-red-500/20',
};
</script>

<template>
    <span
        class="rounded-full border px-3 py-1 text-[10px] font-black uppercase"
        :class="classes[status] ?? 'border-gray-700 bg-gray-800 text-gray-400'"
    >
        {{ status }}
    </span>
</template>
