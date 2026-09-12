<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
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
                </flux:sidebar.group>

                @can('access-admin')
                    <flux:sidebar.group :heading="__('Administration')" class="grid">
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
                    </flux:sidebar.group>
                @endcan
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
