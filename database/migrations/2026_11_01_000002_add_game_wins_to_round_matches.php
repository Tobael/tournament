<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('round_matches', fn (Blueprint $table) => $table->json('game_wins')->nullable());
    }

    public function down(): void
    {
        Schema::table('round_matches', fn (Blueprint $table) => $table->dropColumn('game_wins'));
    }
};
