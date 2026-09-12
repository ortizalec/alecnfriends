<?php

use App\CastMemberStatus;
use App\Models\CastMember;
use App\Models\Episode;
use App\Models\Prediction;
use App\Models\TraitorPrediction;
use App\Models\TraitorPredictionRound;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Predictions')] class extends Component {
    public ?int $murderedCastMemberId = null;
    public ?int $banishedCastMemberId = null;
    public ?int $breakfastCastMemberId = null;
    public ?int $firstTraitorCastMemberId = null;
    public ?int $secondTraitorCastMemberId = null;
    public ?int $thirdTraitorCastMemberId = null;

    public function mount(): void
    {
        $episode = $this->currentEpisode;

        if ($episode) {
            $prediction = Prediction::query()->whereBelongsTo($episode)->whereBelongsTo(Auth::user())->first();
            $this->murderedCastMemberId = $prediction?->murdered_cast_member_id;
            $this->banishedCastMemberId = $prediction?->banished_cast_member_id;
            $this->breakfastCastMemberId = $prediction?->breakfast_cast_member_id;
        }

        $traitorPrediction = $this->traitorPredictionRound?->predictions()
            ->whereBelongsTo(Auth::user())
            ->first();
        $this->firstTraitorCastMemberId = $traitorPrediction?->first_cast_member_id;
        $this->secondTraitorCastMemberId = $traitorPrediction?->second_cast_member_id;
        $this->thirdTraitorCastMemberId = $traitorPrediction?->third_cast_member_id;
    }

    #[Computed]
    public function currentEpisode(): ?Episode
    {
        return Episode::query()->where('predictions_are_open', true)->latest('number')->first();
    }

    #[Computed]
    public function traitorPredictionRound(): ?TraitorPredictionRound
    {
        return TraitorPredictionRound::query()
            ->with(['firstTraitor', 'secondTraitor', 'thirdTraitor'])
            ->first();
    }

    #[Computed]
    public function savedTraitorPrediction(): ?TraitorPrediction
    {
        return $this->traitorPredictionRound?->predictions()
            ->whereBelongsTo(Auth::user())
            ->with(['firstCastMember', 'secondCastMember', 'thirdCastMember'])
            ->first();
    }

    /** @return Collection<int, CastMember> */
    #[Computed]
    public function availableCastMembers(): Collection
    {
        return CastMember::query()->active()->orderBy('name')->get();
    }

    /** @return Collection<int, Prediction> */
    #[Computed]
    public function predictionHistory(): Collection
    {
        return Prediction::query()->whereBelongsTo(Auth::user())
            ->whereHas('episode', fn ($query) => $query->whereNotNull('murdered_cast_member_id')->orWhereNotNull('banished_cast_member_id')->orWhereNotNull('breakfast_cast_member_id'))
            ->with(['episode', 'murderedCastMember', 'banishedCastMember', 'breakfastCastMember'])
            ->latest('episode_id')->get();
    }

    public function save(): void
    {
        $episode = $this->currentEpisode;
        abort_unless($episode && $episode->predictions_are_open, 403);
        abort_if($episode->predictions_close_at?->isPast(), 403);

        $activeCastMember = Rule::exists(CastMember::class, 'id')->where('status', CastMemberStatus::Active);
        $validated = $this->validate([
            'murderedCastMemberId' => ['required', 'integer', $activeCastMember],
            'banishedCastMemberId' => ['required', 'integer', $activeCastMember],
            'breakfastCastMemberId' => ['required', 'integer', $activeCastMember],
        ]);

        Prediction::query()->updateOrCreate(
            ['episode_id' => $episode->id, 'user_id' => Auth::id()],
            [
                'murdered_cast_member_id' => $validated['murderedCastMemberId'],
                'banished_cast_member_id' => $validated['banishedCastMemberId'],
                'breakfast_cast_member_id' => $validated['breakfastCastMemberId'],
            ],
        );

        Flux::toast(variant: 'success', text: __('Predictions saved.'));
    }

    public function saveTraitorPrediction(): void
    {
        abort_if(Auth::user()->is_admin, 403);

        $round = TraitorPredictionRound::query()->where('is_open', true)->first();
        abort_unless($round, 403);

        $castMemberExists = Rule::exists(CastMember::class, 'id');
        $validated = $this->validate([
            'firstTraitorCastMemberId' => ['required', 'integer', $castMemberExists],
            'secondTraitorCastMemberId' => ['required', 'integer', 'different:firstTraitorCastMemberId', $castMemberExists],
            'thirdTraitorCastMemberId' => ['required', 'integer', 'different:firstTraitorCastMemberId', 'different:secondTraitorCastMemberId', $castMemberExists],
        ]);

        TraitorPrediction::query()->updateOrCreate(
            ['traitor_prediction_round_id' => $round->id, 'user_id' => Auth::id()],
            [
                'first_cast_member_id' => $validated['firstTraitorCastMemberId'],
                'second_cast_member_id' => $validated['secondTraitorCastMemberId'],
                'third_cast_member_id' => $validated['thirdTraitorCastMemberId'],
            ],
        );

        unset($this->savedTraitorPrediction);
        Flux::toast(variant: 'success', text: __('Traitor predictions saved.'));
    }
};
?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-8">
    <div class="flex flex-col gap-2"><flux:heading size="xl">{{ __('Episode predictions') }}</flux:heading><flux:text>{{ __('Make your picks before the prediction window closes.') }}</flux:text></div>

    @if ($this->traitorPredictionRound)
        <flux:card class="flex flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <div><flux:heading size="lg">{{ __('Who are the traitors?') }}</flux:heading><flux:text>{{ __('Choose the three cast members you think began the game as traitors.') }}</flux:text></div>
                <flux:badge :color="$this->traitorPredictionRound->is_open ? 'green' : 'zinc'">{{ $this->traitorPredictionRound->is_open ? __('Open') : __('Revealed') }}</flux:badge>
            </div>

            @if ($this->traitorPredictionRound->is_open)
                <form wire:submit="saveTraitorPrediction" class="flex flex-col gap-6">
                    <x-cast-member-picker :cast-members="$this->availableCastMembers" model="firstTraitorCastMemberId" :selected="$firstTraitorCastMemberId" :label="__('First traitor pick')" key-prefix="traitor-first" />
                    <x-cast-member-picker :cast-members="$this->availableCastMembers" model="secondTraitorCastMemberId" :selected="$secondTraitorCastMemberId" :label="__('Second traitor pick')" key-prefix="traitor-second" />
                    <x-cast-member-picker :cast-members="$this->availableCastMembers" model="thirdTraitorCastMemberId" :selected="$thirdTraitorCastMemberId" :label="__('Third traitor pick')" key-prefix="traitor-third" />
                    <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save traitor picks') }}</flux:button></div>
                </form>
            @elseif ($this->savedTraitorPrediction)
                <div class="flex flex-col gap-4">
                    <div class="flex items-center justify-between gap-3"><flux:heading>{{ __('Your traitor picks') }}</flux:heading><flux:badge color="green">{{ trans_choice(':count point|:count points', $this->savedTraitorPrediction->points, ['count' => $this->savedTraitorPrediction->points]) }}</flux:badge></div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ([$this->savedTraitorPrediction->firstCastMember, $this->savedTraitorPrediction->secondCastMember, $this->savedTraitorPrediction->thirdCastMember] as $traitorPick)
                            <div wire:key="traitor-pick-result-{{ $traitorPick->id }}" class="rounded-xl border border-zinc-200 p-3 font-medium dark:border-zinc-700">{{ $traitorPick->name }}</div>
                        @endforeach
                    </div>
                    <flux:text size="sm">{{ __('Correct traitors: :names', ['names' => collect([$this->traitorPredictionRound->firstTraitor, $this->traitorPredictionRound->secondTraitor, $this->traitorPredictionRound->thirdTraitor])->pluck('name')->join(', ')]) }}</flux:text>
                </div>
            @else
                <flux:callout icon="information-circle" heading="{{ __('No traitor picks submitted') }}">{{ __('The answers have been revealed and this prediction can no longer be entered.') }}</flux:callout>
            @endif
        </flux:card>
    @endif

    @if (! $this->currentEpisode)
        <flux:callout icon="clock" heading="{{ __('No predictions are open') }}">{{ __('The next prediction round will appear here when the admin launches it.') }}</flux:callout>
    @elseif ($this->currentEpisode->predictions_close_at?->isPast())
        <flux:callout variant="warning" icon="lock-closed" heading="{{ __('Predictions are closed') }}">{{ __('The entry deadline for episode :number has passed.', ['number' => $this->currentEpisode->number]) }}</flux:callout>
    @else
        <flux:card class="flex flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <div><flux:heading size="lg">{{ __('Episode :number', ['number' => $this->currentEpisode->number]) }}</flux:heading><flux:text>{{ $this->currentEpisode->title }}</flux:text></div>
                @if ($this->currentEpisode->predictions_close_at)<flux:badge color="amber">{{ __('Closes :time', ['time' => $this->currentEpisode->predictions_close_at->format('M j, g:i A')]) }}</flux:badge>@endif
            </div>

            <form wire:submit="save" class="flex flex-col gap-6">
                <x-cast-member-picker :cast-members="$this->availableCastMembers" model="murderedCastMemberId" :selected="$murderedCastMemberId" :label="__('Who will be murdered?')" key-prefix="murdered" />
                <x-cast-member-picker :cast-members="$this->availableCastMembers" model="banishedCastMemberId" :selected="$banishedCastMemberId" :label="__('Who will be banished?')" key-prefix="banished" />
                <x-cast-member-picker :cast-members="$this->availableCastMembers" model="breakfastCastMemberId" :selected="$breakfastCastMemberId" :label="__('Who will come out first for breakfast?')" key-prefix="breakfast" />
                <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save predictions') }}</flux:button></div>
            </form>
        </flux:card>
    @endif

    <section class="flex flex-col gap-4">
        <div><flux:heading size="lg">{{ __('Prediction history') }}</flux:heading><flux:text>{{ __('Review your previous picks and official results.') }}</flux:text></div>
        @forelse ($this->predictionHistory as $prediction)
            <flux:card wire:key="prediction-history-{{ $prediction->id }}" class="flex flex-col gap-4">
                <div class="flex items-center justify-between gap-3"><div><flux:heading>{{ __('Episode :number', ['number' => $prediction->episode->number]) }}</flux:heading><flux:text size="sm">{{ $prediction->episode->title }}</flux:text></div><flux:badge color="green">{{ trans_choice(':count point|:count points', $prediction->points, ['count' => $prediction->points]) }}</flux:badge></div>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ([
                        ['label' => __('Murdered'), 'pick' => $prediction->murderedCastMember, 'resultId' => $prediction->episode->murdered_cast_member_id, 'points' => 1],
                        ['label' => __('Banished'), 'pick' => $prediction->banishedCastMember, 'resultId' => $prediction->episode->banished_cast_member_id, 'points' => 1],
                        ['label' => __('First at breakfast'), 'pick' => $prediction->breakfastCastMember, 'resultId' => $prediction->episode->breakfast_cast_member_id, 'points' => 2],
                    ] as $historyPick)
                        @php($isCorrect = $historyPick['pick']?->id === $historyPick['resultId'])
                        <div class="rounded-xl border p-3 {{ $isCorrect ? 'border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-950/30' : 'border-zinc-200 dark:border-zinc-700' }}">
                            <div class="flex items-center justify-between gap-2"><flux:text size="sm">{{ $historyPick['label'] }}</flux:text><flux:badge :color="$isCorrect ? 'green' : 'red'">{{ $isCorrect ? __('Correct +:points', ['points' => $historyPick['points']]) : __('Incorrect') }}</flux:badge></div>
                            <div class="pt-2 font-medium">{{ $historyPick['pick']?->name ?? __('No pick') }}</div>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @empty
            <flux:callout icon="clock" heading="{{ __('No prediction history yet') }}">{{ __('Completed episode predictions will appear here.') }}</flux:callout>
        @endforelse
    </section>
</div>
