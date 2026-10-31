<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dropdown from '@/Components/Dropdown.vue';
import QrCode from '@/Components/QrCode.vue';
import { copyText } from '@/clipboard';
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

// The link without its scheme, as people would read it off a printed card.
const shortUrl = (url) => url.replace(/^https?:\/\//, '');

const initials = (name) => name.split(/\s+/).filter(Boolean).map((word) => word[0]).slice(0, 2).join('').toUpperCase();
</script>

<template>
    <AppLayout title="My Cards">
        <div class="py-6 sm:py-8">
            <div class="page max-w-[75rem] space-y-6">
                <section class="flex flex-wrap items-end justify-between gap-4">
                    <div class="space-y-1.5">
                        <h1 class="text-[30px] leading-tight font-bold tracking-[-0.025em] text-gray-900">My Cards</h1>
                        <p class="text-[15px] text-gray-500">Digital business cards people save by scanning your QR code.</p>
                    </div>
                    <Link :href="route('cards.create')" class="btn-accent h-10 gap-2 px-4 py-0">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        New card
                    </Link>
                </section>

                <section class="grid items-stretch gap-5 grid-cols-[repeat(auto-fill,minmax(min(100%,32.5rem),1fr))]">
                    <article
                        v-for="card in cards"
                        :key="card.id"
                        class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white transition hover:border-gray-300"
                    >
                        <!-- The card itself: who it is, and its QR code -->
                        <div
                            class="flex flex-wrap items-center gap-[18px] border-b p-[22px]"
                            :class="card.is_public ? 'border-accent/25 bg-indigo-50' : 'border-gray-200 bg-gray-50'"
                        >
                            <img
                                v-if="card.photo_url"
                                :src="card.photo_url"
                                alt=""
                                class="size-[60px] shrink-0 rounded-full object-cover border-2"
                                :class="card.is_public ? 'border-accent' : 'border-gray-300'"
                            >
                            <span
                                v-else
                                class="flex size-[60px] shrink-0 items-center justify-center rounded-full border-2 text-xl font-bold"
                                :class="card.is_public ? 'border-accent bg-accent/15 text-indigo-600' : 'border-gray-300 bg-gray-100 text-gray-500'"
                                aria-hidden="true"
                            >{{ initials(card.full_name) }}</span>
                            <div class="min-w-0 flex-[1_1_200px] space-y-1">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <h2 class="text-[19px] font-bold tracking-[-0.01em] text-gray-900">{{ card.full_name }}</h2>
                                    <span
                                        class="inline-flex h-[22px] items-center gap-1.5 rounded-full px-2 text-[11px] font-semibold"
                                        :class="card.is_public ? 'bg-accent/15 text-indigo-600' : 'bg-gray-200 text-gray-500'"
                                    >
                                        <span class="size-1.5 rounded-full" :class="card.is_public ? 'bg-accent' : 'bg-gray-400'" />
                                        {{ card.is_public ? 'Public' : 'Hidden' }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600">{{ card.job_title || 'No job title yet' }}</p>
                                <p v-if="card.company" class="text-[13px] text-gray-500">{{ card.company }}</p>
                            </div>
                            <div class="shrink-0 rounded-xl bg-[#fff] p-1.5" :class="{ 'opacity-40': ! card.is_public }">
                                <QrCode :value="card.public_url" :size="84" />
                            </div>
                        </div>

                        <!-- What it brought in -->
                        <div class="grid grid-cols-2 border-b border-gray-200">
                            <Link :href="route('contacts.index', { card: card.id })" class="flex flex-col gap-1 border-e border-gray-200 px-[22px] py-[18px] hover:bg-gray-50 transition">
                                <span class="text-[26px] font-bold leading-none tracking-[-0.02em] tabular-nums text-gray-900">{{ card.contacts_count }}</span>
                                <span class="text-[13px] text-gray-500">{{ card.contacts_count === 1 ? 'Contact' : 'Contacts' }} from this card</span>
                            </Link>
                            <div class="flex flex-col gap-1 px-[22px] py-[18px]">
                                <span class="text-[26px] font-bold leading-none tracking-[-0.02em] tabular-nums text-gray-900">{{ card.leads_count }}</span>
                                <span class="text-[13px] text-gray-500">Filled your card form</span>
                            </div>
                        </div>

                        <div class="flex flex-1 flex-col gap-4 px-[22px] py-[18px]">
                            <!-- The link, with a copy button -->
                            <div class="flex h-10 items-center gap-2 rounded-[10px] border border-gray-200 bg-gray-100 ps-3 pe-1.5">
                                <svg class="size-[15px] shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                </svg>
                                <a :href="card.public_url" target="_blank" rel="noopener" class="min-w-0 flex-1 truncate text-[13px] text-gray-600 hover:text-indigo-600">{{ shortUrl(card.public_url) }}</a>
                                <button type="button" class="btn-quiet h-[30px] rounded-[7px] px-2.5 text-xs" @click="copyText(card.public_url)">
                                    <svg class="size-[13px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
                                    Copy
                                </button>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <Link :href="route('cards.print', card.id)" class="btn-accent h-[38px] gap-[7px] rounded-[9px] px-3.5 py-0 text-[13px]">
                                    <svg class="size-[15px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg>
                                    Print QR
                                </Link>
                                <a :href="route('cards.vcard', card.id)" class="btn-quiet h-[38px] gap-[7px]">
                                    <svg class="size-[15px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                    vCard
                                </a>
                                <a :href="card.public_url" target="_blank" rel="noopener" class="btn-quiet h-[38px] gap-[7px]">
                                    Open page
                                    <svg class="size-[13px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" /></svg>
                                </a>
                                <Link :href="route('cards.edit', card.id)" class="btn-quiet h-[38px] gap-[7px]">
                                    <svg class="size-[14px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" /></svg>
                                    Edit
                                </Link>

                                <!-- Hide and delete live behind "more", away from the everyday buttons -->
                                <Dropdown align="right" width="48" class="ms-auto">
                                    <template #trigger>
                                        <button type="button" class="btn-quiet size-[38px] px-0" :aria-label="`More options for ${card.full_name}: hide or delete`">
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <button type="button" class="block w-full px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-100 transition" @click="toggleVisibility(card)">
                                            {{ card.is_public ? 'Hide card' : 'Make public' }}
                                        </button>
                                        <button type="button" class="block w-full px-4 py-2 text-start text-sm text-red-600 hover:bg-gray-100 transition" @click="destroy(card)">
                                            Delete card
                                        </button>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>
                    </article>

                    <!-- Add another (or the first) card -->
                    <Link
                        :href="route('cards.create')"
                        class="group flex min-h-[340px] flex-col items-center justify-center gap-3 rounded-2xl border-[1.5px] border-dashed border-gray-300 p-6 text-center text-gray-500 transition hover:border-accent/40 hover:bg-indigo-50/50 hover:text-gray-900"
                    >
                        <span class="flex size-12 items-center justify-center rounded-xl border border-gray-300 bg-gray-50">
                            <svg class="size-[22px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        </span>
                        <span class="text-[15px] font-semibold">{{ cards.length ? 'Create another card' : 'Create your first card' }}</span>
                        <span class="max-w-[260px] text-[13px] leading-normal">
                            {{ cards.length ? 'One for each role or business, so you can see which one brings people in.' : 'Get your own link and QR code to share at your next meeting.' }}
                        </span>
                    </Link>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
