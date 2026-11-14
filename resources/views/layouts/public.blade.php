<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <a href="{{ route('home') }}" class="me-6 flex items-center gap-2 font-semibold" wire:navigate>
                <x-app-logo-icon class="size-7 fill-current text-black dark:text-white" />
                <span>{{ config('app.name') }}</span>
            </a>

            <flux:navbar class="hidden sm:flex">
                <flux:navbar.item :href="route('marketplace.index')" :current="request()->routeIs('marketplace.*')" wire:navigate>{{ __('Marketplace') }}</flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <div x-data="{ can: !!window.__installPrompt }" x-on:pwa-installable.window="can = true" x-on:pwa-installed.window="can = false" x-cloak>
                <flux:button x-show="can" x-on:click="window.installPwa()" size="sm" variant="ghost" icon="arrow-down-tray" class="me-2">{{ __('Install app') }}</flux:button>
            </div>

            @auth
                <flux:button :href="route('dashboard')" variant="primary" size="sm" wire:navigate>{{ __('My account') }}</flux:button>
            @else
                <flux:button :href="route('login')" variant="ghost" size="sm" wire:navigate>{{ __('Log in') }}</flux:button>
                @if (Route::has('register'))
                    <flux:button :href="route('register')" variant="primary" size="sm" class="ms-2" wire:navigate>{{ __('Sign up') }}</flux:button>
                @endif
            @endauth
        </flux:header>

        <flux:main container>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
