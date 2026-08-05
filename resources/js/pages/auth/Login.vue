<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { store } from '@/routes/login';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Accesso" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="rounded-xl border border-gray-700 bg-gray-800 p-8 shadow-2xl"
    >
        <h1 class="mb-6 text-center text-2xl font-bold text-blue-400">
            Mail Bridge Access
        </h1>

        <div
            v-if="status"
            class="mb-4 rounded border border-green-700 bg-green-900/40 p-3 text-center text-sm text-green-300"
        >
            {{ status }}
        </div>

        <div
            v-if="errors.email || errors.password"
            class="mb-4 rounded border border-red-500 bg-red-900/50 p-3 text-center text-sm text-red-200 italic"
        >
            Credenziali errate!
        </div>

        <div class="mb-4">
            <label class="mb-2 block text-sm text-gray-400" for="email"
                >Email</label
            >
            <input
                id="email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="email"
                class="w-full rounded border border-gray-700 bg-gray-900 p-3 text-white transition outline-none focus:border-blue-500"
            />
        </div>

        <div class="mb-6">
            <label class="mb-2 block text-sm text-gray-400" for="password"
                >Password</label
            >
            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="w-full rounded border border-gray-700 bg-gray-900 p-3 text-white transition outline-none focus:border-blue-500"
            />
        </div>

        <label class="mb-6 flex items-center gap-2 text-sm text-gray-400">
            <input
                type="checkbox"
                name="remember"
                class="rounded border-gray-700 bg-gray-900"
            />
            Ricordami
        </label>

        <button
            type="submit"
            :disabled="processing"
            class="w-full cursor-pointer rounded-lg bg-blue-600 py-3 font-bold text-white transition duration-200 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ processing ? 'Accesso in corso...' : 'Accedi al Pannello' }}
        </button>
    </Form>
</template>
