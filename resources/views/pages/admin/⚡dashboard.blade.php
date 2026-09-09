<?php

use App\Actions\RecalculateVoteScoring;
use App\Models\CastMember;
use App\Models\Episode;
use App\Models\LeagueSetting;
use App\Models\Poll;
use App\Models\User;
use App\CastMemberStatus;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Admin')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public ?int $editingCastMemberId = null;

    public string $name = '';
    public mixed $photo = null;
    public string $status = 'active';
    public bool $isTraitor = false;
    public int $points = 0;
    public string $pollQuestion = '';
    public int $pollMaximumSelections = 1;
    public string $pollOpensAt = '';
    public string $pollClosesAt = '';
    public bool $publishPoll = true;
    public int $episodeNumber = 1;
    public string $episodeTitle = '';
    public string $predictionClosesAt = '';

    /** @return Collection<int, CastMember> */
    #[Computed]
    public function castMembers(): Collection
    {
        return CastMember::query()->withCount('users')->orderBy('name')->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function players(): Collection
    {
        return User::query()
            ->where('is_admin', false)
            ->with(['castMembers' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Poll> */
    #[Computed]
    public function polls(): Collection
    {
        return Poll::query()->latest()->get();
    }

    /** @return Collection<int, Episode> */
    #[Computed]
    public function episodes(): Collection
    {
        return Episode::query()->latest('number')->get();
    }

    #[Computed]
    public function teamSelectionIsOpen(): bool
    {
        return LeagueSetting::teamSelectionIsOpen();
    }

    public function toggleTeamSelection(): void
    {
        Gate::authorize('access-admin');

        LeagueSetting::query()->updateOrCreate(
            ['id' => LeagueSetting::query()->value('id') ?? 1],
            ['team_selection_open' => ! $this->teamSelectionIsOpen],
        );

        unset($this->teamSelectionIsOpen);
        Flux::toast(variant: 'success', text: __('Team selection updated.'));
    }

    public function createCastMember(): void
    {
        Gate::authorize('access-admin');

        $this->resetCastMemberForm();
        Flux::modal('cast-member-form')->show();
    }

    public function editCastMember(int $castMemberId): void
    {
        Gate::authorize('access-admin');

        $castMember = CastMember::query()->findOrFail($castMemberId);
        $this->editingCastMemberId = $castMember->id;
        $this->name = $castMember->name;
        $this->status = $castMember->status->value;
        $this->isTraitor = $castMember->is_traitor;
        $this->points = $castMember->points;
        $this->resetValidation();

        Flux::modal('cast-member-form')->show();
    }

    public function saveCastMember(RecalculateVoteScoring $recalculateVoteScoring): void
    {
        Gate::authorize('access-admin');

        $castMember = $this->editingCastMemberId
            ? CastMember::query()->findOrFail($this->editingCastMemberId)
            : new CastMember;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(CastMember::class)->ignore($castMember)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'status' => ['required', Rule::enum(CastMemberStatus::class)],
            'isTraitor' => ['boolean'],
            'points' => ['required', 'integer', 'min:0'],
        ]);

        $oldPhotoPath = $castMember->photo_path;
        $newPhotoPath = $validated['photo'] instanceof TemporaryUploadedFile
            ? $validated['photo']->store('cast-members', 'public')
            : $oldPhotoPath;

        $castMember->fill([
            'name' => $validated['name'],
            'photo_path' => $newPhotoPath,
            'is_active' => $validated['status'] === CastMemberStatus::Active->value,
            'status' => $validated['status'],
            'is_traitor' => $validated['isTraitor'],
            'points' => $validated['points'],
        ])->save();

        if ($castMember->wasChanged('is_traitor')) {
            Episode::query()->whereHas('roundTableVotes')->each($recalculateVoteScoring);
        }

        if ($newPhotoPath !== $oldPhotoPath && $oldPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        $this->resetCastMemberForm();
        unset($this->castMembers);
        Flux::modal('cast-member-form')->close();
        Flux::toast(variant: 'success', text: __('Cast member saved.'));
    }

    public function setCastMemberStatus(int $castMemberId, string $status): void
    {
        Gate::authorize('access-admin');

        $validatedStatus = CastMemberStatus::from($status);

        $castMember = CastMember::query()->findOrFail($castMemberId);
        $castMember->update([
            'status' => $validatedStatus,
            'is_active' => $validatedStatus === CastMemberStatus::Active,
        ]);

        unset($this->castMembers);
        Flux::toast(variant: 'success', text: __('Cast status updated.'));
    }

    public function savePoll(): void
    {
        Gate::authorize('access-admin');

        $validated = $this->validate([
            'pollQuestion' => ['required', 'string', 'max:255'],
            'pollMaximumSelections' => ['required', 'integer', 'between:1,5'],
            'pollOpensAt' => ['nullable', 'date'],
            'pollClosesAt' => ['nullable', 'date', 'after:pollOpensAt'],
            'publishPoll' => ['boolean'],
        ]);

        Poll::query()->create([
            'question' => $validated['pollQuestion'],
            'maximum_selections' => $validated['pollMaximumSelections'],
            'opens_at' => $validated['pollOpensAt'] ?: null,
            'closes_at' => $validated['pollClosesAt'] ?: null,
            'is_published' => $validated['publishPoll'],
        ]);

        $this->reset('pollQuestion', 'pollOpensAt', 'pollClosesAt');
        $this->pollMaximumSelections = 1;
        unset($this->polls);
        Flux::toast(variant: 'success', text: __('Poll created.'));
    }

    public function launchPredictions(): void
    {
        Gate::authorize('access-admin');

        $validated = $this->validate([
            'episodeNumber' => ['required', 'integer', 'min:1'],
            'episodeTitle' => ['nullable', 'string', 'max:255'],
            'predictionClosesAt' => ['required', 'date', 'after:now'],
        ]);

        Episode::query()->update(['predictions_are_open' => false]);
        Episode::query()->updateOrCreate(
            ['number' => $validated['episodeNumber']],
            [
                'title' => $validated['episodeTitle'] ?: null,
                'predictions_close_at' => $validated['predictionClosesAt'],
                'predictions_are_open' => true,
            ],
        );

        $this->episodeNumber++;
        $this->reset('episodeTitle', 'predictionClosesAt');
        unset($this->episodes);
        Flux::toast(variant: 'success', text: __('Predictions launched.'));
    }

    public function clearTeam(int $userId): void
    {
        Gate::authorize('access-admin');

        $user = User::query()->where('is_admin', false)->findOrFail($userId);
        $user->castMembers()->detach();

        unset($this->players, $this->castMembers);
        Flux::toast(variant: 'success', text: __('Player team cleared.'));
    }

    private function resetCastMemberForm(): void
    {
        $this->reset('editingCastMemberId', 'name', 'photo', 'points');
        $this->status = CastMemberStatus::Active->value;
        $this->isTraitor = false;
        $this->resetValidation();
    }
};
?>

<div class="mx-auto flex w-full max-w-7xl flex-col gap-8">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('League administration') }}</flux:heading>
        <flux:text>{{ __('Manage the cast, player teams, and league availability.') }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="flex flex-col gap-2"><flux:text>{{ __('Players') }}</flux:text><flux:heading size="xl">{{ $this->players->count() }}</flux:heading></flux:card>
        <flux:card class="flex flex-col gap-2"><flux:text>{{ __('Cast members') }}</flux:text><flux:heading size="xl">{{ $this->castMembers->count() }}</flux:heading></flux:card>
        <flux:card class="flex flex-col gap-2"><flux:text>{{ __('Completed teams') }}</flux:text><flux:heading size="xl">{{ $this->players->filter(fn ($player) => $player->castMembers->count() === 5)->count() }}</flux:heading></flux:card>
        <flux:card class="flex flex-col gap-2"><flux:text>{{ __('Team selection') }}</flux:text><flux:badge :color="$this->teamSelectionIsOpen ? 'green' : 'red'" class="w-fit">{{ $this->teamSelectionIsOpen ? __('Open') : __('Closed') }}</flux:badge></flux:card>
    </div>

    <flux:card class="flex flex-col gap-5">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div class="flex flex-col gap-1"><flux:heading size="lg">{{ __('Team selection') }}</flux:heading><flux:text>{{ __('Players can create and edit teams only while selection is open.') }}</flux:text></div>
            <flux:button wire:click="toggleTeamSelection" :variant="$this->teamSelectionIsOpen ? 'danger' : 'primary'" wire:confirm="{{ $this->teamSelectionIsOpen ? __('Close team selection for every player?') : __('Reopen team selection for every player?') }}">{{ $this->teamSelectionIsOpen ? __('Close selection') : __('Open selection') }}</flux:button>
        </div>
    </flux:card>

    <section class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4">
            <div><flux:heading size="lg">{{ __('Cast members') }}</flux:heading><flux:text>{{ __('Add cast members and update their availability.') }}</flux:text></div>
            <flux:button variant="primary" icon="plus" wire:click="createCastMember">{{ __('Add cast member') }}</flux:button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($this->castMembers as $castMember)
                <flux:card wire:key="admin-cast-{{ $castMember->id }}" class="flex items-center justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <img src="{{ $castMember->imageUrl() }}" alt="{{ $castMember->name }}" class="size-14 shrink-0 rounded-lg object-cover" loading="lazy">
                        <div class="min-w-0">
                        <flux:heading class="truncate">{{ $castMember->name }}</flux:heading>
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <flux:badge :color="$castMember->status === CastMemberStatus::Active ? 'green' : ($castMember->status === CastMemberStatus::Murdered ? 'red' : 'amber')">{{ str($castMember->status->value)->headline() }}</flux:badge>
                            <flux:badge :color="$castMember->is_traitor ? 'red' : 'blue'">{{ $castMember->is_traitor ? __('Traitor') : __('Faithful') }}</flux:badge>
                            <flux:badge color="green">{{ $castMember->points }} pts</flux:badge>
                            <flux:text size="sm">{{ trans_choice(':count team|:count teams', $castMember->users_count, ['count' => $castMember->users_count]) }}</flux:text>
                        </div>
                        </div>
                    </div>
                    <flux:dropdown position="bottom" align="end">
                        <flux:button icon="ellipsis-horizontal" variant="ghost" />
                        <flux:menu>
                            <flux:menu.item icon="pencil-square" wire:click="editCastMember({{ $castMember->id }})">{{ __('Edit') }}</flux:menu.item>
                            <flux:menu.item icon="check-circle" wire:click="setCastMemberStatus({{ $castMember->id }}, 'active')">{{ __('Mark active') }}</flux:menu.item>
                            <flux:menu.item icon="x-circle" wire:click="setCastMemberStatus({{ $castMember->id }}, 'murdered')">{{ __('Mark murdered') }}</flux:menu.item>
                            <flux:menu.item icon="arrow-right-circle" wire:click="setCastMemberStatus({{ $castMember->id }}, 'banished')">{{ __('Mark banished') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </flux:card>
            @empty
                <flux:callout class="sm:col-span-2 lg:col-span-3" icon="user-group" heading="{{ __('No cast members yet') }}">{{ __('Add the first cast member to begin building the season roster.') }}</flux:callout>
            @endforelse
        </div>
    </section>

    <section class="flex flex-col gap-4">
        <div><flux:heading size="lg">{{ __('Player teams') }}</flux:heading><flux:text>{{ __('Review each player’s current five-person team.') }}</flux:text></div>
        <div class="grid gap-3 lg:grid-cols-2">
            @forelse ($this->players as $player)
                <flux:card wire:key="admin-player-{{ $player->id }}" class="flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-4">
                        <div><flux:heading>{{ $player->name }}</flux:heading><flux:text size="sm">{{ $player->email }}</flux:text></div>
                        <flux:badge :color="$player->castMembers->count() === 5 ? 'green' : 'amber'">{{ $player->castMembers->count() }}/5</flux:badge>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @forelse ($player->castMembers as $castMember)
                            <flux:badge wire:key="player-{{ $player->id }}-cast-{{ $castMember->id }}" color="zinc">{{ $castMember->name }}</flux:badge>
                        @empty
                            <flux:text size="sm">{{ __('No team selected.') }}</flux:text>
                        @endforelse
                    </div>
                    @if ($player->castMembers->isNotEmpty())
                        <div class="flex justify-end"><flux:button size="sm" variant="danger" wire:click="clearTeam({{ $player->id }})" wire:confirm="{{ __('Clear this player’s entire team?') }}">{{ __('Clear team') }}</flux:button></div>
                    @endif
                </flux:card>
            @empty
                <flux:callout class="lg:col-span-2" icon="users" heading="{{ __('No players yet') }}">{{ __('Registered player accounts will appear here.') }}</flux:callout>
            @endforelse
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <flux:card class="flex flex-col gap-5">
            <div><flux:heading size="lg">{{ __('Create audience poll') }}</flux:heading><flux:text>{{ __('Poll answers use the currently active cast members.') }}</flux:text></div>
            <form wire:submit="savePoll" class="flex flex-col gap-4">
                <flux:input wire:model="pollQuestion" :label="__('Question')" required />
                <flux:input wire:model="pollMaximumSelections" :label="__('Maximum choices')" type="number" min="1" max="5" required />
                <div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="pollOpensAt" :label="__('Opens at')" type="datetime-local" /><flux:input wire:model="pollClosesAt" :label="__('Closes at')" type="datetime-local" /></div>
                <flux:switch wire:model="publishPoll" :label="__('Publish immediately')" />
                <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Create poll') }}</flux:button></div>
            </form>
            <div class="flex flex-col gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                @forelse ($this->polls as $poll)<div wire:key="poll-{{ $poll->id }}" class="flex items-center justify-between gap-3"><flux:text>{{ $poll->question }}</flux:text><flux:badge :color="$poll->is_published ? 'green' : 'zinc'">{{ $poll->is_published ? __('Published') : __('Draft') }}</flux:badge></div>@empty<flux:text size="sm">{{ __('No polls created.') }}</flux:text>@endforelse
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-5">
            <div><flux:heading size="lg">{{ __('Launch episode predictions') }}</flux:heading><flux:text>{{ __('Launching an episode closes any previous prediction round.') }}</flux:text></div>
            <form wire:submit="launchPredictions" class="flex flex-col gap-4">
                <flux:input wire:model="episodeNumber" :label="__('Episode number')" type="number" min="1" required />
                <flux:input wire:model="episodeTitle" :label="__('Episode title')" />
                <flux:input wire:model="predictionClosesAt" :label="__('Predictions close at')" type="datetime-local" required />
                <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Launch predictions') }}</flux:button></div>
            </form>
            <div class="flex flex-col gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                @forelse ($this->episodes as $episode)<div wire:key="episode-{{ $episode->id }}" class="flex items-center justify-between gap-3"><flux:text>{{ __('Episode :number', ['number' => $episode->number]) }} {{ $episode->title }}</flux:text><flux:badge :color="$episode->predictions_are_open ? 'green' : 'zinc'">{{ $episode->predictions_are_open ? __('Open') : __('Closed') }}</flux:badge></div>@empty<flux:text size="sm">{{ __('No prediction rounds launched.') }}</flux:text>@endforelse
            </div>
        </flux:card>
    </section>

    <flux:modal name="cast-member-form" class="max-w-lg">
        <form wire:submit="saveCastMember" class="flex flex-col gap-6">
            <div><flux:heading size="lg">{{ $editingCastMemberId ? __('Edit cast member') : __('Add cast member') }}</flux:heading><flux:text>{{ __('Murdered and banished cast members are removed from prediction options.') }}</flux:text></div>
            <flux:input wire:model="name" :label="__('Name')" required autofocus />
            @if ($photo)
                <img src="{{ $photo->temporaryUrl() }}" alt="{{ __('New cast member photo preview') }}" class="aspect-square w-full max-w-48 rounded-xl object-cover">
            @elseif ($editingCastMemberId)
                @php($editingCastMember = $this->castMembers->firstWhere('id', $editingCastMemberId))
                @if ($editingCastMember)<img src="{{ $editingCastMember->imageUrl() }}" alt="{{ $editingCastMember->name }}" class="aspect-square w-full max-w-48 rounded-xl object-cover">@endif
            @endif
            <flux:input wire:model="photo" :label="__('Photo')" type="file" accept="image/jpeg,image/png,image/webp" />
            <flux:text size="sm">{{ __('JPG, PNG, or WebP up to 4 MB. Photos are saved on this server.') }}</flux:text>
            <flux:input wire:model="points" :label="__('Current points')" type="number" min="0" required />
            <flux:switch wire:model="isTraitor" :label="__('This cast member is a traitor')" />
            <flux:select wire:model="status" :label="__('Status')"><flux:select.option value="active">{{ __('Active') }}</flux:select.option><flux:select.option value="murdered">{{ __('Murdered') }}</flux:select.option><flux:select.option value="banished">{{ __('Banished') }}</flux:select.option></flux:select>
            <div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close><flux:button type="submit" variant="primary">{{ __('Save cast member') }}</flux:button></div>
        </form>
    </flux:modal>
</div>
