import {
    login,
    loginOptions,
    registrationOptions,
    store,
} from '@/routes/passkey';

export type PasskeyLoginExtras = Record<string, unknown>;

/**
 * Whether the browser can create and use passkeys from the server's JSON options (F-01).
 */
export function supportsPasskeys(): boolean {
    return (
        typeof window !== 'undefined' &&
        'PublicKeyCredential' in window &&
        typeof PublicKeyCredential.parseCreationOptionsFromJSON === 'function'
    );
}

/**
 * Whether an error only means the person dismissed the browser's passkey prompt.
 */
export function isPasskeyCancelled(error: unknown): boolean {
    return error instanceof DOMException && error.name === 'NotAllowedError';
}

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

async function request<T>(
    url: string,
    method: 'GET' | 'POST',
    body?: unknown,
): Promise<T> {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        const payload = (await response.json().catch(() => ({}))) as {
            message?: string;
        };

        throw new Error(payload.message ?? '');
    }

    return (await response.json()) as T;
}

/**
 * Create a passkey on this device and save it under the given name (F-02).
 */
export async function registerPasskey(name: string): Promise<void> {
    const { options } = await request<{
        options: PublicKeyCredentialCreationOptionsJSON;
    }>(registrationOptions().url, 'GET');

    const credential = (await navigator.credentials.create({
        publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(options),
    })) as PublicKeyCredential | null;

    if (!credential) {
        throw new DOMException('No credential created', 'NotAllowedError');
    }

    await request(store().url, 'POST', {
        name,
        credential: credential.toJSON(),
    });
}

/**
 * Log in with a passkey and resolve to the URL to continue on (F-01).
 *
 * The extras ride along with the login request, so a claim started on a
 * shared list completes afterwards (F-19).
 */
export async function loginWithPasskey(
    extras: PasskeyLoginExtras = {},
): Promise<string> {
    const { options } = await request<{
        options: PublicKeyCredentialRequestOptionsJSON;
    }>(loginOptions().url, 'GET');

    const credential = (await navigator.credentials.get({
        publicKey: PublicKeyCredential.parseRequestOptionsFromJSON(options),
    })) as PublicKeyCredential | null;

    if (!credential) {
        throw new DOMException('No credential received', 'NotAllowedError');
    }

    const { redirect } = await request<{ redirect: string }>(
        login().url,
        'POST',
        { ...extras, credential: credential.toJSON() },
    );

    return redirect;
}
