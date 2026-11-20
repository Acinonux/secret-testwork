import { describe, expect, it } from 'vitest';
import { base64ToBytes, base64UrlToBytes, bytesToBase64, bytesToBase64Url, decrypt, DecryptError, encrypt, type SecretPayload } from './crypto';

describe('base64 helpers', () => {
    it('round-trips arbitrary bytes, including large arrays', () => {
        const bytes = crypto.getRandomValues(new Uint8Array(100_000).subarray(0, 65_536));
        expect(base64ToBytes(bytesToBase64(bytes))).toEqual(bytes);
        expect(base64UrlToBytes(bytesToBase64Url(bytes))).toEqual(bytes);
    });

    it('produces url-safe output without padding', () => {
        const encoded = bytesToBase64Url(new Uint8Array([251, 255, 191, 0]));
        expect(encoded).toMatch(/^[A-Za-z0-9_-]+$/);
    });
});

describe('encrypt/decrypt', () => {
    const cases: [string, SecretPayload][] = [
        ['ascii text', { type: 'text', text: 'password123' }],
        ['unicode text', { type: 'text', text: 'Пароль 🔐 密码' }],
        ['empty text', { type: 'text', text: '' }],
        ['binary file', { type: 'file', name: 'id_rsa', mime: 'application/octet-stream', data: bytesToBase64(crypto.getRandomValues(new Uint8Array(4096))) }],
    ];

    it.each(cases)('round-trips %s', async (_, payload) => {
        const { blob, key } = await encrypt(payload);
        expect(await decrypt(blob, key)).toEqual(payload);
    });

    it('uses a 256-bit base64url key of 43 chars', async () => {
        const { key } = await encrypt({ type: 'text', text: 'x' });
        expect(key).toMatch(/^[A-Za-z0-9_-]{43}$/);
        expect(base64UrlToBytes(key)).toHaveLength(32);
    });

    it('uses a fresh key and IV every time', async () => {
        const payload: SecretPayload = { type: 'text', text: 'same' };
        const a = await encrypt(payload);
        const b = await encrypt(payload);
        expect(a.key).not.toBe(b.key);
        expect(base64ToBytes(a.blob).subarray(1, 13)).not.toEqual(base64ToBytes(b.blob).subarray(1, 13));
    });

    it('does not leak plaintext into the blob', async () => {
        const { blob } = await encrypt({ type: 'text', text: 'TOP-SECRET-VALUE' });
        expect(atob(blob)).not.toContain('TOP-SECRET-VALUE');
    });

    it('fails with a wrong key', async () => {
        const { blob } = await encrypt({ type: 'text', text: 'x' });
        const { key: otherKey } = await encrypt({ type: 'text', text: 'y' });
        await expect(decrypt(blob, otherKey)).rejects.toBeInstanceOf(DecryptError);
    });

    it('fails when the ciphertext is tampered with', async () => {
        const { blob, key } = await encrypt({ type: 'text', text: 'x' });
        const bytes = base64ToBytes(blob);
        bytes[bytes.length - 1] ^= 1;
        await expect(decrypt(bytesToBase64(bytes), key)).rejects.toBeInstanceOf(DecryptError);
    });

    it('fails on malformed input', async () => {
        const { key } = await encrypt({ type: 'text', text: 'x' });
        await expect(decrypt('not base64!', key)).rejects.toBeInstanceOf(DecryptError);
        await expect(decrypt(bytesToBase64(new Uint8Array(10)), key)).rejects.toBeInstanceOf(DecryptError);
        await expect(decrypt(bytesToBase64(new Uint8Array(40)), 'short')).rejects.toBeInstanceOf(DecryptError);
    });
});
