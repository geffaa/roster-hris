<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Server-side texts (flash messages, emails, payslip PDFs) follow the signed-in person's language. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()->locale ?? null;
        if (in_array($locale, ['en', 'id'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
