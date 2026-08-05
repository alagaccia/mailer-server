<script setup lang="ts">
import { onBeforeUnmount, onMounted, watch } from 'vue';
import { toast } from 'vue-sonner';
import StatusBadge from '@/components/bridge/StatusBadge.vue';
import type { EmailAttachment, EmailDetail } from '@/types/bridge';

const props = defineProps<{
    show: boolean;
    email: EmailDetail | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

watch(
    () => props.show,
    (value) => {
        document.body.style.overflow = value ? 'hidden' : '';
    },
);

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.show) {
        emit('close');
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});

function resizeIframe(event: Event): void {
    const iframe = event.target as HTMLIFrameElement;

    try {
        const height = iframe.contentDocument?.body?.scrollHeight;

        if (height) {
            iframe.style.height = `${height + 20}px`;
        }
    } catch {
        // contenuto non accessibile: mantieni l'altezza minima
    }
}

function downloadAttachment(attachment: EmailAttachment): void {
    try {
        const binary = atob(attachment.content ?? '');
        const bytes = new Uint8Array(binary.length);

        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }

        const blob = new Blob([bytes], {
            type: attachment.mime ?? 'application/octet-stream',
        });
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');

        anchor.href = url;
        anchor.download = attachment.filename ?? 'allegato';
        anchor.click();

        URL.revokeObjectURL(url);
    } catch (error) {
        toast.error(
            `Errore nel download: ${error instanceof Error ? error.message : String(error)}`,
        );
    }
}
</script>

<template>
    <teleport to="body">
        <transition name="fade">
            <div
                v-if="show && email"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <div
                    class="fixed inset-0 bg-black/70 backdrop-blur-sm"
                    @click="emit('close')"
                ></div>

                <div
                    class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col rounded-2xl border border-gray-700 bg-gray-900 shadow-2xl"
                >
                    <div
                        class="flex items-start justify-between border-b border-gray-800 p-6"
                    >
                        <div>
                            <h3 class="text-lg font-bold text-gray-100">
                                Anteprima Email
                                <span class="font-mono text-sm text-gray-500"
                                    >#{{ email.id }}</span
                                >
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ email.recipient }} &bull;
                                {{ email.created_at }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="cursor-pointer text-2xl leading-none text-gray-500 transition hover:text-white"
                            @click="emit('close')"
                        >
                            &times;
                        </button>
                    </div>

                    <div class="space-y-2 border-b border-gray-800 p-6 text-sm">
                        <div v-if="email.uuid" class="flex">
                            <span class="w-20 shrink-0 text-gray-500"
                                >UUID:</span
                            >
                            <span class="font-mono text-xs break-all text-gray-300">{{
                                email.uuid
                            }}</span>
                        </div>
                        <div class="flex">
                            <span class="w-20 shrink-0 text-gray-500">A:</span>
                            <span class="text-gray-200">{{
                                email.recipient
                            }}</span>
                        </div>
                        <div class="flex">
                            <span class="w-20 shrink-0 text-gray-500"
                                >Oggetto:</span
                            >
                            <span class="text-gray-200">{{
                                email.subject
                            }}</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-20 shrink-0 text-gray-500"
                                >Stato:</span
                            >
                            <StatusBadge :status="email.status" />
                        </div>
                        <div v-if="email.attachments.length > 0" class="flex">
                            <span class="w-20 shrink-0 text-gray-500"
                                >Allegati:</span
                            >
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="(
                                        attachment, index
                                    ) in email.attachments"
                                    :key="index"
                                    type="button"
                                    class="group flex cursor-pointer items-center gap-2 rounded border border-blue-500/30 bg-blue-500/10 px-3 py-1 font-mono text-xs text-blue-400 transition hover:bg-blue-500/20"
                                    @click="downloadAttachment(attachment)"
                                >
                                    <svg
                                        class="h-3 w-3"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    {{ attachment.filename ?? 'allegato' }}
                                    <svg
                                        class="h-3 w-3 opacity-0 transition group-hover:opacity-100"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div v-if="email.last_error" class="flex">
                            <span class="w-20 shrink-0 text-gray-500"
                                >Errore:</span
                            >
                            <span class="text-xs text-red-400">{{
                                email.last_error
                            }}</span>
                        </div>
                    </div>

                    <div class="flex-1 overflow-auto p-6">
                        <div class="overflow-hidden rounded-xl bg-gray-100">
                            <iframe
                                :srcdoc="email.body"
                                sandbox="allow-same-origin"
                                class="w-full border-0"
                                style="min-height: 350px"
                                @load="resizeIframe"
                            ></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>
