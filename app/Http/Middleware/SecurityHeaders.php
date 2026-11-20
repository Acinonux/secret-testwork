<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Заголовки безопасности. Ключ секрета живёт в location.hash, поэтому главная
 * защита — не допустить выполнения чужого JS (строгий CSP без inline-скриптов).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->csp(),
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            // Страницы и ответы API не должны оседать в кешах браузера и прокси.
            'Cache-Control' => 'no-store, max-age=0',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    private function csp(): string
    {
        $script = ["'self'"];
        $style = ["'self'", "'unsafe-inline'"];
        $connect = ["'self'"];

        // В dev-режиме ассеты и HMR идут с Vite dev-сервера.
        if (Vite::isRunningHot()) {
            $origin = rtrim(trim((string) @file_get_contents(Vite::hotFile())), '/');
            $script[] = $origin;
            $style[] = $origin;
            $connect[] = $origin;
            $connect[] = preg_replace('#^http#', 'ws', $origin);
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            "img-src 'self' data: blob:",
            "font-src 'self'",
            'connect-src '.implode(' ', $connect),
            "object-src 'none'",
            "base-uri 'none'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
