<script setup lang="ts">
import { emailStatusLabel } from '@/lib/email-status';

defineProps<{
    status: string;
}>();

const classes: Record<string, string> = {
    sent: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20',
    pending: 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20',
    sending: 'bg-blue-500/10 text-blue-500 border-blue-500/20',
    failed: 'bg-red-500/10 text-red-500 border-red-500/20',
};
</script>

<template>
    <span
        class="rounded-full border px-3 py-1 text-[10px] font-black uppercase"
        :class="classes[status] ?? 'border-gray-700 bg-gray-800 text-gray-400'"
    >
        {{ emailStatusLabel(status) }}
    </span>
</template>
