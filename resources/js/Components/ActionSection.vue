<template>
    <section class="bg-white border border-gray-200 rounded-2xl">
        <header class="px-5 sm:px-6 pt-5 sm:pt-6">
            <h3 class="text-base font-semibold text-gray-900">
                <slot name="title" />
            </h3>
            <p class="mt-1 text-sm text-gray-500">
                <slot name="description" />
            </p>
        </header>

        <div class="px-5 sm:px-6 py-5 sm:py-6">
            <slot name="content" />
        </div>
    </section>
</template>
