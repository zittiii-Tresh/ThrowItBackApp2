<div class="mx-auto max-w-7xl px-6 py-8">

    {{-- Breadcrumb + header --}}
    <div class="mb-5">
        <a href="{{ route('archive.browse', $site) }}" class="text-xs text-surface-500 hover:text-brand-600 dark:text-surface-400 dark:hover:text-brand-400">
            ← {{ $site->name }}
        </a>
        <h1 class="mt-1 text-2xl font-semibold text-surface-900 dark:text-surface-100">
            Compare snapshots
        </h1>
    </div>

    {{-- Selector row --}}
    <form wire:submit="runDiff" class="rounded-xl border border-surface-200 bg-white p-4 shadow-sm dark:border-surface-800 dark:bg-surface-900">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_1fr_auto]">

            {{-- Page path --}}
            <label class="block text-xs font-medium text-surface-700 dark:text-surface-300">
                Page
                <select
                    wire:model.live="path"
                    class="mt-1 w-full rounded-md border border-surface-200 bg-white px-3 py-2 text-sm dark:border-surface-800 dark:bg-surface-800"
                >
                    <option value="">— pick a page —</option>
                    @foreach ($availablePaths as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </label>

            {{-- Left run --}}
            <label class="block text-xs font-medium text-surface-700 dark:text-surface-300">
                Old run
                <select
                    wire:model.live="leftRunId"
                    class="mt-1 w-full rounded-md border border-surface-200 bg-white px-3 py-2 text-sm dark:border-surface-800 dark:bg-surface-800"
                >
                    <option value="">— pick a run —</option>
                    @foreach ($runs as $r)
                        <option value="{{ $r->id }}">
                            {{ $r->created_at->format('M j, Y — g:i A') }}  ({{ $r->pages_crawled }}p)
                        </option>
                    @endforeach
                </select>
            </label>

            {{-- Right run --}}
            <label class="block text-xs font-medium text-surface-700 dark:text-surface-300">
                New run
                <select
                    wire:model.live="rightRunId"
                    class="mt-1 w-full rounded-md border border-surface-200 bg-white px-3 py-2 text-sm dark:border-surface-800 dark:bg-surface-800"
                >
                    <option value="">— pick a run —</option>
                    @foreach ($runs as $r)
                        <option value="{{ $r->id }}">
                            {{ $r->created_at->format('M j, Y — g:i A') }}  ({{ $r->pages_crawled }}p)
                        </option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end">
                <button
                    type="submit"
                    @disabled(! $path || ! $leftRunId || ! $rightRunId)
                    class="btn-primary w-full md:w-auto"
                >
                    Run diff
                </button>
            </div>
        </div>

        @if ($runs->count() < 2)
            <p class="mt-3 text-xs text-amber-600 dark:text-amber-400">
                Need at least 2 complete crawl runs to compare. This site currently has {{ $runs->count() }}.
            </p>
        @endif
    </form>

    {{-- Diff output --}}
    @if ($hasRunDiff && ! empty($diff['left']))
        <div class="mt-5 rounded-xl border border-surface-200 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900">

            {{-- Summary strip --}}
            <div class="flex flex-wrap items-center gap-3 border-b border-surface-200 px-4 py-2.5 text-xs dark:border-surface-800">
                <span class="inline-flex items-center gap-1.5 font-medium text-emerald-700 dark:text-emerald-400">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    +{{ $diff['added'] }} added
                </span>
                <span class="inline-flex items-center gap-1.5 font-medium text-rose-700 dark:text-rose-400">
                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                    -{{ $diff['removed'] }} removed
                </span>
                <span class="text-surface-500 dark:text-surface-400">
                    {{ $diff['unchanged'] }} unchanged
                </span>
            </div>

            {{-- Side-by-side columns --}}
            <div class="grid grid-cols-2">
                @php
                    $leftRun  = $runs->firstWhere('id', $leftRunId);
                    $rightRun = $runs->firstWhere('id', $rightRunId);
                @endphp

                {{-- Left (old) --}}
                <div class="border-r border-surface-200 dark:border-surface-800">
                    <div class="border-b border-surface-200 bg-surface-50 px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-surface-600 dark:border-surface-800 dark:bg-surface-800/50 dark:text-surface-300">
                        {{ $leftRun?->created_at->format('M j — g:i A') ?? 'old' }}
                    </div>
                    <div class="max-h-[65vh] overflow-auto font-mono text-xs leading-5">
                        @foreach ($diff['left'] as $seg)
                            <div @class([
                                'whitespace-pre px-3',
                                'bg-rose-50 text-rose-900 dark:bg-rose-950/40 dark:text-rose-200' => $seg['type'] === 'removed',
                                'text-surface-700 dark:text-surface-300' => $seg['type'] === 'unchanged',
                                'bg-surface-50 dark:bg-surface-900/50' => $seg['type'] === 'placeholder',
                            ])>{{ $seg['content'] }}</div>
                        @endforeach
                    </div>
                </div>

                {{-- Right (new) --}}
                <div>
                    <div class="border-b border-surface-200 bg-surface-50 px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-surface-600 dark:border-surface-800 dark:bg-surface-800/50 dark:text-surface-300">
                        {{ $rightRun?->created_at->format('M j — g:i A') ?? 'new' }}
                    </div>
                    <div class="max-h-[65vh] overflow-auto font-mono text-xs leading-5">
                        @foreach ($diff['right'] as $seg)
                            <div @class([
                                'whitespace-pre px-3',
                                'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' => $seg['type'] === 'added',
                                'text-surface-700 dark:text-surface-300' => $seg['type'] === 'unchanged',
                                'bg-surface-50 dark:bg-surface-900/50' => $seg['type'] === 'placeholder',
                            ])>{{ $seg['content'] }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @elseif ($hasRunDiff)
        <div class="mt-5 rounded-xl border border-dashed border-surface-200 bg-white p-12 text-center dark:border-surface-800 dark:bg-surface-900">
            <p class="text-sm text-surface-600 dark:text-surface-400">
                No differences found between the two runs for this page.
            </p>
        </div>
    @endif
</div>
