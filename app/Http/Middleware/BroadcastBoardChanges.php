<?php

namespace App\Http\Middleware;

use App\Events\BoardChanged;
use Closure;
use Illuminate\Http\Request;

class BroadcastBoardChanges
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $livewire = $request->routeIs('*livewire.update');
        $mutation = ! $livewire || collect($request->input('components', []))
            ->flatMap(fn ($component) => $component['calls'] ?? [])
            ->contains(fn ($call) => ! in_array($call['method'] ?? '', ['refresh', '$refresh'], true));
        // After the action and its transactions finish. Life has its own event.
        if ($mutation && ! $request->isMethodSafe() && $response->getStatusCode() < 400
            && ! $request->routeIs('play.life.update')
            && ($livewire || $request->routeIs('play.join'))) {
            rescue(fn () => event(new BoardChanged), report: true);
        }

        return $response;
    }
}
