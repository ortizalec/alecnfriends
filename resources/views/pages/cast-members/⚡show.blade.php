<?php

use App\Actions\RecalculateVoteScoring;
use App\CastMemberStatus;
use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\Episode;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Cast member')] class extends Component {
    use WithFileUploads;

    public CastMember $castMember;
    public string $name = '';
    public mixed $photo = null;
    public string $bio = '';
    public string $status = 'active';
    public bool $isTraitor = false;
    public int $points = 0;
    public string $tab = 'details';

    public function mount(CastMember $castMember): void
    {
        $this->castMember = $castMember;
        $this->name = $castMember->name;
        $this->bio = $castMember->bio ?? '';
        $this->status = $castMember->status->value;
        $this->isTraitor = $castMember->is_traitor;
        $this->points = $castMember->points;
    }

    /** @return Collection<int, CastMemberAction> */
    #[Computed]
    public function actions(): Collection
    {
        return $this->castMember->actions()->with('episode')->latest()->get();
    }

    public function save(RecalculateVoteScoring $recalculateVoteScoring): void
    {
        Gate::authorize('access-admin');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(CastMember::class)->ignore($this->castMember)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'bio' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::enum(CastMemberStatus::class)],
            'isTraitor' => ['boolean'],
            'points' => ['required', 'integer', 'min:0'],
        ]);

        $oldPhotoPath = $this->castMember->photo_path;
        $newPhotoPath = $validated['photo'] instanceof TemporaryUploadedFile
            ? $validated['photo']->store('cast-members', 'public')
            : $oldPhotoPath;

        $this->castMember->fill([
            'name' => $validated['name'],
            'photo_path' => $newPhotoPath,
            'bio' => $validated['bio'],
            'is_active' => $validated['status'] === CastMemberStatus::Active->value,
            'status' => $validated['status'],
            'is_traitor' => $validated['isTraitor'],
            'points' => $validated['points'],
        ])->save();

        if ($this->castMember->wasChanged('is_traitor')) {
            Episode::query()->whereHas('roundTableVotes')->each($recalculateVoteScoring);
        }

        if ($newPhotoPath !== $oldPhotoPath && $oldPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        $this->castMember->refresh();
        $this->photo = null;
        $this->tab = 'details';

        Flux::toast(variant: 'success', text: __('Cast member updated.'));
    }
};
?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    <div class="flex items-center justify-between gap-4">
        <flux:button :href="route('dashboard')" wire:navigate variant="ghost" icon="arrow-left">{{ __('Back') }}</flux:button>
        @can('access-admin')
            <div class="flex rounded-lg bg-zinc-100 p-1 dark:bg-zinc-900">
                <button type="button" wire:click="$set('tab', 'details')" class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $tab === 'details' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}">{{ __('Details') }}</button>
                <button type="button" wire:click="$set('tab', 'edit')" class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $tab === 'edit' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}">{{ __('Edit') }}</button>
            </div>
        @endcan
    </div>

    @if ($tab === 'edit' && auth()->user()->can('access-admin'))
        <form wire:submit="save" class="grid gap-8 sm:grid-cols-[12rem_minmax(0,1fr)] sm:items-start">
            @if ($photo)
                <img src="{{ $photo->temporaryUrl() }}" alt="{{ __('New cast member photo preview') }}" class="aspect-[4/5] w-full max-w-48 rounded-2xl object-cover outline outline-1 -outline-offset-1 outline-black/5 dark:outline-white/10">
            @else
                <img src="{{ $castMember->imageUrl() }}" alt="{{ $castMember->name }}" class="aspect-[4/5] w-full max-w-48 rounded-2xl object-cover outline outline-1 -outline-offset-1 outline-black/5 dark:outline-white/10">
            @endif
            <flux:card class="flex flex-col gap-6">
                <div><flux:heading size="xl">{{ __('Edit :name', ['name' => $castMember->name]) }}</flux:heading><flux:text>{{ __('Update this cast member’s profile and game details.') }}</flux:text></div>
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="photo" type="file" :label="__('Photo')" accept="image/jpeg,image/png,image/webp" />
                <flux:textarea wire:model="bio" :label="__('Biography')" rows="16" maxlength="10000" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="points" type="number" min="0" :label="__('Current points')" required />
                    <flux:select wire:model="status" :label="__('Status')">
                        @foreach (CastMemberStatus::cases() as $castMemberStatus)
                            <flux:select.option :value="$castMemberStatus->value">{{ str($castMemberStatus->value)->headline() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:switch wire:model="isTraitor" :label="__('This cast member is a traitor')" />
                <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save cast member') }}</flux:button></div>
            </flux:card>
        </form>
    @else
        <div class="grid gap-8 sm:grid-cols-[12rem_minmax(0,1fr)] sm:items-start">
            <img src="{{ $castMember->imageUrl() }}" alt="{{ $castMember->name }}" class="aspect-[4/5] w-full max-w-48 rounded-2xl object-cover outline outline-1 -outline-offset-1 outline-black/5 dark:outline-white/10">
            <div class="flex min-w-0 flex-col gap-8">
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <flux:heading size="xl">{{ $castMember->name }}@if ($castMember->is_traitor) <span aria-label="{{ __('Traitor') }}">🔪</span>@endif</flux:heading>
                        <flux:badge :color="$castMember->status->value === 'active' ? 'green' : 'zinc'">{{ str($castMember->status->value)->headline() }}</flux:badge>
                    </div>
                    <flux:text>{{ trans_choice(':count point|:count points', $castMember->points, ['count' => $castMember->points]) }}</flux:text>
                </div>
                <section class="flex flex-col gap-3">
                    <flux:heading size="lg">{{ __('Biography') }}</flux:heading>
                    @if ($castMember->bio)
                        <p class="whitespace-pre-line text-base leading-7 text-zinc-700 dark:text-zinc-300">{{ $castMember->bio }}</p>
                    @else
                        <flux:text>{{ __('Biography coming soon.') }}</flux:text>
                    @endif
                </section>
                <section class="flex flex-col gap-3">
                    <flux:heading size="lg">{{ __('Actions') }}</flux:heading>
                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                            <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400"><tr><th class="px-4 py-3 font-medium">{{ __('Episode') }}</th><th class="px-4 py-3 font-medium">{{ __('Action') }}</th><th class="px-4 py-3 text-right font-medium">{{ __('Points') }}</th><th class="px-4 py-3 text-right font-medium">{{ __('Date') }}</th></tr></thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @forelse ($this->actions as $action)
                                    <tr wire:key="cast-profile-action-{{ $action->id }}"><td class="whitespace-nowrap px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $action->episode->number }}</td><td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $action->type->label() }}</td><td class="whitespace-nowrap px-4 py-3 text-right font-medium text-green-600 dark:text-green-400">+{{ $action->points }}</td><td class="whitespace-nowrap px-4 py-3 text-right text-zinc-500 dark:text-zinc-400">{{ $action->created_at->format('M j, Y') }}</td></tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-6 text-center text-zinc-500 dark:text-zinc-400">{{ __('No actions have been recorded yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    @endif
</div>
