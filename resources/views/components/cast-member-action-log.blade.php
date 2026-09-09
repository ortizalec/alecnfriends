@props(['actions', 'emptyMessage' => 'No cast activity has been recorded yet.'])

<div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
    <div class="grid grid-cols-[1fr_auto] gap-3 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400 sm:grid-cols-[7rem_1fr_1fr_5rem]">
        <span class="hidden sm:block">{{ __('Episode') }}</span><span>{{ __('Cast member') }}</span><span class="hidden sm:block">{{ __('Action') }}</span><span class="text-right">{{ __('Points') }}</span>
    </div>
    @forelse ($actions as $action)
        <div wire:key="activity-{{ $action->id }}" class="grid grid-cols-[1fr_auto] items-center gap-3 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700 sm:grid-cols-[7rem_1fr_1fr_5rem]">
            <flux:text class="hidden sm:block">{{ __('Episode :number', ['number' => $action->episode->number]) }}</flux:text>
            <div class="flex min-w-0 items-center gap-3"><img src="{{ $action->castMember->imageUrl() }}" alt="" class="size-9 shrink-0 rounded-full object-cover"><span class="truncate font-medium">{{ $action->castMember->name }}</span></div>
            <flux:text class="hidden sm:block">{{ $action->type->label() }}</flux:text>
            <flux:badge class="justify-self-end" color="green">+{{ $action->points }}</flux:badge>
        </div>
    @empty
        <div class="p-6 text-center"><flux:text>{{ __($emptyMessage) }}</flux:text></div>
    @endforelse
</div>
