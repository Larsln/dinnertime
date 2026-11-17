import type { ServerInfo } from '@/api/types';

const STORAGE_KEY = 'mealplanner.oidc';
const CALLBACK_PATH = '/oidc/callback';

interface PendingLogin {
    state: string;
    verifier: string;
    tokenEndpoint: string;
    clientId: string;
}

interface ProviderMetadata {
    authorization_endpoint: string;
    token_endpoint: string;
}

function randomString(): string {
    const bytes = crypto.getRandomValues(new Uint8Array(32));

    return base64Url(bytes);
}

function base64Url(bytes: Uint8Array): string {
    return btoa(String.fromCharCode(...bytes))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}

async function challengeFor(verifier: string): Promise<string> {
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier));

    return base64Url(new Uint8Array(digest));
}

function redirectUri(): string {
    return `${window.location.origin}${CALLBACK_PATH}`;
}

/**
 * Send the browser to the identity provider to sign in, using the
 * authorization code flow with PKCE.
 */
export async function startOidcLogin(oidc: NonNullable<ServerInfo['oidc']>): Promise<void> {
    const discoveryUrl = `${oidc.issuer.replace(/\/$/, '')}/.well-known/openid-configuration`;
    const metadata: ProviderMetadata = await (await fetch(discoveryUrl)).json();

    const pending: PendingLogin = {
        state: randomString(),
        verifier: randomString(),
        tokenEndpoint: metadata.token_endpoint,
        clientId: oidc.client_id,
    };

    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(pending));

    const parameters = new URLSearchParams({
        response_type: 'code',
        client_id: oidc.client_id,
        redirect_uri: redirectUri(),
        scope: 'openid profile email',
        state: pending.state,
        code_challenge: await challengeFor(pending.verifier),
        code_challenge_method: 'S256',
    });

    window.location.assign(`${metadata.authorization_endpoint}?${parameters}`);
}

/**
 * Exchange the authorization code the provider redirected back with for
 * an ID token.
 *
 * @throws Error when the response does not belong to the login that was started.
 */
export async function completeOidcLogin(query: URLSearchParams): Promise<string> {
    const pending: PendingLogin | null = JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? 'null');
    sessionStorage.removeItem(STORAGE_KEY);

    const code = query.get('code');

    if (pending === null || code === null || query.get('state') !== pending.state) {
        throw new Error('Unexpected response from the identity provider.');
    }

    const response = await fetch(pending.tokenEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            grant_type: 'authorization_code',
            code,
            redirect_uri: redirectUri(),
            client_id: pending.clientId,
            code_verifier: pending.verifier,
        }),
    });

    const tokens = await response.json();

    if (!response.ok || typeof tokens.id_token !== 'string') {
        throw new Error('The identity provider issued no ID token.');
    }

    return tokens.id_token;
}
