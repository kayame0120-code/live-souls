import _sodium from 'libsodium-wrappers-sumo';

let sodium = null;

async function ready() {
    if (!sodium) {
        await _sodium.ready;
        sodium = _sodium;
    }
    return sodium;
}

function toBase64(uint8) {
    return sodium.to_base64(uint8, sodium.base64_variants.ORIGINAL);
}

function fromBase64(str) {
    return sodium.from_base64(str, sodium.base64_variants.ORIGINAL);
}

export async function deriveWrappingKey(password, saltBase64) {
    const s = await ready();
    const salt = fromBase64(saltBase64);
    return s.crypto_pwhash(
        s.crypto_secretbox_KEYBYTES,
        password,
        salt,
        s.crypto_pwhash_OPSLIMIT_INTERACTIVE,
        s.crypto_pwhash_MEMLIMIT_INTERACTIVE,
        s.crypto_pwhash_ALG_ARGON2ID13
    );
}

export async function generateMasterKey() {
    const s = await ready();
    return s.crypto_secretbox_keygen();
}

export async function generateSalt() {
    const s = await ready();
    const salt = s.randombytes_buf(s.crypto_pwhash_SALTBYTES);
    return toBase64(salt);
}

export async function wrapKey(masterKey, wrappingKey) {
    const s = await ready();
    const nonce = s.randombytes_buf(s.crypto_secretbox_NONCEBYTES);
    const ciphertext = s.crypto_secretbox_easy(masterKey, nonce, wrappingKey);
    const combined = new Uint8Array(nonce.length + ciphertext.length);
    combined.set(nonce);
    combined.set(ciphertext, nonce.length);
    return toBase64(combined);
}

export async function unwrapKey(wrappedBase64, wrappingKey) {
    const s = await ready();
    const combined = fromBase64(wrappedBase64);
    const nonce = combined.slice(0, s.crypto_secretbox_NONCEBYTES);
    const ciphertext = combined.slice(s.crypto_secretbox_NONCEBYTES);
    return s.crypto_secretbox_open_easy(ciphertext, nonce, wrappingKey);
}

export async function encrypt(plaintext, masterKey) {
    const s = await ready();
    const nonce = s.randombytes_buf(s.crypto_secretbox_NONCEBYTES);
    const encoded = s.from_string(plaintext);
    const ciphertext = s.crypto_secretbox_easy(encoded, nonce, masterKey);
    const combined = new Uint8Array(nonce.length + ciphertext.length);
    combined.set(nonce);
    combined.set(ciphertext, nonce.length);
    return toBase64(combined);
}

export async function decrypt(ciphertextBase64, masterKey) {
    const s = await ready();
    const combined = fromBase64(ciphertextBase64);
    const nonce = combined.slice(0, s.crypto_secretbox_NONCEBYTES);
    const ciphertext = combined.slice(s.crypto_secretbox_NONCEBYTES);
    const plaintext = s.crypto_secretbox_open_easy(ciphertext, nonce, masterKey);
    return s.to_string(plaintext);
}

export async function generateRecoveryKey() {
    const s = await ready();
    const key = s.randombytes_buf(32);
    return toBase64(key);
}

export async function setupE2E(loginPassword) {
    const masterKey = await generateMasterKey();

    const pwSalt = await generateSalt();
    const pwWrappingKey = await deriveWrappingKey(loginPassword, pwSalt);
    const wrappedMasterKeyPw = await wrapKey(masterKey, pwWrappingKey);

    const recoveryKey = await generateRecoveryKey();
    const rkSalt = await generateSalt();
    const rkWrappingKey = await deriveWrappingKey(recoveryKey, rkSalt);
    const wrappedMasterKeyRk = await wrapKey(masterKey, rkWrappingKey);

    return {
        recoveryKey,
        keysPayload: {
            wrapped_master_key_pw: wrappedMasterKeyPw,
            pw_salt: pwSalt,
            wrapped_master_key_rk: wrappedMasterKeyRk,
            rk_salt: rkSalt,
        },
    };
}

let clipboardTimeout = null;

export function copyWithAutoExpiry(text, seconds = 45) {
    navigator.clipboard.writeText(text);
    if (clipboardTimeout) clearTimeout(clipboardTimeout);
    clipboardTimeout = setTimeout(() => {
        navigator.clipboard.writeText('');
        clipboardTimeout = null;
    }, seconds * 1000);
}
