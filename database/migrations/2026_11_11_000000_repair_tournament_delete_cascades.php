<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite must disable FK enforcement before starting the table-rebuild transaction.
    public $withinTransaction = false;

    public function up(): void
    {
        $repair = function (): void {
            foreach ([
                ['tournament_users', 'tournament_id', 'tournaments'],
                ['rounds', 'tournament_id', 'tournaments'],
                ['round_matches', 'round_id', 'rounds'],
            ] as [$table, $column, $parent]) {
                $foreign = collect(Schema::getForeignKeys($table))->first(fn ($key) => $key['columns'] === [$column]);
                if ($foreign && strtolower($foreign['on_delete']) === 'cascade') {
                    continue;
                }
                Schema::table($table, function (Blueprint $blueprint) use ($column, $parent, $foreign): void {
                    if ($foreign) {
                        $blueprint->dropForeign([$column]);
                    }
                    $blueprint->foreign($column)->references('id')->on($parent)->cascadeOnDelete();
                });
            }
            if (DB::getDriverName() === 'sqlite' && DB::select('PRAGMA foreign_key_check')) {
                throw new RuntimeException('Foreign key validation failed; tournament cascade repair rolled back.');
            }
        };

        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints(fn () => DB::transaction($repair));
        } else {
            $repair();
        }
    }

    public function down(): void
    {
        // This repairs drift from the original declared schema. Do not restore broken constraints.
    }
};
