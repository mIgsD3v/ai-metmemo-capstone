<script setup>
import { computed, ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { shrink } from '@/images';
import AppLayout from '@/Layouts/AppLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    card: Object,
    accountPhotoUrl: String,
});

const fields = [
    ['full_name', 'Full name'],
    ['job_title', 'Job title'],
    ['company', 'Company'],
    ['email', 'Email'],
    ['phone', 'Phone'],
    ['website', 'Website'],
    ['location', 'Location'],
];

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
const initials = computed(() => form.full_name.split(/\s+/).filter(Boolean).slice(0, 2).map((word) => word[0].toUpperCase()).join('') || '?');

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
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ card ? 'Edit Card' : 'New Card' }}</h2>
        </template>

        <form class="py-6 sm:py-8 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6" @submit.prevent="submit">
            <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 space-y-4">
                <div>
                    <h3 class="font-semibold text-gray-900">Card details</h3>
                    <p class="text-sm text-gray-500 mt-1">This is what people see when they scan your QR code.</p>
                </div>
                <div class="flex items-center gap-4">
                    <img v-if="previewUrl" :src="previewUrl" alt="Card photo" class="size-20 shrink-0 rounded-full object-cover border border-gray-200">
                    <div v-else class="size-20 shrink-0 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl font-semibold select-none" aria-hidden="true">{{ initials }}</div>
                    <div class="min-w-0 space-y-2">
                        <p class="text-sm font-medium text-gray-900">Card photo</p>
                        <p class="text-xs text-gray-500">
                            <template v-if="cardPhotoUrl">Only this card uses this photo.</template>
                            <template v-else-if="accountPhotoUrl">Using your account photo. Upload one to use a different photo on this card.</template>
                            <template v-else>No photo yet, so your initials are shown.</template>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <input ref="photoInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="pickPhoto">
                            <SecondaryButton type="button" @click="photoInput.click()">{{ cardPhotoUrl ? 'Change photo' : 'Upload photo' }}</SecondaryButton>
                            <SecondaryButton v-if="cardPhotoUrl" type="button" @click="removePhoto">Remove</SecondaryButton>
                        </div>
                        <InputError :message="form.errors.photo" />
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div v-for="[key, label] in fields" :key="key">
                        <InputLabel :for="key" :value="label" />
                        <TextInput :id="key" v-model="form[key]" type="text" class="mt-1 block w-full" />
                        <InputError :message="form.errors[key]" class="mt-1" />
                    </div>
                </div>
                <div>
                    <InputLabel for="bio" value="Bio" />
                    <textarea id="bio" v-model="form.bio" rows="3" class="mt-1 block w-full rounded-lg shadow-sm" />
                    <InputError :message="form.errors.bio" class="mt-1" />
                </div>
                <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-4 cursor-pointer">
                    <Checkbox v-model:checked="form.is_public" class="mt-0.5" />
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Card is public</span>
                        <span class="block text-sm text-gray-500">Turn this off to stop the link and QR code from working until you turn it back on.</span>
                    </span>
                </label>
            </section>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end sm:items-center gap-3">
                <Link :href="route('cards.index')" class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Cancel</Link>
                <PrimaryButton class="justify-center" :disabled="form.processing">Save card</PrimaryButton>
            </div>
        </form>
    </AppLayout>
</template>
