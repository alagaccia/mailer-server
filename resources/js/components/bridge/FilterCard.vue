<script setup lang="ts">
import { reactive, watch } from 'vue';
import { emailStatusLabel } from '@/lib/email-status';

export type EmailFilters = {
    filter_recipient: string;
    filter_subject: string;
    filter_status: string;
    filter_date_from: string;
    filter_date_to: string;
};

const props = withDefaults(
    defineProps<{
        filters: EmailFilters;
        statuses?: string[];
    }>(),
    {
        statuses: () => ['pending', 'sending', 'sent', 'failed'],
    },
);

const emit = defineEmits<{
    search: [filters: EmailFilters];
    reset: [];
}>();

const local = reactive<EmailFilters>({ ...props.filters });

watch(
    () => props.filters,
    (value) => {
        Object.assign(local, value);
    },
);

function submit(): void {
    emit('search', { ...local });
}

function reset(): void {
    local.filter_recipient = '';
    local.filter_subject = '';
    local.filter_status = '';
    local.filter_date_from = '';
    local.filter_date_to = '';
    emit('reset');
}
</script>

<template>
    <div
        class="mb-8 rounded-2xl border border-gray-800 bg-gray-900 p-6 shadow-xl"
    >
        <h2 class="mb-4 flex items-center gap-2 font-bold text-gray-200">
            <svg
                class="h-5 w-5 text-blue-400"
                viewBox="0 0 20 20"
                fill="currentColor"
            >
                <path
                    fill-rule="evenodd"
                    d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z"
                    clip-rule="evenodd"
                />
            </svg>
            Filtri di Ricerca
        </h2>

        <form @submit.prevent="submit">
            <div
                class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
            >
                <div>
                    <label
                        class="mb-2 block text-xs font-bold text-gray-500 uppercase"
                        for="filter_recipient"
                    >
                        Destinatario
                    </label>
                    <input
                        id="filter_recipient"
                        v-model="local.filter_recipient"
                        type="text"
                        placeholder="es: user@domain.com"
                        class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition placeholder:text-gray-600 focus:border-blue-500 focus:outline-none"
                    />
                </div>

                <div>
                    <label
                        class="mb-2 block text-xs font-bold text-gray-500 uppercase"
                        for="filter_subject"
                    >
                        Oggetto
                    </label>
                    <input
                        id="filter_subject"
                        v-model="local.filter_subject"
                        type="text"
                        placeholder="es: Benvenuto"
                        class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition placeholder:text-gray-600 focus:border-blue-500 focus:outline-none"
                    />
                </div>

                <div>
                    <label
                        class="mb-2 block text-xs font-bold text-gray-500 uppercase"
                        for="filter_status"
                    >
                        Stato
                    </label>
                    <select
                        id="filter_status"
                        v-model="local.filter_status"
                        class="w-full cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition focus:border-blue-500 focus:outline-none"
                    >
                        <option value="">Tutti</option>
                        <option
                            v-for="status in statuses"
                            :key="status"
                            :value="status"
                        >
                            {{ emailStatusLabel(status) }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        class="mb-2 block text-xs font-bold text-gray-500 uppercase"
                        for="filter_date_from"
                    >
                        Data Da
                    </label>
                    <input
                        id="filter_date_from"
                        v-model="local.filter_date_from"
                        type="date"
                        class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition focus:border-blue-500 focus:outline-none"
                    />
                </div>

                <div>
                    <label
                        class="mb-2 block text-xs font-bold text-gray-500 uppercase"
                        for="filter_date_to"
                    >
                        Data A
                    </label>
                    <input
                        id="filter_date_to"
                        v-model="local.filter_date_to"
                        type="date"
                        class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition focus:border-blue-500 focus:outline-none"
                    />
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <button
                    type="submit"
                    class="cursor-pointer rounded-lg border border-blue-500 bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700"
                >
                    🔍 Cerca
                </button>
                <button
                    type="button"
                    class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-4 py-2 text-sm text-gray-300 transition hover:bg-gray-700 hover:text-white"
                    @click="reset"
                >
                    ✕ Ripristina
                </button>
            </div>
        </form>
    </div>
</template>
