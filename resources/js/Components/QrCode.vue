<script setup>
import { ref, watchEffect } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    value: { type: String, required: true },
    size: { type: Number, default: 160 },
});

const dataUrl = ref(null);

watchEffect(async () => {
    dataUrl.value = await QRCode.toDataURL(props.value, { width: props.size, margin: 1 });
});
</script>

<template>
    <!-- Always black on white so phones can scan it in either theme. -->
    <img v-if="dataUrl" :src="dataUrl" :width="size" :height="size" class="rounded-lg" alt="QR code for this card">
</template>
