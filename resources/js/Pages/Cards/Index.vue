<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import QrCode from '@/Components/QrCode.vue';
import { ask } from '@/confirm';

defineProps({
    cards: Array,
});

const setVisibility = (card, isPublic) => {
    router.patch(route('cards.visibility', card.id), { is_public: isPublic }, { preserveScroll: true });
};

const toggleVisibility = async (card) => {
    if (! card.is_public) {
        setVisibility(card, true);
        return;
    }

    const yes = await ask({
        title: `Hide "${card.full_name}"?`,
        message: 'Its link and QR code stop working until you make it public again. Your contacts are not affected.',
        confirmText: 'Hide card',
        danger: false,
    });

    if (yes) setVisibility(card, false);
};

const destroy = async (card) => {
    // A card that already brought in contacts can't be deleted (the server enforces this too).
    if (card.contacts_count > 0) {
        const count = `${card.contacts_count} ${card.contacts_count === 1 ? 'contact' : 'contacts'}`;

        if (! card.is_public) {
            await ask({
                title: "This card can't be deleted",
                message: `${count} came from it, so it stays as part of their history. It's already hidden, so its link and QR code are off.`,
                confirmText: 'Got it',
                cancelText: null,
                danger: false,
            });
            return;
        }

        const hide = await ask({
            title: "This card can't be deleted",
            message: `${count} came from it, so it stays as part of their history. You can hide it instead: its link and QR code stop working, and you can turn it back on anytime.`,
            confirmText: 'Hide card instead',
            cancelText: 'Keep it public',
            danger: false,
        });

        if (hide) setVisibility(card, false);
        return;
    }

    const yes = await ask({
        title: `Delete "${card.full_name}"?`,
        message: "Its link and QR code will stop working, including on cards you already printed. You can't undo this.",
        confirmText: 'Delete card',
    });

    if (yes) router.delete(route('cards.destroy', card.id));
};
</script>

<template>
    <AppLayout title="My Cards">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Cards</h2>
                <Link :href="route('cards.create')" class="btn-accent">
                    New card
                </Link>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="!cards.length" class="bg-white border border-gray-200 rounded-2xl p-10 text-center space-y-3">
                    <p class="font-medium text-gray-800">No cards yet</p>
                    <p class="text-sm text-gray-500">Create a card to get your own link and QR code.</p>
                    <Link :href="route('cards.create')" class="btn-accent">Create your first card</Link>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                    <article v-for="card in cards" :key="card.id" class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 flex flex-col gap-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <img v-if="card.photo_url" :src="card.photo_url" alt="" class="size-11 shrink-0 rounded-full object-cover border border-gray-200">
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-lg text-gray-900 truncate">{{ card.full_name }}</h3>
                                    <p class="text-sm text-gray-500 truncate">{{ [card.job_title, card.company].filter(Boolean).join(' · ') || 'No job title yet' }}</p>
                                </div>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="card.is_public ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ card.is_public ? 'Public' : 'Hidden' }}
                            </span>
                        </div>

                        <div class="flex items-center gap-5">
                            <div class="shrink-0 rounded-xl bg-white p-1.5 border border-gray-200">
                                <QrCode :value="card.public_url" :size="112" />
                            </div>
                            <div class="min-w-0 space-y-3">
                                <Link
                                    :href="route('contacts.index', { card: card.id })"
                                    class="block rounded-xl border border-gray-200 px-4 py-3 hover:border-indigo-500 transition"
                                >
                                    <span class="block text-2xl font-semibold tabular-nums text-gray-900">{{ card.contacts_count }}</span>
                                    <span class="block text-sm text-indigo-600">{{ card.contacts_count === 1 ? 'contact' : 'contacts' }} from this card →</span>
                                </Link>
                                <a :href="card.public_url" target="_blank" rel="noopener" class="block text-xs text-gray-500 hover:text-indigo-600 break-all">{{ card.public_url }}</a>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 pt-4 border-t border-gray-200 text-sm">
                            <Link :href="route('cards.print', card.id)" class="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 font-medium text-accent-fg hover:opacity-90">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg>
                                Print
                            </Link>
                            <a :href="route('cards.vcard', card.id)" class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                vCard
                            </a>
                            <a :href="card.public_url" target="_blank" rel="noopener" class="rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">Open page</a>
                            <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50" @click="toggleVisibility(card)">
                                {{ card.is_public ? 'Hide' : 'Make public' }}
                            </button>
                            <Link :href="route('cards.edit', card.id)" class="rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">Edit</Link>
                            <button type="button" class="ms-auto text-red-600 hover:underline" @click="destroy(card)">Delete</button>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
