<?php

namespace App\Http\Controllers;

use App\Services\MagicSetCards;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class SetCardController extends Controller
{
    public function topPulls(string $code, MagicSetCards $cards)
    {
        return response()->json($cards->topPulls($code))->header('Cache-Control', 'public, max-age=300');
    }

    public function lookup(Request $request, string $code, MagicSetCards $cards)
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:16', 'regex:/^[a-zA-Z0-9★*†-]+$/u'],
            'subset' => ['nullable', 'regex:/^[a-z0-9]{2,10}$/'],
        ], ['number.required' => 'Bitte eine Kartennummer eingeben.', 'number.regex' => 'Bitte die Kartennummer wie auf der Karte eingeben, z. B. 119 oder 119a.', 'number.max' => 'Die Kartennummer ist zu lang.']);
        try {
            $card = $cards->lookup($code, $data['subset'] ?? $code, $data['number']);
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Preise gerade nicht erreichbar. Bitte erneut versuchen.'], 503);
        }
        if (! $card) {
            return response()->json(['message' => 'Diese Kartennummer wurde im gewählten Set nicht gefunden.'], 404);
        }

        return response()->json(['card' => $card, 'updated_at' => $card['priced_at']]);
    }
}
