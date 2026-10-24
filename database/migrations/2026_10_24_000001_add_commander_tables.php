<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('table_preference')->default('balanced');
            $table->unsignedTinyInteger('round_limit')->default(3);
        });
        Schema::table('round_matches', fn (Blueprint $table) => $table->json('commander_scores')->nullable());
        Schema::create('round_match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_user_id')->constrained()->cascadeOnDelete();
            $table->unique(['round_match_id', 'tournament_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_match_players');
        Schema::table('round_matches', fn (Blueprint $table) => $table->dropColumn('commander_scores'));
        Schema::table('tournaments', fn (Blueprint $table) => $table->dropColumn(['table_preference', 'round_limit']));
    }
};
