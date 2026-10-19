<script setup>
import { Link } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
</script>

<template>
    <Link :href="'/'">
        <ApplicationMark class="text-4xl" />
    </Link>
</template>
