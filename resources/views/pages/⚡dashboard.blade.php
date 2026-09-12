<?php

use App\Models\CastMemberAction;
use App\Models\Episode;
use App\Models\Poll;
use App\Models\TraitorPredictionRound;
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
            ->with('castMembers')
            ->withSum('castMembers', 'points')
            ->withSum('predictions', 'points')
            ->withSum('traitorPredictions', 'points')
            ->withSum('teamChallengeScores', 'points')
            ->orderByRaw('COALESCE(cast_members_sum_points, 0) + COALESCE(predictions_sum_points, 0) + COALESCE(traitor_predictions_sum_points, 0) + COALESCE(team_challenge_scores_sum_points, 0) DESC')
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
    public function hasPendingTraitorPrediction(): bool
    {
        return TraitorPredictionRound::query()
            ->where('is_open', true)
            ->whereDoesntHave('predictions', fn ($query) => $query->where('user_id', Auth::id()))
            ->exists();
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
    @if ($this->hasPendingTraitorPrediction)
        <flux:callout variant="warning" icon="eye" heading="{{ __('Who do you think the traitors are?') }}">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><span>{{ __('Choose your three traitor picks before the opening prediction closes.') }}</span><flux:button :href="route('predictions.edit')" wire:navigate size="sm" variant="primary">{{ __('Pick the traitors') }}</flux:button></div>
        </flux:callout>
    @endif
    @if ($this->pendingSurveyCount > 0)
        <flux:callout variant="warning" icon="clipboard-document-list" heading="{{ trans_choice(':count survey needs your response|:count surveys need your response', $this->pendingSurveyCount, ['count' => $this->pendingSurveyCount]) }}">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><span>{{ __('Submit your choices before the surveys close.') }}</span><flux:button :href="route('surveys.index')" wire:navigate size="sm" variant="primary">{{ __('Complete surveys') }}</flux:button></div>
        </flux:callout>
    @endif

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full min-w-2xl table-fixed text-left">
            <thead class="bg-zinc-50 text-sm font-medium text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                <tr>
                    <th scope="col" class="w-1/4 px-4 py-3 font-medium">{{ __('Player') }}</th>
                    <th scope="col" class="w-3/5 px-4 py-3 font-medium">{{ __('Team') }}</th>
                    <th scope="col" class="px-4 py-3 text-right font-medium">{{ __('Points') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->leaderboard as $player)
                    <tr wire:key="leaderboard-{{ $player->id }}">
                        <td class="px-4 py-4"><flux:heading class="truncate">{{ $player->name }}</flux:heading></td>
                        <td class="px-4 py-4">
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($player->castMembers->sortByDesc('points') as $castMember)
                                    <flux:badge wire:key="leaderboard-{{ $player->id }}-{{ $castMember->id }}" color="zinc" :href="route('cast-members.show', $castMember)" wire:navigate>{{ $castMember->name }}</flux:badge>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-4 text-right text-xl font-bold">{{ ($player->cast_members_sum_points ?? 0) + ($player->predictions_sum_points ?? 0) + ($player->traitor_predictions_sum_points ?? 0) + ($player->team_challenge_scores_sum_points ?? 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="p-8 text-center"><flux:text>{{ __('No player teams have been created yet.') }}</flux:text></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <section class="flex flex-col gap-3">
        <div><flux:heading size="lg">{{ __('Live cast activity') }}</flux:heading><flux:text>{{ __('The latest scoring actions from the current season.') }}</flux:text></div>
        <x-cast-member-action-log :actions="$this->recentActions" />
    </section>
</div>
