<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('auth.login'));
        // password.confirm ミドルウェア用（名義詳細・暗号文取得APIの再認証・基準No.14）
        Fortify::confirmPasswordView(function (Request $request) {
            if (! session('url.intended')) {
                $referer = $request->headers->get('referer');
                if ($referer && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
                    session(['url.intended' => $referer]);
                }
            }
            return view('auth.confirm-password');
        });
        // 2FA(TOTP)ログイン時のコード入力画面（基準No.13）
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
