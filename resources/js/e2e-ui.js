// E2E暗号化のUI統合（セットアップ／アンロック／フォーム暗号化／復号コピー）
// セキュリティ正本: security_requirements_v1.1 エンベロープ方式
import {
    setupE2E,
    deriveWrappingKey,
    unwrapKey,
    wrapKey,
    encrypt,
    decrypt,
    copyWithAutoExpiry,
    generateSalt,
} from './e2e-crypto';

const E2E_PREFIX = 'e2e:';

// マスターキーはページ表示中のみメモリ保持（storageには置かない）
let masterKey = null;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function api(method, url, body = null) {
    const res = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : null,
    });
    if (!res.ok && res.status !== 200) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.error || `通信エラー (HTTP ${res.status})`);
    }
    return res.json();
}

// ---- モーダル ----

function buildModal() {
    const overlay = document.createElement('div');
    overlay.style.cssText =
        'position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;';
    const box = document.createElement('div');
    box.style.cssText =
        'background:#fff;border-radius:14px;max-width:360px;width:100%;padding:22px;font-family:inherit;max-height:85vh;overflow-y:auto;';
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    return { overlay, box };
}

function el(tag, style, text) {
    const e = document.createElement(tag);
    if (style) e.style.cssText = style;
    if (text !== undefined) e.textContent = text;
    return e;
}

function inputEl(type, placeholder) {
    const i = el('input', 'width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccc;border-radius:8px;font-size:14px;margin-bottom:10px;');
    i.type = type;
    i.placeholder = placeholder;
    return i;
}

function buttonEl(label, primary = true) {
    const b = el('button', primary
        ? 'width:100%;padding:11px;border:none;border-radius:8px;background:#C7414F;color:#fff;font-size:14px;font-weight:600;cursor:pointer;margin-top:4px;'
        : 'width:100%;padding:10px;border:none;border-radius:8px;background:transparent;color:#888;font-size:12px;cursor:pointer;margin-top:6px;');
    b.type = 'button';
    b.textContent = label;
    return b;
}

// ---- セットアップ（初回・リカバリーキー発行） ----

function showSetupModal() {
    return new Promise((resolve, reject) => {
        const { overlay, box } = buildModal();

        box.appendChild(el('div', 'font-size:16px;font-weight:700;margin-bottom:8px;', '暗号化の初期設定'));
        box.appendChild(el('p', 'font-size:12px;color:#666;line-height:1.7;margin-bottom:14px;',
            'FC会員番号・ID・パスワードを、この端末の中だけで暗号化して保存します。設定にはログインパスワードが必要です。'));

        const pw1 = inputEl('password', 'ログインパスワード');
        const pw2 = inputEl('password', 'ログインパスワード（確認）');
        const err = el('div', 'color:#C7414F;font-size:12px;margin-bottom:8px;display:none;');
        const btn = buttonEl('設定する');
        const cancel = buttonEl('キャンセル', false);

        box.append(pw1, pw2, err, btn, cancel);

        const fail = (msg) => { err.textContent = msg; err.style.display = 'block'; };

        btn.addEventListener('click', async () => {
            err.style.display = 'none';
            if (!pw1.value) return fail('パスワードを入力してください');
            if (pw1.value !== pw2.value) return fail('パスワードが一致しません');
            btn.disabled = true; btn.textContent = '設定中…';
            try {
                const check = await api('POST', '/api/e2e/verify-password', { password: pw1.value });
                if (!check.valid) { btn.disabled = false; btn.textContent = '設定する'; return fail('ログインパスワードが違います'); }

                const { recoveryKey, keysPayload } = await setupE2E(pw1.value);
                await api('POST', '/api/e2e/keys', keysPayload);

                // アンロック状態にする
                const wrappingKey = await deriveWrappingKey(pw1.value, keysPayload.pw_salt);
                masterKey = await unwrapKey(keysPayload.wrapped_master_key_pw, wrappingKey);

                showRecoveryKeyScreen(box, recoveryKey, () => {
                    overlay.remove();
                    resolve(masterKey);
                });
            } catch (e) {
                btn.disabled = false; btn.textContent = '設定する';
                fail(e.message || '設定に失敗しました');
            }
        });
        cancel.addEventListener('click', () => { overlay.remove(); reject(new Error('キャンセルされました')); });
    });
}

function showRecoveryKeyScreen(box, recoveryKey, onDone) {
    box.textContent = '';
    box.appendChild(el('div', 'font-size:16px;font-weight:700;margin-bottom:8px;', 'リカバリーキーの保管'));
    box.appendChild(el('p', 'font-size:12px;color:#C7414F;font-weight:600;line-height:1.7;margin-bottom:10px;',
        'このキーは今回しか表示されません。パスワードを忘れた場合、このキーがないとFC情報は二度と復元できません。'));

    const keyBox = el('div', 'background:#f5f5f5;border:1px dashed #999;border-radius:8px;padding:12px;font-family:monospace;font-size:11px;word-break:break-all;margin-bottom:10px;', recoveryKey);
    const copyBtn = buttonEl('リカバリーキーをコピー');
    const label = el('label', 'display:flex;align-items:center;gap:8px;font-size:12px;margin:12px 0;cursor:pointer;');
    const check = document.createElement('input');
    check.type = 'checkbox';
    label.append(check, document.createTextNode('安全な場所に保管しました'));
    const done = buttonEl('完了');
    done.disabled = true;
    done.style.opacity = '0.5';

    box.append(keyBox, copyBtn, label, done);

    copyBtn.addEventListener('click', () => {
        navigator.clipboard.writeText(recoveryKey);
        copyBtn.textContent = 'コピーしました';
    });
    check.addEventListener('change', () => {
        done.disabled = !check.checked;
        done.style.opacity = check.checked ? '1' : '0.5';
    });
    done.addEventListener('click', onDone);
}

// ---- アンロック（2回目以降） ----

function showUnlockModal(keys) {
    return new Promise((resolve, reject) => {
        const { overlay, box } = buildModal();

        box.appendChild(el('div', 'font-size:16px;font-weight:700;margin-bottom:8px;', 'FC情報のロック解除'));
        box.appendChild(el('p', 'font-size:12px;color:#666;line-height:1.7;margin-bottom:14px;',
            'ログインパスワードを入力してください。復号はこの端末の中だけで行われます。'));

        const pw = inputEl('password', 'ログインパスワード');
        const err = el('div', 'color:#C7414F;font-size:12px;margin-bottom:8px;display:none;');
        const btn = buttonEl('ロック解除');
        const restore = buttonEl('パスワードを変更した場合はこちら（リカバリーキーで復元）', false);
        const cancel = buttonEl('キャンセル', false);

        box.append(pw, err, btn, restore, cancel);

        const fail = (msg) => { err.textContent = msg; err.style.display = 'block'; };

        btn.addEventListener('click', async () => {
            err.style.display = 'none';
            btn.disabled = true; btn.textContent = '解除中…';
            try {
                const wrappingKey = await deriveWrappingKey(pw.value, keys.pw_salt);
                masterKey = await unwrapKey(keys.wrapped_master_key_pw, wrappingKey);
                overlay.remove();
                resolve(masterKey);
            } catch {
                btn.disabled = false; btn.textContent = 'ロック解除';
                fail('パスワードが違います');
            }
        });

        restore.addEventListener('click', () => {
            showRestoreScreen(box, keys, overlay, resolve, reject);
        });
        cancel.addEventListener('click', () => { overlay.remove(); reject(new Error('キャンセルされました')); });
    });
}

// ---- リカバリーキーによる復元（パスワード変更・リセット後） ----

function showRestoreScreen(box, keys, overlay, resolve, reject) {
    box.textContent = '';
    box.appendChild(el('div', 'font-size:16px;font-weight:700;margin-bottom:8px;', 'リカバリーキーで復元'));
    box.appendChild(el('p', 'font-size:12px;color:#666;line-height:1.7;margin-bottom:14px;',
        '保管しているリカバリーキーと、現在のログインパスワードを入力してください。復元後は現在のパスワードで開けるようになります。'));

    const rk = inputEl('text', 'リカバリーキー');
    const pw = inputEl('password', '現在のログインパスワード');
    const err = el('div', 'color:#C7414F;font-size:12px;margin-bottom:8px;display:none;');
    const btn = buttonEl('復元する');
    const cancel = buttonEl('キャンセル', false);

    box.append(rk, pw, err, btn, cancel);

    const fail = (msg) => { err.textContent = msg; err.style.display = 'block'; };

    btn.addEventListener('click', async () => {
        err.style.display = 'none';
        btn.disabled = true; btn.textContent = '復元中…';
        try {
            // リカバリーキーでマスターキーを取り出す
            const rkWrapping = await deriveWrappingKey(rk.value.trim(), keys.rk_salt);
            const mk = await unwrapKey(keys.wrapped_master_key_rk, rkWrapping);

            // 現在のパスワードを検証してから包み直す
            const check = await api('POST', '/api/e2e/verify-password', { password: pw.value });
            if (!check.valid) { btn.disabled = false; btn.textContent = '復元する'; return fail('ログインパスワードが違います'); }

            const newSalt = await generateSalt();
            const newWrapping = await deriveWrappingKey(pw.value, newSalt);
            const rewrapped = await wrapKey(mk, newWrapping);
            await api('PUT', '/api/e2e/keys/rewrap', {
                wrapped_master_key_pw: rewrapped,
                pw_salt: newSalt,
            });

            masterKey = mk;
            overlay.remove();
            resolve(masterKey);
        } catch (e) {
            btn.disabled = false; btn.textContent = '復元する';
            fail(e.message === 'キャンセルされました' ? e.message : 'リカバリーキーが正しくありません');
        }
    });
    cancel.addEventListener('click', () => { overlay.remove(); reject(new Error('キャンセルされました')); });
}

// ---- 公開API ----

/** マスターキーを確保する（未設定ならセットアップ、ロック中ならアンロック） */
export async function ensureUnlocked() {
    if (masterKey) return masterKey;

    const keys = await api('GET', '/api/e2e/keys');
    if (!keys.has_keys) {
        return showSetupModal();
    }
    return showUnlockModal(keys);
}

/** 値をE2E暗号化して"e2e:"プレフィックス付きで返す */
export async function encryptValue(plaintext) {
    const mk = await ensureUnlocked();
    return E2E_PREFIX + (await encrypt(plaintext, mk));
}

/** "e2e:"プレフィックス付き暗号文を復号する */
export async function decryptValue(value) {
    if (!value || !value.startsWith(E2E_PREFIX)) return value;
    const mk = await ensureUnlocked();
    return decrypt(value.slice(E2E_PREFIX.length), mk);
}

// ---- フォーム統合 ----

const E2E_FIELD_IDS = ['member_no', 'login_id', 'fc_password'];

function wireE2eForm(form) {
    let encrypted = false;

    form.addEventListener('submit', async (e) => {
        if (encrypted) return; // 暗号化済み → 通常送信

        const targets = E2E_FIELD_IDS
            .map((id) => form.querySelector(`#${id}`))
            .filter((f) => f && f.value && !f.value.startsWith(E2E_PREFIX));

        if (targets.length === 0) return; // 暗号化対象なし（既にe2e:か空）

        e.preventDefault();
        try {
            const mk = await ensureUnlocked();
            for (const f of targets) {
                // 会員番号は下3桁を一覧表示ヒントとして別送（暗号化前に取得）
                if (f.id === 'member_no') {
                    const hintField = form.querySelector('#member_no_hint');
                    if (hintField) hintField.value = f.value.slice(-3);
                }
                f.value = E2E_PREFIX + (await encrypt(f.value, mk));
            }
            encrypted = true;
            form.requestSubmit ? form.requestSubmit() : form.submit();
        } catch {
            // キャンセル時は送信中断（平文をサーバーに送らない）
        }
    });

    // 編集画面: プリフィルがe2e:暗号文なら「復号して編集」ボタンを出す
    const hasCipher = E2E_FIELD_IDS.some((id) => {
        const f = form.querySelector(`#${id}`);
        return f && f.value.startsWith(E2E_PREFIX);
    });
    if (hasCipher) {
        const first = form.querySelector(`#${E2E_FIELD_IDS.find((id) => form.querySelector(`#${id}`))}`);
        const note = el('button', 'display:block;margin:4px 0 10px;padding:6px 12px;border:1px solid #C7414F;border-radius:8px;background:#fff;color:#C7414F;font-size:12px;cursor:pointer;', '🔓 暗号化された値を復号して編集する');
        note.type = 'button';
        first.closest('.d-block')?.prepend(note);
        note.addEventListener('click', async () => {
            try {
                const mk = await ensureUnlocked();
                for (const id of E2E_FIELD_IDS) {
                    const f = form.querySelector(`#${id}`);
                    if (f && f.value.startsWith(E2E_PREFIX)) {
                        f.value = await decrypt(f.value.slice(E2E_PREFIX.length), mk);
                    }
                }
                note.remove();
            } catch { /* キャンセル */ }
        });
    }
}

// ---- 一時表示（目ボタン・15秒で自動再伏字） ----

export async function handleRevealButton(btn) {
    const field = btn.closest('.copy-field');
    const span = field?.querySelector('.cf-v');
    if (!span) return;

    // 表示中 → 再伏字
    if (btn.dataset.revealed === '1') {
        clearTimeout(btn._revealTimer);
        span.textContent = btn.dataset.mask;
        btn.dataset.revealed = '';
        btn.textContent = '👁';
        return;
    }

    let value = btn.dataset.copy;
    try {
        if (value.startsWith(E2E_PREFIX)) {
            value = await decryptValue(value); // E2E暗号文はブラウザ内で復号
        }
    } catch {
        return; // アンロックのキャンセル時は何もしない
    }

    btn.dataset.mask = span.textContent;
    span.textContent = value;
    btn.dataset.revealed = '1';
    btn.textContent = '🙈';

    // 肩越し閲覧対策: 15秒後に自動で伏字に戻す
    btn._revealTimer = setTimeout(() => {
        span.textContent = btn.dataset.mask;
        btn.dataset.revealed = '';
        btn.textContent = '👁';
    }, 15000);
}

// ---- コピー（復号 + 自動クリア） ----

export async function handleCopyButton(btn) {
    const value = btn.dataset.copy;
    const done = () => {
        btn.textContent = 'コピー済';
        btn.classList.add('copied');
        setTimeout(() => { btn.textContent = 'コピー'; btn.classList.remove('copied'); }, 1500);
    };

    if (value.startsWith(E2E_PREFIX)) {
        try {
            const plain = await decryptValue(value);
            copyWithAutoExpiry(plain); // 45秒後に自動クリア（基準No.15）
            done();
        } catch { /* キャンセル */ }
    } else {
        copyWithAutoExpiry(value);
        done();
    }
}

// ---- 起動 ----

// ---- 既存データの一括E2E化（名義一覧のバナー） ----

async function isPasswordConfirmed() {
    try {
        const res = await fetch('/user/confirmed-password-status', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        const data = await res.json();
        return !!data.confirmed;
    } catch {
        return false;
    }
}

async function runMigration(banner, pending) {
    // 暗号文取得APIはpassword.confirm必須。未確認なら確認画面を経由させる
    if (!(await isPasswordConfirmed())) {
        window.location.href = '/user/confirm-password';
        return;
    }

    const status = banner.querySelector('[data-e2e-migrate-status]');
    try {
        const mk = await ensureUnlocked();
        let done = 0;

        for (const item of pending) {
            status.textContent = `E2E化中… (${done + 1}/${pending.length}) ${item.name}`;

            // 現在値を取得（レガシー行はサーバーが復号した平文が返る）
            const values = await api('GET', `/api/e2e/ciphertext/${item.id}`);
            const payload = {};
            for (const field of ['member_no', 'login_id', 'password']) {
                const v = values[field];
                if (v && !v.startsWith(E2E_PREFIX)) {
                    payload[field] = E2E_PREFIX + (await encrypt(v, mk));
                    // 会員番号は下3桁を一覧表示ヒントとして別送
                    if (field === 'member_no') {
                        payload.member_no_hint = v.slice(-3);
                    }
                }
            }
            if (Object.keys(payload).length > 0) {
                await api('POST', `/api/e2e/migrate/${item.id}`, payload);
            }
            done++;
        }

        status.textContent = `完了: ${done}件の名義をE2E暗号化しました`;
        setTimeout(() => window.location.reload(), 1200);
    } catch (e) {
        status.textContent = e.message === 'キャンセルされました'
            ? 'キャンセルしました'
            : `エラー: ${e.message || 'E2E化に失敗しました'}`;
    }
}

async function initMigrationBanner() {
    const container = document.querySelector('[data-e2e-migration-banner]');
    if (!container) return;

    try {
        const { pending } = await api('GET', '/api/e2e/migration-status');
        if (!pending || pending.length === 0) return;

        const banner = el('div',
            'background:#FFF3E0;border:1px solid #FFB74D;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:12px;line-height:1.7;');
        banner.appendChild(el('div', 'font-weight:700;color:#E65100;margin-bottom:4px;',
            `旧形式の名義が${pending.length}件あります`));
        banner.appendChild(el('div', 'color:#666;margin-bottom:8px;',
            'FC会員番号・ID・パスワードを、サーバーからも読めないE2E暗号化へ移行できます。'));
        const btn = el('button',
            'padding:8px 16px;border:none;border-radius:8px;background:#E65100;color:#fff;font-size:12px;font-weight:600;cursor:pointer;',
            'すべてE2E暗号化する');
        btn.type = 'button';
        const status = el('div', 'margin-top:6px;color:#666;');
        status.setAttribute('data-e2e-migrate-status', '');
        banner.append(btn, status);
        container.prepend(banner);

        btn.addEventListener('click', () => { btn.disabled = true; runMigration(banner, pending); });
    } catch { /* バナー表示失敗は致命的でないため握りつぶす */ }
}

function init() {
    document.querySelectorAll('form[data-e2e-form]').forEach(wireE2eForm);

    document.querySelectorAll('.reveal-btn[data-copy]').forEach((btn) => {
        btn.addEventListener('click', () => handleRevealButton(btn));
    });

    initMigrationBanner();
}

// vite moduleはDOMContentLoaded前後どちらでも実行されうる
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
