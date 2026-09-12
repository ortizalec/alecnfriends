<?php

use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\LeagueSetting;
use App\Models\TeamChallengeScore;
use App\CastMemberStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My Team')] class extends Component {
    /** @var array<int, int|string> */
    public array $selectedCastMemberIds = [];

    public bool $saved = false;

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

        $this->saved = true;
    }
};
?>

<div wire:poll.5s class="mx-auto flex w-full max-w-6xl flex-col gap-7">
    <header class="flex flex-col gap-1">
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-400">{{ __('Season roster') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
            {{ $this->teamSelectionIsOpen ? __('Choose your team') : __('My team') }}
        </h1>
        <p class="max-w-2xl text-sm leading-6 text-zinc-400">
            {{ $this->teamSelectionIsOpen ? __('Pick exactly 5 cast members. You can change your team until selection closes.') : __('Your season roster and its latest scoring activity.') }}
        </p>
    </header>

    @if ($this->currentTeam->isNotEmpty())
        <section class="flex flex-col gap-3" aria-labelledby="ranked-team-heading">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 id="ranked-team-heading" class="text-lg font-semibold text-white">{{ __('Your ranked team') }}</h2>
                    <p class="text-sm text-zinc-500">{{ __('Highest-scoring cast member first.') }}</p>
                </div>
                <p class="shrink-0 font-mono text-sm font-bold text-emerald-300">
                    {{ $this->currentTeam->sum('points') + $this->challengePoints }} {{ __('PTS') }}
                </p>
            </div>

            <ol class="grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($this->currentTeam as $castMember)
                    <li wire:key="ranked-team-{{ $castMember->id }}" class="group min-w-0">
                        <a href="{{ route('cast-members.show', $castMember) }}" wire:navigate class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                            <div class="relative aspect-[4/5] overflow-hidden bg-zinc-900">
                                <img src="{{ $castMember->imageUrl() }}" alt="{{ $castMember->name }}" class="size-full object-cover grayscale-[15%] transition duration-300 group-hover:scale-[1.02] group-hover:grayscale-0">
                                <span class="absolute left-2 top-2 bg-black/75 px-1.5 py-1 font-mono text-xs font-bold text-zinc-200">#{{ $loop->iteration }}</span>
                                <span class="absolute bottom-2 right-2 bg-emerald-400 px-2 py-1 font-mono text-xs font-black text-emerald-950">{{ $castMember->points }} pts</span>
                            </div>
                            <h3 class="truncate pt-2 text-sm font-semibold text-white transition group-hover:text-emerald-300">{{ $castMember->name }}</h3>
                            <p class="text-xs text-zinc-500">{{ str($castMember->status->value)->headline() }}</p>
                        </a>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @if ($this->teamSelectionIsOpen)
        <form wire:submit="save" class="flex min-w-0 max-w-full flex-col gap-4 overflow-hidden">
            @if ($saved)
                <p class="bg-emerald-400/10 px-3 py-2 text-sm font-medium text-emerald-300" role="status">
                    {{ __('Your team has been saved.') }}
                </p>
            @endif

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">{{ __('Make your picks') }}</h2>
                    <p class="text-sm text-zinc-500">{{ __('Select five active cast members.') }}</p>
                </div>
                <p class="shrink-0 font-mono text-sm font-bold {{ count($selectedCastMemberIds) === 5 ? 'text-emerald-300' : 'text-zinc-400' }}">
                    {{ count($selectedCastMemberIds) }} / 5
                </p>
            </div>

            @error('selectedCastMemberIds')
                <p class="text-sm font-medium text-red-400" role="alert">{{ $message }}</p>
            @enderror
            @error('selectedCastMemberIds.*')
                <p class="text-sm font-medium text-red-400" role="alert">{{ $message }}</p>
            @enderror

            @if ($this->castMembers->isEmpty())
                <div class="bg-zinc-900/70 px-4 py-3">
                    <p class="font-semibold text-white">{{ __('The cast is not available yet') }}</p>
                    <p class="text-sm text-zinc-400">{{ __('Check back after the administrator adds this season’s cast members.') }}</p>
                </div>
            @else
                <div class="flex w-full min-w-0 max-w-full snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain pb-2">
                    @foreach ($this->castMembers as $castMember)
                        @php($isSelected = in_array($castMember->id, $selectedCastMemberIds))
                        <label wire:key="cast-member-{{ $castMember->id }}" class="group relative aspect-[4/5] w-32 shrink-0 snap-start overflow-hidden bg-zinc-900 transition focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-emerald-400 sm:w-36 {{ $isSelected ? 'outline outline-2 -outline-offset-2 outline-emerald-400' : '' }} {{ $castMember->status !== CastMemberStatus::Active ? 'cursor-not-allowed opacity-45' : 'cursor-pointer' }}">
                            <input type="checkbox" wire:model.live="selectedCastMemberIds" value="{{ $castMember->id }}" class="sr-only" @disabled($castMember->status !== CastMemberStatus::Active || (count($selectedCastMemberIds) >= 5 && ! $isSelected))>
                            <img src="{{ $castMember->imageUrl() }}" alt="{{ $castMember->name }}" class="pointer-events-none size-full object-cover transition duration-300 group-hover:scale-[1.03]" loading="lazy">
                            <span class="pointer-events-none absolute inset-0 bg-linear-to-t from-black/95 via-black/5 to-transparent"></span>
                            @if ($isSelected)
                                <span class="pointer-events-none absolute right-2 top-2 flex size-6 items-center justify-center bg-emerald-400 text-sm font-black text-emerald-950" aria-hidden="true">✓</span>
                            @endif
                            <span class="pointer-events-none absolute inset-x-0 bottom-0 flex min-w-0 flex-col gap-0.5 p-2.5">
                                <span class="truncate text-sm font-semibold text-white">{{ $castMember->name }}</span>
                                @if ($castMember->status !== CastMemberStatus::Active)
                                    <span class="text-xs text-zinc-300">{{ str($castMember->status->value)->headline() }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="flex items-center justify-between gap-4">
                    <p class="text-xs text-zinc-500">
                        {{ count($selectedCastMemberIds) === 5 ? __('Your roster is ready.') : __('Choose :count more.', ['count' => max(0, 5 - count($selectedCastMemberIds))]) }}
                    </p>
                    <button type="submit" wire:loading.attr="disabled" class="bg-emerald-400 px-4 py-2 text-sm font-bold text-emerald-950 transition hover:bg-emerald-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-2 focus-visible:ring-offset-[#070b08] disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">{{ __('Save team') }}</span>
                        <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                    </button>
                </div>
            @endif
        </form>
    @endif

    <section class="flex flex-col gap-3" aria-labelledby="team-activity-heading">
        <div>
            <h2 id="team-activity-heading" class="text-lg font-semibold text-white">{{ __('Your team activity') }}</h2>
            <p class="text-sm text-zinc-500">{{ __('Scoring actions for cast members on your team.') }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-lg text-left text-sm">
                <thead class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
                    <tr>
                        <th scope="col" class="pb-2 font-semibold">{{ __('Cast member') }}</th>
                        <th scope="col" class="hidden pb-2 font-semibold sm:table-cell">{{ __('Episode') }}</th>
                        <th scope="col" class="pb-2 font-semibold">{{ __('Action') }}</th>
                        <th scope="col" class="pb-2 text-right font-semibold">{{ __('Points') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->teamActions as $action)
                        <tr wire:key="team-activity-{{ $action->id }}" class="border-t border-white/5">
                            <th scope="row" class="py-2.5 pe-4 font-normal">
                                <div class="flex min-w-0 items-center gap-3">
                                    <img src="{{ $action->castMember->imageUrl() }}" alt="" class="size-9 shrink-0 object-cover">
                                    <span class="truncate font-semibold text-white">{{ $action->castMember->name }}</span>
                                </div>
                            </th>
                            <td class="hidden py-2.5 pe-4 text-zinc-500 sm:table-cell">{{ __('Episode :number', ['number' => $action->episode->number]) }}</td>
                            <td class="py-2.5 pe-4 text-zinc-400">{{ $action->type->label() }}</td>
                            <td class="py-2.5 text-right font-mono font-bold text-emerald-300">+{{ $action->points }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-3 text-zinc-500">{{ __('No scoring actions have been recorded for your team yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
