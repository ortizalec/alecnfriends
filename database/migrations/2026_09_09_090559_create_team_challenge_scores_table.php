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
        Schema::create('team_challenge_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('points')->default(1);
            $table->timestamps();

            $table->unique(['episode_id', 'user_id']);
        });

        $challengeActions = DB::table('cast_member_actions')->where('type', 'challenge_money')->get();

        foreach ($challengeActions->groupBy('cast_member_id') as $castMemberId => $actions) {
            $currentPoints = (int) DB::table('cast_members')->where('id', $castMemberId)->value('points');
            DB::table('cast_members')->where('id', $castMemberId)->update(['points' => max(0, $currentPoints - $actions->sum('points'))]);
        }

        foreach ($challengeActions->groupBy('episode_id') as $episodeId => $actions) {
            $userIds = DB::table('cast_member_user')->whereIn('cast_member_id', $actions->pluck('cast_member_id'))->distinct()->pluck('user_id');
            foreach ($userIds as $userId) {
                DB::table('team_challenge_scores')->insert(['episode_id' => $episodeId, 'user_id' => $userId, 'points' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (DB::table('cast_member_actions')->where('type', 'challenge_money')->get()->groupBy('cast_member_id') as $castMemberId => $actions) {
            DB::table('cast_members')->where('id', $castMemberId)->increment('points', $actions->sum('points'));
        }

        Schema::dropIfExists('team_challenge_scores');
    }
};
