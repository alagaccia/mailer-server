<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/bridge/ConfirmDialog.vue';
import EmailPreviewModal from '@/components/bridge/EmailPreviewModal.vue';
import FilterCard from '@/components/bridge/FilterCard.vue';
import type { EmailFilters } from '@/components/bridge/FilterCard.vue';
import PaginationBar from '@/components/bridge/PaginationBar.vue';
import StatCard from '@/components/bridge/StatCard.vue';
import StatusBadge from '@/components/bridge/StatusBadge.vue';
import { getJson, postJson } from '@/lib/http';
import type {
    EmailDetail,
    EmailRow,
    EmailStats,
    Paginator,
} from '@/types/bridge';

const props = defineProps<{
    stats: EmailStats;
    emails: Paginator<EmailRow>;
    filters: EmailFilters;
}>();

const hasActiveFilters = computed(() =>
    Boolean(
        props.filters.filter_recipient ||
        props.filters.filter_subject ||
        props.filters.filter_date_from ||
        props.filters.filter_date_to,
    ),
);

function visit(filters: EmailFilters, page = 1): void {
    const query: Record<string, string | number> = {};

    if (filters.filter_recipient) {
        query.filter_recipient = filters.filter_recipient;
    }

    if (filters.filter_subject) {
        query.filter_subject = filters.filter_subject;
    }

    if (filters.filter_date_from) {
        query.filter_date_from = filters.filter_date_from;
    }

    if (filters.filter_date_to) {
        query.filter_date_to = filters.filter_date_to;
    }

    if (page > 1) {
        query.page = page;
    }

    router.get('/dashboard', query, {
        preserveState: true,
        preserveScroll: true,
    });
}

function search(filters: EmailFilters): void {
    visit(filters);
}

function resetFilters(): void {
    visit({
        filter_recipient: '',
        filter_subject: '',
        filter_date_from: '',
        filter_date_to: '',
    });
}

function goToPage(page: number): void {
    visit(props.filters, page);
}

// --- Invio manuale -----------------------------------------------------------

const sending = ref<number[]>([]);
const confirmTarget = ref<EmailRow | null>(null);

function askSend(email: EmailRow): void {
    confirmTarget.value = email;
}

async function confirmSend(): Promise<void> {
    const email = confirmTarget.value;
    confirmTarget.value = null;

    if (!email || sending.value.includes(email.id)) {
        return;
    }

    sending.value.push(email.id);

    try {
        const { ok, data } = await postJson(`/emails/${email.id}/send`);

        if (ok) {
            toast.success('Email inviata con successo!');
            router.reload({ only: ['emails', 'stats'] });
        } else {
            toast.error(data.error ?? 'Invio non riuscito.');
        }
    } catch (error) {
        toast.error(
            `Errore di rete: ${error instanceof Error ? error.message : String(error)}`,
        );
    } finally {
        sending.value = sending.value.filter((id) => id !== email.id);
    }
}

// --- Anteprima ---------------------------------------------------------------

const showModal = ref(false);
const current = ref<EmailDetail | null>(null);

async function openModal(email: EmailRow): Promise<void> {
    try {
        const { ok, data } = await getJson<EmailDetail>(`/emails/${email.id}`);

        if (ok) {
            current.value = data as EmailDetail;
            showModal.value = true;
        } else {
            toast.error(data.error ?? 'Email non trovata.');
        }
    } catch (error) {
        toast.error(
            `Errore di rete: ${error instanceof Error ? error.message : String(error)}`,
        );
    }
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="mb-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
            label="In Coda"
            :value="stats.pending"
            color-class="text-yellow-500"
        />
        <StatCard
            label="Inviate con Successo"
            :value="stats.sent"
            color-class="text-emerald-500"
        />
        <StatCard
            label="Errori / Fallite"
            :value="stats.failed"
            color-class="text-red-500"
        />
        <StatCard
            label="Volume Totale"
            :value="stats.total"
            color-class="text-blue-500"
        />
    </div>

    <FilterCard :filters="filters" @search="search" @reset="resetFilters" />

    <div
        class="overflow-hidden rounded-2xl border border-gray-800 bg-gray-900 shadow-2xl"
    >
        <div class="border-b border-gray-800 bg-gray-900/50 px-6 py-4">
            <h2 class="font-bold text-gray-200">
                Log Ultimi Invii
                <span
                    v-if="hasActiveFilters"
                    class="text-xs font-normal text-blue-400"
                    >(filtrati)</span
                >
            </h2>
            <p class="mt-1 text-xs text-gray-500">
                Pagina {{ emails.current_page }} di
                {{ Math.max(emails.last_page, 1) }} &bull; {{ emails.total }}
                {{
                    hasActiveFilters
                        ? 'email corrispondenti ai filtri'
                        : 'email totali'
                }}
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr
                        class="border-b border-gray-800 text-[10px] tracking-widest text-gray-500 uppercase"
                    >
                        <th class="px-6 py-4 font-bold">ID</th>
                        <th class="px-6 py-4 font-bold">Destinatario</th>
                        <th class="px-6 py-4 font-bold">Oggetto</th>
                        <th class="px-6 py-4 text-center font-bold">Stato</th>
                        <th class="px-6 py-4 text-center font-bold">
                            Allegati
                        </th>
                        <th class="px-6 py-4 font-bold">Data/Ora</th>
                        <th class="px-6 py-4 text-center font-bold">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50 text-sm">
                    <tr
                        v-for="email in emails.data"
                        :key="email.id"
                        class="transition hover:bg-white/[0.02]"
                    >
                        <td class="px-6 py-4 font-mono text-gray-500">
                            #{{ email.id }}
                        </td>
                        <td class="px-6 py-4 font-medium text-gray-200">
                            {{ email.recipient }}
                        </td>
                        <td class="px-6 py-4 text-gray-400">
                            {{ email.subject }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <StatusBadge :status="email.status" />
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span
                                v-if="email.attachments_meta"
                                class="rounded-full border border-blue-500/30 bg-blue-500/10 px-2 py-1 text-[10px] font-bold text-blue-400"
                            >
                                {{ email.attachments_meta.count }} file ({{
                                    email.attachments_meta.size
                                }})
                            </span>
                            <span v-else class="text-xs text-gray-600">-</span>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500">
                            {{ email.created_at }}
                        </td>
                        <td
                            class="space-x-2 px-6 py-4 text-center whitespace-nowrap"
                        >
                            <button
                                type="button"
                                :disabled="
                                    email.status === 'sent' ||
                                    sending.includes(email.id)
                                "
                                class="cursor-pointer rounded-lg border border-green-500/30 bg-green-600/20 px-3 py-1 text-xs text-green-400 transition hover:bg-green-600 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                                @click="askSend(email)"
                            >
                                {{
                                    sending.includes(email.id)
                                        ? 'Invio...'
                                        : 'Invia'
                                }}
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded-lg border border-blue-500/30 bg-blue-600/20 px-3 py-1 text-xs text-blue-400 transition hover:bg-blue-600 hover:text-white"
                                @click="openModal(email)"
                            >
                                Visualizza
                            </button>
                        </td>
                    </tr>

                    <tr v-if="emails.data.length === 0">
                        <td
                            colspan="7"
                            class="px-6 py-10 text-center text-gray-600 italic"
                        >
                            Nessun dato presente in coda.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationBar
            :current-page="emails.current_page"
            :last-page="emails.last_page"
            :total="emails.total"
            :from="emails.from"
            :to="emails.to"
            @goto="goToPage"
        />
    </div>

    <EmailPreviewModal
        :show="showModal"
        :email="current"
        @close="showModal = false"
    />

    <ConfirmDialog
        :show="confirmTarget !== null"
        title="Invio manuale"
        :message="`Inviare l'email #${confirmTarget?.id} a ${confirmTarget?.recipient}?`"
        confirm-label="Invia"
        @confirm="confirmSend"
        @cancel="confirmTarget = null"
    />
</template>
