<script setup>
import { today as localToday } from '@/dates';
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dropdown from '@/Components/Dropdown.vue';
import Pagination from '@/Components/Pagination.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    contacts: Object,
    cards: Array,
    filters: Object,
    perPageOptions: Array,
});

const search = ref(props.filters.search ?? '');
const card = ref(props.filters.card ?? '');

// Changing search or card starts again at page 1; the page size is kept.
const reload = ({ page, perPage } = {}) => {
    router.get(route('contacts.index'), {
        search: search.value || undefined,
        card: card.value || undefined,
        per_page: (perPage ?? props.filters.per_page) === props.perPageOptions[0] ? undefined : (perPage ?? props.filters.per_page),
        page: page > 1 ? page : undefined,
    }, { preserveState: true, preserveScroll: ! page, replace: ! page });
};

let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => reload(), 300);
});
watch(card, () => reload());

const activeCard = computed(() => props.cards.find((option) => option.id === props.filters.card));
const heading = computed(() => {
    if (props.filters.card === 'none') return 'Not from a card';
    return activeCard.value ? `Contacts from ${activeCard.value.full_name}` : 'Contacts';
});

const today = localToday();
const isDue = (contact) => contact.next_follow_up_at && contact.next_follow_up_at <= today;

const formatDay = (value) => {
    if (! value) return '';
    const [y, m, d] = value.slice(0, 10).split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

const sourceLabels = { manual: 'Added by hand', lead_form: 'Card form', scan: 'Scanned' };

// Exports use the filters the list is showing (not text still being typed), so you get what you see.
const exportOptions = [
    { format: 'xlsx', label: 'Excel (.xlsx)', hint: 'For Excel or Google Sheets' },
    { format: 'csv', label: 'CSV (.csv)', hint: 'For any spreadsheet or CRM import' },
    { format: 'vcf', label: 'vCard (.vcf)', hint: 'Import into your phone or Google Contacts' },
];
const filtered = computed(() => Boolean(props.filters.search || props.filters.card));
const exportUrl = (format) => route('contacts.export', {
    format,
    search: props.filters.search || undefined,
    card: props.filters.card || undefined,
});
const initials = (name) => name.split(/\s+/).filter(Boolean).map((word) => word[0]).slice(0, 2).join('').toUpperCase();
</script>

<template>
    <AppLayout title="Contacts">
        <template #header>
            <div class="flex justify-between items-center gap-4">
                <div class="min-w-0">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight truncate">{{ heading }}</h2>
                    <p class="text-sm text-gray-500 mt-1">{{ contacts.total }} {{ contacts.total === 1 ? 'person' : 'people' }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <Dropdown v-if="contacts.total" align="right" width="72">
                        <template #trigger>
                            <button type="button" class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 sm:px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition" aria-haspopup="menu">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                <span class="hidden sm:inline">Export</span>
                            </button>
                        </template>
                        <template #content>
                            <p class="px-4 pt-2 pb-1 text-xs text-gray-500">
                                {{ filtered ? `The ${contacts.total} ${contacts.total === 1 ? 'contact' : 'contacts'} matching your filters` : `All ${contacts.total} ${contacts.total === 1 ? 'contact' : 'contacts'}` }}
                            </p>
                            <a
                                v-for="option in exportOptions"
                                :key="option.format"
                                :href="exportUrl(option.format)"
                                class="block px-4 py-2 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition"
                            >
                                <span class="block text-sm font-medium text-gray-900">{{ option.label }}</span>
                                <span class="block text-xs text-gray-500">{{ option.hint }}</span>
                            </a>
                        </template>
                    </Dropdown>
                    <Link :href="route('contacts.create')" class="btn-accent">
                        <span class="sm:hidden">Add</span>
                        <span class="hidden sm:inline">New contact</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                <!-- Filters -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <TextInput v-model="search" type="search" placeholder="Search name, company, email or event" class="w-full sm:w-96" aria-label="Search contacts" />
                    <select
                        v-model="card"
                        class="w-full sm:w-72 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        aria-label="Filter by card"
                    >
                        <option value="">All cards</option>
                        <option v-for="option in cards" :key="option.id" :value="option.id">
                            {{ option.full_name }}{{ option.job_title ? ` · ${option.job_title}` : '' }}
                        </option>
                        <option value="none">Not from a card (scanned or added)</option>
                    </select>
                </div>

                <!-- Phone: list -->
                <ul class="md:hidden bg-white border border-gray-200 rounded-2xl divide-y divide-gray-200">
                    <li v-for="contact in contacts.data" :key="contact.id">
                        <Link :href="route('contacts.show', contact.id)" class="flex items-center gap-3 px-4 py-3 active:bg-gray-50">
                            <span class="flex items-center justify-center size-10 shrink-0 rounded-full bg-indigo-50 text-indigo-600 text-sm font-semibold">{{ initials(contact.full_name) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-gray-900 truncate">{{ contact.full_name }}</span>
                                <span class="block text-sm text-gray-500 truncate">
                                    {{ [contact.company, contact.card?.full_name ? `via ${contact.card.full_name}` : sourceLabels[contact.source]].filter(Boolean).join(' · ') }}
                                </span>
                            </span>
                            <span
                                v-if="contact.next_follow_up_at"
                                class="shrink-0 text-xs font-medium rounded-full px-2 py-0.5"
                                :class="isDue(contact) ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' : 'text-gray-400'"
                            >
                                {{ formatDay(contact.next_follow_up_at) }}
                            </span>
                        </Link>
                    </li>
                    <li v-if="! contacts.data.length" class="px-4 py-10 text-center text-sm text-gray-500">No contacts found.</li>
                </ul>

                <!-- Larger screens: table -->
                <div class="hidden md:block bg-white border border-gray-200 rounded-2xl overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wider text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-3 font-medium">Name</th>
                                <th class="px-5 py-3 font-medium">Company</th>
                                <th class="px-5 py-3 font-medium">Card</th>
                                <th class="px-5 py-3 font-medium">Where you met</th>
                                <th class="px-5 py-3 font-medium">Source</th>
                                <th class="px-5 py-3 font-medium">Follow up</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="contact in contacts.data" :key="contact.id" class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <Link :href="route('contacts.show', contact.id)" class="flex items-center gap-3 font-medium text-gray-900 hover:text-indigo-600">
                                        <span class="flex items-center justify-center size-8 shrink-0 rounded-full bg-indigo-50 text-indigo-600 text-xs font-semibold">{{ initials(contact.full_name) }}</span>
                                        {{ contact.full_name }}
                                    </Link>
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ contact.company }}</td>
                                <td class="px-5 py-3">
                                    <span v-if="contact.card" class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ contact.card.full_name }}</span>
                                    <span v-else class="text-gray-400">None</span>
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ contact.event }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ sourceLabels[contact.source] }}</td>
                                <td class="px-5 py-3 tabular-nums" :class="isDue(contact) ? 'text-amber-600 font-semibold' : 'text-gray-600'">{{ formatDay(contact.next_follow_up_at) }}</td>
                            </tr>
                            <tr v-if="! contacts.data.length">
                                <td colspan="6" class="px-5 py-10 text-center text-gray-500">No contacts found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="contacts.total" class="bg-white border border-gray-200 rounded-2xl px-4 sm:px-5 py-3">
                    <Pagination :paginator="contacts" :per-page-options="perPageOptions" label="contacts" @change="reload" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
