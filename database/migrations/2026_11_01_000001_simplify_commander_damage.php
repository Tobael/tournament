<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('round_matches')->whereNotNull('life_state')->orderBy('id')->chunkById(100, function ($matches) {
            foreach ($matches as $match) {
                $state = json_decode($match->life_state, true, flags: JSON_THROW_ON_ERROR);
                $changed = false;
                foreach ($state as &$seat) {
                    $damage = [];
                    foreach ($seat['damage'] ?? [] as $source => $value) {
                        $opponent = explode(':', (string) $source)[0];
                        $changed = $changed || str_contains((string) $source, ':');
                        $damage[$opponent] = ($damage[$opponent] ?? 0) + $value;
                    }
                    $seat['damage'] = $damage;
                }
                unset($seat);
                if ($changed) {
                    DB::table('round_matches')->where('id', $match->id)->update([
                        'life_state' => json_encode($state, JSON_THROW_ON_ERROR),
                        'life_revision' => $match->life_revision + 1,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Opponent totals remain valid; the removed per-commander split is not restored.
    }
};
