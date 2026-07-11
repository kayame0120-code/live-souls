<?php

namespace App\Http\Controllers;

use App\Models\E2eAccessLog;
use App\Models\E2eKey;
use App\Models\FcMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class E2eKeyController extends Controller
{
    public function getKeys()
    {
        $key = E2eKey::where('user_id', Auth::id())->first();

        if (! $key) {
            return response()->json(['has_keys' => false]);
        }

        return response()->json([
            'has_keys' => true,
            'wrapped_master_key_pw' => $key->wrapped_master_key_pw,
            'pw_salt' => $key->pw_salt,
            // リカバリーキーによる復元フロー用（暗号文のみ・鍵平文は含まない）
            'wrapped_master_key_rk' => $key->wrapped_master_key_rk,
            'rk_salt' => $key->rk_salt,
        ]);
    }

    /**
     * E2Eセットアップ時のログインパスワード検証。
     * KDFに使うパスワードのタイポで鍵が開かなくなる事故を防ぐ
     * （ログインパスワード自体はサーバー既知のためNo.11の対象外）。
     */
    public function verifyPassword(Request $request)
    {
        $rateLimitKey = 'e2e-verify-pw:' . Auth::id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            return response()->json(['error' => '試行回数が多すぎます。しばらくしてから再度お試しください。'], 429);
        }
        RateLimiter::hit($rateLimitKey, 60);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        return response()->json([
            'valid' => Hash::check($validated['password'], Auth::user()->password),
        ]);
    }

    public function storeKeys(Request $request)
    {
        $validated = $request->validate([
            'wrapped_master_key_pw' => ['required', 'string'],
            'pw_salt' => ['required', 'string'],
            'wrapped_master_key_rk' => ['required', 'string'],
            'rk_salt' => ['required', 'string'],
        ]);

        $existing = E2eKey::where('user_id', Auth::id())->first();
        if ($existing) {
            return response()->json(['error' => '鍵は既に登録済みです'], 409);
        }

        E2eKey::create([
            'user_id' => Auth::id(),
            ...$validated,
        ]);

        return response()->json(['ok' => true]);
    }

    public function rewrapKeys(Request $request)
    {
        $validated = $request->validate([
            'wrapped_master_key_pw' => ['required', 'string'],
            'pw_salt' => ['required', 'string'],
        ]);

        $key = E2eKey::where('user_id', Auth::id())->firstOrFail();
        $key->update($validated);

        return response()->json(['ok' => true]);
    }

    /**
     * 旧形式（E2E化前）の名義一覧を返す（一括E2E化のUI用）。
     * 3フィールドのいずれかが非nullかつ"e2e:"でない行が対象。
     */
    public function migrationStatus()
    {
        $pending = FcMembership::with('person')
            ->get()
            ->filter(function ($m) {
                $raw = $m->getRawOriginal();
                foreach (['member_no', 'login_id', 'password'] as $field) {
                    $value = $raw[$field] ?? null;
                    if ($value !== null && $value !== '' && ! FcMembership::isE2eValue($value)) {
                        return true;
                    }
                }
                return false;
            })
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->displayName()])
            ->values();

        return response()->json(['pending' => $pending]);
    }

    /**
     * 旧形式の名義をE2E形式へ移行する。
     * 受け付けるのは"e2e:"プレフィックス付き暗号文のみ（平文は拒否）。
     */
    public function migrate(Request $request, FcMembership $fcMembership)
    {
        abort_unless($fcMembership->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'member_no' => ['nullable', 'string', 'starts_with:' . FcMembership::E2E_PREFIX],
            'login_id' => ['nullable', 'string', 'starts_with:' . FcMembership::E2E_PREFIX],
            'password' => ['nullable', 'string', 'starts_with:' . FcMembership::E2E_PREFIX],
        ], [
            'member_no.starts_with' => 'E2E暗号文のみ受け付けます',
            'login_id.starts_with' => 'E2E暗号文のみ受け付けます',
            'password.starts_with' => 'E2E暗号文のみ受け付けます',
        ]);

        $update = array_filter($validated, fn ($v) => $v !== null && $v !== '');
        if (! empty($update)) {
            $fcMembership->update($update);
        }

        E2eAccessLog::create([
            'user_id' => Auth::id(),
            'fc_membership_id' => $fcMembership->id,
            'action' => 'migrate_to_e2e',
            'ip_address' => request()->ip(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function getCiphertext(FcMembership $fcMembership)
    {
        abort_unless($fcMembership->user_id === Auth::id(), 403);

        $rateLimitKey = 'e2e-ciphertext:' . Auth::id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
            return response()->json(['error' => 'レート制限を超えました。しばらくしてから再度お試しください。'], 429);
        }
        RateLimiter::hit($rateLimitKey, 60);

        E2eAccessLog::create([
            'user_id' => Auth::id(),
            'fc_membership_id' => $fcMembership->id,
            'action' => 'get_ciphertext',
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'member_no' => $fcMembership->member_no,
            'login_id' => $fcMembership->login_id,
            'password' => $fcMembership->password,
        ]);
    }
}
