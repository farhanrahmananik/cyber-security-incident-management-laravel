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
        Schema::create('incident_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->index(['incident_id', 'assigned_at']);
            $table->index(['assigned_to_id', 'assigned_at']);
            $table->index('assigned_by_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_assignments');
    }
};
