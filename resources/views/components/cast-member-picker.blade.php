@props(['castMembers', 'model', 'selected' => null, 'label', 'keyPrefix', 'showPoints' => false])

<fieldset class="flex min-w-0 max-w-full flex-col gap-3 overflow-hidden">
    <legend class="font-medium text-zinc-900 dark:text-white">{{ $label }}</legend>
    <div class="flex w-full min-w-0 max-w-full snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain pb-3">
        @foreach ($castMembers as $castMember)
            @php($isSelected = (int) $selected === $castMember->id)
            <label wire:key="{{ $keyPrefix }}-{{ $castMember->id }}" class="group relative aspect-[4/5] w-36 shrink-0 snap-start cursor-pointer overflow-hidden rounded-xl bg-zinc-100 shadow-sm ring-offset-2 transition hover:-translate-y-0.5 focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-[var(--color-accent)] dark:bg-zinc-800 dark:ring-offset-zinc-900 sm:w-40 {{ $isSelected ? 'ring-2 ring-accent' : '' }}">
                <input type="radio" wire:model.live="{{ $model }}" value="{{ $castMember->id }}" class="sr-only">
                <img src="{{ $castMember->imageUrl() }}" alt="{{ $castMember->name }}" class="pointer-events-none size-full object-cover outline outline-1 -outline-offset-1 outline-black/5 transition duration-300 group-hover:scale-105 group-hover:opacity-90 dark:outline-white/10" loading="lazy">
                <span class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/90 via-black/15 to-transparent"></span>
                <span class="pointer-events-none absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 p-3">
                    <span class="truncate text-sm font-semibold text-white">{{ $castMember->name }}</span>
                    @if ($showPoints)<span class="shrink-0 text-xs font-medium text-white/75">{{ $castMember->points }} pts</span>@endif
                </span>
            </label>
        @endforeach
    </div>
    <flux:error :name="$model" />
</fieldset>
