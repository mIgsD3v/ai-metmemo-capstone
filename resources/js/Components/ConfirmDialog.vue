<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { answer, dialog } from '@/confirm';

const cancelButton = ref(null);
const confirmButton = ref(null);

// Focus "Cancel" first so a stray Enter never deletes anything (info-only dialogs focus their one button);
// lock page scroll while open.
watch(() => dialog.open, async (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
        await nextTick();
        (cancelButton.value ?? confirmButton.value)?.focus();
    }
});

const onKey = (event) => {
    if (dialog.open && event.key === 'Escape') answer(false);
};
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0"
    >
        <div v-if="dialog.open" class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center p-4 pb-[calc(1rem+env(safe-area-inset-bottom,0px))]">
            <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" aria-hidden="true" @click="answer(false)" />

            <div
                class="relative w-full max-w-md rounded-2xl bg-white border border-gray-200 shadow-2xl p-6 animate-[dialog-in_.2s_ease-out]"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="confirm-title"
                aria-describedby="confirm-message"
            >
                <div class="flex gap-4">
                    <span
                        class="flex items-center justify-center size-11 shrink-0 rounded-full"
                        :class="dialog.danger ? 'bg-red-100 text-red-600 dark:bg-red-950/60 dark:text-red-400' : 'bg-indigo-50 text-indigo-600'"
                    >
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path
                                v-if="dialog.danger"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                            />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                        </svg>
                    </span>
                    <div class="min-w-0 pt-0.5">
                        <h2 id="confirm-title" class="text-lg font-semibold text-gray-900">{{ dialog.title }}</h2>
                        <p v-if="dialog.message" id="confirm-message" class="mt-1.5 text-sm leading-relaxed text-gray-600">{{ dialog.message }}</p>
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                    <button
                        v-if="dialog.cancelText"
                        ref="cancelButton"
                        type="button"
                        class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white transition"
                        @click="answer(false)"
                    >
                        {{ dialog.cancelText }}
                    </button>
                    <button
                        ref="confirmButton"
                        type="button"
                        class="inline-flex justify-center rounded-md px-4 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white transition"
                        :class="dialog.danger
                            ? 'bg-red-600 text-[#fff] hover:bg-red-500 focus:ring-red-500'
                            : 'bg-accent text-accent-fg hover:opacity-90 focus:ring-indigo-500'"
                        @click="answer(true)"
                    >
                        {{ dialog.confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<style>
@keyframes dialog-in {
    from { transform: translateY(8px) scale(0.98); }
    to { transform: none; }
}
@media (prefers-reduced-motion: reduce) {
    [class*="animate-[dialog-in"] { animation: none; }
}
</style>
