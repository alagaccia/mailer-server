<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-gray-900 p-6 text-white antialiased"
    >
        <div class="w-full max-w-md">
            <slot />
        </div>

        <Toaster position="top-right" theme="dark" />
    </div>
</template>
