<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next)
    {
        $nonce = Str::random(32);
        $request->attributes->set('csp-nonce', $nonce);
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                // 'wasm-unsafe-eval'はlibsodium.js(WebAssembly)のコンパイル許可のみ。
                // JSのeval()は引き続き禁止（'unsafe-eval'とは別のスコープ済みディレクティブ）
                "script-src 'self' 'nonce-{$nonce}' 'wasm-unsafe-eval'",
                "script-src-attr 'unsafe-inline'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com",
                "img-src 'self' data: blob:",
                "connect-src 'self' https://maps.googleapis.com",
                "frame-src 'none'",
                "object-src 'none'",
                "base-uri 'self'",
            ]));
        }

        return $response;
    }
}
