import { logError } from '@/lib/errorLog';

/**
 * Error answered by the API, carrying field messages for validation failures.
 */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

interface ApiContext {
    token: () => string | null;
    locale: () => string;
    onUnauthorized: () => void;
}

type Query = Record<string, string | number | readonly (string | number)[] | null | undefined>;

const BASE_PATH = '/api/v1';

let context: ApiContext = {
    token: () => null,
    locale: () => 'en',
    onUnauthorized: () => undefined,
};

/**
 * Connect the client to the session, so requests carry the token and the
 * interface language, and expired sessions are reported.
 */
export function configureApi(apiContext: ApiContext): void {
    context = apiContext;
}

function buildUrl(path: string, query: Query = {}): string {
    const parameters = new URLSearchParams();

    for (const [key, value] of Object.entries(query)) {
        if (Array.isArray(value)) {
            value.forEach((entry) => parameters.append(`${key}[]`, String(entry)));
        } else if (value !== null && value !== undefined && value !== '') {
            parameters.set(key, String(value));
        }
    }

    const suffix = parameters.size > 0 ? `?${parameters}` : '';

    return `${BASE_PATH}${path}${suffix}`;
}

async function send<T>(method: string, url: string, body?: BodyInit, contentType?: string): Promise<T> {
    const token = context.token();

    const place = `${method} ${url.split('?')[0]}`;

    const response = await fetch(url, {
        method,
        body,
        headers: {
            Accept: 'application/json',
            'Accept-Language': context.locale(),
            ...(contentType ? { 'Content-Type': contentType } : {}),
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
    }).catch((error: unknown) => {
        logError('request', place, error instanceof Error ? error.message : String(error));

        throw error;
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        if (response.status === 401) {
            context.onUnauthorized();
        }

        const message = payload.message ?? response.statusText;
        const fields = Object.values((payload.errors ?? {}) as Record<string, string[]>).flat();

        if (response.status !== 401) {
            logError('request', place, [response.status, message, ...fields].join(' · '));
        }

        throw new ApiError(response.status, message, payload.errors);
    }

    return payload as T;
}

function sendJson<T>(method: string, path: string, body?: unknown): Promise<T> {
    return send<T>(
        method,
        buildUrl(path),
        body === undefined ? undefined : JSON.stringify(body),
        'application/json',
    );
}

export const api = {
    get: <T>(path: string, query?: Query) => send<T>('GET', buildUrl(path, query)),
    post: <T>(path: string, body?: unknown) => sendJson<T>('POST', path, body),
    put: <T>(path: string, body?: unknown) => sendJson<T>('PUT', path, body),
    patch: <T>(path: string, body?: unknown) => sendJson<T>('PATCH', path, body),
    delete: <T = void>(path: string) => sendJson<T>('DELETE', path),
    upload: <T>(path: string, form: FormData) => send<T>('POST', buildUrl(path), form),
};

/**
 * Resource wrapped in the API's data envelope.
 */
export interface Envelope<T> {
    data: T;
}

/**
 * Unwrap a single resource or an unpaginated list.
 */
export async function data<T>(request: Promise<Envelope<T>>): Promise<T> {
    return (await request).data;
}
