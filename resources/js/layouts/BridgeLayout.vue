<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Toaster } from '@/components/ui/sonner';
import { postJson } from '@/lib/http';

const page = usePage();

const user = computed(() => page.props.auth.user);
const mailerEnabled = ref<boolean>(Boolean(page.props.mailerEnabled));

watch(
    () => page.props.mailerEnabled,
    (value) => {
        mailerEnabled.value = Boolean(value);
    },
);

const toggling = ref(false);

async function toggleMailer(): Promise<void> {
    if (toggling.value) {
        return;
    }

    toggling.value = true;

    try {
        const { ok, data } = await postJson<{ enabled?: string }>(
            '/mailer/toggle',
        );

        if (ok && data.enabled !== undefined) {
            mailerEnabled.value = data.enabled === '1';
            toast.success(
                mailerEnabled.value ? 'Mailer attivato' : 'Mailer fermato',
            );
        } else {
            toast.error(
                data.error ?? 'Impossibile aggiornare lo stato del mailer.',
            );
        }
    } catch {
        toast.error('Errore di rete durante il cambio di stato.');
    } finally {
        toggling.value = false;
    }
}

function logout(): void {
    router.post('/logout');
}

const navLinks = computed(() => {
    const links = [{ title: 'Dashboard', href: '/dashboard' }];

    if (user.value?.is_admin) {
        links.push({ title: 'Impostazioni', href: '/settings/smtp' });
        links.push({ title: 'Utenti', href: '/users' });
    }

    links.push({ title: 'Profilo', href: '/settings/profile' });

    return links;
});

function isCurrent(href: string): boolean {
    if (href === '/settings/smtp' || href === '/settings/profile') {
        return page.url.startsWith(href);
    }

    return (
        page.url === href ||
        page.url.startsWith(href + '?') ||
        page.url.startsWith(href + '/')
    );
}
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-gray-100 antialiased">
        <div class="container mx-auto px-6 py-8">
            <header
                class="mb-10 flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between"
            >
                <div>
                    <Link href="/dashboard">
                        <h1
                            class="bg-gradient-to-r from-blue-400 to-emerald-400 bg-clip-text text-3xl font-extrabold text-transparent"
                        >
                            Mail Bridge Controller
                        </h1>
                    </Link>
                    <p class="text-sm text-gray-500">
                        SMTP Gateway Monitor (v2.0)
                    </p>
                </div>

                <div v-if="user" class="flex flex-wrap items-center gap-3">
                    <span
                        class="rounded border px-3 py-1 font-mono text-xs"
                        :class="
                            mailerEnabled
                                ? 'border-green-700 bg-green-900/40 text-green-400'
                                : 'border-red-700 bg-red-900/40 text-red-400'
                        "
                    >
                        MAILER: {{ mailerEnabled ? 'ATTIVO' : 'FERMO' }}
                    </span>

                    <button
                        type="button"
                        :disabled="toggling"
                        class="cursor-pointer rounded-lg border px-4 py-2 text-xs font-bold transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="
                            mailerEnabled
                                ? 'border-red-900/50 bg-red-600/20 text-red-400 hover:bg-red-600 hover:text-white'
                                : 'border-green-900/50 bg-green-600/20 text-green-400 hover:bg-green-600 hover:text-white'
                        "
                        @click="toggleMailer"
                    >
                        {{
                            mailerEnabled
                                ? 'Ferma Invio Email'
                                : 'Riattiva Invio Email'
                        }}
                    </button>

                    <nav class="flex flex-wrap items-center gap-2">
                        <Link
                            v-for="link in navLinks"
                            :key="link.href"
                            :href="link.href"
                            class="rounded-lg border px-4 py-2 text-sm transition"
                            :class="
                                isCurrent(link.href)
                                    ? 'border-blue-500/50 bg-blue-600/20 text-blue-400'
                                    : 'border-gray-700 bg-gray-800 text-gray-300 hover:bg-gray-700 hover:text-white'
                            "
                        >
                            {{ link.title }}
                        </Link>
                    </nav>

                    <button
                        type="button"
                        class="cursor-pointer rounded-lg border border-red-900/50 bg-red-600/20 px-4 py-2 text-sm text-red-400 transition hover:bg-red-600 hover:text-white"
                        @click="logout"
                    >
                        Logout
                    </button>
                </div>
            </header>

            <main>
                <slot />
            </main>

            <footer
                class="mt-8 text-center text-[10px] tracking-[0.2em] text-gray-700 uppercase"
            >
                Bridge Powered by Andrea Lagaccia &bull; Laravel Engine
            </footer>
        </div>

        <Toaster position="top-right" theme="dark" />
    </div>
</template>
