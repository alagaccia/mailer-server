<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        show: boolean;
        title: string;
        message: string;
        confirmLabel?: string;
        cancelLabel?: string;
        danger?: boolean;
    }>(),
    {
        confirmLabel: 'Conferma',
        cancelLabel: 'Annulla',
        danger: false,
    },
);

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.show) {
        emit('cancel');
    }
}
</script>

<template>
    <teleport to="body">
        <transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                tabindex="-1"
                @keydown="onKeydown"
            >
                <div
                    class="fixed inset-0 bg-black/70 backdrop-blur-sm"
                    @click="emit('cancel')"
                ></div>

                <div
                    class="relative z-10 w-full max-w-md rounded-2xl border border-gray-700 bg-gray-900 p-6 shadow-2xl"
                >
                    <h3 class="mb-2 text-lg font-bold text-gray-100">
                        {{ title }}
                    </h3>
                    <p class="mb-6 text-sm text-gray-400">{{ message }}</p>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-4 py-2 text-sm text-gray-300 transition hover:bg-gray-700 hover:text-white"
                            @click="emit('cancel')"
                        >
                            {{ cancelLabel }}
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-lg border px-4 py-2 text-sm font-bold text-white transition"
                            :class="
                                danger
                                    ? 'border-red-500 bg-red-600 hover:bg-red-700'
                                    : 'border-blue-500 bg-blue-600 hover:bg-blue-700'
                            "
                            @click="emit('confirm')"
                        >
                            {{ confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>
