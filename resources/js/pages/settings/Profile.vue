<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profilo" />

    <div class="max-w-xl rounded-2xl border border-gray-800 bg-gray-900 p-6">
        <h2 class="font-bold text-gray-200">Profilo</h2>
        <p class="mt-1 mb-6 text-xs text-gray-500">
            Aggiorna nome e indirizzo email del tuo account.
        </p>

        <Form
            v-bind="ProfileController.update.form()"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="mb-4">
                <label
                    class="mb-2 block text-xs font-bold text-gray-500 uppercase"
                    for="name"
                    >Nome</label
                >
                <input
                    id="name"
                    type="text"
                    name="name"
                    :defaultValue="user?.name"
                    required
                    autocomplete="name"
                    class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition focus:border-blue-500 focus:outline-none"
                />
                <p v-if="errors.name" class="mt-2 text-xs text-red-400">
                    {{ errors.name }}
                </p>
            </div>

            <div class="mb-6">
                <label
                    class="mb-2 block text-xs font-bold text-gray-500 uppercase"
                    for="email"
                    >Email</label
                >
                <input
                    id="email"
                    type="email"
                    name="email"
                    :defaultValue="user?.email"
                    required
                    autocomplete="username"
                    class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition focus:border-blue-500 focus:outline-none"
                />
                <p v-if="errors.email" class="mt-2 text-xs text-red-400">
                    {{ errors.email }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="processing"
                    class="cursor-pointer rounded-lg border border-blue-500 bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Salva
                </button>
                <span v-if="recentlySuccessful" class="text-xs text-emerald-500"
                    >Salvato.</span
                >
            </div>
        </Form>
    </div>
</template>
