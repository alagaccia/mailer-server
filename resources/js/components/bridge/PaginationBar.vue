<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    currentPage: number;
    lastPage: number;
    total: number;
    from: number | null;
    to: number | null;
}>();

const emit = defineEmits<{
    goto: [page: number];
}>();

const formatter = new Intl.NumberFormat('it-IT');

const pages = computed(() => {
    const start = Math.max(1, props.currentPage - 3);
    const end = Math.min(props.lastPage, props.currentPage + 3);
    const list: number[] = [];

    for (let page = start; page <= end; page++) {
        list.push(page);
    }

    return list;
});

function goto(page: number): void {
    if (page >= 1 && page <= props.lastPage && page !== props.currentPage) {
        emit('goto', page);
    }
}
</script>

<template>
    <div
        v-if="lastPage > 1"
        class="flex flex-col items-center justify-between gap-3 border-t border-gray-800 px-6 py-4 sm:flex-row"
    >
        <p class="text-xs text-gray-500">
            Mostrando {{ from ?? 0 }}&ndash;{{ to ?? 0 }} di
            {{ formatter.format(total) }}
        </p>

        <div class="flex items-center space-x-1">
            <button
                v-if="currentPage > 1"
                type="button"
                class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-3 py-1.5 text-xs text-gray-400 transition hover:bg-gray-700 hover:text-white"
                @click="goto(1)"
            >
                &laquo;
            </button>
            <button
                v-if="currentPage > 1"
                type="button"
                class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-3 py-1.5 text-xs text-gray-400 transition hover:bg-gray-700 hover:text-white"
                @click="goto(currentPage - 1)"
            >
                &lsaquo;
            </button>

            <span v-if="pages[0] > 1" class="px-2 text-xs text-gray-600"
                >&hellip;</span
            >

            <button
                v-for="page in pages"
                :key="page"
                type="button"
                class="cursor-pointer rounded-lg border px-3 py-1.5 text-xs transition"
                :class="
                    page === currentPage
                        ? 'border-blue-500 bg-blue-600 font-bold text-white'
                        : 'border-gray-700 bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white'
                "
                @click="goto(page)"
            >
                {{ page }}
            </button>

            <span
                v-if="pages[pages.length - 1] < lastPage"
                class="px-2 text-xs text-gray-600"
                >&hellip;</span
            >

            <button
                v-if="currentPage < lastPage"
                type="button"
                class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-3 py-1.5 text-xs text-gray-400 transition hover:bg-gray-700 hover:text-white"
                @click="goto(currentPage + 1)"
            >
                &rsaquo;
            </button>
            <button
                v-if="currentPage < lastPage"
                type="button"
                class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-3 py-1.5 text-xs text-gray-400 transition hover:bg-gray-700 hover:text-white"
                @click="goto(lastPage)"
            >
                &raquo;
            </button>
        </div>
    </div>
</template>
