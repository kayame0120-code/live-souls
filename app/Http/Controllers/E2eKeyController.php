<?php

namespace App\Http\Controllers;

use App\Models\E2eAccessLog;
use App\Models\E2eKey;
use App\Models\FcMembership;
use App\Models\Person;
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

        $pendingPersons = Person::withoutGlobalScopes()
            ->where('user_id', Auth::id())
            ->get()
            ->filter(function ($p) {
                $raw = $p->getRawOriginal();
                foreach (['phone', 'address'] as $field) {
                    $value = $raw[$field] ?? null;
                    if ($value !== null && $value !== '' && ! Person::isE2eValue($value)) {
                        return true;
                    }
                }
                return false;
            })
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'type' => 'person'])
            ->values();

        $merged = $pending->merge($pendingPersons);

        return response()->json(['pending' => $merged]);
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
            // 会員番号の下3桁ヒント（一覧表示用・平文・低機微）
            'member_no_hint' => ['nullable', 'string', 'max:3'],
        ], [
            'member_no.starts_with' => 'E2E暗号文のみ受け付けます',
            'login_id.starts_with' => 'E2E暗号文のみ受け付けます',
            'password.starts_with' => 'E2E暗号文のみ受け付けます',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($fcMembership, $validated) {
            $raw = $fcMembership->getRawOriginal();

            // 移行対象のレガシーフィールドを特定
            $legacyFields = [];
            foreach (['member_no', 'login_id', 'password'] as $field) {
                $v = $raw[$field] ?? null;
                if ($v !== null && $v !== '' && ! FcMembership::isE2eValue($v)) {
                    $legacyFields[] = $field;
                }
            }

            // レガシー値が存在するフィールドは全てE2E値が送られていなければ拒否
            // （部分送信によるデータ喪失を防止）
            $missingE2e = array_filter($legacyFields, fn ($f) => empty($validated[$f]));
            if (! empty($missingE2e)) {
                abort(422, '移行対象の全フィールド(' . implode('/', $missingE2e) . ')にE2E暗号文を送ってください');
            }

            $update = [];
            foreach (['member_no', 'login_id', 'password'] as $field) {
                if (! empty($validated[$field])) {
                    $update[$field] = $validated[$field];
                }
            }

            if (! empty($validated['member_no_hint'])) {
                $update['member_no_hint'] = mb_substr($validated['member_no_hint'], -3);
            }

            if (! empty($update)) {
                $fcMembership->update($update);
            }

            E2eAccessLog::create([
                'user_id' => Auth::id(),
                'fc_membership_id' => $fcMembership->id,
                'action' => 'migrate_to_e2e',
                'ip_address' => request()->ip(),
            ]);
        });

        return response()->json(['ok' => true]);
    }

    public function getPersonCiphertext(Person $person)
    {
        abort_unless($person->user_id === Auth::id(), 403);

        $rateLimitKey = 'e2e-ciphertext:' . Auth::id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
            return response()->json(['error' => 'レート制限を超えました。しばらくしてから再度お試しください。'], 429);
        }
        RateLimiter::hit($rateLimitKey, 60);

        return response()->json([
            'phone' => $person->phone,
            'address' => $person->address,
        ]);
    }

    public function migratePerson(Request $request, Person $person)
    {
        abort_unless($person->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'starts_with:' . Person::E2E_PREFIX],
            'address' => ['nullable', 'string', 'starts_with:' . Person::E2E_PREFIX],
        ], [
            'phone.starts_with' => 'E2E暗号文のみ受け付けます',
            'address.starts_with' => 'E2E暗号文のみ受け付けます',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($person, $validated) {
            $raw = $person->getRawOriginal();

            $legacyFields = [];
            foreach (['phone', 'address'] as $field) {
                $v = $raw[$field] ?? null;
                if ($v !== null && $v !== '' && ! Person::isE2eValue($v)) {
                    $legacyFields[] = $field;
                }
            }

            $missingE2e = array_filter($legacyFields, fn ($f) => empty($validated[$f]));
            if (! empty($missingE2e)) {
                abort(422, '移行対象の全フィールド(' . implode('/', $missingE2e) . ')にE2E暗号文を送ってください');
            }

            $update = [];
            foreach (['phone', 'address'] as $field) {
                if (! empty($validated[$field])) {
                    $update[$field] = $validated[$field];
                }
            }

            if (! empty($update)) {
                $person->update($update);
            }
        });

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
