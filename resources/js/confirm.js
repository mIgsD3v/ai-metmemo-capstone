import { reactive } from 'vue';

// One shared confirmation dialog for the app (rendered by Components/ConfirmDialog.vue),
// used instead of the browser's own confirm() box.
export const dialog = reactive({
    open: false,
    title: '',
    message: '',
    confirmText: 'Delete',
    cancelText: 'Cancel',
    danger: true,
    resolve: null,
});

/**
 * Ask the user to confirm. Resolves true for yes, false for no.
 * @example if (await ask({ title: 'Delete this card?', message: '...' })) { ... }
 */
export function ask({ title, message = '', confirmText = 'Delete', cancelText = 'Cancel', danger = true }) {
    // A dialog already open answers "no" before the new one replaces it.
    dialog.resolve?.(false);

    return new Promise((resolve) => {
        Object.assign(dialog, { open: true, title, message, confirmText, cancelText, danger, resolve });
    });
}

export function answer(value) {
    const resolve = dialog.resolve;
    dialog.open = false;
    dialog.resolve = null;
    resolve?.(value);
}
