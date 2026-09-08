import { describe, expect, it } from 'vitest';
import {
    DecryptionError,
    decodeKey,
    decryptPaste,
    encodeKey,
    encryptPaste,
    generateKey,
} from './paste-crypto';

const text = 'Wachtwoord: hunter2\nServer: 10.0.0.4';

describe('paste encryption', () => {
    it('returns the original text when the right key is used', async () => {
        const key = generateKey();
        const payload = await encryptPaste(text, key, '', 'plaintext', false);

        await expect(
            decryptPaste(payload, key, '', 'plaintext', false),
        ).resolves.toBe(text);
    });

    it('does not leave the plain text anywhere in the payload', async () => {
        const key = generateKey();
        const payload = await encryptPaste(text, key, '', 'plaintext', false);

        expect(payload).not.toContain('hunter2');
        expect(payload).not.toContain('10.0.0.4');
    });

    it('produces a different ciphertext every time', async () => {
        const key = generateKey();
        const first = await encryptPaste(text, key, '', 'plaintext', false);
        const second = await encryptPaste(text, key, '', 'plaintext', false);

        expect(first).not.toBe(second);
    });

    it('refuses to decrypt with a different key', async () => {
        const payload = await encryptPaste(
            text,
            generateKey(),
            '',
            'plaintext',
            false,
        );

        await expect(
            decryptPaste(payload, generateKey(), '', 'plaintext', false),
        ).rejects.toBeInstanceOf(DecryptionError);
    });

    it('needs the password as well when one was set', async () => {
        const key = generateKey();
        const payload = await encryptPaste(
            text,
            key,
            'letmein',
            'plaintext',
            false,
        );

        await expect(
            decryptPaste(payload, key, '', 'plaintext', false),
        ).rejects.toBeInstanceOf(DecryptionError);

        await expect(
            decryptPaste(payload, key, 'letmein', 'plaintext', false),
        ).resolves.toBe(text);
    });

    it('fails when the server changes the format it claims', async () => {
        const key = generateKey();
        const payload = await encryptPaste(text, key, '', 'markdown', false);

        await expect(
            decryptPaste(payload, key, '', 'plaintext', false),
        ).rejects.toBeInstanceOf(DecryptionError);
    });

    it('fails when the server changes the burn flag it claims', async () => {
        const key = generateKey();
        const payload = await encryptPaste(text, key, '', 'plaintext', true);

        await expect(
            decryptPaste(payload, key, '', 'plaintext', false),
        ).rejects.toBeInstanceOf(DecryptionError);
    });

    it('rejects a payload it cannot parse', async () => {
        await expect(
            decryptPaste('not json', generateKey(), '', 'plaintext', false),
        ).rejects.toBeInstanceOf(DecryptionError);
    });
});

describe('key encoding', () => {
    it('survives a trip through the URL fragment', () => {
        const key = generateKey();

        expect(decodeKey(encodeKey(key))).toEqual(key);
    });

    it('produces a fragment safe to put in a URL', () => {
        expect(encodeKey(generateKey())).toMatch(/^[A-Za-z0-9_-]+$/);
    });

    it('rejects a fragment that is not a full key', () => {
        expect(() => decodeKey('too-short')).toThrow(DecryptionError);
    });
});
