<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/bridge/ConfirmDialog.vue';

type UserRow = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    created_at: string | null;
};

defineProps<{
    users: UserRow[];
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth.user?.id);

// Errori "globali" (es. guardie su eliminazione) arrivano nella prop errors.
watch(
    () => page.props.errors as Record<string, string> | undefined,
    (errors) => {
        if (errors?.user) {
            toast.error(errors.user);
        }
    },
);

// --- Form crea/modifica ------------------------------------------------------

const showForm = ref(false);
const editing = ref<UserRow | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    is_admin: false,
});

function openCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function openEdit(user: UserRow): void {
    editing.value = user;
    form.reset();
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.is_admin = user.is_admin;
    showForm.value = true;
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    };

    if (editing.value) {
        form.put(`/users/${editing.value.id}`, options);
    } else {
        form.post('/users', options);
    }
}

// --- Eliminazione ------------------------------------------------------------

const deleting = ref<UserRow | null>(null);

function confirmDelete(): void {
    const user = deleting.value;
    deleting.value = null;

    if (user) {
        router.delete(`/users/${user.id}`, { preserveScroll: true });
    }
}

const inputClass =
    'w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-200 transition placeholder:text-gray-600 focus:border-blue-500 focus:outline-none';
const labelClass = 'mb-2 block text-xs font-bold text-gray-500 uppercase';
</script>

<template>
    <Head title="Utenti" />

    <div
        class="overflow-hidden rounded-2xl border border-gray-800 bg-gray-900 shadow-2xl"
    >
        <div
            class="flex items-center justify-between border-b border-gray-800 bg-gray-900/50 px-6 py-4"
        >
            <div>
                <h2 class="font-bold text-gray-200">Utenti</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Gestione degli accessi al pannello.
                </p>
            </div>
            <button
                type="button"
                class="cursor-pointer rounded-lg border border-blue-500 bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700"
                @click="openCreate"
            >
                + Nuovo Utente
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr
                        class="border-b border-gray-800 text-[10px] tracking-widest text-gray-500 uppercase"
                    >
                        <th class="px-6 py-4 font-bold">ID</th>
                        <th class="px-6 py-4 font-bold">Nome</th>
                        <th class="px-6 py-4 font-bold">Email</th>
                        <th class="px-6 py-4 text-center font-bold">Ruolo</th>
                        <th class="px-6 py-4 font-bold">Creato</th>
                        <th class="px-6 py-4 text-center font-bold">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50 text-sm">
                    <tr
                        v-for="user in users"
                        :key="user.id"
                        class="transition hover:bg-white/[0.02]"
                    >
                        <td class="px-6 py-4 font-mono text-gray-500">
                            #{{ user.id }}
                        </td>
                        <td class="px-6 py-4 font-medium text-gray-200">
                            {{ user.name }}
                            <span
                                v-if="user.id === currentUserId"
                                class="text-xs text-gray-500"
                                >(tu)</span
                            >
                        </td>
                        <td class="px-6 py-4 text-gray-400">
                            {{ user.email }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span
                                class="rounded-full border px-3 py-1 text-[10px] font-black uppercase"
                                :class="
                                    user.is_admin
                                        ? 'border-blue-500/20 bg-blue-500/10 text-blue-400'
                                        : 'border-gray-700 bg-gray-800 text-gray-400'
                                "
                            >
                                {{ user.is_admin ? 'Admin' : 'Utente' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500">
                            {{ user.created_at }}
                        </td>
                        <td
                            class="space-x-2 px-6 py-4 text-center whitespace-nowrap"
                        >
                            <button
                                type="button"
                                class="cursor-pointer rounded-lg border border-blue-500/30 bg-blue-600/20 px-3 py-1 text-xs text-blue-400 transition hover:bg-blue-600 hover:text-white"
                                @click="openEdit(user)"
                            >
                                Modifica
                            </button>
                            <button
                                type="button"
                                :disabled="user.id === currentUserId"
                                class="cursor-pointer rounded-lg border border-red-500/30 bg-red-600/20 px-3 py-1 text-xs text-red-400 transition hover:bg-red-600 hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                                @click="deleting = user"
                            >
                                Elimina
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modale crea/modifica -->
    <teleport to="body">
        <transition name="fade">
            <div
                v-if="showForm"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <div
                    class="fixed inset-0 bg-black/70 backdrop-blur-sm"
                    @click="showForm = false"
                ></div>

                <div
                    class="relative z-10 w-full max-w-lg rounded-2xl border border-gray-700 bg-gray-900 p-6 shadow-2xl"
                >
                    <h3 class="mb-6 text-lg font-bold text-gray-100">
                        {{
                            editing
                                ? `Modifica utente #${editing.id}`
                                : 'Nuovo utente'
                        }}
                    </h3>

                    <form @submit.prevent="submit">
                        <div class="mb-4">
                            <label :class="labelClass" for="user_name"
                                >Nome</label
                            >
                            <input
                                id="user_name"
                                v-model="form.name"
                                type="text"
                                required
                                :class="inputClass"
                            />
                            <p
                                v-if="form.errors.name"
                                class="mt-2 text-xs text-red-400"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="mb-4">
                            <label :class="labelClass" for="user_email"
                                >Email</label
                            >
                            <input
                                id="user_email"
                                v-model="form.email"
                                type="email"
                                required
                                :class="inputClass"
                            />
                            <p
                                v-if="form.errors.email"
                                class="mt-2 text-xs text-red-400"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label :class="labelClass" for="user_password"
                                    >Password</label
                                >
                                <input
                                    id="user_password"
                                    v-model="form.password"
                                    type="password"
                                    autocomplete="new-password"
                                    :placeholder="
                                        editing
                                            ? 'Lascia vuota per non modificarla'
                                            : ''
                                    "
                                    :required="!editing"
                                    :class="inputClass"
                                />
                                <p
                                    v-if="form.errors.password"
                                    class="mt-2 text-xs text-red-400"
                                >
                                    {{ form.errors.password }}
                                </p>
                            </div>
                            <div>
                                <label
                                    :class="labelClass"
                                    for="user_password_confirmation"
                                    >Conferma password</label
                                >
                                <input
                                    id="user_password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    :required="!editing || form.password !== ''"
                                    :class="inputClass"
                                />
                            </div>
                        </div>

                        <label
                            class="mb-6 flex items-center gap-2 text-sm text-gray-300"
                        >
                            <input
                                v-model="form.is_admin"
                                type="checkbox"
                                class="rounded border-gray-700 bg-gray-800"
                            />
                            Amministratore
                        </label>
                        <p
                            v-if="form.errors.is_admin"
                            class="-mt-4 mb-4 text-xs text-red-400"
                        >
                            {{ form.errors.is_admin }}
                        </p>

                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="cursor-pointer rounded-lg border border-gray-700 bg-gray-800 px-4 py-2 text-sm text-gray-300 transition hover:bg-gray-700 hover:text-white"
                                @click="showForm = false"
                            >
                                Annulla
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="cursor-pointer rounded-lg border border-blue-500 bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{
                                    editing ? 'Salva modifiche' : 'Crea utente'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>
    </teleport>

    <ConfirmDialog
        :show="deleting !== null"
        title="Eliminare l'utente?"
        :message="`L'utente ${deleting?.name} (${deleting?.email}) verrà eliminato definitivamente.`"
        confirm-label="Elimina"
        danger
        @confirm="confirmDelete"
        @cancel="deleting = null"
    />
</template>
