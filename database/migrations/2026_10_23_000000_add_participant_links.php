<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', fn (Blueprint $table) => $table->uuid('invite_token')->nullable()->unique());
        Schema::table('tournament_users', fn (Blueprint $table) => $table->string('recovery_hash', 64)->nullable()->unique());
        DB::table('tournaments')->orderBy('id')->each(function ($tournament) {
            DB::table('tournaments')->where('id', $tournament->id)->update(['invite_token' => (string) Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('tournament_users', fn (Blueprint $table) => $table->dropColumn('recovery_hash'));
        Schema::table('tournaments', fn (Blueprint $table) => $table->dropColumn('invite_token'));
    }
};
