<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    card: Object,
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
});

const submit = () => {
    if (props.card) {
        form.put(route('cards.update', props.card.id));
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
                    <p class="text-sm text-gray-500 mt-1">This is what people see when they scan your QR code. Your profile photo is shown too.</p>
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
