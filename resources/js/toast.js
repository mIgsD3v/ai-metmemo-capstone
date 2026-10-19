import { reactive } from 'vue';

// One shared list of toasts for the whole app. Rendered by Components/Toaster.vue.
export const toasts = reactive([]);

let nextId = 1;

export function dismiss(id) {
    const index = toasts.findIndex((item) => item.id === id);
    if (index !== -1) toasts.splice(index, 1);
}

/**
 * Show a short message that hides itself.
 * @param {string} message
 * @param {'success'|'danger'|'info'} style
 */
export function toast(message, style = 'success', timeout = 4000) {
    if (! message) return;

    // Same message already showing: restart it instead of stacking a duplicate.
    const existing = toasts.find((item) => item.message === message);
    if (existing) dismiss(existing.id);

    const id = nextId++;
    toasts.push({ id, message, style });
    if (toasts.length > 3) toasts.shift();
    setTimeout(() => dismiss(id), timeout);
}

/** Show the flash message Laravel sends back with a redirect, if any. */
export function toastFlash(page) {
    const flash = page?.props?.jetstream?.flash;
    if (flash?.banner) toast(flash.banner, flash.bannerStyle === 'danger' ? 'danger' : 'success');
}
