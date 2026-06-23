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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number')->unique();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('incident_category_id')->constrained('incident_categories')->restrictOnDelete();
            $table->foreignId('severity_level_id')->constrained('severity_levels')->restrictOnDelete();
            $table->foreignId('priority_level_id')->constrained('priority_levels')->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->text('impact_summary')->nullable();
            $table->string('affected_system')->nullable();
            $table->dateTime('occurred_at')->nullable()->index();
            $table->dateTime('detected_at')->nullable()->index();
            $table->string('status')->default('reported')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['reporter_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
