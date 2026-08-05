<script setup lang="ts">
defineProps<{
    label: string;
    value: number;
    colorClass: string;
}>();

const formatter = new Intl.NumberFormat('it-IT');
</script>

<template>
    <div class="rounded-2xl border border-gray-800 bg-gray-900 p-6">
        <p class="text-xs font-bold tracking-wider text-gray-500 uppercase">
            {{ label }}
        </p>
        <p class="mt-2 text-4xl font-black" :class="colorClass">
            {{ formatter.format(value) }}
        </p>
    </div>
</template>
