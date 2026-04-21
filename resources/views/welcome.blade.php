@extends('layouts.app')

@section('content')
    {{--
      Landing hero. The .blob-stage is `relative overflow-hidden` so the drifting
      gradient blobs (see .blob in app.css) stay contained within the hero section
      instead of bleeding into the footer. aria-hidden because blobs are decorative.
    --}}
    <div class="blob-stage relative flex min-h-[calc(100vh-4rem)] items-center overflow-hidden">
        {{--
          Blob centers are placed well inside the stage (top-1/4, center-ish)
          so they never show a hard crop at the edges as they drift.
          Each blob is ~30rem — large enough to feel cinematic even with the big
          translate/rotate values in the keyframes.
        --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
            {{-- 3 large drifting blobs on the gradient background --}}
            <div class="blob blob--a left-[3%]   top-[8%]    h-[30rem] w-[30rem]"></div>
            <div class="blob blob--b right-[-2%] top-[22%]   h-[36rem] w-[36rem]"></div>
            <div class="blob blob--c left-[28%]  bottom-[4%] h-[28rem] w-[28rem]"></div>
        </div>

        <section class="relative mx-auto flex max-w-3xl flex-col items-center px-6 py-20 text-center">
        <span class="mb-6 inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 dark:border-brand-900 dark:bg-brand-950 dark:text-brand-300">
            Internal Tool · Sites at Scale
        </span>

        <h1 class="text-4xl font-semibold tracking-tight text-surface-900 dark:text-surface-100 sm:text-5xl">
            Browse past versions of
            <span class="bg-gradient-to-r from-brand-500 to-brand-300 bg-clip-text text-transparent">your sites</span>
        </h1>

        <p class="mt-4 max-w-xl text-base text-surface-600 dark:text-surface-400">
            Search any URL to view archived snapshots and recover assets.
            The user archive UI comes online in Phase&nbsp;6 of the build.
        </p>

        <div class="mt-10 flex items-center gap-4">
            <a href="{{ url('/admin') }}" class="btn-primary">Open admin panel</a>
            <a href="{{ url('/horizon') }}" class="text-sm font-medium text-surface-600 hover:text-brand-600 dark:text-surface-400 dark:hover:text-brand-400">
                Horizon dashboard →
            </a>
        </div>

        <div class="mt-16 grid grid-cols-3 gap-4 text-left text-xs text-surface-500 dark:text-surface-400">
            <div class="rounded-lg border border-surface-200 p-4 dark:border-surface-800">
                <div class="font-medium text-surface-900 dark:text-surface-100">Laravel 11</div>
                <div class="mt-1">Backbone</div>
            </div>
            <div class="rounded-lg border border-surface-200 p-4 dark:border-surface-800">
                <div class="font-medium text-surface-900 dark:text-surface-100">Filament 3</div>
                <div class="mt-1">Admin panel</div>
            </div>
            <div class="rounded-lg border border-surface-200 p-4 dark:border-surface-800">
                <div class="font-medium text-surface-900 dark:text-surface-100">Spatie Crawler</div>
                <div class="mt-1">Crawl engine</div>
            </div>
        </div>
        </section>
    </div>
@endsection
