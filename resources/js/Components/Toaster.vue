<script setup>
import { dismiss, toasts } from '@/toast';

const icons = {
    success: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    danger: 'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z',
    info: 'm11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
};
</script>

<template>
    <!-- Phones: top center, clear of the bottom tab bar. Larger screens: bottom right. -->
    <div
        class="pointer-events-none fixed inset-x-0 top-0 z-[60] flex flex-col items-center gap-2 px-4 pt-[calc(4rem+env(safe-area-inset-top,0px))] sm:inset-x-auto sm:top-auto sm:bottom-0 sm:end-0 sm:items-end sm:px-6 sm:pt-0 sm:pb-6"
        aria-live="polite"
        role="status"
    >
        <transition-group
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2 sm:translate-y-2"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-for="item in toasts"
                :key="item.id"
                class="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-xl bg-gray-900 px-4 py-3 text-sm text-white shadow-lg ring-1 ring-black/5"
            >
                <svg
                    class="size-5 shrink-0"
                    :class="{ 'text-indigo-400': item.style === 'success', 'text-red-400': item.style === 'danger', 'text-gray-400': item.style === 'info' }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.8"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.style] ?? icons.info" />
                </svg>
                <p class="flex-1 font-medium">{{ item.message }}</p>
                <button type="button" class="-me-1 rounded-md p-1 text-gray-400 hover:text-white" aria-label="Dismiss" @click="dismiss(item.id)">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </transition-group>
    </div>
</template>
