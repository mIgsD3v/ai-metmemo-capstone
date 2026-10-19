<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { ask } from '@/confirm';

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);
const selectedPhoto = ref(null);

// Phone photos are often several MB, over PHP's upload limit; shrink to a 512px JPEG first.
const resizePhoto = (file, maxSize = 512) => new Promise((resolve) => {
    const url = URL.createObjectURL(file);
    const img = new Image();

    img.onload = () => {
        const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.width * scale);
        canvas.height = Math.round(img.height * scale);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);

        canvas.toBlob((blob) => {
            resolve(blob ? new File([blob], 'photo.jpg', { type: 'image/jpeg' }) : file);
        }, 'image/jpeg', 0.85);
    };

    // Formats the browser can't decode go up as-is and the server validates them.
    img.onerror = () => {
        URL.revokeObjectURL(url);
        resolve(file);
    };

    img.src = url;
});

const updateProfileInformation = () => {
    if (selectedPhoto.value) {
        form.photo = selectedPhoto.value;
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = async () => {
    const original = photoInput.value.files[0];

    if (! original) return;

    const photo = await resizePhoto(original);
    selectedPhoto.value = photo;

    const reader = new FileReader();

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = async () => {
    const yes = await ask({
        title: 'Remove your photo?',
        message: 'Your initials will show instead, here and on your public card.',
        confirmText: 'Remove photo',
    });

    if (! yes) return;

    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    selectedPhoto.value = null;
    form.photo = null;

    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <FormSection @submitted="updateProfileInformation">
        <template #title>
            Personal information
        </template>

        <template #description>
            Your photo also appears on your public card.
        </template>

        <template #form>
            <!-- Profile Photo -->
            <div v-if="$page.props.jetstream.managesProfilePhotos" class="col-span-6">
                <input
                    id="photo"
                    ref="photoInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    @change="updatePhotoPreview"
                >

                <div class="flex items-center gap-5">
                    <img v-show="! photoPreview" :src="user.profile_photo_url" :alt="user.name" class="size-20 shrink-0 rounded-full object-cover ring-1 ring-gray-200">
                    <span
                        v-show="photoPreview"
                        class="block size-20 shrink-0 rounded-full bg-cover bg-no-repeat bg-center ring-1 ring-gray-200"
                        :style="'background-image: url(\'' + photoPreview + '\');'"
                    />

                    <div class="space-y-2">
                        <div class="flex flex-wrap gap-2">
                            <SecondaryButton type="button" @click.prevent="selectNewPhoto">
                                {{ user.profile_photo_path || photoPreview ? 'Change photo' : 'Upload photo' }}
                            </SecondaryButton>
                            <button
                                v-if="user.profile_photo_path"
                                type="button"
                                class="px-3 py-2 text-sm font-medium text-red-600 hover:underline"
                                @click.prevent="deletePhoto"
                            >
                                Remove
                            </button>
                        </div>
                        <p class="text-xs text-gray-500">JPG, PNG or WebP. Large photos are resized for you.</p>
                        <p v-show="photoPreview" class="text-xs font-medium text-indigo-600">New photo selected. Save to apply it.</p>
                    </div>
                </div>

                <InputError :message="form.errors.photo" class="mt-2" />
            </div>

            <!-- Name -->
            <div class="col-span-6 sm:col-span-3">
                <InputLabel for="name" value="Name" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autocomplete="name"
                />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <!-- Email -->
            <div class="col-span-6 sm:col-span-3">
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autocomplete="username"
                />
                <InputError :message="form.errors.email" class="mt-2" />

                <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null">
                    <p class="text-sm mt-2">
                        Your email address is unverified.

                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            @click.prevent="sendEmailVerification"
                        >
                            Click here to re-send the verification email.
                        </Link>
                    </p>

                    <div v-show="verificationLinkSent" class="mt-2 font-medium text-sm text-green-600">
                        A new verification link has been sent to your email address.
                    </div>
                </div>
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                Saved.
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Save
            </PrimaryButton>
        </template>
    </FormSection>
</template>
