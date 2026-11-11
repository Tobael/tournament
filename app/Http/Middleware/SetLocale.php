<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->session()->get('locale', $request->cookie('arcane_locale', 'de'));
        app()->setLocale(in_array($locale, ['de', 'en'], true) ? $locale : 'de');

        return $next($request);
    }
}
