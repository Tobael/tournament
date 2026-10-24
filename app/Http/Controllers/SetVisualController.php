<?php

namespace App\Http\Controllers;

use App\Services\MagicSetVisuals;

class SetVisualController extends Controller
{
    public function artwork(string $code, MagicSetVisuals $visuals)
    {
        return response()->json($visuals->artwork($code))->header('Cache-Control', 'public, max-age=300');
    }

    public function symbol(string $code, MagicSetVisuals $visuals)
    {
        $svg = $visuals->symbol($code);
        abort_unless($svg, 404);

        return response($svg)->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox")
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
