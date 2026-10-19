<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-md font-semibold text-sm text-accent-fg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-page disabled:opacity-50 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
