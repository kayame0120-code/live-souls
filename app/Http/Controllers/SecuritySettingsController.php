<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/**
 * セキュリティ設定画面（2FA有効化・基準No.13）。
 * 2FAの有効化/確認/無効化の実処理はFortify標準ルートが担い、
 * 本コントローラは状態表示のみを行う。
 */
class SecuritySettingsController extends Controller
{
    public function show()
    {
        $user = Auth::user();

        // 2FAの状態: disabled（未設定）/ pending（QR表示済み・コード未確認）/ enabled（確認済み）
        $state = 'disabled';
        if ($user->two_factor_secret) {
            $state = $user->two_factor_confirmed_at ? 'enabled' : 'pending';
        }

        $qrCodeSvg = $state === 'pending' ? $user->twoFactorQrCodeSvg() : null;
        $recoveryCodes = $state === 'enabled' ? $user->recoveryCodes() : [];

        return view('settings.security', compact('state', 'qrCodeSvg', 'recoveryCodes'));
    }
}
