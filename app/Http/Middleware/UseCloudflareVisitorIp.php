<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseCloudflareVisitorIp
{
    public function handle(Request $request, Closure $next): Response
    {
        // The public Traefik routes verify the Cloudflare peer. Anubis can
        // rewrite X-Forwarded-For, so use the verified single-address header
        // for Laravel's per-visitor login, recovery and participant limits.
        $visitor = $request->header('CF-Connecting-IP');
        if (is_string($visitor) && filter_var($visitor, FILTER_VALIDATE_IP)) {
            $request->headers->set('X-Forwarded-For', $visitor);
        }

        return $next($request);
    }
}
