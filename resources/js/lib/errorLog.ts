/**
 * Keeps the latest errors of this browser, so they can be exported and
 * passed on when something goes wrong. Entries never hold tokens or what
 * was sent, only what failed and how.
 */
export interface ErrorLogEntry {
    time: string;
    kind: 'request' | 'script';
    context: string;
    message: string;
}

const STORAGE_KEY = 'dinnertime.errorLog';
const MAX_ENTRIES = 300;

export function readErrorLog(): ErrorLogEntry[] {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');
    } catch {
        return [];
    }
}

/**
 * Requests cut off because the page is being left are no errors.
 */
let isLeaving = false;

export function logError(kind: ErrorLogEntry['kind'], context: string, message: string): void {
    if (isLeaving) {
        return;
    }

    const entries = [...readErrorLog(), { time: new Date().toISOString(), kind, context, message }];

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(entries.slice(-MAX_ENTRIES)));
    } catch {
        localStorage.removeItem(STORAGE_KEY);
    }
}

export function clearErrorLog(): void {
    localStorage.removeItem(STORAGE_KEY);
}

/**
 * The log as plain text, headed by what is needed to place the errors:
 * the page, the browser and the time of the export.
 */
export function errorLogText(): string {
    const head = [
        'Dinnertime web error log',
        `Exported: ${new Date().toISOString()}`,
        `Browser: ${navigator.userAgent}`,
        `Language: ${navigator.language}`,
        `Viewport: ${window.innerWidth}x${window.innerHeight}`,
        '',
    ];

    return [
        ...head,
        ...readErrorLog().map((entry) => `${entry.time} [${entry.kind}] ${entry.context}: ${entry.message}`),
    ].join('\n');
}

/**
 * Record script errors nothing else caught.
 */
export function watchScriptErrors(): void {
    window.addEventListener('pagehide', () => {
        isLeaving = true;
    });
    window.addEventListener('pageshow', () => {
        isLeaving = false;
    });
    window.addEventListener('error', (event) => {
        logError('script', `${event.filename.split('/').pop() ?? ''}:${event.lineno}`, event.message);
    });
    window.addEventListener('unhandledrejection', (event) => {
        const reason = event.reason;

        if (!(reason instanceof Error && reason.name === 'ApiError')) {
            logError(
                'script',
                'promise',
                reason instanceof Error ? `${reason.name}: ${reason.message}` : String(reason),
            );
        }
    });
}
