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
        Schema::table('episodes', function (Blueprint $table) {
            $table->foreignId('murdered_cast_member_id')->nullable()->constrained('cast_members')->nullOnDelete();
            $table->foreignId('banished_cast_member_id')->nullable()->constrained('cast_members')->nullOnDelete();
            $table->foreignId('breakfast_cast_member_id')->nullable()->constrained('cast_members')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('murdered_cast_member_id');
            $table->dropConstrainedForeignId('banished_cast_member_id');
            $table->dropConstrainedForeignId('breakfast_cast_member_id');
        });
    }
};
