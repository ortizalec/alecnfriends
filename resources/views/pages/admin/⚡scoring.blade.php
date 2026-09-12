<?php

use App\Actions\ScoreEpisodePredictions;
use App\Actions\RecalculateVoteScoring;
use App\Actions\RecalculateTeamChallengeScores;
use App\CastMemberActionType;
use App\CastMemberStatus;
use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\Episode;
use App\Models\RoundTableVote;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Live Scoring')] class extends Component {
    public string $section = 'actions';
    public ?int $episodeId = null;
    public ?int $castMemberId = null;
    public string $actionType = 'shield';
    public ?int $murderedCastMemberId = null;
    public ?int $banishedCastMemberId = null;
    public ?int $breakfastCastMemberId = null;
    public ?int $voterCastMemberId = null;
    public ?int $voteTargetCastMemberId = null;

    public function mount(): void
    {
        $this->section = match (true) {
            request()->routeIs('admin.scoring.results') => 'results',
            request()->routeIs('admin.scoring.votes') => 'votes',
            request()->routeIs('admin.scoring.activity') => 'activity',
            default => 'actions',
        };
        $this->episodeId = Episode::query()->latest('number')->value('id');
        $this->loadEpisodeResults();
    }

    public function updatedEpisodeId(): void
    {
        $this->loadEpisodeResults();
    }

    /** @return Collection<int, Episode> */
    #[Computed]
    public function episodes(): Collection
    {
        return Episode::query()->latest('number')->get();
    }

    /** @return Collection<int, CastMember> */
    #[Computed]
    public function castMembers(): Collection
    {
        return CastMember::query()->orderBy('name')->get();
    }

    /** @return Collection<int, CastMember> */
    #[Computed]
    public function eligibleVoters(): Collection
    {
        if (! $this->episodeId) {
            return new Collection;
        }

        $votedCastMemberIds = RoundTableVote::query()
            ->where('episode_id', $this->episodeId)
            ->pluck('voter_cast_member_id');

        return CastMember::query()->active()
            ->whereNotIn('id', $votedCastMemberIds)
            ->orderBy('name')->get();
    }

    /** @return Collection<int, CastMemberAction> */
    #[Computed]
    public function recentActions(): Collection
    {
        if (! $this->episodeId) {
            return new Collection;
        }

        return CastMemberAction::query()
            ->where('episode_id', $this->episodeId)
            ->with('castMember')
            ->latest()
            ->get();
    }

    /** @return Collection<int, RoundTableVote> */
    #[Computed]
    public function roundTableVotes(): Collection
    {
        if (! $this->episodeId) {
            return new Collection;
        }

        return RoundTableVote::query()->where('episode_id', $this->episodeId)
            ->with(['voter', 'target'])->orderBy('created_at')->get();
    }

    /** @return array<int, array{value: string, label: string, points: int}> */
    #[Computed]
    public function actionTypes(): array
    {
        return array_map(fn (CastMemberActionType $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'points' => $type->points(),
        ], array_values(array_filter(
            CastMemberActionType::cases(),
            fn (CastMemberActionType $type): bool => ! in_array($type, [CastMemberActionType::LoneVote, CastMemberActionType::FaithfulVotesTraitor, CastMemberActionType::TraitorSurvivesVote], true),
        )));
    }

    public function saveVote(RecalculateVoteScoring $recalculateVoteScoring): void
    {
        Gate::authorize('access-admin');

        $validated = $this->validate([
            'episodeId' => ['required', 'integer', Rule::exists(Episode::class, 'id')],
            'voterCastMemberId' => [
                'required',
                'integer',
                Rule::exists(CastMember::class, 'id')->where('status', CastMemberStatus::Active),
                Rule::unique(RoundTableVote::class, 'voter_cast_member_id')->where('episode_id', $this->episodeId),
            ],
            'voteTargetCastMemberId' => ['required', 'integer', 'different:voterCastMemberId', Rule::exists(CastMember::class, 'id')],
        ]);

        $vote = RoundTableVote::query()->create([
            'episode_id' => $validated['episodeId'],
            'voter_cast_member_id' => $validated['voterCastMemberId'],
            'target_cast_member_id' => $validated['voteTargetCastMemberId'],
            'created_by' => Auth::id(),
        ]);
        $recalculateVoteScoring($vote->episode);

        $this->reset('voterCastMemberId', 'voteTargetCastMemberId');
        unset($this->castMembers, $this->eligibleVoters, $this->roundTableVotes, $this->recentActions);
        Flux::toast(variant: 'success', text: __('Vote saved and vote-based scores recalculated.'));
    }

    public function deleteVote(int $voteId, RecalculateVoteScoring $recalculateVoteScoring): void
    {
        Gate::authorize('access-admin');

        $vote = RoundTableVote::query()->where('episode_id', $this->episodeId)->findOrFail($voteId);
        $episode = $vote->episode;
        $actions = CastMemberAction::query()->where('round_table_vote_id', $vote->id)->get();

        DB::transaction(function () use ($actions, $vote): void {
            foreach ($actions->groupBy('cast_member_id') as $castMemberId => $castMemberActions) {
                $castMember = CastMember::query()->lockForUpdate()->findOrFail($castMemberId);
                $castMember->update(['points' => max(0, $castMember->points - $castMemberActions->sum('points'))]);
            }

            $vote->delete();
        });
        $recalculateVoteScoring($episode);

        unset($this->castMembers, $this->eligibleVoters, $this->roundTableVotes, $this->recentActions);
        Flux::toast(variant: 'success', text: __('Vote removed and vote-based scores recalculated.'));
    }

    public function recordAction(RecalculateTeamChallengeScores $recalculateTeamChallengeScores): void
    {
        Gate::authorize('access-admin');

        $validated = $this->validate([
            'episodeId' => ['required', 'integer', Rule::exists(Episode::class, 'id')],
            'castMemberId' => ['required', 'integer', Rule::exists(CastMember::class, 'id')],
            'actionType' => ['required', Rule::enum(CastMemberActionType::class)],
        ]);
        $type = CastMemberActionType::from($validated['actionType']);

        DB::transaction(function () use ($validated, $type): void {
            CastMemberAction::query()->create([
                'episode_id' => $validated['episodeId'],
                'cast_member_id' => $validated['castMemberId'],
                'created_by' => Auth::id(),
                'type' => $type,
                'points' => $type->points(),
            ]);
            if ($type !== CastMemberActionType::ChallengeMoney) {
                CastMember::query()->whereKey($validated['castMemberId'])->increment('points', $type->points());
            }
        });

        if ($type === CastMemberActionType::ChallengeMoney) {
            $recalculateTeamChallengeScores(Episode::query()->findOrFail($validated['episodeId']));
        }

        $this->castMemberId = null;
        unset($this->castMembers, $this->recentActions);
        Flux::toast(variant: 'success', text: __('Action recorded and scores updated.'));
    }

    public function deleteAction(int $actionId, RecalculateTeamChallengeScores $recalculateTeamChallengeScores): void
    {
        Gate::authorize('access-admin');

        $episode = DB::transaction(function () use ($actionId): Episode {
            $action = CastMemberAction::query()->findOrFail($actionId);
            abort_if($action->round_table_vote_id, 422, 'Vote-based actions must be changed by editing the recorded vote.');
            if ($action->type !== CastMemberActionType::ChallengeMoney) {
                $castMember = CastMember::query()->lockForUpdate()->findOrFail($action->cast_member_id);
                $castMember->update(['points' => max(0, $castMember->points - $action->points)]);
            }
            $episode = $action->episode;
            $action->delete();

            return $episode;
        });

        $recalculateTeamChallengeScores($episode);

        unset($this->castMembers, $this->recentActions);
        Flux::toast(variant: 'success', text: __('Action removed and scores corrected.'));
    }

    public function saveMurderedResult(ScoreEpisodePredictions $scorePredictions): void
    {
        Gate::authorize('access-admin');

        $validated = $this->validate([
            'episodeId' => ['required', 'integer', Rule::exists(Episode::class, 'id')],
            'murderedCastMemberId' => ['required', 'integer', Rule::exists(CastMember::class, 'id')],
        ]);

        DB::transaction(function () use ($validated, $scorePredictions): void {
            $episode = Episode::query()->findOrFail($validated['episodeId']);
            $episode->update([
                'murdered_cast_member_id' => $validated['murderedCastMemberId'],
                'predictions_are_open' => false,
            ]);
            CastMember::query()->whereKey($validated['murderedCastMemberId'])->update(['status' => CastMemberStatus::Murdered, 'is_active' => false]);
            $scorePredictions($episode);
        });

        Flux::toast(variant: 'success', text: __('Murdered result saved and prediction points recalculated.'));
    }

    public function saveBanishedResult(ScoreEpisodePredictions $scorePredictions, RecalculateVoteScoring $recalculateVoteScoring): void
    {
        Gate::authorize('access-admin');

        $validated = $this->validate([
            'episodeId' => ['required', 'integer', Rule::exists(Episode::class, 'id')],
            'banishedCastMemberId' => ['required', 'integer', Rule::exists(CastMember::class, 'id')],
        ]);

        DB::transaction(function () use ($validated, $scorePredictions, $recalculateVoteScoring): void {
            $episode = Episode::query()->findOrFail($validated['episodeId']);
            $episode->update([
                'banished_cast_member_id' => $validated['banishedCastMemberId'],
                'predictions_are_open' => false,
            ]);
            CastMember::query()->whereKey($validated['banishedCastMemberId'])->update(['status' => CastMemberStatus::Banished, 'is_active' => false]);
            $scorePredictions($episode);
            $recalculateVoteScoring($episode);
        });

        Flux::toast(variant: 'success', text: __('Banished result saved and prediction points recalculated.'));
    }

    public function saveBreakfastResult(ScoreEpisodePredictions $scorePredictions): void
    {
        Gate::authorize('access-admin');

        $validated = $this->validate([
            'episodeId' => ['required', 'integer', Rule::exists(Episode::class, 'id')],
            'breakfastCastMemberId' => ['required', 'integer', Rule::exists(CastMember::class, 'id')],
        ]);

        DB::transaction(function () use ($validated, $scorePredictions): void {
            $episode = Episode::query()->findOrFail($validated['episodeId']);
            $episode->update([
                'breakfast_cast_member_id' => $validated['breakfastCastMemberId'],
                'predictions_are_open' => false,
            ]);
            $scorePredictions($episode);
        });

        Flux::toast(variant: 'success', text: __('Breakfast result saved and prediction points recalculated.'));
    }

    private function loadEpisodeResults(): void
    {
        $episode = $this->episodeId ? Episode::query()->find($this->episodeId) : null;
        $this->murderedCastMemberId = $episode?->murdered_cast_member_id;
        $this->banishedCastMemberId = $episode?->banished_cast_member_id;
        $this->breakfastCastMemberId = $episode?->breakfast_cast_member_id;
        unset($this->recentActions);
        unset($this->roundTableVotes);
        unset($this->eligibleVoters);
    }
};
?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <div class="flex flex-col gap-2"><flux:heading size="xl">{{ __('Live episode scoring') }}</flux:heading><flux:text>{{ __('Record cast actions as they happen and publish official prediction results.') }}</flux:text></div>

    @if ($this->episodes->isEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('Create an episode first') }}">{{ __('Launch an episode prediction round from the admin dashboard before tracking its scoring.') }}</flux:callout>
    @else
        <flux:select wire:model.live="episodeId" :label="__('Episode')">
            @foreach ($this->episodes as $episode)<flux:select.option wire:key="score-episode-{{ $episode->id }}" :value="$episode->id">{{ __('Episode :number', ['number' => $episode->number]) }} — {{ $episode->title }}</flux:select.option>@endforeach
        </flux:select>

        <nav aria-label="{{ __('Live scoring sections') }}" class="flex gap-1 overflow-x-auto border-b border-white/10">
            @foreach ([
                'admin.scoring' => __('Actions'),
                'admin.scoring.results' => __('Results'),
                'admin.scoring.votes' => __('Votes'),
                'admin.scoring.activity' => __('Activity'),
            ] as $routeName => $label)
                <a href="{{ route($routeName) }}" wire:navigate class="shrink-0 border-b-2 px-3 py-2 text-sm font-semibold transition {{ request()->routeIs($routeName) ? 'border-emerald-400 text-emerald-300' : 'border-transparent text-zinc-500 hover:text-zinc-200' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        @if ($section === 'actions')
            <flux:card class="flex flex-col gap-5">
                <div><flux:heading size="lg">{{ __('Record cast action') }}</flux:heading><flux:text>{{ __('Points are added immediately to every fantasy team containing that cast member.') }}</flux:text></div>
                <form wire:submit="recordAction" class="flex flex-col gap-4">
                    <x-cast-member-picker :cast-members="$this->castMembers" model="castMemberId" :selected="$castMemberId" :label="__('Cast member')" key-prefix="action-cast" show-points />
                    <flux:select wire:model="actionType" :label="__('Action')">
                        @foreach ($this->actionTypes as $action)<flux:select.option wire:key="action-type-{{ $action['value'] }}" :value="$action['value']">{{ $action['label'] }} (+{{ $action['points'] }})</flux:select.option>@endforeach
                    </flux:select>
                    <flux:button type="submit" variant="primary">{{ __('Record action') }}</flux:button>
                </form>
            </flux:card>
        @endif

        @if ($section === 'results')
            <flux:card class="flex flex-col gap-5">
                <div><flux:heading size="lg">{{ __('Official prediction answers') }}</flux:heading><flux:text>{{ __('Save each answer as it happens. The round closes with the first result, and points are recalculated after every answer.') }}</flux:text></div>

                <form wire:submit="saveMurderedResult" class="flex flex-col gap-4 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                    <flux:heading>{{ __('Murdered') }}</flux:heading>
                    <x-cast-member-picker :cast-members="$this->castMembers" model="murderedCastMemberId" :selected="$murderedCastMemberId" :label="__('Murdered')" key-prefix="result-murdered" />
                    <div class="flex justify-end"><flux:button type="submit" variant="primary" wire:confirm="{{ __('Save the murdered result and recalculate prediction points?') }}">{{ __('Save murdered answer') }}</flux:button></div>
                </form>

                <form wire:submit="saveBanishedResult" class="flex flex-col gap-4 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                    <flux:heading>{{ __('Banished') }}</flux:heading>
                    <x-cast-member-picker :cast-members="$this->castMembers" model="banishedCastMemberId" :selected="$banishedCastMemberId" :label="__('Banished')" key-prefix="result-banished" />
                    <div class="flex justify-end"><flux:button type="submit" variant="primary" wire:confirm="{{ __('Save the banished result and recalculate prediction points?') }}">{{ __('Save banished answer') }}</flux:button></div>
                </form>

                <form wire:submit="saveBreakfastResult" class="flex flex-col gap-4 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                    <flux:heading>{{ __('First out for breakfast') }}</flux:heading>
                    <x-cast-member-picker :cast-members="$this->castMembers" model="breakfastCastMemberId" :selected="$breakfastCastMemberId" :label="__('First out for breakfast')" key-prefix="result-breakfast" />
                    <div class="flex justify-end"><flux:button type="submit" variant="primary" wire:confirm="{{ __('Save the breakfast result and recalculate prediction points?') }}">{{ __('Save breakfast answer') }}</flux:button></div>
                </form>
            </flux:card>
        @endif

        @if ($section === 'votes')
        <flux:card class="flex min-w-0 max-w-full flex-col gap-5 overflow-hidden">
            <div><flux:heading size="lg">{{ __('Round-table votes') }}</flux:heading><flux:text>{{ __('Enter each ballot. Correct faithful votes, lone votes, and votes received by surviving traitors are scored automatically.') }}</flux:text></div>
            <form wire:submit="saveVote" class="flex min-w-0 max-w-full flex-col gap-5 overflow-hidden">
                @if ($this->eligibleVoters->isNotEmpty())
                    <x-cast-member-picker :cast-members="$this->eligibleVoters" model="voterCastMemberId" :selected="$voterCastMemberId" :label="__('Who is voting?')" key-prefix="vote-voter" />
                    <x-cast-member-picker :cast-members="$this->castMembers" model="voteTargetCastMemberId" :selected="$voteTargetCastMemberId" :label="__('Who did they vote for?')" key-prefix="vote-target" />
                    <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save vote') }}</flux:button></div>
                @else
                    <flux:callout variant="success" icon="check-circle" heading="{{ __('Every active cast member has voted') }}">{{ __('Delete a ballot below if you need to correct it.') }}</flux:callout>
                @endif
            </form>
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                @forelse ($this->roundTableVotes as $vote)
                    <div wire:key="round-table-vote-{{ $vote->id }}" class="flex items-center justify-between gap-4 border-b border-zinc-200 px-4 py-3 last:border-b-0 dark:border-zinc-700">
                        <div class="flex min-w-0 items-center gap-2"><span class="truncate font-medium">{{ $vote->voter->name }}</span><span class="text-zinc-400">→</span><span class="truncate font-medium">{{ $vote->target->name }}</span></div>
                        <flux:button size="sm" variant="danger" icon="trash" wire:click="deleteVote({{ $vote->id }})" wire:confirm="{{ __('Remove this vote and recalculate scoring?') }}" />
                    </div>
                @empty
                    <div class="p-5 text-center"><flux:text>{{ __('No votes recorded for this episode.') }}</flux:text></div>
                @endforelse
            </div>
        </flux:card>
        @endif

        @if ($section === 'activity')
        <section class="flex flex-col gap-3">
            <flux:heading size="lg">{{ __('Episode action log') }}</flux:heading>
            @forelse ($this->recentActions as $action)
                <flux:card wire:key="recorded-action-{{ $action->id }}" class="flex items-center justify-between gap-4 py-3">
                    <div><flux:heading>{{ $action->castMember->name }}</flux:heading><flux:text size="sm">{{ $action->type->label() }}</flux:text></div>
                    <div class="flex items-center gap-3"><flux:badge color="green">+{{ $action->points }}</flux:badge>@if ($action->round_table_vote_id)<flux:badge color="blue">{{ __('From vote') }}</flux:badge>@else<flux:button size="sm" variant="danger" icon="trash" wire:click="deleteAction({{ $action->id }})" wire:confirm="{{ __('Remove this action and reverse its points?') }}" />@endif</div>
                </flux:card>
            @empty
                <flux:text>{{ __('No actions recorded for this episode.') }}</flux:text>
            @endforelse
        </section>
        @endif
    @endif
</div>
