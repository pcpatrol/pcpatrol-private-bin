/**
 * End-to-end encryption for pastes.
 *
 * The browser generates a random key, encrypts the text with it, and sends
 * only the ciphertext to the server. The key travels in the fragment of the
 * share URL, which browsers never put on the wire, so the server can hand out
 * the ciphertext without ever being able to read it.
 */

const KEY_BYTES = 32;
const IV_BYTES = 12;
const SALT_BYTES = 16;
const KDF_ITERATIONS = 310000;
const PAYLOAD_VERSION = 1;

export type PasteFormat = 'plaintext' | 'markdown' | 'code';

/** Byte array backed by a plain ArrayBuffer, as the Web Crypto API requires. */
export type PasteKey = Uint8Array<ArrayBuffer>;

export type EncryptedPayload = {
    v: number;
    ct: string;
    iv: string;
    salt: string;
    iter: number;
};

/** Thrown when a paste cannot be decrypted with the key and password given. */
export class DecryptionError extends Error {
    constructor(message = 'Unable to decrypt this paste.') {
        super(message);
        this.name = 'DecryptionError';
    }
}

function assertCryptoAvailable(): void {
    if (typeof crypto === 'undefined' || crypto.subtle === undefined) {
        throw new Error(
            'This browser does not expose the Web Crypto API. A secure (https) connection is required.',
        );
    }
}

function toBase64(bytes: Uint8Array): string {
    let binary = '';

    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }

    return btoa(binary);
}

function fromBase64(value: string): PasteKey {
    const binary = atob(value);
    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes;
}

/** Encode the key for use in a URL fragment. */
export function encodeKey(key: Uint8Array): string {
    return toBase64(key)
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}

/** Read a key back out of a URL fragment. */
export function decodeKey(value: string): PasteKey {
    const normalised = value.replace(/-/g, '+').replace(/_/g, '/');
    const padded = normalised.padEnd(Math.ceil(normalised.length / 4) * 4, '=');

    let key: PasteKey;

    try {
        key = fromBase64(padded);
    } catch {
        throw new DecryptionError(
            'The link is missing a valid decryption key.',
        );
    }

    if (key.length !== KEY_BYTES) {
        throw new DecryptionError(
            'The link is missing a valid decryption key.',
        );
    }

    return key;
}

/** Generate a fresh random key for a new paste. */
export function generateKey(): PasteKey {
    assertCryptoAvailable();

    return crypto.getRandomValues(new Uint8Array(KEY_BYTES));
}

/**
 * Bind the paste's public metadata to the ciphertext.
 *
 * Passing this as additional authenticated data means a server that alters the
 * format or the burn flag makes decryption fail instead of going unnoticed.
 */
function additionalData(
    format: PasteFormat,
    burnAfterReading: boolean,
): PasteKey {
    return new TextEncoder().encode(
        `${PAYLOAD_VERSION}:${format}:${burnAfterReading ? 1 : 0}`,
    );
}

/**
 * Stretch the URL key, plus the optional password, into an AES-GCM key.
 */
async function deriveKey(
    key: PasteKey,
    password: string,
    salt: PasteKey,
    iterations: number,
): Promise<CryptoKey> {
    const passwordBytes = new TextEncoder().encode(password);
    const material = new Uint8Array(key.length + passwordBytes.length);
    material.set(key, 0);
    material.set(passwordBytes, key.length);

    const imported = await crypto.subtle.importKey(
        'raw',
        material,
        'PBKDF2',
        false,
        ['deriveKey'],
    );

    return crypto.subtle.deriveKey(
        { name: 'PBKDF2', salt, iterations, hash: 'SHA-256' },
        imported,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt'],
    );
}

/** Encrypt a paste and return the payload that gets stored server-side. */
export async function encryptPaste(
    text: string,
    key: PasteKey,
    password: string,
    format: PasteFormat,
    burnAfterReading: boolean,
): Promise<string> {
    assertCryptoAvailable();

    const iv = crypto.getRandomValues(new Uint8Array(IV_BYTES));
    const salt = crypto.getRandomValues(new Uint8Array(SALT_BYTES));
    const aesKey = await deriveKey(key, password, salt, KDF_ITERATIONS);

    const ciphertext = await crypto.subtle.encrypt(
        {
            name: 'AES-GCM',
            iv,
            additionalData: additionalData(format, burnAfterReading),
        },
        aesKey,
        new TextEncoder().encode(text),
    );

    const payload: EncryptedPayload = {
        v: PAYLOAD_VERSION,
        ct: toBase64(new Uint8Array(ciphertext)),
        iv: toBase64(iv),
        salt: toBase64(salt),
        iter: KDF_ITERATIONS,
    };

    return JSON.stringify(payload);
}

/** Decrypt a stored payload back into the original text. */
export async function decryptPaste(
    payload: string,
    key: PasteKey,
    password: string,
    format: PasteFormat,
    burnAfterReading: boolean,
): Promise<string> {
    assertCryptoAvailable();

    let parsed: EncryptedPayload;

    try {
        parsed = JSON.parse(payload) as EncryptedPayload;
    } catch {
        throw new DecryptionError(
            'This paste is stored in a format we cannot read.',
        );
    }

    if (parsed.v !== PAYLOAD_VERSION) {
        throw new DecryptionError(
            'This paste was made with a different version of the tool.',
        );
    }

    const aesKey = await deriveKey(
        key,
        password,
        fromBase64(parsed.salt),
        parsed.iter,
    );

    try {
        const plaintext = await crypto.subtle.decrypt(
            {
                name: 'AES-GCM',
                iv: fromBase64(parsed.iv),
                additionalData: additionalData(format, burnAfterReading),
            },
            aesKey,
            fromBase64(parsed.ct),
        );

        return new TextDecoder().decode(plaintext);
    } catch {
        throw new DecryptionError();
    }
}
