<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>
                <flux:navbar.item icon="user-group" :href="route('team.edit')" :current="request()->routeIs('team.edit')" wire:navigate>
                    {{ __('My Team') }}
                </flux:navbar.item>
                <flux:navbar.item icon="clipboard-document-check" :href="route('predictions.edit')" :current="request()->routeIs('predictions.edit')" wire:navigate>
                    {{ __('Predictions') }}
                </flux:navbar.item>
                <flux:navbar.item icon="clipboard-document-list" :href="route('surveys.index')" :current="request()->routeIs('surveys.index')" wire:navigate>
                    {{ __('Surveys') }}
                </flux:navbar.item>
                @can('access-admin')
                    <flux:navbar.item icon="wrench-screwdriver" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>
                        {{ __('Admin') }}
                    </flux:navbar.item>
                    <flux:navbar.item icon="bolt" :href="route('admin.scoring')" :current="request()->routeIs('admin.scoring*')" wire:navigate>
                        {{ __('Live Scoring') }}
                    </flux:navbar.item>
                @endcan
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                <flux:tooltip :content="__('Search')" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                </flux:tooltip>
                <flux:tooltip :content="__('Repository')" position="bottom">
                    <flux:navbar.item
                        class="h-10 max-lg:hidden [&>div>svg]:size-5"
                        icon="folder-git-2"
                        href="https://github.com/laravel/livewire-starter-kit"
                        target="_blank"
                        :label="__('Repository')"
                    />
                </flux:tooltip>
                <flux:tooltip :content="__('Documentation')" position="bottom">
                    <flux:navbar.item
                        class="h-10 max-lg:hidden [&>div>svg]:size-5"
                        icon="book-open-text"
                        href="https://laravel.com/docs/starter-kits#livewire"
                        target="_blank"
                        :label="__('Documentation')"
                    />
                </flux:tooltip>
            </flux:navbar>

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')">
                    <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard')  }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-group" :href="route('team.edit')" :current="request()->routeIs('team.edit')" wire:navigate>
                        {{ __('My Team') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-check" :href="route('predictions.edit')" :current="request()->routeIs('predictions.edit')" wire:navigate>
                        {{ __('Predictions') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('surveys.index')" :current="request()->routeIs('surveys.index')" wire:navigate>
                        {{ __('Surveys') }}
                    </flux:sidebar.item>
                    @can('access-admin')
                        <flux:sidebar.item icon="wrench-screwdriver" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>
                            {{ __('Admin') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="bolt" :href="route('admin.scoring')" :current="request()->routeIs('admin.scoring*')" wire:navigate>
                            {{ __('Live Scoring') }}
                        </flux:sidebar.item>
                        @if (request()->routeIs('admin.scoring*'))
                            <div class="ms-4 grid border-s border-emerald-400/20 ps-2">
                                <flux:sidebar.item :href="route('admin.scoring')" :current="request()->routeIs('admin.scoring')" wire:navigate>{{ __('Actions') }}</flux:sidebar.item>
                                <flux:sidebar.item :href="route('admin.scoring.results')" :current="request()->routeIs('admin.scoring.results')" wire:navigate>{{ __('Results') }}</flux:sidebar.item>
                                <flux:sidebar.item :href="route('admin.scoring.votes')" :current="request()->routeIs('admin.scoring.votes')" wire:navigate>{{ __('Votes') }}</flux:sidebar.item>
                                <flux:sidebar.item :href="route('admin.scoring.activity')" :current="request()->routeIs('admin.scoring.activity')" wire:navigate>{{ __('Activity') }}</flux:sidebar.item>
                            </div>
                        @endif
                    @endcan
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
