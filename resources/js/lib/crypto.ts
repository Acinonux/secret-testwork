/**
 * Сквозное шифрование секретов в браузере (Web Crypto API, AES-256-GCM).
 *
 * Формат блоба: version (1 байт) || iv (12 байт) || ciphertext + GCM tag (16 байт).
 * Ключ (32 байта) кодируется в base64url и передаётся только во фрагменте URL.
 */

export type SecretPayload =
    | { type: 'text'; text: string }
    | { type: 'file'; name: string; mime: string; data: string };

export class DecryptError extends Error {
    constructor(message = 'Не удалось расшифровать секрет') {
        super(message);
        this.name = 'DecryptError';
    }
}

const VERSION = 1;
const IV_BYTES = 12;
const KEY_BYTES = 32;
const ALGORITHM = 'AES-GCM';

export function bytesToBase64(bytes: Uint8Array): string {
    let binary = '';
    const chunk = 0x8000;
    for (let i = 0; i < bytes.length; i += chunk) {
        binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
    }
    return btoa(binary);
}

export function base64ToBytes(base64: string): Uint8Array {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
}

export function bytesToBase64Url(bytes: Uint8Array): string {
    return bytesToBase64(bytes).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

export function base64UrlToBytes(value: string): Uint8Array {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    return base64ToBytes(base64 + '='.repeat((4 - (base64.length % 4)) % 4));
}

function importKey(raw: Uint8Array, usage: KeyUsage): Promise<CryptoKey> {
    return crypto.subtle.importKey('raw', raw, ALGORITHM, false, [usage]);
}

export async function encrypt(payload: SecretPayload): Promise<{ blob: string; key: string }> {
    const rawKey = crypto.getRandomValues(new Uint8Array(KEY_BYTES));
    const iv = crypto.getRandomValues(new Uint8Array(IV_BYTES));
    const plaintext = new TextEncoder().encode(JSON.stringify(payload));

    const ciphertext = new Uint8Array(
        await crypto.subtle.encrypt({ name: ALGORITHM, iv }, await importKey(rawKey, 'encrypt'), plaintext),
    );

    const blob = new Uint8Array(1 + IV_BYTES + ciphertext.length);
    blob[0] = VERSION;
    blob.set(iv, 1);
    blob.set(ciphertext, 1 + IV_BYTES);

    return { blob: bytesToBase64(blob), key: bytesToBase64Url(rawKey) };
}

export async function decrypt(blob: string, key: string): Promise<SecretPayload> {
    try {
        const rawKey = base64UrlToBytes(key);
        const bytes = base64ToBytes(blob);
        if (rawKey.length !== KEY_BYTES || bytes[0] !== VERSION || bytes.length < 1 + IV_BYTES + 16) {
            throw new DecryptError();
        }

        const iv = bytes.subarray(1, 1 + IV_BYTES);
        const plaintext = await crypto.subtle.decrypt(
            { name: ALGORITHM, iv },
            await importKey(rawKey, 'decrypt'),
            bytes.subarray(1 + IV_BYTES),
        );

        return JSON.parse(new TextDecoder().decode(plaintext)) as SecretPayload;
    } catch {
        throw new DecryptError();
    }
}

/** Читает файл и упаковывает его в полезную нагрузку для шифрования. */
export async function fileToPayload(file: File): Promise<SecretPayload> {
    const data = bytesToBase64(new Uint8Array(await file.arrayBuffer()));
    return { type: 'file', name: file.name, mime: file.type || 'application/octet-stream', data };
}
