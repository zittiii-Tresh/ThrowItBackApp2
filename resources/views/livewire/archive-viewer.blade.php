<div>
    @php
        $site = $snapshot->crawlRun->site;
        $viewportWidths = [
            'desktop' => 'max-w-none',
            'tablet'  => 'max-w-[820px]',
            'mobile'  => 'max-w-[400px]',
        ];
        $viewportClass = $viewportWidths[$viewport] ?? 'max-w-none';

        $assetTypeLabels = [
            'all' => 'All', 'image' => 'Images', 'stylesheet' => 'CSS',
            'javascript' => 'JS', 'font' => 'Fonts', 'other' => 'Other',
        ];
    @endphp

    {{-- Dark archive bar (per proposal PDF) --}}
    <div class="bg-surface-900 text-surface-100">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-3">
            <div class="min-w-0 flex items-baseline gap-3">
                <span class="shrink-0 rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white">
                    Archived
                </span>
                <span class="truncate font-mono text-sm">{{ $snapshot->url }}</span>
            </div>
            <div class="shrink-0 text-right text-xs">
                <div class="font-medium">{{ $snapshot->created_at->format('M j, Y · g:i A') }}</div>
                <div class="text-surface-400">
                    {{ $siblings->count() }} pages · {{ $snapshot->asset_count }} assets
                </div>
            </div>
        </div>
    </div>

    {{-- Toolbar: viewport switcher + page tabs + assets toggle --}}
    <div class="border-b border-surface-200 bg-white dark:border-surface-800 dark:bg-surface-950">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-6 py-3 text-sm">

            <a href="{{ route('archive.browse', $site) }}" class="text-xs text-surface-500 hover:text-brand-600 dark:text-surface-400 dark:hover:text-brand-400">
                ← Calendar
            </a>

            {{-- Viewport switcher --}}
            <div class="inline-flex items-center gap-1 rounded-md border border-surface-200 bg-surface-50 p-0.5 text-xs dark:border-surface-800 dark:bg-surface-900">
                <span class="px-2 text-surface-500 dark:text-surface-400">Viewport:</span>
                @foreach (['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile'] as $key => $label)
                    <button
                        type="button"
                        wire:click="setViewport('{{ $key }}')"
                        @class([
                            'rounded px-2.5 py-1 font-medium transition',
                            'bg-white text-brand-700 shadow-sm dark:bg-surface-800 dark:text-brand-300' => $viewport === $key,
                            'text-surface-600 hover:text-surface-900 dark:text-surface-400 dark:hover:text-surface-100' => $viewport !== $key,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </div>

            {{-- Page tabs — every captured page in the same crawl run --}}
            @if ($siblings->count() > 1)
                <div class="inline-flex flex-wrap items-center gap-1">
                    <span class="text-xs text-surface-500 dark:text-surface-400">Page:</span>
                    @foreach ($siblings as $sib)
                        <a
                            href="{{ url('/view/' . $sib->id) }}{{ request()->query() ? '?' . http_build_query(request()->query()) : '' }}"
                            wire:navigate
                            @class([
                                'rounded-md px-2 py-1 text-xs font-mono transition',
                                'bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300' => $sib->id === $snapshot->id,
                                'text-surface-600 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800' => $sib->id !== $snapshot->id,
                            ])
                            title="{{ $sib->title ?: $sib->path }}"
                        >
                            {{ $sib->path === '/' ? '/' : rtrim($sib->path, '/') }}
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="ml-auto">
                <button
                    type="button"
                    wire:click="toggleAssetsPanel"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium transition',
                        'border-brand-200 bg-brand-50 text-brand-700 dark:border-brand-800 dark:bg-brand-950 dark:text-brand-300' => $assetsPanelOpen,
                        'border-surface-200 bg-white text-surface-700 hover:border-brand-200 dark:border-surface-800 dark:bg-surface-900 dark:text-surface-300' => ! $assetsPanelOpen,
                    ])
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h10M4 17h16"/>
                    </svg>
                    Assets ({{ $snapshot->asset_count }})
                </button>
            </div>
        </div>
    </div>

    {{-- Main content area: iframe viewer + optional assets panel --}}
    <div class="mx-auto flex max-w-7xl gap-4 px-6 py-5">

        {{-- iframe loads the archived HTML, which points at /archive/asset/... --}}
        <div class="flex-1 rounded-xl border border-surface-200 bg-surface-50 p-2 dark:border-surface-800 dark:bg-surface-900">
            <div class="mx-auto {{ $viewportClass }} overflow-hidden rounded-lg border border-surface-200 bg-white shadow-sm dark:border-surface-800 dark:bg-white">
                <iframe
                    src="{{ url('/archive/snapshot/' . $snapshot->id) }}"
                    class="h-[80vh] w-full"
                    sandbox="allow-same-origin allow-scripts allow-forms allow-popups"
                    title="Archived: {{ $snapshot->url }}"
                ></iframe>
            </div>
        </div>

        {{-- Assets panel (Screen 4) — slide-in from the right when open --}}
        @if ($assetsPanelOpen)
            <aside class="w-80 shrink-0 self-start rounded-xl border border-surface-200 bg-white p-4 shadow-sm dark:border-surface-800 dark:bg-surface-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-surface-900 dark:text-surface-100">
                        All assets crawled
                    </h2>
                    <span class="text-xs text-surface-500 dark:text-surface-400">
                        {{ $assetCounts['all'] ?? 0 }}
                    </span>
                </div>

                {{-- Type filter tabs --}}
                <div class="mt-3 flex flex-wrap gap-1 text-xs">
                    @foreach ($assetTypeLabels as $key => $label)
                        @php $c = $assetCounts[$key] ?? 0; @endphp
                        <button
                            type="button"
                            wire:click="setAssetType('{{ $key }}')"
                            @disabled($c === 0 && $key !== 'all')
                            @class([
                                'rounded-full px-2.5 py-1 transition',
                                'bg-brand-600 text-white font-semibold' => $assetType === $key,
                                'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:hover:bg-surface-700' => $assetType !== $key && ($c > 0 || $key === 'all'),
                                'bg-surface-50 text-surface-300 dark:bg-surface-900 dark:text-surface-600 cursor-not-allowed' => $c === 0 && $key !== 'all',
                            ])
                        >
                            {{ $label }}
                            @if ($c > 0)<span class="ml-1 opacity-70">{{ $c }}</span>@endif
                        </button>
                    @endforeach
                </div>

                {{-- Asset list --}}
                <ul class="mt-4 max-h-[70vh] space-y-1.5 overflow-y-auto pr-1">
                    @forelse ($assets as $asset)
                        <li class="group flex items-center justify-between gap-2 rounded-md border border-surface-100 bg-surface-50 px-2.5 py-2 text-xs dark:border-surface-800 dark:bg-surface-800/50">
                            <div class="flex min-w-0 items-center gap-2">
                                @php
                                    $badgeColor = match ($asset->type->value) {
                                        'image' => 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300',
                                        'stylesheet' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
                                        'javascript' => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
                                        'font' => 'bg-pink-100 text-pink-700 dark:bg-pink-950 dark:text-pink-300',
                                        default => 'bg-surface-200 text-surface-700 dark:bg-surface-700 dark:text-surface-300',
                                    };
                                @endphp
                                <span class="shrink-0 rounded px-1.5 py-0.5 text-[9px] font-semibold uppercase {{ $badgeColor }}">
                                    {{ $asset->type->label() }}
                                </span>
                                <div class="min-w-0">
                                    <div class="truncate font-mono text-surface-900 dark:text-surface-100" title="{{ $asset->url }}">
                                        {{ $asset->basename() }}
                                    </div>
                                    <div class="text-[10px] text-surface-500 dark:text-surface-400">
                                        {{ $asset->sizeHuman() }}
                                    </div>
                                </div>
                            </div>
                            <a
                                href="{{ url('/archive/asset/' . $snapshot->id . '/' . sha1($asset->url)) }}"
                                download="{{ $asset->basename() }}"
                                class="shrink-0 rounded p-1 text-surface-500 opacity-0 transition group-hover:opacity-100 hover:bg-brand-50 hover:text-brand-700 dark:hover:bg-brand-950 dark:hover:text-brand-300"
                                title="Download"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 10l5 5 5-5M12 15V3"/>
                                </svg>
                            </a>
                        </li>
                    @empty
                        <li class="rounded-md border border-dashed border-surface-200 p-6 text-center text-xs text-surface-500 dark:border-surface-800 dark:text-surface-400">
                            No {{ $assetType === 'all' ? '' : $assetTypeLabels[$assetType] . ' ' }}assets captured for this page.
                        </li>
                    @endforelse
                </ul>
            </aside>
        @endif
    </div>
</div>
