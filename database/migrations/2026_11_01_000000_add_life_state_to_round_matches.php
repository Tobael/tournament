<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('round_matches', function (Blueprint $table) {
            $table->json('life_state')->nullable();
            $table->unsignedInteger('life_revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('round_matches', fn (Blueprint $table) => $table->dropColumn(['life_state', 'life_revision']));
    }
};
