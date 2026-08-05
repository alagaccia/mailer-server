<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const items = computed(() => {
    const links = [
        { title: 'Profilo', href: '/settings/profile' },
        { title: 'Sicurezza', href: '/settings/security' },
    ];

    if (page.props.auth.user?.is_admin) {
        links.push({ title: 'SMTP & Chiave API', href: '/settings/smtp' });
    }

    return links;
});

function isCurrent(href: string): boolean {
    return page.url.startsWith(href);
}
</script>

<template>
    <div>
        <nav class="mb-8 flex flex-wrap gap-2" aria-label="Impostazioni">
            <Link
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                class="rounded-lg border px-4 py-2 text-sm transition"
                :class="
                    isCurrent(item.href)
                        ? 'border-blue-500 bg-blue-600 font-bold text-white'
                        : 'border-gray-700 bg-gray-800 text-gray-300 hover:bg-gray-700 hover:text-white'
                "
            >
                {{ item.title }}
            </Link>
        </nav>

        <slot />
    </div>
</template>
