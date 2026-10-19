<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { addDays, today } from '@/dates';

const props = defineProps({
    contact: Object,
    cards: Array,
    events: { type: Array, default: () => [] },
});

const editing = computed(() => !! props.contact);

const form = useForm({
    full_name: props.contact?.full_name ?? '',
    job_title: props.contact?.job_title ?? '',
    company: props.contact?.company ?? '',
    email: props.contact?.email ?? '',
    phone: props.contact?.phone ?? '',
    event: props.contact?.event ?? '',
    // New contacts default to "met today, follow up in 2 days" so most only need a name.
    met_at: props.contact ? (props.contact.met_at ?? '') : today(),
    next_follow_up_at: props.contact ? (props.contact.next_follow_up_at ?? '') : addDays(2),
    card_id: props.contact?.card_id ?? '',
    tags: [...(props.contact?.tags ?? [])],
    note: '',
});

const submit = () => {
    if (editing.value) {
        form.put(route('contacts.update', props.contact.id));
    } else {
        form.post(route('contacts.store'));
    }
};

// Follow-up quick picks.
const followUpOptions = [
    { label: 'Tomorrow', days: 1 },
    { label: 'In 2 days', days: 2 },
    { label: 'Next week', days: 7 },
    { label: 'In 2 weeks', days: 14 },
];
const pickingDate = ref(false);
const followUpChoice = computed(() => {
    if (! form.next_follow_up_at) return 'none';
    const match = followUpOptions.find((option) => addDays(option.days) === form.next_follow_up_at);
    return match && ! pickingDate.value ? match.days : 'custom';
});
const chooseFollowUp = (days) => {
    pickingDate.value = false;
    form.next_follow_up_at = addDays(days);
};
const chooseNoFollowUp = () => {
    pickingDate.value = false;
    form.next_follow_up_at = '';
};

// Tags as chips.
const tagInput = ref('');
const addTag = () => {
    const tag = tagInput.value.trim().replace(/,$/, '');
    if (tag && ! form.tags.includes(tag) && form.tags.length < 15) form.tags.push(tag);
    tagInput.value = '';
};
const onTagKey = (event) => {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        addTag();
    } else if (event.key === 'Backspace' && ! tagInput.value && form.tags.length) {
        form.tags.pop();
    }
};
const removeTag = (tag) => {
    form.tags = form.tags.filter((item) => item !== tag);
};

// Live preview.
const initials = computed(() => form.full_name.split(/\s+/).filter(Boolean).map((word) => word[0]).slice(0, 2).join('').toUpperCase() || '?');
const role = computed(() => [form.job_title, form.company].filter(Boolean).join(' · '));
const throughCard = computed(() => props.cards.find((card) => card.id === form.card_id));
const formatDay = (value) => {
    if (! value) return null;
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};

const icons = {
    person: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
    email: 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75',
    phone: 'M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z',
    pin: 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z',
    clock: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    note: 'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10',
    camera: 'M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z',
};

const chip = (active) => active
    ? 'border-indigo-500 bg-indigo-50 text-indigo-600'
    : 'border-gray-300 text-gray-600 hover:border-gray-400 hover:text-gray-900';
</script>

<template>
    <AppLayout :title="editing ? 'Edit contact' : 'New contact'">
        <form class="py-6 sm:py-8" @submit.prevent="submit">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Title -->
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <Link :href="editing ? route('contacts.show', contact.id) : route('contacts.index')" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                            {{ editing ? contact.full_name : 'Contacts' }}
                        </Link>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">{{ editing ? 'Edit contact' : 'New contact' }}</h1>
                    </div>
                </div>

                <!-- Scan shortcut -->
                <Link
                    v-if="! editing"
                    :href="route('scan.create')"
                    class="flex items-center gap-4 rounded-2xl border border-dashed border-indigo-500/60 bg-indigo-50/60 px-5 py-4 hover:bg-indigo-50 transition"
                >
                    <span class="flex items-center justify-center size-10 shrink-0 rounded-full bg-accent text-accent-fg">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons.camera" />
                        </svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-gray-900">Have their business card?</span>
                        <span class="block text-sm text-gray-600">Scan it and the AI fills in this form for you.</span>
                    </span>
                    <span class="hidden sm:inline text-sm font-medium text-indigo-600">Scan instead →</span>
                </Link>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    <div class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl divide-y divide-gray-200">
                        <!-- Who they are -->
                        <section class="p-5 sm:p-6 space-y-4">
                            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                <svg class="size-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.person" /></svg>
                                Who they are
                            </h2>
                            <div>
                                <InputLabel for="full_name">Full name <span class="text-red-500">*</span></InputLabel>
                                <TextInput id="full_name" v-model="form.full_name" autocomplete="off" placeholder="e.g. Juan Dela Cruz" class="mt-1 block w-full" required autofocus />
                                <InputError :message="form.errors.full_name" class="mt-1" />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="job_title" value="Job title" />
                                    <TextInput id="job_title" v-model="form.job_title" placeholder="e.g. Operations Manager" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.job_title" class="mt-1" />
                                </div>
                                <div>
                                    <InputLabel for="company" value="Company" />
                                    <TextInput id="company" v-model="form.company" placeholder="e.g. Bayanihan Logistics" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.company" class="mt-1" />
                                </div>
                            </div>
                        </section>

                        <!-- How to reach them -->
                        <section class="p-5 sm:p-6 space-y-4">
                            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                <svg class="size-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.email" /></svg>
                                How to reach them
                            </h2>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="email" value="Email" />
                                    <div class="relative mt-1">
                                        <svg class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.email" /></svg>
                                        <TextInput id="email" v-model="form.email" type="email" autocomplete="off" placeholder="name@company.com" class="block w-full ps-9" />
                                    </div>
                                    <InputError :message="form.errors.email" class="mt-1" />
                                </div>
                                <div>
                                    <InputLabel for="phone" value="Phone" />
                                    <div class="relative mt-1">
                                        <svg class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.phone" /></svg>
                                        <TextInput id="phone" v-model="form.phone" type="tel" autocomplete="off" placeholder="+63 917 …" class="block w-full ps-9" />
                                    </div>
                                    <InputError :message="form.errors.phone" class="mt-1" />
                                </div>
                            </div>
                        </section>

                        <!-- Where you met -->
                        <section class="p-5 sm:p-6 space-y-4">
                            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                <svg class="size-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.pin" /></svg>
                                Where you met
                            </h2>
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="sm:col-span-2">
                                    <InputLabel for="event" value="Event or place" />
                                    <TextInput id="event" v-model="form.event" list="event-options" autocomplete="off" placeholder="e.g. Expo SMX, client visit" class="mt-1 block w-full" />
                                    <datalist id="event-options">
                                        <option v-for="event in events" :key="event" :value="event" />
                                    </datalist>
                                    <InputError :message="form.errors.event" class="mt-1" />
                                </div>
                                <div>
                                    <InputLabel for="met_at" value="Met on" />
                                    <TextInput id="met_at" v-model="form.met_at" type="date" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.met_at" class="mt-1" />
                                </div>
                            </div>
                            <div v-if="cards.length">
                                <InputLabel value="Which of your cards were you using?" />
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition" :class="chip(! form.card_id)" @click="form.card_id = ''">
                                        None
                                    </button>
                                    <button
                                        v-for="card in cards"
                                        :key="card.id"
                                        type="button"
                                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                                        :class="chip(form.card_id === card.id)"
                                        @click="form.card_id = card.id"
                                    >
                                        {{ card.full_name }}<span v-if="card.job_title" class="opacity-70"> · {{ card.job_title }}</span>
                                    </button>
                                </div>
                                <InputError :message="form.errors.card_id" class="mt-1" />
                            </div>
                        </section>

                        <!-- Follow up -->
                        <section class="p-5 sm:p-6 space-y-4">
                            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                <svg class="size-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.clock" /></svg>
                                Follow up
                            </h2>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="option in followUpOptions"
                                    :key="option.days"
                                    type="button"
                                    class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                                    :class="chip(followUpChoice === option.days)"
                                    @click="chooseFollowUp(option.days)"
                                >
                                    {{ option.label }}
                                </button>
                                <button type="button" class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition" :class="chip(followUpChoice === 'custom')" @click="pickingDate = true">
                                    Pick a date
                                </button>
                                <button type="button" class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition" :class="chip(followUpChoice === 'none')" @click="chooseNoFollowUp">
                                    No reminder
                                </button>
                            </div>
                            <div v-if="followUpChoice === 'custom'" class="max-w-xs">
                                <InputLabel for="next_follow_up_at" value="Follow up on" />
                                <TextInput id="next_follow_up_at" v-model="form.next_follow_up_at" type="date" :min="today()" class="mt-1 block w-full" />
                            </div>
                            <p class="text-sm text-gray-500">
                                <template v-if="form.next_follow_up_at">MetMemo will remind you on <span class="font-medium text-gray-900">{{ formatDay(form.next_follow_up_at) }}</span>.</template>
                                <template v-else>No reminder for this contact.</template>
                            </p>
                            <InputError :message="form.errors.next_follow_up_at" />
                        </section>

                        <!-- First note and tags -->
                        <section class="p-5 sm:p-6 space-y-4">
                            <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                <svg class="size-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.note" /></svg>
                                {{ editing ? 'Tags' : 'What you talked about' }}
                            </h2>
                            <div v-if="! editing">
                                <InputLabel for="note" value="First note" />
                                <textarea
                                    id="note"
                                    v-model="form.note"
                                    rows="3"
                                    placeholder="e.g. Wants a quote for 3 branches. Decision by end of month. Send the brochure."
                                    class="mt-1 block w-full rounded-lg shadow-sm"
                                />
                                <p class="mt-1 text-xs text-gray-500">Private to you. This is what MetMemo's assistant reads when it drafts your follow-up.</p>
                                <InputError :message="form.errors.note" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="tag_input" value="Tags" />
                                <div class="mt-1 flex flex-wrap items-center gap-2 rounded-lg border border-gray-300 bg-white px-2 py-1.5 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                                    <span v-for="tag in form.tags" :key="tag" class="inline-flex items-center gap-1 rounded-full bg-indigo-50 ps-2.5 pe-1 py-0.5 text-sm text-indigo-600">
                                        {{ tag }}
                                        <button type="button" class="rounded-full p-0.5 hover:bg-indigo-100" :aria-label="`Remove ${tag}`" @click="removeTag(tag)">
                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                        </button>
                                    </span>
                                    <input
                                        id="tag_input"
                                        v-model="tagInput"
                                        type="text"
                                        :placeholder="form.tags.length ? '' : 'e.g. investor, supplier (press Enter)'"
                                        class="min-w-[8rem] flex-1 border-0 bg-transparent p-1 text-sm shadow-none focus:ring-0"
                                        @keydown="onTagKey"
                                        @blur="addTag"
                                    >
                                </div>
                                <InputError :message="form.errors.tags" class="mt-1" />
                            </div>
                        </section>
                    </div>

                    <!-- Live preview -->
                    <aside class="hidden lg:block lg:sticky lg:top-6 space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Preview</p>
                        <div class="bg-white border border-gray-200 rounded-2xl p-5 space-y-4">
                            <div class="flex items-center gap-3">
                                <span class="flex items-center justify-center size-12 shrink-0 rounded-full bg-indigo-50 text-indigo-600 font-semibold">{{ initials }}</span>
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900 truncate">{{ form.full_name || 'Their name' }}</div>
                                    <div class="text-sm text-gray-500 truncate">{{ role || 'Job title · Company' }}</div>
                                </div>
                            </div>
                            <ul class="space-y-2 text-sm">
                                <li v-if="form.email" class="flex items-center gap-2 text-gray-700">
                                    <svg class="size-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.email" /></svg>
                                    <span class="truncate">{{ form.email }}</span>
                                </li>
                                <li v-if="form.phone" class="flex items-center gap-2 text-gray-700">
                                    <svg class="size-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.phone" /></svg>
                                    {{ form.phone }}
                                </li>
                                <li v-if="form.event || form.met_at" class="flex items-center gap-2 text-gray-700">
                                    <svg class="size-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.pin" /></svg>
                                    <span class="truncate">{{ [form.event, formatDay(form.met_at)].filter(Boolean).join(' · ') }}</span>
                                </li>
                                <li v-if="throughCard" class="text-gray-500">via {{ throughCard.full_name }}</li>
                            </ul>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-if="form.next_follow_up_at"
                                    class="rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200 px-2.5 py-0.5 text-xs font-medium"
                                >
                                    Follow up {{ formatDay(form.next_follow_up_at) }}
                                </span>
                                <span v-for="tag in form.tags" :key="tag" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-600">{{ tag }}</span>
                            </div>
                            <p v-if="form.note" class="border-t border-gray-200 pt-3 text-sm text-gray-600 line-clamp-4">{{ form.note }}</p>
                        </div>
                        <p class="text-xs text-gray-500">This is how they will appear in your contacts.</p>
                    </aside>
                </div>

                <!-- Actions: pinned above the tab bar on phones -->
                <div class="sticky bottom-[calc(4rem+env(safe-area-inset-bottom,0px))] lg:static z-10 -mx-4 sm:mx-0 lg:mx-0 border-t lg:border-0 border-gray-200 bg-page/95 backdrop-blur px-4 pt-3 pb-10 lg:p-0 lg:bg-transparent">
                    <div class="flex gap-3 lg:justify-end">
                        <Link
                            :href="editing ? route('contacts.show', contact.id) : route('contacts.index')"
                            class="flex-1 lg:flex-none inline-flex justify-center rounded-md border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton class="flex-[2] lg:flex-none justify-center py-2.5" :disabled="form.processing">
                            {{ editing ? 'Save changes' : 'Save contact' }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
