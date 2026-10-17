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
