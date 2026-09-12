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
        Schema::create('traitor_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traitor_prediction_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('first_cast_member_id')->constrained('cast_members');
            $table->foreignId('second_cast_member_id')->constrained('cast_members');
            $table->foreignId('third_cast_member_id')->constrained('cast_members');
            $table->unsignedTinyInteger('points')->default(0)->index();
            $table->timestamps();

            $table->unique(['traitor_prediction_round_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traitor_predictions');
    }
};
