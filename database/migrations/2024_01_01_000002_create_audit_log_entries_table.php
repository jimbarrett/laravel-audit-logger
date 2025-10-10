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
        Schema::create('model_audit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_audit_id')->constrained('model_audits')->onDelete('cascade');
            $table->string('updated_field');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();

            $table->index('model_audit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_audit_entries');
    }
};
