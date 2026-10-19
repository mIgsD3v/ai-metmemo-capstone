<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import QrCode from '@/Components/QrCode.vue';
import TextInput from '@/Components/TextInput.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { copyText } from '@/clipboard';

const props = defineProps({
    card: Object,
    publicUrl: String,
});

const initials = props.card.full_name
    .split(/\s+/)
    .filter(Boolean)
    .map((word) => word[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

const firstName = props.card.full_name.split(/\s+/)[0];
const role = [props.card.job_title, props.card.company].filter(Boolean).join(' · ');

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
    web: 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418',
    pin: 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z',
    save: 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3',
};

const details = computed(() => [
    props.card.email && { icon: icons.email, label: 'Email', value: props.card.email, href: `mailto:${props.card.email}` },
    props.card.phone && { icon: icons.phone, label: 'Phone', value: props.card.phone, href: `tel:${props.card.phone}` },
    props.card.website && { icon: icons.web, label: 'Website', value: websiteLabel.value, href: props.card.website, external: true },
    props.card.location && { icon: icons.pin, label: 'Location', value: props.card.location },
].filter(Boolean));

const copyLink = () => copyText(props.publicUrl);

const lead = useForm({
    full_name: '',
    email: '',
    phone: '',
    company: '',
    message: '',
});

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
</script>

<template>
    <Head :title="card.full_name" />

    <div class="relative min-h-screen bg-page px-4 pt-16 pb-8 sm:py-12 overflow-hidden">
        <div class="absolute inset-x-0 top-0 h-[28rem] bg-grid pointer-events-none" aria-hidden="true" />
        <ThemeToggle class="absolute top-4 right-4 z-10" />

        <div class="relative max-w-md mx-auto space-y-4">
            <!-- The card -->
            <section class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden">
                <div class="h-24 bg-gray-900 dark:bg-gray-100 relative">
                    <div class="absolute inset-0 bg-grid opacity-70" aria-hidden="true" />
                </div>

                <div class="px-6 pb-6 -mt-14 text-center">
                    <img
                        v-if="card.photo_url"
                        :src="card.photo_url"
                        :alt="card.full_name"
                        class="relative mx-auto size-28 rounded-full object-cover ring-4 ring-white"
                    >
                    <div
                        v-else
                        class="relative mx-auto size-28 rounded-full bg-indigo-50 text-indigo-600 ring-4 ring-white flex items-center justify-center text-3xl font-semibold select-none"
                        aria-hidden="true"
                    >
                        {{ initials }}
                    </div>

                    <h1 class="mt-4 text-2xl font-bold tracking-tight text-gray-900">{{ card.full_name }}</h1>
                    <p v-if="role" class="mt-1 text-gray-600">{{ role }}</p>
                    <p v-if="card.bio" class="mt-4 text-sm leading-relaxed text-gray-600 whitespace-pre-line">{{ card.bio }}</p>

                    <a :href="route('public-card.vcard', card.id)" class="btn-accent w-full mt-6 gap-2 py-3 text-base">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons.save" />
                        </svg>
                        Save to contacts
                    </a>
                </div>

                <ul v-if="details.length" class="border-t border-gray-200 divide-y divide-gray-200">
                    <li v-for="item in details" :key="item.label">
                        <component
                            :is="item.href ? 'a' : 'div'"
                            :href="item.href"
                            :target="item.external ? '_blank' : undefined"
                            :rel="item.external ? 'noopener' : undefined"
                            class="flex items-center gap-4 px-6 py-3.5"
                            :class="item.href ? 'hover:bg-gray-50 transition' : ''"
                        >
                            <span class="flex items-center justify-center size-10 shrink-0 rounded-full bg-indigo-50 text-indigo-600">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                                </svg>
                            </span>
                            <span class="min-w-0 text-start">
                                <span class="block text-xs text-gray-500">{{ item.label }}</span>
                                <span class="block text-sm font-medium text-gray-900 truncate">{{ item.value }}</span>
                            </span>
                        </component>
                    </li>
                </ul>
            </section>

            <!-- Share -->
            <section class="bg-white border border-gray-200 rounded-3xl p-6 flex items-center gap-5">
                <div class="shrink-0 rounded-xl bg-white p-1.5 border border-gray-200">
                    <QrCode :value="publicUrl" :size="96" />
                </div>
                <div class="min-w-0 space-y-2">
                    <h2 class="font-semibold text-gray-900">Share this card</h2>
                    <p class="text-sm text-gray-500">Let someone scan the code, or send them the link.</p>
                    <button type="button" class="text-sm font-medium text-indigo-600 hover:underline" @click="copyLink">
                        Copy link
                    </button>
                </div>
            </section>

            <!-- Lead form -->
            <section class="bg-white border border-gray-200 rounded-3xl p-6">
                <template v-if="sent">
                    <div class="text-center py-6 space-y-2">
                        <div class="mx-auto flex items-center justify-center size-12 rounded-full bg-indigo-50 text-indigo-600">
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                        <h2 class="font-semibold text-gray-900">Thanks! Your details were sent.</h2>
                        <p class="text-sm text-gray-500">{{ firstName }} will get back to you.</p>
                        <button type="button" class="text-sm text-indigo-600 hover:underline" @click="sent = false">Send another message</button>
                    </div>
                </template>

                <template v-else>
                    <h2 class="font-semibold text-gray-900">Leave your details</h2>
                    <p class="text-sm text-gray-500 mt-1">Your details go only to {{ firstName }}, so they can get back to you.</p>

                    <form class="mt-5 space-y-4" @submit.prevent="submitLead">
                        <div>
                            <InputLabel for="lead_name" value="Your name" />
                            <TextInput id="lead_name" v-model="lead.full_name" autocomplete="name" class="mt-1 block w-full" required />
                            <InputError :message="lead.errors.full_name" class="mt-1" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="lead_email" value="Email" />
                                <TextInput id="lead_email" v-model="lead.email" type="email" autocomplete="email" class="mt-1 block w-full" />
                                <InputError :message="lead.errors.email" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="lead_phone" value="Phone" />
                                <TextInput id="lead_phone" v-model="lead.phone" type="tel" autocomplete="tel" placeholder="+63…" class="mt-1 block w-full" />
                                <InputError :message="lead.errors.phone" class="mt-1" />
                            </div>
                            <p class="sm:col-span-2 -mt-2 text-xs text-gray-500">Add at least an email or a phone number.</p>
                        </div>
                        <div>
                            <InputLabel for="lead_company" value="Company (optional)" />
                            <TextInput id="lead_company" v-model="lead.company" autocomplete="organization" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel for="lead_message" value="Message (optional)" />
                            <textarea
                                id="lead_message"
                                v-model="lead.message"
                                rows="3"
                                :placeholder="`What can ${firstName} help you with?`"
                                class="mt-1 block w-full rounded-lg shadow-sm"
                            />
                            <InputError :message="lead.errors.message" class="mt-1" />
                        </div>
                        <PrimaryButton class="w-full justify-center py-3" :disabled="lead.processing">
                            {{ lead.processing ? 'Sending…' : 'Send my details' }}
                        </PrimaryButton>
                    </form>
                </template>
            </section>

            <footer class="pt-2 pb-4 flex justify-center">
                <Link href="/" class="inline-flex items-center gap-2 text-xs text-gray-500 hover:text-gray-800">
                    Made with <ApplicationMark class="!text-sm" />
                </Link>
            </footer>
        </div>
    </div>
</template>
