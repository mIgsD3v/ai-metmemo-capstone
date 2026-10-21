<script setup>
import { computed } from 'vue';

// A live, scaled-down copy of the public card page (Public/Card.vue), shown beside the card form
// so you see what people get when they scan your QR code while you type.
const props = defineProps({
    card: { type: Object, required: true },
    photoUrl: String,
});

const icons = {
    email: 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75',
    phone: 'M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z',
    web: 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418',
    pin: 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z',
    save: 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3',
};

const name = computed(() => props.card.full_name?.trim() || 'Your name');
const initials = computed(() => name.value.split(/\s+/).filter(Boolean).slice(0, 2).map((word) => word[0].toUpperCase()).join(''));
const role = computed(() => [props.card.job_title, props.card.company].filter(Boolean).join(' · '));
const website = computed(() => {
    try {
        const url = new URL(props.card.website);
        return url.host + (url.pathname === '/' ? '' : url.pathname);
    } catch {
        return props.card.website;
    }
});

const details = computed(() => [
    props.card.email && { icon: icons.email, label: 'Email', value: props.card.email },
    props.card.phone && { icon: icons.phone, label: 'Phone', value: props.card.phone },
    props.card.website && { icon: icons.web, label: 'Website', value: website.value },
    props.card.location && { icon: icons.pin, label: 'Location', value: props.card.location },
].filter(Boolean));
</script>

<template>
    <section class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden" aria-label="Preview of your public card">
        <div class="h-20 bg-gray-900 dark:bg-gray-100 relative">
            <div class="absolute inset-0 bg-grid opacity-70" aria-hidden="true" />
        </div>

        <div class="px-6 pb-6 -mt-12 text-center">
            <img v-if="photoUrl" :src="photoUrl" alt="" class="relative mx-auto size-24 rounded-full object-cover ring-4 ring-white">
            <div v-else class="relative mx-auto size-24 rounded-full bg-indigo-50 text-indigo-600 ring-4 ring-white flex items-center justify-center text-2xl font-semibold select-none" aria-hidden="true">
                {{ initials }}
            </div>

            <p class="mt-3 text-xl font-bold tracking-tight" :class="card.full_name?.trim() ? 'text-gray-900' : 'text-gray-400'">{{ name }}</p>
            <p v-if="role" class="mt-0.5 text-sm text-gray-600">{{ role }}</p>
            <p v-if="card.bio" class="mt-3 text-sm leading-relaxed text-gray-600 whitespace-pre-line line-clamp-4">{{ card.bio }}</p>

            <div class="btn-accent w-full mt-5 gap-2 py-2.5 pointer-events-none" aria-hidden="true">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="icons.save" />
                </svg>
                Save to contacts
            </div>
        </div>

        <ul v-if="details.length" class="border-t border-gray-200 divide-y divide-gray-200">
            <li v-for="item in details" :key="item.label" class="flex items-center gap-3 px-6 py-3">
                <span class="flex items-center justify-center size-9 shrink-0 rounded-full bg-indigo-50 text-indigo-600">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                </span>
                <span class="min-w-0 text-start">
                    <span class="block text-xs text-gray-500">{{ item.label }}</span>
                    <span class="block text-sm font-medium text-gray-900 truncate">{{ item.value }}</span>
                </span>
            </li>
        </ul>
    </section>
</template>
