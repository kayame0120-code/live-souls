<?php

namespace App\Http\Controllers;

use App\Models\E2eAccessLog;
use App\Models\E2eKey;
use App\Models\FcMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
