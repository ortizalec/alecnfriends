<?php

use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\LeagueSetting;
use App\Models\TeamChallengeScore;
use App\CastMemberStatus;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My Team')] class extends Component {
    /** @var array<int, int|string> */
    public array $selectedCastMemberIds = [];

    public function mount(): void
    {
        $this->selectedCastMemberIds = Auth::user()->castMembers()->pluck('cast_members.id')->all();
    }

    /** @return Collection<int, CastMember> */
    #[Computed]
    public function castMembers(): Collection
    {
        return CastMember::query()->orderBy('name')->get();
    }

    /** @return Collection<int, CastMember> */
    #[Computed]
    public function currentTeam(): Collection
    {
        return Auth::user()->castMembers()->orderByDesc('points')->orderBy('name')->get();
    }

    /** @return Collection<int, CastMemberAction> */
    #[Computed]
    public function teamActions(): Collection
    {
        return CastMemberAction::query()
            ->whereIn('cast_member_id', $this->currentTeam->modelKeys())
            ->with(['castMember', 'episode'])
            ->latest()->limit(20)->get();
    }

    #[Computed]
    public function teamSelectionIsOpen(): bool
    {
        return LeagueSetting::teamSelectionIsOpen();
    }

    #[Computed]
    public function challengePoints(): int
    {
        return TeamChallengeScore::query()->where('user_id', Auth::id())->sum('points');
    }

    public function save(): void
    {
        abort_unless($this->teamSelectionIsOpen, 403);

        $validated = $this->validate([
            'selectedCastMemberIds' => ['required', 'array', 'size:5'],
            'selectedCastMemberIds.*' => [
                'integer',
                'distinct',
                Rule::exists(CastMember::class, 'id')->where('status', CastMemberStatus::Active),
            ],
        ], [
            'selectedCastMemberIds.size' => 'Choose exactly 5 cast members for your team.',
            'selectedCastMemberIds.*.exists' => 'One of your choices is no longer available.',
        ]);

        Auth::user()->castMembers()->sync($validated['selectedCastMemberIds']);

        Flux::toast(variant: 'success', text: __('Your team has been saved.'));
    }
};
?>

<div wire:poll.5s class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ $this->teamSelectionIsOpen ? __('Choose your team') : __('My team') }}</flux:heading>
        <flux:text>{{ $this->teamSelectionIsOpen ? __('Pick exactly 5 cast members. You can change your team until selection closes.') : __('Your season roster and its latest scoring activity.') }}</flux:text>
    </div>

    @if ($this->currentTeam->isNotEmpty())
        <section class="flex flex-col gap-3">
            <div class="flex items-end justify-between gap-4">
                <div><flux:heading size="lg">{{ __('Your ranked team') }}</flux:heading><flux:text>{{ __('Highest-scoring cast member first.') }}</flux:text></div>
                <flux:badge color="green">{{ $this->currentTeam->sum('points') + $this->challengePoints }} {{ __('total points') }}</flux:badge>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($this->currentTeam as $castMember)
                    <flux:card wire:key="ranked-team-{{ $castMember->id }}" class="flex flex-col gap-3">
                        <div class="flex items-center justify-between gap-2"><span class="font-bold text-zinc-400">#{{ $loop->iteration }}</span><flux:badge color="green">{{ $castMember->points }} pts</flux:badge></div>
                        <img src="{{ $castMember->imageUrl() }}" alt="{{ $castMember->name }}" class="aspect-square w-full rounded-lg object-cover">
                        <flux:heading>{{ $castMember->name }}</flux:heading>
                        <flux:text size="sm">{{ str($castMember->status->value)->headline() }}</flux:text>
                    </flux:card>
                @endforeach
            </div>
        </section>
    @endif

    @if ($this->teamSelectionIsOpen)
      <form wire:submit="save" class="flex min-w-0 max-w-full flex-col gap-6 overflow-hidden">
        <div class="flex items-center justify-between gap-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text class="font-medium">{{ count($selectedCastMemberIds) }} {{ __('of 5 selected') }}</flux:text>
            <flux:badge :color="count($selectedCastMemberIds) === 5 ? 'green' : 'amber'">
                {{ count($selectedCastMemberIds) === 5 ? __('Ready to save') : __('Choose :count more', ['count' => max(0, 5 - count($selectedCastMemberIds))]) }}
            </flux:badge>
        </div>

        <flux:error name="selectedCastMemberIds" />
        <flux:error name="selectedCastMemberIds.*" />

        @if ($this->castMembers->isEmpty())
            <flux:callout icon="user-group" heading="{{ __('The cast is not available yet') }}">
                {{ __('Check back after the administrator adds this season’s cast members.') }}
            </flux:callout>
        @else
            <div class="flex w-full min-w-0 max-w-full snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain pb-3">
                @foreach ($this->castMembers as $castMember)
                    @php($isSelected = in_array($castMember->id, $selectedCastMemberIds))
                    <label wire:key="cast-member-{{ $castMember->id }}" class="group relative w-40 shrink-0 snap-start overflow-hidden rounded-xl border transition {{ $isSelected ? 'border-accent bg-accent/5 ring-2 ring-accent' : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' }} {{ $castMember->status !== CastMemberStatus::Active ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:border-zinc-400 dark:hover:border-zinc-500' }}">
                        <input type="checkbox" wire:model.live="selectedCastMemberIds" value="{{ $castMember->id }}" class="sr-only" @disabled($castMember->status !== CastMemberStatus::Active || (count($selectedCastMemberIds) >= 5 && ! $isSelected))>
                        <img src="{{ $castMember->imageUrl() }}" alt="{{ $castMember->name }}" class="aspect-square w-full object-cover" loading="lazy">
                        <span class="flex min-w-0 flex-col gap-1 p-3">
                            <span class="font-semibold text-zinc-900 dark:text-white">{{ $castMember->name }}</span>
                            @if ($castMember->status !== CastMemberStatus::Active)
                                <span class="text-sm text-zinc-500">{{ str($castMember->status->value)->headline() }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        @endif

        @if ($this->castMembers->isNotEmpty())
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ __('Save team') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        @endif
      </form>
    @endif

    <section class="flex flex-col gap-3">
        <div><flux:heading size="lg">{{ __('Your team activity') }}</flux:heading><flux:text>{{ __('Scoring actions for cast members on your team.') }}</flux:text></div>
        <x-cast-member-action-log :actions="$this->teamActions" empty-message="No scoring actions have been recorded for your team yet." />
    </section>
</div>
