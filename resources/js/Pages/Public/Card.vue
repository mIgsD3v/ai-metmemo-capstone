<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import InputError from '@/Components/InputError.vue';
import QrCode from '@/Components/QrCode.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { copyText } from '@/clipboard';

const props = defineProps({
    card: Object,
    publicUrl: String,
    // A signed-in visitor: { isOwner } for the card's owner, else { name, cards, swappedContactId }. Null for guests.
    viewer: Object,
});

const initials = props.card.full_name
    .split(/\s+/)
    .filter(Boolean)
    .map((word) => word[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

const firstName = props.card.full_name.split(/\s+/)[0];

// Show "example.com/path" instead of the full URL with protocol and tracking query.
const websiteLabel = computed(() => {
    if (! props.card.website) return null;
    try {
        const url = new URL(props.card.website);
        return (url.host + url.pathname).replace(/\/$/, '');
    } catch {
        return props.card.website;
    }
});

const icons = {
    email: 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75',
    phone: 'M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z',
    text: 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z',
    web: 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418',
    pin: 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z',
    save: 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3',
    copy: 'M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75',
    external: 'm4.5 19.5 15-15m0 0H8.25m11.25 0v11.25',
    share: 'M9 8.25H7.5a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25H15m0-3-3-3m0 0-3 3m3-3V15',
    arrow: 'M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3',
};

// One-tap actions under "Save to contacts": only the ones this card has details for.
const quickActions = computed(() => [
    props.card.phone && { label: 'Call', icon: icons.phone, href: `tel:${props.card.phone}` },
    props.card.email && { label: 'Email', icon: icons.email, href: `mailto:${props.card.email}` },
    props.card.phone && { label: 'Text', icon: icons.text, href: `sms:${props.card.phone}` },
    props.card.website && { label: 'Website', icon: icons.web, href: props.card.website, external: true },
].filter(Boolean));

const details = computed(() => [
    props.card.email && { icon: icons.email, label: 'Email', value: props.card.email, href: `mailto:${props.card.email}`, copy: true },
    props.card.phone && { icon: icons.phone, label: 'Phone', value: props.card.phone, href: `tel:${props.card.phone}`, copy: true },
    props.card.website && { icon: icons.web, label: 'Website', value: websiteLabel.value, href: props.card.website, external: true },
].filter(Boolean));

// Pass the card on: the phone's share sheet where there is one, otherwise copy the link.
const share = async () => {
    if (navigator.share) {
        try {
            await navigator.share({ title: props.card.full_name, url: props.publicUrl });
            return;
        } catch {
            // Closed the share sheet: nothing to do.
            return;
        }
    }
    copyText(props.publicUrl);
};

const lead = useForm({
    full_name: '',
    email: '',
    phone: '',
    company: '',
    message: '',
});

// Swap cards (signed-in visitors): send one of your cards, newest first by default.
const swap = useForm({ card_id: props.viewer?.cards?.[0]?.id ?? null });
const submitSwap = () => swap.post(route('public-card.swap', props.card.id));
const viewerInitials = (props.viewer?.name ?? '').split(/\s+/).filter(Boolean).map((word) => word[0]).slice(0, 2).join('').toUpperCase();

const sent = ref(false);
const submitLead = () => {
    lead.post(route('public-card.leads.store', props.card.id), {
        preserveScroll: true,
        onSuccess: () => {
            lead.reset();
            sent.value = true;
        },
    });
};

const fieldClass = 'field h-[50px] sm:h-[46px] rounded-xl px-3.5 text-base sm:text-[15px] font-normal';
const labelClass = 'flex flex-col gap-[7px] text-[13px] font-medium text-gray-600';
</script>

<template>
    <Head :title="card.full_name" />

    <div class="relative flex min-h-screen flex-col bg-page text-gray-900">
        <!-- Desktop: logo and theme on a bar. Phones: the theme button sits on the card's green band. -->
        <header class="hidden items-center justify-between px-6 py-4 sm:flex">
            <Link href="/" aria-label="MetMemo home"><ApplicationMark class="!text-xl" /></Link>
            <ThemeToggle />
        </header>
        <ThemeToggle class="absolute end-3 top-3 z-10 sm:hidden" />

        <main class="mx-auto w-full max-w-[1040px] flex-1 sm:px-6 sm:pb-12 sm:pt-4 lg:grid lg:grid-cols-2 lg:items-start lg:gap-6">
            <!-- The card -->
            <article class="overflow-hidden sm:rounded-3xl sm:border sm:border-gray-200 sm:bg-white lg:col-start-1 lg:row-start-1">
                <div class="h-[150px] sm:h-[120px] border-b border-accent/25 bg-indigo-50 bg-grid-lines" />
                <div class="-mt-[60px] sm:-mt-14 flex flex-col items-center gap-1.5 px-5 pb-6 text-center sm:px-7">
                    <img
                        v-if="card.photo_url"
                        :src="card.photo_url"
                        :alt="card.full_name"
                        class="size-[120px] sm:size-28 rounded-full border-[5px] border-page sm:border-white object-cover"
                    >
                    <div
                        v-else
                        class="flex size-[120px] sm:size-28 items-center justify-center rounded-full border-[5px] border-page sm:border-white bg-accent/15 text-4xl font-bold text-indigo-600 select-none"
                        aria-hidden="true"
                    >{{ initials }}</div>

                    <h1 class="mt-2.5 text-[28px] font-bold leading-tight tracking-[-0.025em]">{{ card.full_name }}</h1>
                    <p v-if="card.job_title || card.company" class="text-[15px] leading-snug text-gray-600">
                        {{ card.job_title }}<template v-if="card.job_title && card.company"><span class="hidden sm:inline"> · </span><br class="sm:hidden"></template><b v-if="card.company" class="font-semibold text-gray-900">{{ card.company }}</b>
                    </p>
                    <p v-if="card.location" class="inline-flex items-center gap-1.5 text-[13px] text-gray-500">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.pin" /></svg>
                        {{ card.location }}
                    </p>
                    <p v-if="card.bio" class="mt-2.5 max-w-[360px] text-[15px] leading-relaxed text-gray-600 whitespace-pre-line">{{ card.bio }}</p>

                    <a :href="route('public-card.vcard', card.id)" class="btn-accent mt-[18px] h-14 sm:h-[52px] w-full gap-2.5 rounded-2xl sm:rounded-[14px] py-0 text-[17px] sm:text-base">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons.save" />
                        </svg>
                        Save to contacts
                    </a>

                    <div v-if="quickActions.length" class="mt-3.5 grid w-full gap-1.5 sm:gap-2" :style="{ gridTemplateColumns: `repeat(${quickActions.length}, minmax(0, 1fr))` }">
                        <a
                            v-for="action in quickActions"
                            :key="action.label"
                            :href="action.href"
                            :target="action.external ? '_blank' : undefined"
                            :rel="action.external ? 'noopener' : undefined"
                            class="group flex flex-col items-center gap-1.5 text-xs font-medium text-gray-600 hover:text-gray-900"
                        >
                            <span class="flex size-[52px] sm:size-12 items-center justify-center rounded-full border border-gray-300 bg-gray-50 text-gray-900 transition group-hover:border-accent/40 group-active:scale-95">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="action.icon" /></svg>
                            </span>
                            {{ action.label }}
                        </a>
                    </div>
                </div>

                <ul v-if="details.length" class="mx-4 overflow-hidden rounded-[18px] border border-gray-200 bg-white sm:mx-0 sm:rounded-none sm:border-0 sm:border-t">
                    <li v-for="(item, i) in details" :key="item.label" class="flex items-center" :class="{ 'border-t border-gray-200': i }">
                        <a
                            :href="item.href"
                            :target="item.external ? '_blank' : undefined"
                            :rel="item.external ? 'noopener' : undefined"
                            class="flex min-h-16 min-w-0 flex-1 items-center gap-3.5 px-4 py-2.5 transition hover:bg-gray-50"
                        >
                            <span class="flex size-10 sm:size-[38px] shrink-0 items-center justify-center rounded-xl bg-accent/10 text-indigo-600">
                                <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" /></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs text-gray-400">{{ item.label }}</span>
                                <span class="block truncate text-[15px] font-medium tabular-nums">{{ item.value }}</span>
                            </span>
                            <svg v-if="item.external" class="me-1 size-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.external" /></svg>
                        </a>
                        <button
                            v-if="item.copy"
                            type="button"
                            class="me-2.5 flex size-[38px] shrink-0 items-center justify-center rounded-[10px] text-gray-500 transition hover:bg-gray-50 hover:text-gray-900"
                            :aria-label="`Copy ${item.label.toLowerCase()}`"
                            @click="copyText(item.value, `${item.label} copied`)"
                        >
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.copy" /></svg>
                        </button>
                    </li>
                </ul>
            </article>

            <!-- Swap contacts: one tap for a signed-in MetMemo user, the lead form for everyone else -->
            <section class="mx-4 mt-4 overflow-hidden rounded-[18px] sm:mx-0 sm:rounded-3xl border border-gray-200 bg-white lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:mt-0">
                <!-- The owner looking at their own card -->
                <div v-if="viewer?.isOwner" class="space-y-3 px-6 py-10 text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-indigo-600">Your card</p>
                    <h2 class="text-xl font-bold tracking-[-0.02em]">This is how people see your card</h2>
                    <p class="mx-auto max-w-sm text-sm leading-relaxed text-gray-500">Visitors can save you to their phone here, leave their details, or swap cards in one tap if they use MetMemo too.</p>
                    <Link :href="route('cards.index')" class="btn-quiet h-10 px-4 text-sm">Go to My Cards</Link>
                </div>

                <!-- A signed-in MetMemo user: swap cards both ways in one tap -->
                <template v-else-if="viewer">
                    <div class="space-y-1.5 px-[18px] pt-5 sm:px-6 sm:pt-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-indigo-600">Swap cards</p>
                        <h2 class="text-xl sm:text-[22px] font-bold tracking-[-0.02em]">
                            {{ viewer.swappedContactId ? `You and ${firstName} already swapped` : `Swap cards with ${firstName}` }}
                        </h2>
                        <p class="text-sm leading-relaxed text-gray-500">
                            One tap: {{ firstName }} gets your card, and {{ firstName }} is added to your MetMemo contacts. You both get a
                            follow-up reminder. Your notes stay private to you.
                        </p>
                    </div>
                    <form class="flex flex-col gap-4 p-[18px] sm:p-6 sm:pt-5" @submit.prevent="submitSwap">
                        <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-100 p-3.5">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent/15 text-sm font-bold text-indigo-600">{{ viewerInitials }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs text-gray-400">Signed in as</span>
                                <span class="block truncate text-[15px] font-semibold">{{ viewer.name }}</span>
                            </span>
                        </div>
                        <label v-if="viewer.cards.length > 1" :class="labelClass">
                            Send this card
                            <select v-model="swap.card_id" :class="fieldClass">
                                <option v-for="mine in viewer.cards" :key="mine.id" :value="mine.id">
                                    {{ [mine.full_name, mine.job_title, mine.company].filter(Boolean).join(' · ') }}
                                </option>
                            </select>
                        </label>
                        <p v-else-if="! viewer.cards.length" class="text-xs text-gray-400">You have no card yet, so {{ firstName }} gets your name and email.</p>
                        <InputError :message="swap.errors.card_id" />
                        <button type="submit" class="btn-accent h-[54px] sm:h-[52px] gap-2 rounded-[14px] py-0 text-base" :disabled="swap.processing">
                            <svg class="size-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                            {{ swap.processing ? 'Swapping…' : (viewer.swappedContactId ? 'Swap again to update' : 'Swap cards') }}
                        </button>
                        <Link v-if="viewer.swappedContactId" :href="route('contacts.show', viewer.swappedContactId)" class="text-center text-sm font-medium text-indigo-600 hover:underline">
                            Open {{ firstName }} in your contacts
                        </Link>
                    </form>
                </template>

                <div v-else-if="sent" class="space-y-2 px-6 py-10 text-center">
                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-accent/15 text-indigo-600">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </div>
                    <h2 class="text-lg font-semibold">Thanks! Your details were sent.</h2>
                    <p class="text-sm text-gray-500">{{ firstName }} will get back to you.</p>
                    <button type="button" class="text-sm font-medium text-indigo-600 hover:underline" @click="sent = false">Send another message</button>
                </div>

                <template v-else>
                    <div class="space-y-1.5 px-[18px] pt-5 sm:px-6 sm:pt-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-indigo-600">Swap contacts</p>
                        <h2 class="text-xl sm:text-[22px] font-bold tracking-[-0.02em]">Send {{ firstName }} your details</h2>
                        <p class="text-sm leading-relaxed text-gray-500">So {{ firstName }} can get back to you. Only {{ firstName }} sees this; it isn't shared or added to any list.</p>
                    </div>
                    <form class="flex flex-col gap-3.5 p-[18px] sm:gap-4 sm:p-6 sm:pt-5" @submit.prevent="submitLead">
                        <label :class="labelClass">
                            Your name
                            <input v-model="lead.full_name" autocomplete="name" placeholder="e.g. Juan Dela Cruz" required :class="fieldClass">
                            <InputError :message="lead.errors.full_name" />
                        </label>
                        <div class="grid gap-3.5 sm:gap-3 grid-cols-[repeat(auto-fit,minmax(min(100%,10.5rem),1fr))]">
                            <label :class="labelClass">
                                Email
                                <input v-model="lead.email" type="email" autocomplete="email" placeholder="you@company.com" :class="fieldClass">
                                <InputError :message="lead.errors.email" />
                            </label>
                            <label :class="labelClass">
                                Phone
                                <input v-model="lead.phone" type="tel" autocomplete="tel" placeholder="+63 917 …" :class="fieldClass">
                                <InputError :message="lead.errors.phone" />
                            </label>
                        </div>
                        <p class="-mt-1.5 sm:-mt-2 text-xs text-gray-400">Email or phone: one is enough.</p>
                        <label :class="labelClass">
                            <span>Company <span class="font-normal text-gray-400">Optional</span></span>
                            <input v-model="lead.company" autocomplete="organization" placeholder="Where you work" :class="fieldClass">
                        </label>
                        <label :class="labelClass">
                            <span>Message <span class="font-normal text-gray-400">Optional</span></span>
                            <textarea
                                v-model="lead.message"
                                rows="3"
                                :placeholder="`What can ${firstName} help you with?`"
                                class="field h-auto resize-y rounded-xl px-3.5 py-3 text-base sm:text-[15px] font-normal leading-normal"
                            />
                            <InputError :message="lead.errors.message" />
                        </label>
                        <button type="submit" class="btn-accent h-[54px] sm:h-[52px] gap-2 rounded-[14px] py-0 text-base" :disabled="lead.processing">
                            {{ lead.processing ? 'Sending…' : 'Send my details' }}
                            <svg v-if="! lead.processing" class="size-[17px]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.arrow" /></svg>
                        </button>
                        <p class="text-center text-[13px] text-gray-500">
                            Use MetMemo?
                            <a :href="route('public-card.swap.start', card.id)" class="font-medium text-indigo-600 hover:underline">Log in to swap cards in one tap</a>
                        </p>
                    </form>
                </template>
            </section>

            <!-- Pass this card on -->
            <section class="mx-4 mt-4 flex items-center gap-3.5 sm:gap-4 rounded-[18px] sm:rounded-[20px] border border-gray-200 bg-white p-3.5 sm:p-4 sm:mx-0 lg:col-start-1 lg:row-start-2 lg:mt-0">
                <div class="shrink-0 rounded-xl bg-[#fff] p-1">
                    <QrCode :value="publicUrl" :size="68" />
                </div>
                <div class="min-w-0 flex-1 space-y-0.5">
                    <p class="text-[15px] font-semibold">Pass this card on</p>
                    <p class="text-[13px] leading-snug text-gray-500">
                        <span class="sm:hidden">Show the code or send the link.</span>
                        <span class="hidden sm:inline">Someone else can scan this code, or you can send them the link.</span>
                    </p>
                </div>
                <button type="button" class="btn-quiet size-11 shrink-0 rounded-xl px-0 sm:size-auto sm:h-10 sm:px-3.5 sm:text-sm" aria-label="Share this card" @click="share">
                    <svg class="size-[18px] sm:size-[15px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.share" /></svg>
                    <span class="hidden sm:inline">Share</span>
                </button>
            </section>
        </main>

        <footer class="flex items-center justify-center gap-1.5 px-5 pb-8 pt-6 text-[13px] text-gray-400">
            Made with
            <Link href="/" aria-label="MetMemo home"><ApplicationMark class="!text-sm" /></Link>
            ·
            <Link :href="route('register')" class="font-medium text-indigo-600 hover:text-indigo-700">Make your own card</Link>
        </footer>
    </div>
</template>
