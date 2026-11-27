<?php

declare(strict_types=1);

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
        Schema::table('todos', function (Blueprint $table): void {
            // Nullable so the column can be added to a table that already has
            // rows (a NOT NULL column without a default fails on PostgreSQL
            // and SQLite). Pre-existing rows stay unowned and are therefore
            // unreachable through the API until they are backfilled; switch
            // to NOT NULL in a later migration once that is done.
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            // The listing endpoint filters by owner and sorts newest first.
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropColumn('user_id');
        });
    }
};
