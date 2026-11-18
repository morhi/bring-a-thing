import {
    login,
    loginOptions,
    registrationOptions,
    store,
} from '@/routes/passkey';
import { t } from '@/lib/i18n';

export type PasskeyLoginExtras = Record<string, unknown>;

/**
 * Whether the browser can run passkey ceremonies at all (F-01).
 *
 * The JSON helpers are optional: older browsers get the base64url fallbacks below.
 */
export function supportsPasskeys(): boolean {
    return (
        typeof window !== 'undefined' &&
        'PublicKeyCredential' in window &&
        window.isSecureContext
    );
}

function errorName(error: unknown): string | null {
    return error instanceof DOMException ? error.name : null;
}

/**
 * Whether an error only means the person dismissed the browser's passkey prompt.
 */
export function isPasskeyCancelled(error: unknown): boolean {
    return errorName(error) === 'NotAllowedError';
}

/**
 * Whether the device already holds a passkey for this account.
 */
export function isPasskeyDuplicate(error: unknown): boolean {
    return errorName(error) === 'InvalidStateError';
}

function toBuffer(value: string): ArrayBuffer {
    const base64 = value.replaceAll('-', '+').replaceAll('_', '/');
    const binary = atob(base64.padEnd(Math.ceil(base64.length / 4) * 4, '='));

    return Uint8Array.from(binary, (char) => char.charCodeAt(0)).buffer;
}

function toBase64url(buffer: ArrayBuffer | null): string | null {
    if (buffer === null) {
        return null;
    }

    return btoa(String.fromCharCode(...new Uint8Array(buffer)))
        .replaceAll('+', '-')
        .replaceAll('/', '_')
        .replaceAll('=', '');
}

function creationOptions(
    options: PublicKeyCredentialCreationOptionsJSON,
): PublicKeyCredentialCreationOptions {
    if (
        typeof PublicKeyCredential.parseCreationOptionsFromJSON === 'function'
    ) {
        return PublicKeyCredential.parseCreationOptionsFromJSON(options);
    }

    return {
        ...options,
        challenge: toBuffer(options.challenge),
        user: { ...options.user, id: toBuffer(options.user.id) },
        excludeCredentials: options.excludeCredentials?.map((entry) => ({
            ...entry,
            id: toBuffer(entry.id),
        })),
    } as unknown as PublicKeyCredentialCreationOptions;
}

function requestOptions(
    options: PublicKeyCredentialRequestOptionsJSON,
): PublicKeyCredentialRequestOptions {
    if (typeof PublicKeyCredential.parseRequestOptionsFromJSON === 'function') {
        return PublicKeyCredential.parseRequestOptionsFromJSON(options);
    }

    return {
        ...options,
        challenge: toBuffer(options.challenge),
        allowCredentials: options.allowCredentials?.map((entry) => ({
            ...entry,
            id: toBuffer(entry.id),
        })),
    } as unknown as PublicKeyCredentialRequestOptions;
}

function credentialJson(credential: PublicKeyCredential): unknown {
    if (typeof credential.toJSON === 'function') {
        return credential.toJSON();
    }

    const response = credential.response;
    const base = { clientDataJSON: toBase64url(response.clientDataJSON) };

    return {
        id: credential.id,
        rawId: toBase64url(credential.rawId),
        type: credential.type,
        authenticatorAttachment: credential.authenticatorAttachment,
        clientExtensionResults: credential.getClientExtensionResults(),
        response:
            response instanceof AuthenticatorAttestationResponse
                ? {
                      ...base,
                      attestationObject: toBase64url(
                          response.attestationObject,
                      ),
                      transports: response.getTransports?.() ?? [],
                  }
                : {
                      ...base,
                      authenticatorData: toBase64url(
                          (response as AuthenticatorAssertionResponse)
                              .authenticatorData,
                      ),
                      signature: toBase64url(
                          (response as AuthenticatorAssertionResponse)
                              .signature,
                      ),
                      userHandle: toBase64url(
                          (response as AuthenticatorAssertionResponse)
                              .userHandle,
                      ),
                  },
    };
}

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

/**
 * The session or CSRF token expired, so the request can only be retried.
 */
export class PasskeyPageExpiredError extends Error {}

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
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });

    if (response.status === 419) {
        throw new PasskeyPageExpiredError(t('error.pageExpired'));
    }

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
        publicKey: creationOptions(options),
    })) as PublicKeyCredential | null;

    if (!credential) {
        throw new DOMException('No credential created', 'NotAllowedError');
    }

    await request(store().url, 'POST', {
        name,
        credential: credentialJson(credential),
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
        publicKey: requestOptions(options),
    })) as PublicKeyCredential | null;

    if (!credential) {
        throw new DOMException('No credential received', 'NotAllowedError');
    }

    const { redirect } = await request<{ redirect: string }>(
        login().url,
        'POST',
        { ...extras, credential: credentialJson(credential), remember: true },
    );

    return redirect;
}
