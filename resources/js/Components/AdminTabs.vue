<script setup>
import { Link } from '@inertiajs/vue3';
import { adminPages, can } from '@/permissions';

// Overview · Users · Roles under every admin page's title (phones have no sidebar to switch with).
const pages = adminPages.filter((page) => can(page.permission));
</script>

<template>
    <nav v-if="pages.length > 1" class="-mb-px flex gap-1 overflow-x-auto" aria-label="Administration">
        <Link
            v-for="page in pages"
            :key="page.route"
            :href="route(page.route)"
            class="flex h-10 shrink-0 items-center gap-2 border-b-2 px-3 text-sm font-medium transition"
            :class="route().current(page.match) ? 'border-accent text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-800'"
            :aria-current="route().current(page.match) ? 'page' : undefined"
        >
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="page.icon" /></svg>
            {{ page.label }}
        </Link>
    </nav>
</template>
