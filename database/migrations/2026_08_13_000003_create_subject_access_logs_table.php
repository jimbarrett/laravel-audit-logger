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
        Schema::create('subject_access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('user_id');
            $table->string('access_type');
            $table->string('ip_address', 45)->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->unsignedBigInteger('sub_context_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
            $table->index('access_type');
            $table->index('created_at');
            $table->index('context_id');

            // The two questions an auditor actually asks: everyone who touched this
            // record, and everything this user looked at.
            $table->index(['subject_type', 'subject_id', 'user_id', 'created_at'], 'subject_access_logs_subject_user_time_index');
            $table->index(['user_id', 'created_at']);
        });

        // No foreign keys by design. Cascading from patients or users would delete the
        // record of who accessed what at exactly the moment that record matters most.
        // This matches model_audits, which also carries no foreign key to its subject.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_access_logs');
    }
};
