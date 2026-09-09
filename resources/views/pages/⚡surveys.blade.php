<?php

use App\CastMemberStatus;
use App\Models\CastMember;
use App\Models\Poll;
use App\Models\PollResponse;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Surveys')] class extends Component {
    /** @var array<int, array<int, int|string>> */
    public array $answers = [];

    public function mount(): void
    {
        PollResponse::query()->where('user_id', Auth::id())->get()->groupBy('poll_id')
            ->each(function (Collection $responses, int $pollId): void {
                $this->answers[$pollId] = $responses->pluck('cast_member_id')->all();
            });
    }

    /** @return Collection<int, Poll> */
    #[Computed]
    public function openPolls(): Collection
    {
        return Poll::query()->open()->orderBy('closes_at')->get();
    }

    /** @return Collection<int, CastMember> */
    #[Computed]
    public function activeCastMembers(): Collection
    {
        return CastMember::query()->active()->orderBy('name')->get();
    }

    /** @return array<int, array{respondents: int, results: array<int, array{castMember: CastMember, votes: int, percentage: int}>}> */
    #[Computed]
    public function surveyResults(): array
    {
        $results = [];
        $responsesByPoll = PollResponse::query()
            ->whereIn('poll_id', $this->openPolls->modelKeys())
            ->with('castMember')->get()->groupBy('poll_id');

        foreach ($responsesByPoll as $pollId => $responses) {
            $respondentCount = $responses->unique('user_id')->count();
            $castMemberResults = $responses->groupBy('cast_member_id')
                ->map(function (Collection $castMemberResponses) use ($respondentCount): array {
                    $voteCount = $castMemberResponses->count();

                    return [
                        'castMember' => $castMemberResponses->firstOrFail()->castMember,
                        'votes' => $voteCount,
                        'percentage' => (int) round(($voteCount / $respondentCount) * 100),
                    ];
                })->sortByDesc('votes')->values()->all();

            $results[$pollId] = ['respondents' => $respondentCount, 'results' => $castMemberResults];
        }

        return $results;
    }

    public function save(int $pollId): void
    {
        $poll = Poll::query()->open()->findOrFail($pollId);

        $validated = $this->validate([
            "answers.$pollId" => ['required', 'array', 'min:1', 'max:'.$poll->maximum_selections],
            "answers.$pollId.*" => ['integer', 'distinct', Rule::exists(CastMember::class, 'id')->where('status', CastMemberStatus::Active)],
        ]);

        DB::transaction(function () use ($poll, $validated, $pollId): void {
            PollResponse::query()->whereBelongsTo($poll)->where('user_id', Auth::id())->delete();

            foreach ($validated['answers'][$pollId] as $castMemberId) {
                PollResponse::query()->create(['poll_id' => $poll->id, 'user_id' => Auth::id(), 'cast_member_id' => $castMemberId]);
            }
        });

        unset($this->surveyResults);
        Flux::toast(variant: 'success', text: __('Survey response saved.'));
    }
};
?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <div class="flex flex-col gap-2"><flux:heading size="xl">{{ __('Surveys') }}</flux:heading><flux:text>{{ __('Answer the league’s open audience questions.') }}</flux:text></div>

    @forelse ($this->openPolls as $poll)
        <flux:card wire:key="survey-{{ $poll->id }}" class="flex flex-col gap-5">
            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                <div><flux:heading size="lg">{{ $poll->question }}</flux:heading><flux:text>{{ trans_choice('Choose up to :count cast member|Choose up to :count cast members', $poll->maximum_selections, ['count' => $poll->maximum_selections]) }}</flux:text></div>
                @if ($poll->closes_at)<flux:badge color="amber">{{ __('Closes :time', ['time' => $poll->closes_at->format('M j, g:i A')]) }}</flux:badge>@endif
            </div>
            <form wire:submit="save({{ $poll->id }})" class="flex min-w-0 max-w-full flex-col gap-4 overflow-hidden">
                <div class="flex w-full min-w-0 max-w-full snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain pb-3">
                    @foreach ($this->activeCastMembers as $castMember)
                        @php($isSelected = in_array($castMember->id, $answers[$poll->id] ?? []))
                        <label wire:key="survey-{{ $poll->id }}-cast-{{ $castMember->id }}" class="w-36 shrink-0 snap-start cursor-pointer overflow-hidden rounded-xl border bg-white transition dark:bg-zinc-900 sm:w-40 {{ $isSelected ? 'border-accent ring-2 ring-accent' : 'border-zinc-200 dark:border-zinc-700' }}">
                            <input type="checkbox" wire:model.live="answers.{{ $poll->id }}" value="{{ $castMember->id }}" class="sr-only" @disabled(count($answers[$poll->id] ?? []) >= $poll->maximum_selections && ! $isSelected)>
                            <img src="{{ $castMember->imageUrl() }}" alt="{{ $castMember->name }}" class="aspect-square w-full object-cover" loading="lazy">
                            <span class="block truncate p-3 text-sm font-semibold">{{ $castMember->name }}</span>
                        </label>
                    @endforeach
                </div>
                <flux:error name="answers.{{ $poll->id }}" />
                <flux:error name="answers.{{ $poll->id }}.*" />
                <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save response') }}</flux:button></div>
            </form>

            @if (count($answers[$poll->id] ?? []) > 0 && isset($this->surveyResults[$poll->id]))
                @php($pollResults = $this->surveyResults[$poll->id])
                <section class="flex flex-col gap-4 border-t border-zinc-200 pt-5 dark:border-zinc-700">
                    <div><flux:heading>{{ __('Survey results') }}</flux:heading><flux:text>{{ trans_choice('Based on :count respondent|Based on :count respondents', $pollResults['respondents'], ['count' => $pollResults['respondents']]) }}</flux:text></div>
                    <div class="flex flex-col gap-3">
                        @foreach ($pollResults['results'] as $result)
                            <div wire:key="survey-result-{{ $poll->id }}-{{ $result['castMember']->id }}" class="flex items-center gap-3">
                                <img src="{{ $result['castMember']->imageUrl() }}" alt="" class="size-11 shrink-0 rounded-full object-cover">
                                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex min-w-0 items-center gap-2"><span class="truncate font-medium">{{ $result['castMember']->name }}</span>@if (in_array($result['castMember']->id, $answers[$poll->id]))<flux:badge size="sm" color="blue">{{ __('Your pick') }}</flux:badge>@endif</div>
                                        <span class="shrink-0 text-sm font-semibold">{{ $result['percentage'] }}%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700"><div class="h-full rounded-full bg-accent" style="width: {{ $result['percentage'] }}%"></div></div>
                                    <flux:text size="sm">{{ trans_choice(':count vote|:count votes', $result['votes'], ['count' => $result['votes']]) }}</flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </flux:card>
    @empty
        <flux:callout icon="clipboard-document-list" heading="{{ __('No surveys are open') }}">{{ __('New audience questions will appear here when the administrator publishes them.') }}</flux:callout>
    @endforelse
</div>
