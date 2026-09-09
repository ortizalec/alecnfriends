<?php

use App\Models\CastMemberAction;
use App\Models\Episode;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Leaderboard')] class extends Component {
    /** @return Collection<int, User> */
    #[Computed]
    public function leaderboard(): Collection
    {
        return User::query()
            ->where('is_admin', false)
            ->with('castMembers')
            ->withSum('castMembers', 'points')
            ->withSum('predictions', 'points')
            ->withSum('teamChallengeScores', 'points')
            ->orderByRaw('COALESCE(cast_members_sum_points, 0) + COALESCE(predictions_sum_points, 0) + COALESCE(team_challenge_scores_sum_points, 0) DESC')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function hasPendingPrediction(): bool
    {
        $episode = Episode::query()->where('predictions_are_open', true)
            ->where(fn ($query) => $query->whereNull('predictions_close_at')->orWhere('predictions_close_at', '>', now()))
            ->latest('number')->first();

        return $episode && ! $episode->predictions()->where('user_id', Auth::id())->exists();
    }

    #[Computed]
    public function pendingSurveyCount(): int
    {
        return Poll::query()->open()
            ->whereDoesntHave('pollResponses', fn ($query) => $query->where('user_id', Auth::id()))
            ->count();
    }

    /** @return Collection<int, CastMemberAction> */
    #[Computed]
    public function recentActions(): Collection
    {
        return CastMemberAction::query()->with(['castMember', 'episode'])->latest()->limit(20)->get();
    }
};
?>

<div wire:poll.5s class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('League leaderboard') }}</flux:heading>
        <flux:text>{{ __('Team scores are the combined points earned by each player’s five cast members.') }}</flux:text>
    </div>

    @if ($this->hasPendingPrediction)
        <flux:callout variant="warning" icon="clock" heading="{{ __('Your episode prediction is waiting') }}">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><span>{{ __('Complete it before the prediction window closes.') }}</span><flux:button :href="route('predictions.edit')" wire:navigate size="sm" variant="primary">{{ __('Make predictions') }}</flux:button></div>
        </flux:callout>
    @endif
    @if ($this->pendingSurveyCount > 0)
        <flux:callout variant="warning" icon="clipboard-document-list" heading="{{ trans_choice(':count survey needs your response|:count surveys need your response', $this->pendingSurveyCount, ['count' => $this->pendingSurveyCount]) }}">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><span>{{ __('Submit your choices before the surveys close.') }}</span><flux:button :href="route('surveys.index')" wire:navigate size="sm" variant="primary">{{ __('Complete surveys') }}</flux:button></div>
        </flux:callout>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="grid grid-cols-[3rem_1fr_auto] gap-4 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400 sm:grid-cols-[4rem_1fr_2fr_6rem]">
            <span>{{ __('Rank') }}</span><span>{{ __('Player') }}</span><span class="hidden sm:block">{{ __('Team') }}</span><span class="text-right">{{ __('Points') }}</span>
        </div>
        @forelse ($this->leaderboard as $player)
            <div wire:key="leaderboard-{{ $player->id }}" class="grid grid-cols-[3rem_1fr_auto] items-center gap-4 border-t border-zinc-200 px-4 py-4 dark:border-zinc-700 sm:grid-cols-[4rem_1fr_2fr_6rem]">
                <span class="text-lg font-bold text-zinc-500">#{{ $loop->iteration }}</span>
                <div class="min-w-0"><flux:heading class="truncate">{{ $player->name }}</flux:heading><flux:text size="sm">{{ $player->castMembers->count() }}/5 selected</flux:text></div>
                <div class="hidden flex-wrap gap-1.5 sm:flex">
                    @foreach ($player->castMembers->sortByDesc('points') as $castMember)
                        <flux:badge wire:key="leaderboard-{{ $player->id }}-{{ $castMember->id }}" color="zinc">{{ $castMember->name }}</flux:badge>
                    @endforeach
                </div>
                <div class="text-right"><div class="text-xl font-bold">{{ ($player->cast_members_sum_points ?? 0) + ($player->predictions_sum_points ?? 0) + ($player->team_challenge_scores_sum_points ?? 0) }}</div><flux:text size="sm">{{ $player->predictions_sum_points ?? 0 }} prediction · {{ $player->team_challenge_scores_sum_points ?? 0 }} challenge</flux:text></div>
            </div>
        @empty
            <div class="p-8 text-center"><flux:text>{{ __('No player teams have been created yet.') }}</flux:text></div>
        @endforelse
    </div>

    <section class="flex flex-col gap-3">
        <div><flux:heading size="lg">{{ __('Live cast activity') }}</flux:heading><flux:text>{{ __('The latest scoring actions from the current season.') }}</flux:text></div>
        <x-cast-member-action-log :actions="$this->recentActions" />
    </section>
</div>
