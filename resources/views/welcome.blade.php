<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>@include('partials.head', ['title' => __('How to play')])</head>
<body class="min-h-screen bg-stone-50 font-sans text-zinc-950 antialiased dark:bg-zinc-950 dark:text-white">
<div class="relative isolate overflow-hidden">
    <div class="absolute inset-x-0 top-0 -z-10 h-[38rem] bg-[radial-gradient(circle_at_top_left,rgba(225,29,72,.18),transparent_38%),radial-gradient(circle_at_80%_10%,rgba(245,158,11,.18),transparent_32%)] dark:bg-[radial-gradient(circle_at_top_left,rgba(244,63,94,.2),transparent_38%),radial-gradient(circle_at_80%_10%,rgba(245,158,11,.14),transparent_32%)]"></div>
    <header class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"><img src="{{ asset('logo.png') }}" alt="" class="size-11 rounded-xl object-cover shadow-sm ring-1 ring-black/5 dark:ring-white/10"><span class="text-lg font-black tracking-tight">{{ config('app.name') }}</span></a>
        <nav class="flex items-center gap-2" aria-label="Account">
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-full bg-zinc-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 dark:bg-white dark:text-zinc-950 dark:hover:bg-rose-100">Go to leaderboard</a>
            @else
                <a href="{{ route('login') }}" class="rounded-full px-4 py-2.5 text-sm font-semibold transition hover:bg-black/5 dark:hover:bg-white/10">Log in</a>
                <a href="{{ route('register') }}" class="rounded-full bg-zinc-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 dark:bg-white dark:text-zinc-950 dark:hover:bg-rose-100">Join the league</a>
            @endauth
        </nav>
    </header>

    <main>
        <section class="mx-auto grid w-full max-w-7xl gap-12 px-6 pb-20 pt-14 lg:grid-cols-[1.1fr_.9fr] lg:items-center lg:px-8 lg:pb-28 lg:pt-24">
            <div class="flex max-w-3xl flex-col items-start gap-7">
                <span class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-bold uppercase tracking-[.18em] text-rose-700 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-300">Fantasy Faithful season scoring</span>
                <div class="flex flex-col gap-5"><h1 class="text-5xl font-black tracking-[-.04em] text-balance sm:text-6xl lg:text-7xl">Build your team.<br><span class="text-rose-600 dark:text-rose-400">Trust no one.</span></h1><p class="max-w-2xl text-lg leading-8 text-zinc-600 dark:text-zinc-300">Pick five cast members, predict each episode’s biggest moments, and climb the leaderboard as the season unfolds.</p></div>
                <a href="#how-to-play" class="group inline-flex items-center gap-2 text-sm font-bold text-rose-700 dark:text-rose-300">See how it works <span class="transition group-hover:translate-y-0.5" aria-hidden="true">↓</span></a>
            </div>
            <div class="relative mx-auto w-full max-w-md">
                <div class="absolute -inset-5 -z-10 rotate-3 rounded-[2.5rem] bg-amber-300/35 blur-2xl dark:bg-amber-500/10"></div>
                <div class="overflow-hidden rounded-[2rem] border border-white/80 bg-white/80 p-6 shadow-2xl shadow-rose-950/10 backdrop-blur dark:border-white/10 dark:bg-zinc-900/80">
                    <div class="flex items-center justify-between border-b border-zinc-200 pb-5 dark:border-zinc-700"><div><p class="text-xs font-bold uppercase tracking-widest text-zinc-500">Your mission</p><p class="mt-1 text-2xl font-black">Outscore the castle</p></div><span class="text-4xl" aria-hidden="true">🏰</span></div>
                    <ol class="mt-6 flex flex-col gap-5">
                        <li class="flex gap-4"><span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-rose-600 font-black text-white">1</span><div><p class="font-bold">Draft five</p><p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">Choose exactly five active cast members while team selection is open.</p></div></li>
                        <li class="flex gap-4"><span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-400 font-black text-zinc-950">2</span><div><p class="font-bold">Make your calls</p><p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">Submit the opening traitor picks and each episode’s three predictions.</p></div></li>
                        <li class="flex gap-4"><span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-950 font-black text-white dark:bg-white dark:text-zinc-950">3</span><div><p class="font-bold">Watch the points roll in</p><p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">Your cast, prediction, and challenge points combine into one leaderboard score.</p></div></li>
                    </ol>
                </div>
            </div>
        </section>

        <section id="how-to-play" class="border-y border-zinc-200 bg-white/80 dark:border-zinc-800 dark:bg-zinc-900/60">
            <div class="mx-auto w-full max-w-7xl px-6 py-20 lg:px-8">
                <div class="max-w-2xl"><p class="text-sm font-bold uppercase tracking-[.18em] text-rose-600 dark:text-rose-400">How to play</p><h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Three ways to build your score</h2></div>
                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    <article class="rounded-3xl border border-zinc-200 bg-stone-50 p-7 dark:border-zinc-700 dark:bg-zinc-950"><span class="text-3xl" aria-hidden="true">🎭</span><h3 class="mt-5 text-xl font-black">Your team</h3><p class="mt-3 leading-7 text-zinc-600 dark:text-zinc-400">Pick exactly five active cast members. You can revise the lineup until team selection closes. Their scoring actions count toward every fantasy team that includes them.</p></article>
                    <article class="rounded-3xl border border-zinc-200 bg-stone-50 p-7 dark:border-zinc-700 dark:bg-zinc-950"><span class="text-3xl" aria-hidden="true">🔮</span><h3 class="mt-5 text-xl font-black">Your predictions</h3><p class="mt-3 leading-7 text-zinc-600 dark:text-zinc-400">Before an episode’s prediction window closes, choose one active cast member for murder, banishment, and first out at breakfast. You may revise your answers while the window is open.</p></article>
                    <article class="rounded-3xl border border-zinc-200 bg-stone-50 p-7 dark:border-zinc-700 dark:bg-zinc-950"><span class="text-3xl" aria-hidden="true">🗳️</span><h3 class="mt-5 text-xl font-black">Audience surveys</h3><p class="mt-3 leading-7 text-zinc-600 dark:text-zinc-400">Answer occasional cast-member surveys while they are open. A question may allow one or several choices, and results appear after you respond. Surveys do not add leaderboard points.</p></article>
                </div>
            </div>
        </section>

        <section class="mx-auto w-full max-w-7xl px-6 py-20 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-[.8fr_1.2fr] lg:items-start">
                <div class="lg:sticky lg:top-8"><p class="text-sm font-bold uppercase tracking-[.18em] text-rose-600 dark:text-rose-400">Scoring guide</p><h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Every point currently tracked</h2><p class="mt-4 max-w-md leading-7 text-zinc-600 dark:text-zinc-400">Cast-member points follow that person onto your team. Prediction and challenge bonuses are added directly to your player total.</p></div>
                <div class="grid gap-6 sm:grid-cols-2">
                    <article class="rounded-3xl bg-zinc-950 p-7 text-white shadow-xl dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
                        <div class="flex items-start justify-between"><div><p class="text-sm font-bold uppercase tracking-widest text-rose-300">Cast actions</p><h3 class="mt-2 text-2xl font-black">Team points</h3></div><span class="text-3xl" aria-hidden="true">⚔️</span></div>
                        <dl class="mt-7 flex flex-col gap-4 text-sm"><div class="flex justify-between gap-4"><dt>Earns a shield</dt><dd class="font-black text-amber-300">+2</dd></div><div class="flex justify-between gap-4"><dt>Makes the night’s first accusation</dt><dd class="font-black text-amber-300">+1</dd></div><div class="flex justify-between gap-4"><dt>Casts the only vote for a cast member</dt><dd class="font-black text-amber-300">+1</dd></div><div class="flex justify-between gap-4"><dt>Faithful correctly votes for a traitor</dt><dd class="font-black text-amber-300">+1</dd></div><div class="flex justify-between gap-4"><dt>Traitor receives a vote but survives banishment</dt><dd class="font-black text-amber-300">+1</dd></div></dl>
                    </article>
                    <article class="rounded-3xl border border-zinc-200 bg-white p-7 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-start justify-between"><div><p class="text-sm font-bold uppercase tracking-widest text-rose-600 dark:text-rose-400">Each episode</p><h3 class="mt-2 text-2xl font-black">Prediction points</h3></div><span class="text-3xl" aria-hidden="true">🔍</span></div>
                        <dl class="mt-7 flex flex-col gap-4 text-sm"><div class="flex justify-between gap-4"><dt>Correctly predicts who is murdered</dt><dd class="font-black text-rose-600 dark:text-rose-400">+1</dd></div><div class="flex justify-between gap-4"><dt>Correctly predicts who is banished</dt><dd class="font-black text-rose-600 dark:text-rose-400">+1</dd></div><div class="flex justify-between gap-4"><dt>Correctly predicts first out at breakfast</dt><dd class="font-black text-rose-600 dark:text-rose-400">+2</dd></div></dl>
                        <p class="mt-7 rounded-2xl bg-rose-50 p-4 text-sm leading-6 text-rose-900 dark:bg-rose-950/50 dark:text-rose-200">A perfect episode prediction is worth <strong>4 points</strong>.</p>
                    </article>
                    <article class="rounded-3xl border border-zinc-200 bg-white p-7 dark:border-zinc-700 dark:bg-zinc-900"><div class="flex items-start justify-between"><div><p class="text-sm font-bold uppercase tracking-widest text-rose-600 dark:text-rose-400">Opening prediction</p><h3 class="mt-2 text-2xl font-black">Name the traitors</h3></div><span class="text-3xl" aria-hidden="true">🗡️</span></div><p class="mt-5 leading-7 text-zinc-600 dark:text-zinc-400">Choose three different cast members in the one-time opening prediction.</p><div class="mt-6 flex items-center justify-between rounded-2xl bg-zinc-100 p-4 dark:bg-zinc-800"><span class="text-sm font-medium">Each correct traitor</span><strong class="text-rose-600 dark:text-rose-400">+1 point</strong></div></article>
                    <article class="rounded-3xl border border-zinc-200 bg-white p-7 dark:border-zinc-700 dark:bg-zinc-900"><div class="flex items-start justify-between"><div><p class="text-sm font-bold uppercase tracking-widest text-rose-600 dark:text-rose-400">Challenge bonus</p><h3 class="mt-2 text-2xl font-black">Share the money</h3></div><span class="text-3xl" aria-hidden="true">💰</span></div><p class="mt-5 leading-7 text-zinc-600 dark:text-zinc-400">If at least one member of your fantasy team earns challenge money in an episode, your player score receives one bonus point.</p><div class="mt-6 flex items-center justify-between rounded-2xl bg-zinc-100 p-4 dark:bg-zinc-800"><span class="text-sm font-medium">Per qualifying episode</span><strong class="text-rose-600 dark:text-rose-400">+1 point</strong></div></article>
                </div>
            </div>
        </section>

        <section class="bg-rose-700 text-white dark:bg-rose-950"><div class="mx-auto grid w-full max-w-7xl gap-10 px-6 py-20 lg:grid-cols-2 lg:items-center lg:px-8"><div><p class="text-sm font-bold uppercase tracking-[.18em] text-rose-200">Your game night rhythm</p><h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Check in. Lock it in. Watch it play out.</h2></div><ol class="grid gap-4 sm:grid-cols-3"><li class="rounded-2xl bg-white/10 p-5 ring-1 ring-white/15"><span class="text-sm font-black text-rose-200">01</span><p class="mt-3 font-bold">Check the dashboard</p><p class="mt-2 text-sm leading-6 text-rose-100">See your rank and any predictions or surveys still waiting.</p></li><li class="rounded-2xl bg-white/10 p-5 ring-1 ring-white/15"><span class="text-sm font-black text-rose-200">02</span><p class="mt-3 font-bold">Submit in time</p><p class="mt-2 text-sm leading-6 text-rose-100">Complete open picks before their displayed closing time.</p></li><li class="rounded-2xl bg-white/10 p-5 ring-1 ring-white/15"><span class="text-sm font-black text-rose-200">03</span><p class="mt-3 font-bold">Follow the season</p><p class="mt-2 text-sm leading-6 text-rose-100">Review your history, team activity, and live leaderboard score.</p></li></ol></div></section>
        <section class="mx-auto flex w-full max-w-4xl flex-col items-center gap-6 px-6 py-24 text-center"><span class="text-5xl" aria-hidden="true">👁️</span><h2 class="text-4xl font-black tracking-tight">Ready to enter the castle?</h2><p class="max-w-xl text-lg leading-8 text-zinc-600 dark:text-zinc-400">Create your account, choose your five, and keep your suspicions close.</p>@auth<a href="{{ route('dashboard') }}" class="rounded-full bg-rose-600 px-6 py-3 font-bold text-white transition hover:bg-rose-700">View the leaderboard</a>@else<a href="{{ route('register') }}" class="rounded-full bg-rose-600 px-6 py-3 font-bold text-white transition hover:bg-rose-700">Create your account</a>@endauth</section>
    </main>
    <footer class="border-t border-zinc-200 px-6 py-8 text-center text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">{{ config('app.name') }} · Fantasy Faithful season scoring</footer>
</div>
@fluxScripts
</body>
</html>
