<?php

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
        Schema::table('incidents', function (Blueprint $table) {
            $table->foreignId('current_assigned_to_id')
                ->nullable()
                ->after('reporter_id')
                ->index()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropForeign(['current_assigned_to_id']);
            $table->dropIndex(['current_assigned_to_id']);
            $table->dropColumn('current_assigned_to_id');
        });
    }
};
