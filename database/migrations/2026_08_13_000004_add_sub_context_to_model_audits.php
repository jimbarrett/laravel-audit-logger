<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Gives the write side the same second tenancy dimension the read side records, so
     * both tables can answer "which location did this happen at".
     */
    public function up(): void
    {
        Schema::table('model_audits', function (Blueprint $table) {
            $table->unsignedBigInteger('sub_context_id')->nullable()->after('context_id');
            $table->index('sub_context_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_audits', function (Blueprint $table) {
            $table->dropIndex(['sub_context_id']);
            $table->dropColumn('sub_context_id');
        });
    }
};
