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
        Schema::create('traitor_prediction_rounds', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_open')->default(false)->index();
            $table->foreignId('first_traitor_cast_member_id')->nullable()->constrained('cast_members');
            $table->foreignId('second_traitor_cast_member_id')->nullable()->constrained('cast_members');
            $table->foreignId('third_traitor_cast_member_id')->nullable()->constrained('cast_members');
            $table->timestamp('revealed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traitor_prediction_rounds');
    }
};
