// What a scanned QR code holds: another MetMemo card, someone's contact details, or anything else.
// Read in the browser: no upload and no AI call.

/** The card's id when the QR is a MetMemo card link (this site's /c/{id}), otherwise null. */
export function metMemoCardId(text, origin = window.location.origin) {
    try {
        const url = new URL(text.trim());
        if (url.origin !== origin) return null;

        return url.pathname.match(/^\/c\/([0-9a-f-]{36})\/?$/i)?.[1] ?? null;
    } catch {
        return null;
    }
}

/**
 * Contact details from a vCard or MeCard QR code (what most "contact QR" generators make), as the scan
 * form's fields. Null when the QR is neither, or has no name.
 */
export function parseContactQr(text) {
    const value = text.trim();

    if (/^BEGIN:VCARD/i.test(value)) return fromVCard(value);
    if (/^MECARD:/i.test(value)) return fromMeCard(value);

    return null;
}

const empty = () => ({ full_name: '', job_title: '', company: '', email: '', phone: '', telephone: '' });

function fromVCard(text) {
    const fields = empty();
    const numbers = [];
    let structuredName = '';

    // Long lines continue on the next one, starting with a space or tab.
    for (const line of text.replace(/\r?\n[ \t]/g, '').split(/\r?\n/)) {
        const colon = line.indexOf(':');
        if (colon < 0) continue;

        // "item1.TEL;TYPE=CELL" -> name TEL, params "TYPE=CELL".
        const [name, ...params] = line.slice(0, colon).replace(/^[^.;]*\./, '').split(';');
        const raw = line.slice(colon + 1);
        const types = params.join(';').toUpperCase();

        switch (name.toUpperCase()) {
            case 'FN': fields.full_name ||= unescape(raw); break;
            case 'N': structuredName = nameFromParts(splitEscaped(raw, ';')); break;
            case 'ORG': fields.company ||= unescape(splitEscaped(raw, ';')[0] ?? ''); break;
            case 'TITLE': fields.job_title ||= unescape(raw); break;
            case 'EMAIL': fields.email ||= unescape(raw); break;
            case 'TEL': numbers.push({ number: unescape(raw).replace(/^tel:/i, ''), mobile: /CELL|MOBILE/.test(types) }); break;
        }
    }

    fields.full_name ||= structuredName;
    assignNumbers(fields, numbers);

    return fields.full_name ? fields : null;
}

function fromMeCard(text) {
    const fields = empty();
    const numbers = [];

    for (const part of splitEscaped(text.replace(/^MECARD:/i, ''), ';')) {
        const colon = part.indexOf(':');
        if (colon < 0) continue;

        const raw = unescape(part.slice(colon + 1));

        switch (part.slice(0, colon).toUpperCase()) {
            // "Dela Cruz,Juan" -> "Juan Dela Cruz"
            case 'N': fields.full_name = raw.split(',').map((s) => s.trim()).filter(Boolean).reverse().join(' '); break;
            case 'ORG': fields.company ||= raw; break;
            case 'TITLE': fields.job_title ||= raw; break;
            case 'EMAIL': fields.email ||= raw; break;
            case 'TEL': numbers.push({ number: raw, mobile: false }); break;
        }
    }

    assignNumbers(fields, numbers);

    return fields.full_name ? fields : null;
}

// A number marked as a cell phone is the mobile; another one is the telephone. Unmarked: first is the mobile.
function assignNumbers(fields, numbers) {
    const mobile = numbers.find((n) => n.mobile) ?? numbers[0];
    const other = numbers.find((n) => n !== mobile);

    fields.phone = mobile?.number ?? '';
    fields.telephone = other?.number ?? '';
}

// vCard N: Family;Given;Middle;Prefix;Suffix
function nameFromParts([family = '', given = '', middle = '']) {
    return [given, middle, family].map((part) => unescape(part).trim()).filter(Boolean).join(' ');
}

// Split on a separator that isn't escaped with a backslash.
function splitEscaped(text, separator) {
    const parts = [''];

    for (let i = 0; i < text.length; i++) {
        if (text[i] === '\\' && i + 1 < text.length) {
            parts[parts.length - 1] += text[i] + text[++i];
        } else if (text[i] === separator) {
            parts.push('');
        } else {
            parts[parts.length - 1] += text[i];
        }
    }

    return parts;
}

function unescape(value) {
    return value.replace(/\\([nN])/g, ' ').replace(/\\(.)/g, '$1').trim();
}
