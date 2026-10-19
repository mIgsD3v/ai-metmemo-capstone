<script setup>
import { today as localToday } from '@/dates';
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ask } from '@/confirm';

const props = defineProps({
    contact: Object,
});

const noteForm = useForm({ body: '' });

const addNote = () => {
    noteForm.post(route('contacts.notes.store', props.contact.id), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
};

const destroy = async () => {
    const notes = props.contact.notes.length;
    const yes = await ask({
        title: `Delete ${props.contact.full_name}?`,
        message: notes
            ? `This also deletes ${notes} ${notes === 1 ? 'note' : 'notes'} about them. You can't undo this.`
            : "You can't undo this.",
        confirmText: 'Delete contact',
    });

    if (yes) router.delete(route('contacts.destroy', props.contact.id));
};

const initials = props.contact.full_name.split(/\s+/).filter(Boolean).map((word) => word[0]).slice(0, 2).join('').toUpperCase();
const role = [props.contact.job_title, props.contact.company].filter(Boolean).join(' · ');

const formatDay = (value) => {
    if (! value) return null;
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
};

const today = localToday();
const followUp = computed(() => {
    const day = props.contact.next_follow_up_at?.slice(0, 10);
    if (! day) return null;
    if (day < today) return { text: `Overdue since ${formatDay(day)}`, tone: 'warn' };
    if (day === today) return { text: 'Follow up today', tone: 'warn' };
    return { text: `Follow up on ${formatDay(day)}`, tone: 'calm' };
});

const sourceLabels = { manual: 'Added by hand', lead_form: 'Left details on your card', scan: 'Scanned from a paper card' };

const details = computed(() => [
    ['Email', props.contact.email],
    ['Phone', props.contact.phone],
    ['Company', props.contact.company],
    ['Job title', props.contact.job_title],
    ['Where you met', props.contact.event],
    ['Met on', formatDay(props.contact.met_at)],
    ['How they were added', sourceLabels[props.contact.source]],
    ['Through your card', props.contact.card?.full_name],
].filter(([, value]) => value));

const sortedNotes = computed(() => [...props.contact.notes].sort((a, b) => b.created_at.localeCompare(a.created_at)));

const noteTime = (value) => new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
</script>

<template>
    <AppLayout :title="contact.full_name">
        <div class="py-6 sm:py-8">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <Link :href="route('contacts.index')" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Contacts
                </Link>

                <!-- Header -->
                <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6">
                    <div class="flex items-start gap-4">
                        <span class="flex items-center justify-center size-14 sm:size-16 shrink-0 rounded-full bg-indigo-50 text-indigo-600 text-lg font-semibold">{{ initials }}</span>
                        <div class="min-w-0 flex-1">
                            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900">{{ contact.full_name }}</h1>
                            <p v-if="role" class="text-gray-600">{{ role }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-if="followUp"
                                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="followUp.tone === 'warn'
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200'
                                        : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ followUp.text }}
                                </span>
                                <span v-for="tag in contact.tags ?? []" :key="tag" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-600">{{ tag }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-2 sm:flex gap-2">
                        <a v-if="contact.phone" :href="`tel:${contact.phone}`" class="btn-accent gap-2">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                            </svg>
                            Call
                        </a>
                        <a v-if="contact.email" :href="`mailto:${contact.email}`" class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                            Email
                        </a>
                        <Link :href="route('contacts.edit', contact.id)" class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            Edit
                        </Link>
                        <button type="button" class="inline-flex items-center justify-center rounded-md px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition sm:ms-auto" @click="destroy">
                            Delete
                        </button>
                    </div>
                </section>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                    <!-- Details -->
                    <section class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl self-start">
                        <h2 class="px-5 sm:px-6 pt-5 font-semibold text-gray-900">Details</h2>
                        <dl class="mt-3 divide-y divide-gray-200">
                            <div v-for="[label, value] in details" :key="label" class="px-5 sm:px-6 py-3">
                                <dt class="text-xs text-gray-500">{{ label }}</dt>
                                <dd class="text-sm font-medium text-gray-900 break-words">{{ value }}</dd>
                            </div>
                        </dl>
                    </section>

                    <!-- Notes -->
                    <section class="lg:col-span-3 bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 space-y-5">
                        <div>
                            <h2 class="font-semibold text-gray-900">Meeting notes</h2>
                            <p class="text-sm text-gray-500 mt-1">What you talked about. This is what MetMemo remembers for your follow-up.</p>
                        </div>

                        <form class="space-y-3" @submit.prevent="addNote">
                            <label for="note_body" class="sr-only">New note</label>
                            <textarea
                                id="note_body"
                                v-model="noteForm.body"
                                rows="3"
                                placeholder="What did you talk about? What should happen next?"
                                class="block w-full rounded-lg shadow-sm"
                            />
                            <InputError :message="noteForm.errors.body" />
                            <div class="flex justify-end">
                                <PrimaryButton :disabled="noteForm.processing || ! noteForm.body.trim()">Add note</PrimaryButton>
                            </div>
                        </form>

                        <p v-if="! sortedNotes.length" class="text-sm text-gray-500 text-center py-6">No notes yet.</p>
                        <ol v-else class="relative border-s border-gray-200 ms-2 space-y-5">
                            <li v-for="note in sortedNotes" :key="note.id" class="ps-5 relative">
                                <span class="absolute -start-[5px] top-1.5 size-2.5 rounded-full bg-indigo-500" aria-hidden="true" />
                                <p class="text-xs text-gray-500">{{ noteTime(note.created_at) }}</p>
                                <p class="mt-1 text-sm text-gray-800 whitespace-pre-line">{{ note.body }}</p>
                            </li>
                        </ol>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
