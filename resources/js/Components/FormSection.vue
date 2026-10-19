<script setup>
import { computed, useSlots } from 'vue';

defineEmits(['submitted']);

const hasActions = computed(() => !! useSlots().actions);
</script>

<template>
    <section class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
        <form @submit.prevent="$emit('submitted')">
            <header class="px-5 sm:px-6 pt-5 sm:pt-6">
                <h3 class="text-base font-semibold text-gray-900">
                    <slot name="title" />
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    <slot name="description" />
                </p>
            </header>

            <div class="px-5 sm:px-6 py-5 sm:py-6">
                <div class="grid grid-cols-6 gap-5">
                    <slot name="form" />
                </div>
            </div>

            <footer v-if="hasActions" class="flex items-center justify-end gap-3 px-5 sm:px-6 py-3 bg-gray-50 border-t border-gray-200">
                <slot name="actions" />
            </footer>
        </form>
    </section>
</template>
