<script setup>
// "Continue with Google" for the login and register pages. A plain link, not an Inertia visit: the browser
// leaves for Google's consent screen and comes back to /auth/google/callback.
defineProps({
    label: { type: String, default: 'Continue with Google' },
});
</script>

<template>
    <template v-if="$page.props.googleLogin">
        <div class="my-5 flex items-center gap-3 text-xs text-gray-400" aria-hidden="true">
            <span class="h-px flex-1 bg-gray-200" />
            or
            <span class="h-px flex-1 bg-gray-200" />
        </div>

        <a
            :href="route('auth.google')"
            class="flex h-11 w-full items-center justify-center gap-3 rounded-[10px] border border-gray-300 bg-white text-sm font-semibold text-gray-800 transition hover:bg-gray-50 active:translate-y-px"
        >
            <!-- Google's "G" mark, in its brand colours. -->
            <svg class="size-[18px]" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z" />
                <path fill="#FF3D00" d="m6.306 14.691 6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z" />
                <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0 1 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z" />
                <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z" />
            </svg>
            {{ label }}
        </a>

        <!-- Google sign-up skips the form's terms checkbox, so the agreement is stated here. -->
        <p v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="mt-3 text-center text-xs text-gray-400">
            By continuing, you agree to the
            <a :href="route('terms.show')" target="_blank" class="underline hover:text-gray-700">Terms of Service</a>
            and
            <a :href="route('policy.show')" target="_blank" class="underline hover:text-gray-700">Privacy Policy</a>.
        </p>
    </template>
</template>
