<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const props = defineProps({
    status: { type: Number, default: 404 },
    context: { type: String, default: null },
});

// Errors can happen before the usual page data is shared (e.g. an unknown URL), so read it defensively.
const page = usePage();
const signedIn = computed(() => !! page.props.auth?.user);

const content = computed(() => {
    if (props.status === 404 && props.context === 'card') {
        return {
            title: "This card isn't available",
            message: 'The link may be mistyped, or the owner has turned this card off for now. If someone gave you this card, ask them for their latest link.',
        };
    }

    return {
        403: { title: "You don't have access to this", message: 'This belongs to another account. If you think it should be yours, check that you are signed in with the right email.' },
        404: { title: 'Page not found', message: "The page you're looking for doesn't exist or has moved. Check the address, or head back home." },
        500: { title: 'Something went wrong', message: 'We hit an error on our side. Please try again in a moment.' },
        503: { title: "We'll be right back", message: 'MetMemo is down for a short update. Please try again in a few minutes.' },
    }[props.status] ?? { title: 'Something went wrong', message: 'Please try again.' };
});

const home = computed(() => (signedIn.value
    ? { href: route('dashboard'), label: 'Go to dashboard' }
    : { href: '/', label: 'Go to MetMemo' }));

const showCreateCard = computed(() => props.context === 'card' && ! signedIn.value);

const goBack = () => {
    if (window.history.length > 1) window.history.back();
    else window.location.href = home.value.href;
};
</script>

<template>
    <Head :title="content.title" />

    <div class="relative min-h-screen bg-page flex flex-col overflow-hidden">
        <div class="absolute inset-0 bg-grid pointer-events-none" aria-hidden="true" />

        <header class="relative flex items-center justify-between px-4 sm:px-8 h-16">
            <Link :href="home.href" aria-label="MetMemo home">
                <ApplicationMark class="!text-2xl" />
            </Link>
            <ThemeToggle />
        </header>

        <main class="relative flex-1 flex items-center justify-center px-4 pb-16">
            <div class="w-full max-w-lg text-center">
                <!-- The MetMemo mark, split apart: the two people who didn't meet. -->
                <svg class="mx-auto" width="224" height="104" viewBox="0 0 120 56" fill="none" aria-hidden="true">
                    <g class="text-gray-900">
                        <circle cx="16" cy="10" r="6" fill="currentColor" />
                        <path d="M16 50V24l14 14" stroke="currentColor" stroke-width="6.5" stroke-linecap="round" stroke-linejoin="round" />
                    </g>
                    <path d="M44 36h32" stroke="currentColor" class="text-gray-300" stroke-width="3" stroke-linecap="round" stroke-dasharray="1 8" />
                    <g class="text-indigo-500">
                        <circle cx="104" cy="10" r="6" fill="currentColor" />
                        <path d="M90 38l14-14v26" stroke="currentColor" stroke-width="6.5" stroke-linecap="round" stroke-linejoin="round" />
                    </g>
                </svg>

                <p class="mt-6 font-mono text-sm font-medium tracking-widest text-indigo-600">ERROR {{ status }}</p>
                <h1 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 text-balance">{{ content.title }}</h1>
                <p class="mt-4 text-gray-600 leading-relaxed">{{ content.message }}</p>

                <div class="mt-8 flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3">
                    <Link :href="home.href" class="btn-accent py-3">{{ home.label }}</Link>
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-md border border-gray-300 px-5 py-3 text-sm font-medium text-gray-700 hover:bg-white transition"
                        @click="goBack"
                    >
                        Go back
                    </button>
                </div>

                <div v-if="showCreateCard" class="mt-10 rounded-2xl border border-gray-200 bg-white p-5 text-start flex flex-col sm:flex-row sm:items-center gap-3">
                    <p class="flex-1 text-sm text-gray-600">Want a digital business card of your own? It's free.</p>
                    <Link :href="route('register')" class="text-sm font-semibold text-indigo-600 hover:underline">Create yours →</Link>
                </div>
            </div>
        </main>
    </div>
</template>
