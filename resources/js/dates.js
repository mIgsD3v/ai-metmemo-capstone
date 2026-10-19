// Dates as YYYY-MM-DD in the viewer's own timezone.
// (toISOString() is UTC, which in the Philippines gives yesterday's date before 8 AM.)

const pad = (number) => String(number).padStart(2, '0');

export function localDate(date = new Date()) {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function today() {
    return localDate();
}

export function addDays(days, from = new Date()) {
    const date = new Date(from);
    date.setDate(date.getDate() + days);
    return localDate(date);
}
