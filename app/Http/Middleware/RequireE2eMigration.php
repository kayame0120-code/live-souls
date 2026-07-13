<?php

namespace App\Http\Middleware;

use App\Models\FcMembership;
use App\Models\Person;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 未移行のレガシー名義行がある場合、移行画面以外へのアクセスをブロックする。
 * 名義情報をDB上に平文相当で残さないことの強制（security_criteria v1.1 基準No.1/No.11）。
 */
class RequireE2eMigration
{
    private const ALLOWED_ROUTES = [
        'home',
        'logout',
        'e2e.migrate-page',
        'api.e2e.*',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';

        foreach (self::ALLOWED_ROUTES as $pattern) {
            if (str_ends_with($pattern, '*')) {
                if (str_starts_with($routeName, rtrim($pattern, '*'))) {
                    return $next($request);
                }
            } elseif ($routeName === $pattern) {
                return $next($request);
            }
        }

        $userId = Auth::id();
        $hasLegacy = FcMembership::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where(function ($q) {
                $q->where(function ($q) { $q->whereNotNull('member_no')->where('member_no', 'NOT LIKE', 'e2e:%')->where('member_no', '!=', ''); })
                  ->orWhere(function ($q) { $q->whereNotNull('login_id')->where('login_id', 'NOT LIKE', 'e2e:%')->where('login_id', '!=', ''); })
                  ->orWhere(function ($q) { $q->whereNotNull('password')->where('password', 'NOT LIKE', 'e2e:%')->where('password', '!=', ''); });
            })
            ->exists();

        if ($hasLegacy) {
            return redirect()->route('e2e.migrate-page');
        }

        $hasLegacyPerson = Person::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where(function ($q) {
                $q->where(function ($q) { $q->whereNotNull('phone')->where('phone', 'NOT LIKE', 'e2e:%')->where('phone', '!=', ''); })
                  ->orWhere(function ($q) { $q->whereNotNull('address')->where('address', 'NOT LIKE', 'e2e:%')->where('address', '!=', ''); });
            })
            ->exists();

        if ($hasLegacyPerson) {
            return redirect()->route('e2e.migrate-page');
        }

        return $next($request);
    }
}
