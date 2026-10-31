<script setup>
import { computed, ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { shrink } from '@/images';
import AppLayout from '@/Layouts/AppLayout.vue';
import CardPreview from '@/Components/CardPreview.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    card: Object,
    accountPhotoUrl: String,
});

// The form's sections, as in the design: who you are, then how people reach you.
const sections = [
    {
        title: 'Who you are',
        fields: [
            { key: 'full_name', label: 'Full name', placeholder: 'e.g. Maria Santos', wide: true, required: true },
            { key: 'job_title', label: 'Job title', placeholder: 'e.g. Licensed Real Estate Broker' },
            { key: 'company', label: 'Company', placeholder: 'e.g. Santos Realty' },
        ],
    },
    {
        title: 'How people reach you',
        fields: [
            { key: 'email', label: 'Email', placeholder: 'name@company.com', type: 'email' },
            { key: 'phone', label: 'Phone', placeholder: '+63 917 …', type: 'tel' },
            { key: 'website', label: 'Website', placeholder: 'santosrealty.ph' },
            { key: 'location', label: 'Location', placeholder: 'e.g. BGC, Taguig' },
        ],
    },
];

const BIO_MAX = 2000; // CardRequest's limit

const form = useForm({
    full_name: props.card?.full_name ?? '',
    job_title: props.card?.job_title ?? '',
    company: props.card?.company ?? '',
    bio: props.card?.bio ?? '',
    email: props.card?.email ?? '',
    phone: props.card?.phone ?? '',
    website: props.card?.website ?? '',
    location: props.card?.location ?? '',
    is_public: props.card?.is_public ?? true,
    photo: null,
    remove_photo: false,
});

// The card's own photo (saved or just picked). Without one, visitors see the account photo, then initials.
const photoInput = ref(null);
const cardPhotoUrl = ref(props.card?.photo_url ?? null);
const previewUrl = computed(() => cardPhotoUrl.value ?? props.accountPhotoUrl);

const pickPhoto = async (event) => {
    const original = event.target.files[0];
    event.target.value = '';

    if (! original) return;

    // A profile photo is shown small, so 800px is plenty and stays well under the 2 MB limit.
    form.photo = await shrink(original, 800, 'card-photo.jpg');
    form.remove_photo = false;
    cardPhotoUrl.value = URL.createObjectURL(form.photo);
};

const removePhoto = () => {
    form.photo = null;
    form.remove_photo = true;
    cardPhotoUrl.value = null;
};

const submit = () => {
    if (props.card) {
        // Browsers can't send files with PUT, so post with Laravel's method override.
        form.transform((data) => ({ ...data, _method: 'put' })).post(route('cards.update', props.card.id));
    } else {
        form.post(route('cards.store'));
    }
};
</script>

<template>
    <AppLayout :title="card ? 'Edit Card' : 'New Card'">
        <div class="py-6 sm:py-8">
            <div class="page max-w-[75rem] space-y-6">
                <section class="space-y-2.5">
                    <Link :href="route('cards.index')" class="inline-flex items-center gap-1.5 text-[13px] font-medium text-gray-500 hover:text-gray-800">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                        My Cards
                    </Link>
                    <h1 class="text-[26px] sm:text-[30px] leading-tight font-bold tracking-[-0.025em] text-gray-900">{{ card ? 'Edit card' : 'New card' }}</h1>
                    <p class="text-[15px] text-gray-500">
                        This is what people see after they scan your QR code.<template v-if="! card"> Your QR code is made when you save.</template>
                    </p>
                </section>

                <div class="flex flex-wrap items-start gap-6">
                    <form class="min-w-0 flex-[3_1_520px] overflow-hidden rounded-2xl border border-gray-200 bg-white" @submit.prevent="submit">
                        <!-- Photo -->
                        <fieldset class="space-y-[18px] p-5 sm:p-6">
                            <legend class="float-left mb-1 text-[15px] font-semibold text-gray-900">Photo</legend>
                            <div class="clear-both flex flex-wrap items-center gap-[18px]">
                                <img v-if="previewUrl" :src="previewUrl" alt="Card photo" class="size-[76px] shrink-0 rounded-full object-cover">
                                <div v-else class="flex size-[76px] shrink-0 items-center justify-center rounded-full border-[1.5px] border-dashed border-gray-300 bg-gray-100 text-gray-400" aria-hidden="true">
                                    <svg class="size-[26px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                </div>
                                <div class="min-w-0 space-y-2">
                                    <div class="flex flex-wrap gap-2">
                                        <input ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="pickPhoto">
                                        <button type="button" class="btn-quiet h-[38px] gap-[7px] px-3.5" @click="photoInput.click()">
                                            <svg class="size-[15px]" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                                            {{ cardPhotoUrl ? 'Change photo' : 'Upload photo' }}
                                        </button>
                                        <button v-if="cardPhotoUrl" type="button" class="btn-quiet h-[38px] px-3.5" @click="removePhoto">Remove</button>
                                    </div>
                                    <p class="text-xs text-gray-400">
                                        <template v-if="cardPhotoUrl">Only this card uses this photo.</template>
                                        <template v-else-if="accountPhotoUrl">Using your account photo. Upload one to use a different photo on this card.</template>
                                        <template v-else>Square JPG or PNG, at least 400 px. Without one, your initials are shown.</template>
                                    </p>
                                    <InputError :message="form.errors.photo" />
                                </div>
                            </div>
                        </fieldset>

                        <!-- Who you are / How people reach you -->
                        <fieldset v-for="section in sections" :key="section.title" class="space-y-[18px] border-t border-gray-200 p-5 sm:p-6">
                            <legend class="float-left mb-1 text-[15px] font-semibold text-gray-900">{{ section.title }}</legend>
                            <div class="clear-both grid gap-4 grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))]">
                                <label v-for="field in section.fields" :key="field.key" class="flex flex-col gap-[7px] text-[13px] font-medium text-gray-600" :class="{ 'col-span-full': field.wide }">
                                    {{ field.label }}
                                    <input
                                        v-model="form[field.key]"
                                        :type="field.type ?? 'text'"
                                        :placeholder="field.placeholder"
                                        :required="field.required"
                                        class="field font-normal"
                                    >
                                    <InputError :message="form.errors[field.key]" />
                                </label>
                            </div>
                        </fieldset>

                        <!-- About you -->
                        <fieldset class="space-y-[18px] border-t border-gray-200 p-5 sm:p-6">
                            <legend class="float-left mb-1 text-[15px] font-semibold text-gray-900">About you</legend>
                            <label class="clear-both flex flex-col gap-[7px] text-[13px] font-medium text-gray-600">
                                Short bio
                                <textarea
                                    v-model="form.bio"
                                    rows="4"
                                    :maxlength="BIO_MAX"
                                    placeholder="One or two lines on what you do and who you help."
                                    class="field h-auto resize-y py-3 font-normal leading-normal"
                                />
                                <span class="self-end text-xs font-normal text-gray-400 tabular-nums">{{ form.bio.length }} / {{ BIO_MAX }}</span>
                                <InputError :message="form.errors.bio" />
                            </label>
                        </fieldset>

                        <!-- Visibility -->
                        <fieldset class="border-t border-gray-200 p-5 sm:p-6">
                            <legend class="sr-only">Visibility</legend>
                            <div class="flex items-start gap-4 rounded-xl border border-gray-200 bg-gray-100 p-4">
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.is_public"
                                    aria-label="Card is public"
                                    class="flex h-[26px] w-11 shrink-0 rounded-full p-[3px] transition"
                                    :class="form.is_public ? 'justify-end bg-accent' : 'justify-start bg-gray-300'"
                                    @click="form.is_public = ! form.is_public"
                                >
                                    <span class="size-5 rounded-full transition" :class="form.is_public ? 'bg-accent-fg' : 'bg-white'" />
                                </button>
                                <div class="space-y-1">
                                    <p class="text-sm font-semibold text-gray-900">Card is {{ form.is_public ? 'public' : 'hidden' }}</p>
                                    <p class="text-[13px] leading-normal text-gray-500">Turn this off to pause the link and QR code. They start working again when you turn it back on.</p>
                                </div>
                            </div>
                        </fieldset>

                        <div class="flex flex-wrap items-center justify-end gap-2.5 border-t border-gray-200 bg-gray-100/60 px-5 py-4 sm:px-6">
                            <Link :href="route('cards.index')" class="btn-quiet h-[42px] px-4 text-sm">Cancel</Link>
                            <button type="submit" class="btn-accent h-[42px] px-5 py-0" :disabled="form.processing">Save card</button>
                        </div>
                    </form>

                    <!-- Live preview -->
                    <aside class="hidden min-w-0 flex-[2_1_320px] flex-col gap-3 lg:flex lg:sticky lg:top-24">
                        <div class="flex items-center justify-between">
                            <p class="eyebrow">Live preview</p>
                            <p class="text-xs text-gray-400">What people see after scanning</p>
                        </div>
                        <CardPreview :card="form" :photo-url="previewUrl" />
                    </aside>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
