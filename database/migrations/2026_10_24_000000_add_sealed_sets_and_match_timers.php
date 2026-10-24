<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('format')->default('sealed');
            $table->string('set_code', 10)->nullable();
            $table->string('set_name')->nullable();
        });
        Schema::table('round_matches', function (Blueprint $table) {
            $table->timestamp('timer_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('round_matches', fn (Blueprint $table) => $table->dropColumn('timer_ends_at'));
        Schema::table('tournaments', fn (Blueprint $table) => $table->dropColumn(['format', 'set_code', 'set_name']));
    }
};
