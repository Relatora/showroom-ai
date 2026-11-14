/**
 * Formatting helpers, all built on the browser's Intl APIs so we get proper Canadian
 * formatting ("$45,900", "12,400 km") without a date or number library.
 */

const currency = new Intl.NumberFormat('en-CA', {
    style: 'currency',
    currency: 'CAD',
    maximumFractionDigits: 0,
});

const compactCurrency = new Intl.NumberFormat('en-CA', {
    style: 'currency',
    currency: 'CAD',
    notation: 'compact',
    maximumFractionDigits: 1,
});

const number = new Intl.NumberFormat('en-CA');
const relative = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

export const money = (value: number | null | undefined): string =>
    value == null ? '—' : currency.format(value);

export const compactMoney = (value: number): string =>
    compactCurrency.format(value);

export const km = (value: number): string => `${number.format(value)} km`;

export const count = (value: number): string => number.format(value);

/**
 * "3 days ago", "in 2 hours", "yesterday". Picks the largest unit that makes sense.
 */
export function timeAgo(iso: string | null | undefined): string {
    if (!iso) {
        return 'never';
    }

    const seconds = (new Date(iso).getTime() - Date.now()) / 1000;
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31_536_000],
        ['month', 2_592_000],
        ['week', 604_800],
        ['day', 86_400],
        ['hour', 3_600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relative.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}

export function dateTime(iso: string): string {
    return new Date(iso).toLocaleString('en-CA', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export function time(iso: string): string {
    return new Date(iso).toLocaleTimeString('en-CA', {
        hour: 'numeric',
        minute: '2-digit',
    });
}
