<?php

use App\CastMemberStatus;
use App\Models\CastMember;
use App\Models\Episode;
use App\Models\Prediction;
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

    public function mount(): void
    {
        $episode = $this->currentEpisode;

        if (! $episode) {
            return;
        }

        $prediction = Prediction::query()->whereBelongsTo($episode)->whereBelongsTo(Auth::user())->first();
        $this->murderedCastMemberId = $prediction?->murdered_cast_member_id;
        $this->banishedCastMemberId = $prediction?->banished_cast_member_id;
        $this->breakfastCastMemberId = $prediction?->breakfast_cast_member_id;
    }

    #[Computed]
    public function currentEpisode(): ?Episode
    {
        return Episode::query()->where('predictions_are_open', true)->latest('number')->first();
    }

    /** @return Collection<int, CastMember> */
    #[Computed]
    public function availableCastMembers(): Collection
    {
        return CastMember::query()->active()->orderBy('name')->get();
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
};
?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-8">
    <div class="flex flex-col gap-2"><flux:heading size="xl">{{ __('Episode predictions') }}</flux:heading><flux:text>{{ __('Make your picks before the prediction window closes.') }}</flux:text></div>

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
</div>
