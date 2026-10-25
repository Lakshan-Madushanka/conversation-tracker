const units: ReadonlyArray<[Intl.RelativeTimeFormatUnit, number]> = [
    ['year', 31_536_000_000],
    ['month', 2_592_000_000],
    ['week', 604_800_000],
    ['day', 86_400_000],
    ['hour', 3_600_000],
    ['minute', 60_000],
];

/**
 * Turns an ISO timestamp into a quiet, human phrase like "2 hours ago".
 * Returns an empty string when the timestamp cannot be read.
 */
export function relativeTime(iso: string, now: number = Date.now()): string {
    const time = Date.parse(iso);

    if (Number.isNaN(time)) {
        return '';
    }

    const formatter = new Intl.RelativeTimeFormat(undefined, {
        numeric: 'auto',
    });
    const delta = time - now;

    for (const [unit, milliseconds] of units) {
        if (Math.abs(delta) >= milliseconds) {
            return formatter.format(Math.round(delta / milliseconds), unit);
        }
    }

    return formatter.format(Math.round(delta / 1000), 'second');
}

/**
 * The first moment of the day a timestamp falls on, in the learner's own
 * timezone. Comparing these tells us whether something happened on a later
 * calendar day, not merely a later moment.
 */
function startOfDay(time: number): number {
    const date = new Date(time);

    return new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
    ).getTime();
}

/** Whether an ISO timestamp falls on a calendar day after another one. */
export function isLaterDay(iso: string, thanIso: string): boolean {
    const time = Date.parse(iso);
    const thanTime = Date.parse(thanIso);

    if (Number.isNaN(time) || Number.isNaN(thanTime)) {
        return false;
    }

    return startOfDay(time) > startOfDay(thanTime);
}

/**
 * Turns an ISO timestamp into a short date such as "12 Oct", keeping the year
 * only when it is not the current one. Returns an empty string when the
 * timestamp cannot be read.
 */
export function shortDate(iso: string): string {
    const time = Date.parse(iso);

    if (Number.isNaN(time)) {
        return '';
    }

    const date = new Date(time);
    const sameYear = date.getFullYear() === new Date().getFullYear();

    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        ...(sameYear ? {} : { year: 'numeric' }),
    }).format(date);
}
