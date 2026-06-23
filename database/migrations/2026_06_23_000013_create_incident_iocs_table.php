<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('incident_iocs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('type');
            $table->string('value', 2048);
            $table->text('description')->nullable();
            $table->string('confidence')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('incident_id');
            $table->index('type');
            $table->index('created_by_id');
            $table->index('first_seen_at');
            $table->index('last_seen_at');
        });

        $this->createValueIndexes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_iocs');
    }

    /**
     * Create value indexes while keeping long IOC values compatible with MySQL.
     */
    private function createValueIndexes(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX incident_iocs_value_index ON incident_iocs (`value`(768))');
            DB::statement('CREATE INDEX incident_iocs_incident_type_value_index ON incident_iocs (`incident_id`, `type`(64), `value`(512))');

            return;
        }

        Schema::table('incident_iocs', function (Blueprint $table) {
            $table->index('value');
            $table->index(['incident_id', 'type', 'value'], 'incident_iocs_incident_type_value_index');
        });
    }
};
