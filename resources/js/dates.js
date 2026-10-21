// Dates as YYYY-MM-DD in the viewer's own timezone.
// (toISOString() is UTC, which in the Philippines gives yesterday's date before 8 AM.)

const pad = (number) => String(number).padStart(2, '0');

export function localDate(date = new Date()) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function today() {
    return localDate();
}

const units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];

/** "just now", "5 minutes ago", "1 month ago", in the viewer's language. */
export function timeAgo(iso) {
    const seconds = Math.round((new Date(iso) - new Date()) / 1000);
    const format = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) return format.format(Math.round(seconds / size), unit);
    }

    return 'just now';
}

export function addDays(days, from = new Date()) {
    const date = new Date(from);
    date.setDate(date.getDate() + days);
    return localDate(date);
}
