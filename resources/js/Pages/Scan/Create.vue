<script setup>
import { today as localToday } from '@/dates';
import { shrink } from '@/images';
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { metMemoCardId, parseContactQr } from '@/qrRead';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PhoneInput from '@/Components/PhoneInput.vue';
import QrScanner from '@/Components/QrScanner.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    cards: Array,
});

// What to scan: a paper business card (the AI reads it) or a QR code (read in the browser).
const mode = ref('card');

// A card: capture -> reading -> review. A QR: swap (a MetMemo card), review (a contact QR) or other.
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
    ['phone', 'Mobile', 'tel'],
    ['telephone', 'Telephone', 'tel'],
];

const today = localToday();

const form = useForm({
    full_name: '',
    job_title: '',
    company: '',
    email: '',
    phone: '',
    telephone: '',
    event: '',
    met_at: today,
    card_id: '',
    note: '',
});

const flaggedCount = computed(() => scannedFields.filter(([key]) => scanned.value[key]?.needs_review).length);

const onPhoto = async (event) => {
    const original = event.target.files[0];
    event.target.value = '';

    if (! original) return;

    // A 1600px JPEG keeps the card text readable for the AI.
    const photo = await shrink(original, 1600, 'card.jpg');
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

// QR codes: another MetMemo card -> swap; a contact QR -> the review form; anything else -> shown as is.
const qrCardId = ref(null);
const qrText = ref('');
const fromQr = ref(false);
const swap = useForm({ card_id: props.cards[0]?.id ?? null });

const onQr = (text) => {
    const cardId = metMemoCardId(text);
    const contact = cardId ? null : parseContactQr(text);

    if (cardId) {
        // Another MetMemo card: swap straight away, sharing the card picked above the scanner.
        qrCardId.value = cardId;
        step.value = 'swap';
        swapCards();
    } else if (contact) {
        Object.assign(form, contact);
        scanned.value = {};
        readError.value = null;
        photoUrl.value = null;
        fromQr.value = true;
        step.value = 'review';
    } else {
        qrText.value = text;
        step.value = 'other';
    }
};

const swapCards = () => {
    swap.post(route('public-card.swap', qrCardId.value));
};

const qrLink = computed(() => (/^https?:\/\//i.test(qrText.value.trim()) ? qrText.value.trim() : null));

const chooseMode = (value) => {
    mode.value = value;
    startOver();
};

const typeByHand = () => {
    readError.value = null;
    scanned.value = {};
    photoUrl.value = null;
    step.value = 'review';
};

const startOver = () => {
    form.reset();
    fromQr.value = false;
    qrCardId.value = null;
    qrText.value = '';
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
    <AppLayout title="Scan">
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ mode === 'qr' ? 'Scan a QR code' : 'Scan a business card' }}</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ mode === 'qr' ? 'Swap cards with another MetMemo member, or save a contact QR.' : 'The AI reads it, you check it, MetMemo remembers it.' }}
                    </p>
                </div>
                <button v-if="step === 'review'" type="button" class="hidden sm:inline text-sm text-indigo-600 hover:underline" @click="startOver">
                    Scan another
                </button>
            </div>
        </template>

        <div class="py-6 sm:py-10">
            <div class="page space-y-6">
                <!-- What to scan -->
                <div v-if="step === 'capture'" class="grid grid-cols-2 gap-1 rounded-xl bg-gray-100 p-1 text-sm font-medium" role="tablist" aria-label="What to scan">
                    <button
                        v-for="[value, label] in [['card', 'Business card'], ['qr', 'QR code']]"
                        :key="value"
                        type="button"
                        role="tab"
                        :aria-selected="mode === value"
                        class="rounded-lg px-3 py-2 transition"
                        :class="mode === value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                        @click="chooseMode(value)"
                    >
                        {{ label }}
                    </button>
                </div>

                <!-- Step indicator -->
                <ol v-if="mode === 'card'" class="flex items-center gap-2 text-xs font-medium" aria-label="Progress">
                    <li
                        v-for="(label, key) in { capture: '1. Photo', reading: '2. AI reads', review: '3. You review' }"
                        :key="key"
                        class="flex-1 rounded-full px-3 py-1.5 text-center"
                        :class="step === key ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500'"
                    >
                        {{ label }}
                    </li>
                </ol>

                <!-- QR: camera or a picture -->
                <div v-if="step === 'capture' && mode === 'qr'" class="grid gap-6 lg:grid-cols-5 items-start">
                    <section class="lg:col-span-3 bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 space-y-5">
                        <div v-if="cards.length > 1">
                            <InputLabel for="swap_card" value="Card to share when you swap" />
                            <select id="swap_card" v-model="swap.card_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="card in cards" :key="card.id" :value="card.id">
                                    {{ card.full_name }}{{ card.job_title ? ` · ${card.job_title}` : '' }}
                                </option>
                                <option :value="null">Just my name and email</option>
                            </select>
                        </div>
                        <QrScanner @decoded="onQr" />
                    </section>
                    <aside class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl p-6 sm:p-8">
                        <h2 class="font-semibold text-gray-900">What you can scan</h2>
                        <ul class="mt-4 space-y-4 text-sm">
                            <li>
                                <span class="block font-medium text-gray-900">Another MetMemo card</span>
                                <span class="block text-gray-500">You swap cards as soon as it's scanned: you're both added to each other's contacts.</span>
                            </li>
                            <li>
                                <span class="block font-medium text-gray-900">A contact QR (vCard)</span>
                                <span class="block text-gray-500">The details fill in for you to check and save.</span>
                            </li>
                        </ul>
                        <p class="mt-6 rounded-xl bg-gray-50 px-4 py-3 text-xs text-gray-500">QR codes are read on your device. Nothing is uploaded and no AI is used.</p>
                    </aside>
                </div>

                <!-- QR: another MetMemo card. The swap starts on scan; when it succeeds, their contact page opens. -->
                <section v-else-if="step === 'swap'" class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-10 flex flex-col items-center gap-4 text-center">
                    <span class="flex size-14 items-center justify-center rounded-full bg-accent text-accent-fg">
                        <svg class="size-7" :class="{ 'animate-pulse': swap.processing }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                    </span>
                    <template v-if="! swap.hasErrors">
                        <h3 class="font-semibold text-gray-900">Swapping cards…</h3>
                        <p class="text-sm text-gray-500">You'll both be in each other's contacts in a moment.</p>
                    </template>
                    <template v-else>
                        <h3 class="font-semibold text-gray-900">The swap didn't go through</h3>
                        <InputError :message="swap.errors.card_id ?? 'Please try again.'" />
                        <div class="flex flex-col-reverse sm:flex-row gap-3">
                            <SecondaryButton type="button" class="justify-center" @click="startOver">Scan another</SecondaryButton>
                            <PrimaryButton type="button" class="justify-center" :disabled="swap.processing" @click="swapCards">Try again</PrimaryButton>
                        </div>
                    </template>
                </section>

                <!-- QR: something else -->
                <section v-else-if="step === 'other'" class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 space-y-4">
                    <h3 class="font-semibold text-gray-900">This QR code isn't a MetMemo card or a contact</h3>
                    <p class="break-all rounded-xl bg-gray-50 px-4 py-3 font-mono text-sm text-gray-700">{{ qrText }}</p>
                    <div class="flex flex-col-reverse sm:flex-row gap-3">
                        <SecondaryButton type="button" class="justify-center" @click="startOver">Scan another</SecondaryButton>
                        <a v-if="qrLink" :href="qrLink" target="_blank" rel="noopener noreferrer nofollow" class="btn-quiet h-10 justify-center px-4 text-sm">Open link</a>
                    </div>
                    <p v-if="qrLink" class="text-xs text-gray-500">It opens another website. Only open links you trust.</p>
                </section>

                <!-- 1. Capture: the photo action leads; on wide screens the how-it-works panel sits beside it. -->
                <div v-else-if="step === 'capture'" class="grid gap-6 lg:grid-cols-5 items-start">
                <section class="lg:col-span-3 bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 space-y-6">
                    <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="hidden" @change="onPhoto">
                    <input ref="galleryInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onPhoto">

                    <button
                        type="button"
                        class="w-full aspect-[16/10] max-h-80 rounded-2xl border-2 border-dashed border-gray-300 hover:border-indigo-500 hover:bg-indigo-50/40 transition flex flex-col items-center justify-center gap-3 text-gray-500"
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

                    <ul class="grid gap-2 text-sm text-gray-500 lg:hidden">
                        <li class="flex gap-2"><span class="text-indigo-500">•</span> Lay the card flat in good light and fill the frame.</li>
                        <li class="flex gap-2"><span class="text-indigo-500">•</span> Nothing is saved until you review it. The photo itself is never stored.</li>
                    </ul>
                </section>

                <aside class="hidden lg:block lg:col-span-2 bg-white border border-gray-200 rounded-2xl p-6 sm:p-8">
                    <h2 class="font-semibold text-gray-900">How it works</h2>
                    <ol class="mt-5 space-y-5">
                        <li v-for="(item, index) in [
                            ['Take or upload a photo', 'Lay the card flat in good light and fill the frame.'],
                            ['The AI reads it', 'Name, title, company, email, mobile and telephone, in a few seconds.'],
                            ['You check and save', 'Fields the AI is unsure about are highlighted. Nothing is saved until you confirm.'],
                        ]" :key="index" class="flex gap-4">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-600">{{ index + 1 }}</span>
                            <span>
                                <span class="block text-sm font-medium text-gray-900">{{ item[0] }}</span>
                                <span class="mt-0.5 block text-sm text-gray-500">{{ item[1] }}</span>
                            </span>
                        </li>
                    </ol>
                    <p class="mt-6 rounded-xl bg-gray-50 px-4 py-3 text-xs text-gray-500">The photo is only used to read the card and is never stored.</p>
                </aside>
                </div>

                <!-- 2. Reading -->
                <section v-else-if="step === 'reading'" class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-10 flex flex-col items-center gap-5 text-center">
                    <img v-if="photoUrl" :src="photoUrl" alt="Your card photo" class="max-h-80 rounded-xl border border-gray-200 object-contain">
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
                    <div v-if="fromQr" class="rounded-xl bg-indigo-50 text-indigo-600 px-4 py-3 text-sm font-medium">
                        Read from a contact QR code. Give it a quick look and save.
                    </div>
                    <div v-else-if="readError" class="rounded-xl border border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200 dark:border-amber-800 px-4 py-3 text-sm">
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
                                <PhoneInput
                                    v-if="type === 'tel'"
                                    :id="key"
                                    v-model="form[key]"
                                    :kind="key === 'telephone' ? 'telephone' : 'mobile'"
                                    icon
                                    class="mt-1"
                                    :field-class="`border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm ${scanned[key]?.needs_review ? '!border-amber-400 !ring-amber-300' : ''}`"
                                />
                                <TextInput
                                    v-else
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
