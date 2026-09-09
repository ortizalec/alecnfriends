@props(['castMembers', 'model', 'selected' => null, 'label', 'keyPrefix', 'showPoints' => false])

<fieldset class="flex min-w-0 max-w-full flex-col gap-3 overflow-hidden">
    <legend class="font-medium text-zinc-900 dark:text-white">{{ $label }}</legend>
    <div class="flex w-full min-w-0 max-w-full snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain pb-3">
        @foreach ($castMembers as $castMember)
            @php($isSelected = (int) $selected === $castMember->id)
            <label wire:key="{{ $keyPrefix }}-{{ $castMember->id }}" class="group w-36 shrink-0 snap-start cursor-pointer overflow-hidden rounded-xl border bg-white transition hover:-translate-y-0.5 hover:border-zinc-400 dark:bg-zinc-900 sm:w-40 {{ $isSelected ? 'border-accent ring-2 ring-accent' : 'border-zinc-200 dark:border-zinc-700' }}">
                <input type="radio" wire:model.live="{{ $model }}" value="{{ $castMember->id }}" class="sr-only">
                <img src="{{ $castMember->imageUrl() }}" alt="{{ $castMember->name }}" class="aspect-square w-full object-cover" loading="lazy">
                <span class="flex items-center justify-between gap-2 p-3">
                    <span class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $castMember->name }}</span>
                    @if ($showPoints)<span class="shrink-0 text-xs text-zinc-500">{{ $castMember->points }} pts</span>@endif
                </span>
            </label>
        @endforeach
    </div>
    <flux:error :name="$model" />
</fieldset>
