<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue';
import jsQR from 'jsqr';

// Reads a QR code from the live camera or from an uploaded picture, in the browser, and emits its text.
const emit = defineEmits(['decoded']);

const video = ref(null);
const fileInput = ref(null);
const scanning = ref(false);
const error = ref(null);
const canvas = document.createElement('canvas');
const context = canvas.getContext('2d', { willReadFrequently: true });
let stream = null;

// Draws the source at most `max` pixels wide and looks for a QR code in it.
const decode = (source, width, height, max, inversion) => {
    const scale = Math.min(1, max / Math.max(width, height));
    canvas.width = Math.round(width * scale);
    canvas.height = Math.round(height * scale);
    context.drawImage(source, 0, 0, canvas.width, canvas.height);

    return jsQR(context.getImageData(0, 0, canvas.width, canvas.height).data, canvas.width, canvas.height, { inversionAttempts: inversion })?.data;
};

const stop = () => {
    scanning.value = false;
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
};

const found = (text) => {
    stop();
    navigator.vibrate?.(60);
    emit('decoded', text);
};

const tick = () => {
    if (! scanning.value) return;

    const v = video.value;
    if (v?.readyState >= v?.HAVE_ENOUGH_DATA) {
        const text = decode(v, v.videoWidth, v.videoHeight, 720, 'dontInvert');
        if (text) return found(text);
    }

    requestAnimationFrame(tick);
};

const startCamera = async () => {
    error.value = null;

    if (! navigator.mediaDevices?.getUserMedia) {
        error.value = 'This browser can\'t open the camera here. Upload a picture of the QR code instead.';
        return;
    }

    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
        scanning.value = true;
        await nextTick();
        video.value.srcObject = stream;
        await video.value.play();
        requestAnimationFrame(tick);
    } catch (e) {
        stop();
        error.value = e?.name === 'NotAllowedError'
            ? 'Camera access was blocked. Allow it in your browser settings, or upload a picture of the QR code.'
            : 'The camera could not start. Upload a picture of the QR code instead.';
    }
};

const onPicture = async (event) => {
    const file = event.target.files[0];
    event.target.value = '';
    if (! file) return;

    error.value = null;
    stop();

    try {
        const image = await createImageBitmap(file);
        const text = decode(image, image.width, image.height, 1600, 'attemptBoth');
        image.close();

        if (text) return found(text);
        error.value = 'No QR code found in that picture. Try a closer, sharper one with the whole code in view.';
    } catch {
        error.value = 'That picture could not be opened. Try a JPG or PNG.';
    }
};

onBeforeUnmount(stop);
</script>

<template>
    <div class="space-y-4">
        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onPicture">

        <div v-if="scanning" class="relative mx-auto aspect-square w-full max-w-sm overflow-hidden rounded-2xl bg-black">
            <video ref="video" class="size-full object-cover" playsinline muted />
            <!-- Aiming frame -->
            <div class="pointer-events-none absolute inset-[15%] rounded-2xl border-4 border-white/90 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]" />
            <p class="absolute inset-x-0 bottom-3 text-center text-sm font-medium text-white drop-shadow">Point at the QR code</p>
        </div>

        <button
            v-else
            type="button"
            class="w-full aspect-[16/10] max-h-80 rounded-2xl border-2 border-dashed border-gray-300 hover:border-indigo-500 hover:bg-indigo-50/40 transition flex flex-col items-center justify-center gap-3 text-gray-500"
            @click="startCamera"
        >
            <span class="flex items-center justify-center size-16 rounded-full bg-accent text-accent-fg">
                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z" />
                </svg>
            </span>
            <span class="text-base font-semibold text-gray-900">Scan a QR code with your camera</span>
            <span class="text-sm">Another MetMemo card, or a contact QR</span>
        </button>

        <div v-if="error" class="rounded-xl border border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200 dark:border-amber-800 px-4 py-3 text-sm">
            {{ error }}
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <button v-if="scanning" type="button" class="btn-quiet h-11 sm:flex-1 justify-center text-sm" @click="stop">Stop camera</button>
            <button type="button" class="btn-quiet h-11 sm:flex-1 justify-center text-sm" @click="fileInput.click()">Upload a picture of a QR code</button>
        </div>
    </div>
</template>
