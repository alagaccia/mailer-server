<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';

defineProps<{
    passwordRules?: string;
}>();
</script>

<template>
    <Head title="Sicurezza" />

    <div class="max-w-xl rounded-2xl border border-gray-800 bg-gray-900 p-6">
        <h2 class="font-bold text-gray-200">Aggiorna password</h2>
        <p class="mt-1 mb-6 text-xs text-gray-500">
            Usa una password lunga e casuale per mantenere sicuro il tuo
            account.
        </p>

        <Form
            v-bind="SecurityController.update.form()"
            :reset-on-success="[
                'current_password',
                'password',
                'password_confirmation',
            ]"
            v-slot="{ errors, processing, recentlySuccessful }"
        >
            <div class="mb-4">
                <label
                    class="mb-2 block text-xs font-bold text-gray-500 uppercase"
                    for="current_password"
                >
                    Password attuale
                </label>
                <input
                    id="current_password"
                    type="password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition focus:border-blue-500 focus:outline-none"
                />
                <p
                    v-if="errors.current_password"
                    class="mt-2 text-xs text-red-400"
                >
                    {{ errors.current_password }}
                </p>
            </div>

            <div class="mb-4">
                <label
                    class="mb-2 block text-xs font-bold text-gray-500 uppercase"
                    for="password"
                >
                    Nuova password
                </label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition focus:border-blue-500 focus:outline-none"
                />
                <p v-if="errors.password" class="mt-2 text-xs text-red-400">
                    {{ errors.password }}
                </p>
            </div>

            <div class="mb-6">
                <label
                    class="mb-2 block text-xs font-bold text-gray-500 uppercase"
                    for="password_confirmation"
                >
                    Conferma nuova password
                </label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition focus:border-blue-500 focus:outline-none"
                />
                <p
                    v-if="errors.password_confirmation"
                    class="mt-2 text-xs text-red-400"
                >
                    {{ errors.password_confirmation }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="processing"
                    class="cursor-pointer rounded-lg border border-blue-500 bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Aggiorna password
                </button>
                <span v-if="recentlySuccessful" class="text-xs text-emerald-500"
                    >Salvata.</span
                >
            </div>
        </Form>
    </div>
</template>
