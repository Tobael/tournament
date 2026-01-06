<?php

use App\Enums\RoundMatchResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('round_matches', function (Blueprint $table) {
            $table->id();
            $table->enum('result', RoundMatchResult::cases())->nullable();
            $table->foreignId('round_id')->constrained();
            $table->foreignId('player_a_id')->constrained(table: 'tournament_users', column: 'id');
            $table->foreignId('player_b_id')->nullable()->constrained(table: 'tournament_users', column: 'id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_matches');
    }
};
