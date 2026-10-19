<script setup>
import ThemeToggle from '@/Components/ThemeToggle.vue';
</script>

<template>
    <div class="relative min-h-screen flex flex-col justify-center items-center px-4 py-10 bg-page overflow-hidden">
        <div class="absolute inset-0 bg-grid pointer-events-none" aria-hidden="true" />
        <ThemeToggle class="absolute top-4 right-4" />

        <div class="relative">
            <slot name="logo" />
        </div>

        <div class="relative w-full max-w-md mt-8 px-6 py-7 sm:px-8 bg-white border border-gray-200 rounded-2xl shadow-sm">
            <slot />
        </div>
    </div>
</template>
