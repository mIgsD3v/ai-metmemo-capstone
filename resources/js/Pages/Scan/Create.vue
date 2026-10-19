<script setup>
import { today as localToday } from '@/dates';
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    cards: Array,
});

// capture -> reading -> review
const step = ref('capture');
const photoUrl = ref(null);
const readError = ref(null);
const scanned = ref({});
const cameraInput = ref(null);
const galleryInput = ref(null);

const scannedFields = [
    ['full_name', 'Full name', 'text'],
    ['job_title', 'Job title', 'text'],
    ['company', 'Company', 'text'],
    ['email', 'Email', 'email'],
    ['phone', 'Phone', 'tel'],
];

const today = localToday();

const form = useForm({
    full_name: '',
    job_title: '',
    company: '',
    email: '',
    phone: '',
    event: '',
    met_at: today,
    card_id: '',
    note: '',
});

const flaggedCount = computed(() => scannedFields.filter(([key]) => scanned.value[key]?.needs_review).length);

// Phone photos are several MB; a 1600px JPEG keeps the text readable and the upload fast.
const shrink = (file, maxSize = 1600) => new Promise((resolve) => {
    const url = URL.createObjectURL(file);
    const img = new Image();

    img.onload = () => {
        const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.width * scale);
        canvas.height = Math.round(img.height * scale);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);
        canvas.toBlob((blob) => resolve(blob ? new File([blob], 'card.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', 0.85);
    };

    img.onerror = () => {
        URL.revokeObjectURL(url);
        resolve(file);
    };

    img.src = url;
});

const onPhoto = async (event) => {
    const original = event.target.files[0];
    event.target.value = '';

    if (! original) return;

    const photo = await shrink(original);
    photoUrl.value = URL.createObjectURL(photo);
    readError.value = null;
    step.value = 'reading';

    const data = new FormData();
    data.append('image', photo);

    try {
        const { data: body } = await window.axios.post(route('scan.read'), data);
        scanned.value = body.fields;

        for (const [key] of scannedFields) {
            form[key] = body.fields[key]?.value ?? '';
        }
    } catch (error) {
        readError.value = error.response?.data?.message
            ?? error.response?.data?.errors?.image?.[0]
            ?? 'The AI could not read this card right now. You can type the details below.';
        scanned.value = {};
    }

    step.value = 'review';
};

const typeByHand = () => {
    readError.value = null;
    scanned.value = {};
    photoUrl.value = null;
    step.value = 'review';
};

const startOver = () => {
    form.reset();
    scanned.value = {};
    readError.value = null;
    photoUrl.value = null;
    step.value = 'capture';
};

const save = () => {
    form.post(route('scan.store'));
};

const confidenceLabel = (field) => `${Math.round((field?.confidence ?? 0) * 100)}% sure`;
</script>

<template>
    <AppLayout title="Scan a card">
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Scan a business card</h2>
                    <p class="text-sm text-gray-500 mt-1">The AI reads it, you check it, MetMemo remembers it.</p>
                </div>
                <button v-if="step === 'review'" type="button" class="hidden sm:inline text-sm text-indigo-600 hover:underline" @click="startOver">
                    Scan another
                </button>
            </div>
        </template>

        <div class="py-6 sm:py-10">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Step indicator -->
                <ol class="flex items-center gap-2 text-xs font-medium" aria-label="Progress">
                    <li
                        v-for="(label, key) in { capture: '1. Photo', reading: '2. AI reads', review: '3. You review' }"
                        :key="key"
                        class="flex-1 rounded-full px-3 py-1.5 text-center"
                        :class="step === key ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500'"
                    >
                        {{ label }}
                    </li>
                </ol>

                <!-- 1. Capture -->
                <section v-if="step === 'capture'" class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 space-y-6">
                    <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="onPhoto">
                    <input ref="galleryInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onPhoto">

                    <button
                        type="button"
                        class="w-full aspect-[16/10] max-h-72 rounded-2xl border-2 border-dashed border-gray-300 hover:border-indigo-500 hover:bg-indigo-50/40 transition flex flex-col items-center justify-center gap-3 text-gray-500"
                        @click="cameraInput.click()"
                    >
                        <span class="flex items-center justify-center size-16 rounded-full bg-accent text-accent-fg">
                            <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                            </svg>
                        </span>
                        <span class="text-base font-semibold text-gray-900">Take a photo of the card</span>
                        <span class="text-sm">Opens your camera on a phone</span>
                    </button>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <SecondaryButton type="button" class="justify-center flex-1" @click="galleryInput.click()">Upload a photo</SecondaryButton>
                        <SecondaryButton type="button" class="justify-center flex-1" @click="typeByHand">Type it in instead</SecondaryButton>
                    </div>

                    <ul class="grid gap-2 text-sm text-gray-500">
                        <li class="flex gap-2"><span class="text-indigo-500">•</span> Lay the card flat in good light and fill the frame.</li>
                        <li class="flex gap-2"><span class="text-indigo-500">•</span> Nothing is saved until you review it. The photo itself is never stored.</li>
                    </ul>
                </section>

                <!-- 2. Reading -->
                <section v-else-if="step === 'reading'" class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 flex flex-col items-center gap-5 text-center">
                    <img v-if="photoUrl" :src="photoUrl" alt="Your card photo" class="max-h-56 rounded-xl border border-gray-200 object-contain">
                    <div class="flex items-center gap-3 text-gray-700 font-medium">
                        <svg class="size-5 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        Reading the card…
                    </div>
                    <p class="text-sm text-gray-500">This usually takes a few seconds.</p>
                </section>

                <!-- 3. Review -->
                <form v-else class="space-y-6" @submit.prevent="save">
                    <div v-if="readError" class="rounded-xl border border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200 dark:border-amber-800 px-4 py-3 text-sm">
                        {{ readError }}
                    </div>
                    <div v-else-if="Object.keys(scanned).length" class="rounded-xl bg-indigo-50 text-indigo-600 px-4 py-3 text-sm font-medium">
                        <template v-if="flaggedCount">The AI is unsure about {{ flaggedCount }} {{ flaggedCount === 1 ? 'field' : 'fields' }}. Check the highlighted ones before saving.</template>
                        <template v-else>The AI read every field confidently. Give it a quick look and save.</template>
                    </div>

                    <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 grid gap-6 md:grid-cols-5">
                        <div v-if="photoUrl" class="md:col-span-2">
                            <img :src="photoUrl" alt="Your card photo" class="w-full rounded-xl border border-gray-200 object-contain md:sticky md:top-6">
                        </div>

                        <div class="grid gap-4 content-start" :class="photoUrl ? 'md:col-span-3' : 'md:col-span-5 sm:grid-cols-2'">
                            <div v-for="[key, label, type] in scannedFields" :key="key">
                                <div class="flex items-center justify-between gap-2">
                                    <InputLabel :for="key" :value="label" />
                                    <span
                                        v-if="scanned[key]?.needs_review"
                                        class="text-[11px] font-semibold rounded-full px-2 py-0.5 bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200"
                                    >
                                        Check this · {{ confidenceLabel(scanned[key]) }}
                                    </span>
                                    <span v-else-if="scanned[key]?.value" class="text-[11px] text-gray-400">{{ confidenceLabel(scanned[key]) }}</span>
                                </div>
                                <TextInput
                                    :id="key"
                                    v-model="form[key]"
                                    :type="type"
                                    class="mt-1 block w-full"
                                    :class="scanned[key]?.needs_review ? '!border-amber-400 !ring-amber-300' : ''"
                                />
                                <InputError :message="form.errors[key]" class="mt-1" />
                            </div>
                        </div>
                    </section>

                    <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 space-y-4">
                        <h3 class="font-semibold text-gray-900">About this meeting</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="event" value="Where you met" />
                                <TextInput id="event" v-model="form.event" placeholder="e.g. Expo SMX, booth 214" class="mt-1 block w-full" />
                                <InputError :message="form.errors.event" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel for="met_at" value="Met on" />
                                <TextInput id="met_at" v-model="form.met_at" type="date" class="mt-1 block w-full" />
                                <InputError :message="form.errors.met_at" class="mt-1" />
                            </div>
                            <div v-if="cards.length" class="sm:col-span-2">
                                <InputLabel for="card_id" value="Which of your cards were you using?" />
                                <select id="card_id" v-model="form.card_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">None</option>
                                    <option v-for="card in cards" :key="card.id" :value="card.id">
                                        {{ card.full_name }}{{ card.job_title ? ` · ${card.job_title}` : '' }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.card_id" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <InputLabel for="note" value="Note (what you talked about)" />
                                <textarea id="note" v-model="form.note" rows="3" placeholder="e.g. Wants a quote for 3 branches. Decision by end of month." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <InputError :message="form.errors.note" class="mt-1" />
                            </div>
                        </div>
                        <p class="text-xs text-gray-500">MetMemo will remind you to follow up in 2 days.</p>
                    </section>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <SecondaryButton type="button" class="justify-center" @click="startOver">Scan another</SecondaryButton>
                        <PrimaryButton class="justify-center" :disabled="form.processing">Save contact</PrimaryButton>
                    </div>
                    <p class="text-center sm:text-end text-xs text-gray-500">
                        Or <Link :href="route('contacts.index')" class="text-indigo-600 hover:underline">go to your contacts</Link>
                    </p>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
