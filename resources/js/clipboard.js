import { toast } from '@/toast';

// The Clipboard API only exists on https and localhost. Over plain http (e.g. testing on a phone
// at http://192.168.x.x) fall back to a hidden textarea + execCommand('copy'), which still works there.
function legacyCopy(text) {
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    area.setSelectionRange(0, text.length);

    let copied = false;
    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }

    document.body.removeChild(area);
    return copied;
}

/** Copy text and say so in a toast. Returns true when it was copied. */
export async function copyText(text, message = 'Link copied') {
    let copied = false;

    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            copied = true;
        } catch {
            copied = false;
        }
    }

    if (! copied) copied = legacyCopy(text);

    toast(copied ? message : 'Could not copy. Press and hold the link to copy it.', copied ? 'success' : 'danger');
    return copied;
}
