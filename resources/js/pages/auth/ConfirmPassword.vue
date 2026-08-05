<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { store } from '@/routes/password/confirm';
</script>

<template>
    <Head title="Conferma password" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="rounded-xl border border-gray-700 bg-gray-800 p-8 shadow-2xl"
    >
        <h1 class="mb-2 text-center text-2xl font-bold text-blue-400">
            Conferma la password
        </h1>
        <p class="mb-6 text-center text-sm text-gray-400">
            Questa è un'area protetta: conferma la tua password per continuare.
        </p>

        <div class="mb-6">
            <label class="mb-2 block text-sm text-gray-400" for="password"
                >Password</label
            >
            <input
                id="password"
                type="password"
                name="password"
                required
                autofocus
                autocomplete="current-password"
                class="w-full rounded border border-gray-700 bg-gray-900 p-3 text-white transition outline-none focus:border-blue-500"
            />
            <p v-if="errors.password" class="mt-2 text-xs text-red-400">
                {{ errors.password }}
            </p>
        </div>

        <button
            type="submit"
            :disabled="processing"
            class="w-full cursor-pointer rounded-lg bg-blue-600 py-3 font-bold text-white transition duration-200 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            Conferma
        </button>
    </Form>
</template>
