@php
    /** @var \App\Models\CrawlRun $run */
    $run = $getRecord();
    $isRunning = $run->status->value === 'running';

    if ($isRunning) {
        $priorCount = \App\Models\CrawlRun::where('site_id', $run->site_id)
            ->whereIn('status', ['complete', 'partial'])
            ->where('id', '<>', $run->id)
            ->latest('id')
            ->value('pages_crawled');

        // Ignore tiny priors (likely failed runs from a wrong URL) so the
        // progress bar doesn't hit 100% on page 2.
        $expected = max(
            $run->pages_crawled + 1,
            $priorCount > 5 ? $priorCount : 0,
            $run->site?->max_pages > 5 ? (int) ($run->site->max_pages / 10) : 10,
        );
        $percent = (int) min(100, round(($run->pages_crawled / $expected) * 100));
    }
@endphp

@if ($isRunning)
    {{-- Compact one-line indicator (no progress bar). --}}
    <div class="inline-flex items-center text-[11px] font-medium text-primary-600 dark:text-primary-400" style="gap: 0.5rem;">
        <svg class="animate-spin" fill="none" viewBox="0 0 24 24" style="width: 0.875rem; height: 0.875rem;">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
        </svg>
        <span>Running</span>
        <span class="text-gray-600 dark:text-gray-400">{{ $percent }}%</span>
    </div>
@else
    @php $c = $run->status->color(); @endphp
    <span @class([
        'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium',
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $c === 'success',
        'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'        => $c === 'warning',
        'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300'            => $c === 'danger',
        'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300'                => $c === 'info',
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'           => $c === 'gray',
    ])>
        {{ $run->status->label() }}
    </span>
@endif
